<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceSetting;
use App\Models\CalendarEvent;
use App\Models\Chat;
use App\Models\ChatMember;
use App\Models\Employee;
use App\Models\Message;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $user = Auth::user();

        $currentEmployee = Employee::query()
            ->with(['department', 'position'])
            ->where('email', $user->email)
            ->first();

        if (! $currentEmployee) {
            abort(403, 'Employee record not found');
        }

        $role = $currentEmployee->role;
        $userDepartmentId = $currentEmployee->department_id;

        // Get events (today and upcoming in 7 days)
        $events = $this->getEvents($role, $userDepartmentId);

        // Get last 7 attendances
        $last7Attendance = $this->getLast7Attendance($currentEmployee->id);

        // Get today's attendance for current user
        $todayAttendance = $this->getTodayAttendance($currentEmployee->id);

        // Get birthdays (today and upcoming)
        $birthdays = $this->getBirthdays();

        // Get unseen group chats
        $unreadMessages = $this->getUnseenGroupChats($user->id);

        // Calculate monthly stats
        $stats = $this->calculateMonthlyStats($currentEmployee->id);

        // Get today's employee attendance (admin only)
        $employeeAttendance = [];
        if ($role === 'admin') {
            $employeeAttendance = $this->getTodayEmployeeAttendance();
        }

        return Inertia::render('Dashboard', [
            'currentUser' => $this->transformEmployee($currentEmployee),
            'events' => $events,
            'last7Attendance' => $last7Attendance,
            'todayAttendance' => $todayAttendance,
            'birthdays' => $birthdays,
            'unreadMessages' => $unreadMessages,
            'stats' => $stats,
            'employeeAttendance' => $employeeAttendance,
        ]);
    }

    private function getEvents(string $role, ?int $userDepartmentId): array
    {
        $today = Carbon::today();
        $sevenDaysFromNow = Carbon::today()->addDays(7);

        $eventsQuery = CalendarEvent::query()
            ->with(['category', 'creator', 'department', 'attendees.user'])
            ->where('start_date', '>=', $today->toDateString())
            ->where('start_date', '<=', $sevenDaysFromNow->toDateString())
            ->orderBy('start_date')
            ->orderBy('start_time');

        if ($role !== 'admin') {
            $eventsQuery->where(function ($query) use ($userDepartmentId) {
                $query->where('visibility', 'everyone')
                    ->orWhere(function ($q) use ($userDepartmentId) {
                        $q->where('visibility', 'department')
                            ->where('department_id', $userDepartmentId);
                    });
            });
        }

        $events = $eventsQuery->get();

        return $events->map(function ($event) use ($today) {
            $startDate = Carbon::parse($event->start_date);
            $isToday = $startDate->isSameDay($today);

            // Map category name to type
            $type = 'meeting';
            if ($event->category) {
                $categoryName = strtolower($event->category->name);
                if (str_contains($categoryName, 'deadline')) {
                    $type = 'deadline';
                } elseif (str_contains($categoryName, 'holiday')) {
                    $type = 'holiday';
                } elseif (str_contains($categoryName, 'training')) {
                    $type = 'training';
                }
            }

            // Format time
            $time = 'All Day';
            if (! $event->is_all_day && $event->start_time) {
                $time = Carbon::parse($event->start_time)->format('g:i A');
            }

            return [
                'id' => $event->id,
                'title' => $event->title,
                'date' => Carbon::parse($event->start_date)->format('M j, Y'),
                'time' => $time,
                'type' => $type,
                'isToday' => $isToday,
                'location' => $event->location ?? '',
            ];
        })->toArray();
    }

    private function getLast7Attendance(int $employeeId): array
    {
        $attendances = Attendance::query()
            ->where('employee_id', $employeeId)
            ->orderByDesc('date')
            ->limit(7)
            ->get();

        return $attendances->map(function ($attendance) {
            $date = $attendance->date instanceof Carbon ? $attendance->date->toDateString() : $attendance->date;

            return [
                'date' => $date,
                'timeIn' => $attendance->time_in ? Carbon::parse($attendance->time_in)->format('g:i A') : null,
                'timeOut' => $attendance->time_out ? Carbon::parse($attendance->time_out)->format('g:i A') : null,
                'hoursWorked' => $attendance->total_hours,
                'status' => strtolower($attendance->status),
            ];
        })->reverse()->values()->toArray(); // Reverse to show oldest first (for chart)
    }

    private function getTodayAttendance(int $employeeId): array
    {
        $today = Carbon::today();
        $attendance = Attendance::query()
            ->where('employee_id', $employeeId)
            ->whereDate('date', $today)
            ->first();

        return [
            'timeIn' => $attendance && $attendance->time_in ? Carbon::parse($attendance->time_in)->format('g:i A') : null,
            'timeOut' => $attendance && $attendance->time_out ? Carbon::parse($attendance->time_out)->format('g:i A') : null,
            'date' => $today->format('M j, Y'),
        ];
    }

    private function getTodayEmployeeAttendance(): array
    {
        $today = Carbon::today();

        // Get attendance settings
        $attendanceSetting = AttendanceSetting::query()->latest()->first()
            ?? new AttendanceSetting(AttendanceSetting::defaultValues());

        $attendances = Attendance::query()
            ->with(['employee.department', 'employee.position'])
            ->whereDate('date', $today)
            ->whereNotNull('time_in') // Only show employees who have timeIn
            ->get();

        return $attendances->map(function ($attendance) use ($today, $attendanceSetting) {
            // Calculate status based on timeIn vs required_time_in
            $timeIn = Carbon::parse($attendance->time_in);
            $requiredTimeInForDate = Carbon::createFromFormat('Y-m-d H:i', $today->toDateString().' '.$attendanceSetting->required_time_in);

            $status = $timeIn->greaterThan($requiredTimeInForDate) ? 'late' : 'present';

            return [
                'id' => $attendance->id,
                'name' => $attendance->employee->full_name,
                'department' => $attendance->employee->department?->name ?? 'N/A',
                'timeIn' => $timeIn->format('g:i A'),
                'timeOut' => $attendance->time_out ? Carbon::parse($attendance->time_out)->format('g:i A') : '-',
                'status' => $status,
            ];
        })->toArray();
    }

    private function getBirthdays(): array
    {
        $today = Carbon::today();
        $thirtyDaysFromNow = Carbon::today()->addDays(30);

        $employees = Employee::query()
            ->with('department')
            ->whereNotNull('birth_date')
            ->get()
            ->filter(function ($employee) use ($today, $thirtyDaysFromNow) {
                if (! $employee->birth_date) {
                    return false;
                }

                $birthDate = $employee->birth_date instanceof Carbon
                    ? $employee->birth_date
                    : Carbon::parse($employee->birth_date);

                // Get this year's birthday
                $thisYearBirthday = Carbon::create($today->year, $birthDate->month, $birthDate->day);

                // If birthday already passed this year, check next year
                if ($thisYearBirthday->isPast() && ! $thisYearBirthday->isToday()) {
                    $thisYearBirthday = Carbon::create($today->year + 1, $birthDate->month, $birthDate->day);
                }

                // Check if birthday is within the next 30 days (including today)
                return $thisYearBirthday->isSameDay($today) || ($thisYearBirthday->isFuture() && $thisYearBirthday->lte($thirtyDaysFromNow));
            })
            ->map(function ($employee) use ($today) {
                $birthDate = $employee->birth_date instanceof Carbon
                    ? $employee->birth_date
                    : Carbon::parse($employee->birth_date);

                // Get this year's birthday
                $thisYearBirthday = Carbon::create($today->year, $birthDate->month, $birthDate->day);

                // If birthday already passed this year, check next year
                if ($thisYearBirthday->isPast() && ! $thisYearBirthday->isToday()) {
                    $thisYearBirthday = Carbon::create($today->year + 1, $birthDate->month, $birthDate->day);
                }

                // Calculate age
                $age = $today->year - $birthDate->year;
                if ($birthDate->copy()->addYears($age)->gt($today)) {
                    $age--;
                }

                // Format date
                $dateFormatted = 'Today';
                if (! $thisYearBirthday->isToday()) {
                    $dateFormatted = $thisYearBirthday->format('M j');
                }

                return [
                    'id' => $employee->id,
                    'name' => $employee->full_name,
                    'department' => $employee->department?->name ?? '',
                    'date' => $dateFormatted,
                    'age' => $age,
                ];
            })
            ->sortBy(function ($birthday) use ($today) {
                // Sort by date: today first, then upcoming
                if ($birthday['date'] === 'Today') {
                    return 0;
                }

                $date = Carbon::parse($birthday['date'].' '.$today->year);
                if ($date->isPast()) {
                    $date = $date->addYear();
                }

                return $date->timestamp;
            })
            ->values()
            ->take(10) // Limit to 10 birthdays
            ->toArray();

        return $employees;
    }

    private function getUnseenGroupChats(int $userId): array
    {
        // Get all chats where user is a member and is_seen = false
        $chatMemberIds = ChatMember::where('user_id', $userId)
            ->where('is_seen', false)
            ->pluck('chat_id');

        if ($chatMemberIds->isEmpty()) {
            return [];
        }

        $chats = Chat::whereIn('id', $chatMemberIds)
            ->get();

        $chatsWithMessages = $chats->map(function ($chat) {
            // Get last message
            $lastMessage = Message::where('chat_id', $chat->id)
                ->where('is_deleted', false)
                ->with('user')
                ->latest()
                ->first();

            $messageContent = 'No messages yet';
            $messageTime = 'Just now';
            $messageTimestamp = 0;

            if ($lastMessage) {
                $messageContent = $lastMessage->content;
                $messageCreatedAt = $lastMessage->created_at instanceof Carbon
                    ? $lastMessage->created_at
                    : Carbon::parse($lastMessage->created_at);
                $messageTime = $this->formatMessageDate($messageCreatedAt);
                $messageTimestamp = $messageCreatedAt->timestamp;
            }

            return [
                'id' => $chat->id,
                'sender' => $chat->name,
                'message' => $messageContent,
                'time' => $messageTime,
                '_timestamp' => $messageTimestamp, // Temporary field for sorting
            ];
        })
            ->sortByDesc('_timestamp')
            ->values()
            ->map(function ($chat) {
                // Remove temporary timestamp field before returning
                unset($chat['_timestamp']);

                return $chat;
            })
            ->toArray();

        return $chatsWithMessages;
    }

    private function formatRelativeTime(Carbon $time): string
    {
        $now = Carbon::now();
        $diffInMinutes = $now->diffInMinutes($time);

        if ($diffInMinutes < 1) {
            return 'Just now';
        }

        if ($diffInMinutes < 60) {
            return "{$diffInMinutes} min ago";
        }

        $diffInHours = $now->diffInHours($time);
        if ($diffInHours < 24) {
            return "{$diffInHours} hour".($diffInHours > 1 ? 's' : '').' ago';
        }

        $diffInDays = $now->diffInDays($time);
        if ($diffInDays < 7) {
            return "{$diffInDays} day".($diffInDays > 1 ? 's' : '').' ago';
        }

        return $time->format('M j, Y');
    }

    private function formatMessageDate(Carbon $time): string
    {
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        $timeDate = $time->copy()->startOfDay();

        if ($timeDate->isSameDay($today)) {
            return 'Today, '.$time->format('g:i A');
        }

        if ($timeDate->isSameDay($yesterday)) {
            return 'Yesterday, '.$time->format('g:i A');
        }

        // If within the last 7 days, show day name
        if ($timeDate->greaterThan($today->copy()->subDays(7))) {
            return $time->format('D, g:i A');
        }

        // Otherwise show full date
        return $time->format('M j, g:i A');
    }

    private function calculateMonthlyStats(int $employeeId): array
    {
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        $attendances = Attendance::query()
            ->where('employee_id', $employeeId)
            ->whereBetween('date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->get();

        $totalPresents = $attendances->where('status', 'Present')->count();
        $totalLate = $attendances->where('status', 'Late')->count();
        $totalIncomplete = $attendances->where('status', 'Incomplete')->count();
        $totalWorkingDays = $attendances->count();

        $totalHours = $attendances->sum('total_hours') ?? 0;
        $averageDaily = $totalWorkingDays > 0 ? round($totalHours / $totalWorkingDays, 1) : 0;

        $lateRate = $totalWorkingDays > 0 ? round(($totalLate / $totalWorkingDays) * 100, 1) : 0;
        $incompleteRate = $totalWorkingDays > 0 ? round(($totalIncomplete / $totalWorkingDays) * 100, 1) : 0;

        return [
            'totalPresents' => $totalPresents,
            'workingDays' => "{$totalWorkingDays} Day".($totalWorkingDays !== 1 ? 's' : ''),
            'totalLate' => $totalLate,
            'lateRate' => "{$lateRate}%",
            'totalIncomplete' => $totalIncomplete,
            'incompleteRate' => "{$incompleteRate}%",
            'hoursWorked' => round($totalHours, 1).'h',
            'averageDaily' => "{$averageDaily}h/day",
        ];
    }

    private function transformEmployee(Employee $employee): array
    {
        $birthDate = $employee->birth_date instanceof Carbon ? $employee->birth_date->format('Y-m-d') : $employee->birth_date;
        $avatarUrl = $employee->avatar ? Storage::url($employee->avatar) : null;

        return [
            'id' => $employee->id,
            'employee_code' => $employee->employee_code,
            'name' => $employee->full_name,
            'first_name' => $employee->first_name,
            'last_name' => $employee->last_name,
            'department' => $employee->department?->name ?? '',
            'department_id' => $employee->department_id,
            'position' => $employee->position?->name ?? '',
            'position_id' => $employee->position_id,
            'role' => $employee->role,
            'email' => $employee->email,
            'contact_number' => $employee->contact_number,
            'birth_date' => $birthDate,
            'avatar' => $avatarUrl,
        ];
    }
}
