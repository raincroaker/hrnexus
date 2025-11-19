<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EventCategory;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    public function index(): Response
    {
        $user = Auth::user();

        // Get current user's employee record
        $currentEmployee = Employee::query()
            ->where('email', $user->email)
            ->with(['department'])
            ->first();

        if (! $currentEmployee) {
            abort(403, 'Employee record not found');
        }

        $role = $currentEmployee->role;
        $userDepartmentId = $currentEmployee->department_id;

        // Fetch events based on role
        $eventsQuery = CalendarEvent::query()
            ->with(['category', 'creator', 'department', 'attendees.user'])
            ->orderBy('start_date')
            ->orderBy('start_time');

        if ($role === 'admin') {
            // Admin: Get all events (everyone + all departments)
            $events = $eventsQuery->get();
        } else {
            // Department Manager and Employee: Get everyone events + their department events
            $events = $eventsQuery->where(function ($query) use ($userDepartmentId) {
                $query->where('visibility', 'everyone')
                    ->orWhere(function ($q) use ($userDepartmentId) {
                        $q->where('visibility', 'department')
                            ->where('department_id', $userDepartmentId);
                    });
            })->get();
        }

        // Transform events for frontend
        $transformedEvents = $events->map(function ($event) {
            return $this->transformEvent($event);
        });

        // Get all departments (for admin/managers who can create events)
        $departments = Department::query()
            ->orderBy('name')
            ->get()
            ->map(fn ($dept) => [
                'id' => $dept->id,
                'code' => $dept->code,
                'name' => $dept->name,
            ]);

        // Get all event categories
        $categories = EventCategory::query()
            ->orderBy('name')
            ->get()
            ->map(fn ($cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
                'color' => $cat->color,
            ]);

        // Get user's department info
        $userDepartment = $currentEmployee->department
            ? [
                'id' => $currentEmployee->department->id,
                'code' => $currentEmployee->department->code,
                'name' => $currentEmployee->department->name,
            ]
            : null;

        return Inertia::render('Calendar', [
            'currentUser' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $role,
            ],
            'userDepartment' => $userDepartment,
            'events' => $transformedEvents,
            'departments' => $departments,
            'categories' => $categories,
        ]);
    }

    private function transformEvent(CalendarEvent $event): array
    {
        $user = Auth::user();
        $startDate = \Carbon\Carbon::parse($event->start_date);
        $endDate = $event->end_date ? \Carbon\Carbon::parse($event->end_date) : null;

        return [
            'id' => $event->id,
            'title' => $event->title,
            'description' => $event->description,
            'location' => $event->location,
            'is_all_day' => $event->is_all_day,
            'start_date' => $event->start_date,
            'end_date' => $event->end_date,
            'start_time' => $event->start_time,
            'end_time' => $event->end_time,
            'visibility' => $event->visibility,
            'category' => $event->category
                ? [
                    'id' => $event->category->id,
                    'name' => $event->category->name,
                    'color' => $event->category->color,
                ]
                : null,
            'creator' => [
                'id' => $event->creator->id,
                'name' => $event->creator->name,
            ],
            'department' => $event->department
                ? [
                    'id' => $event->department->id,
                    'code' => $event->department->code,
                    'name' => $event->department->name,
                ]
                : null,
            'attendees' => $event->attendees->map(function ($attendee) {
                // Find employee by email (User and Employee are linked by email)
                $employee = Employee::where('email', $attendee->user->email)
                    ->with(['department', 'position'])
                    ->first();

                $departmentCode = $employee?->department?->code ?? null;
                $positionName = $employee?->position?->name ?? null;

                return [
                    'id' => $attendee->user->id,
                    'name' => $attendee->user->name,
                    'email' => $attendee->user->email,
                    'department_code' => $departmentCode,
                    'position' => $positionName,
                ];
            })->toArray(),
            'current_user_is_attending' => $user ? $event->attendees->where('user_id', $user->id)->isNotEmpty() : false,
        ];
    }
}
