<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCalendarEventRequest;
use App\Http\Requests\UpdateCalendarEventRequest;
use App\Models\CalendarEvent;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class CalendarEventController extends Controller
{
    /**
     * Store a newly created calendar event.
     */
    public function store(StoreCalendarEventRequest $request): JsonResponse
    {
        $user = Auth::user();
        $employee = Employee::where('email', $user->email)->first();

        // For department managers, ensure department_id matches their department
        if ($employee->role === 'department_manager') {
            $request->merge(['department_id' => $employee->department_id]);
            $request->merge(['visibility' => 'department']);
        }

        $event = CalendarEvent::create([
            'user_id' => $user->id,
            'category_id' => $request->validated()['category_id'] ?? null,
            'department_id' => $request->validated()['department_id'] ?? null,
            'visibility' => $request->validated()['visibility'],
            'title' => $request->validated()['title'],
            'description' => $request->validated()['description'] ?? null,
            'location' => $request->validated()['location'] ?? null,
            'is_all_day' => $request->validated()['is_all_day'],
            'start_date' => $request->validated()['start_date'],
            'end_date' => $request->validated()['end_date'] ?? null,
            'start_time' => $request->validated()['start_time'] ?? null,
            'end_time' => $request->validated()['end_time'] ?? null,
        ]);

        $event->load(['category', 'creator', 'department', 'attendees.user']);

        return response()->json([
            'message' => 'Event created successfully.',
            'event' => $this->transformEvent($event),
        ], 201);
    }

    /**
     * Update the specified calendar event.
     */
    public function update(UpdateCalendarEventRequest $request, CalendarEvent $calendarEvent): JsonResponse
    {
        $user = Auth::user();
        $employee = Employee::where('email', $user->email)->first();

        // For department managers, ensure department_id matches their department
        if ($employee && $employee->role === 'department_manager') {
            $request->merge(['department_id' => $employee->department_id]);
            $request->merge(['visibility' => 'department']);
        }

        $calendarEvent->update($request->validated());

        $calendarEvent->load(['category', 'creator', 'department', 'attendees.user']);

        return response()->json([
            'message' => 'Event updated successfully.',
            'event' => $this->transformEvent($calendarEvent),
        ]);
    }

    /**
     * Remove the specified calendar event.
     */
    public function destroy(CalendarEvent $calendarEvent): JsonResponse
    {
        $user = Auth::user();
        $employee = Employee::where('email', $user->email)->first();

        // Admin can delete any event
        // Department managers can only delete events they created or events in their department
        if ($employee->role !== 'admin') {
            if ($calendarEvent->user_id !== $user->id &&
                ! ($calendarEvent->visibility === 'department' && $calendarEvent->department_id === $employee->department_id)) {
                return response()->json([
                    'message' => 'You do not have permission to delete this event.',
                ], 403);
            }
        }

        $calendarEvent->delete();

        return response()->json([
            'message' => 'Event deleted successfully.',
        ]);
    }

    /**
     * Transform event for API response.
     */
    private function transformEvent(CalendarEvent $event): array
    {
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
                return [
                    'id' => $attendee->user->id,
                    'name' => $attendee->user->name,
                    'email' => $attendee->user->email,
                ];
            })->toArray(),
        ];
    }
}
