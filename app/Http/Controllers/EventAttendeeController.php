<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Models\EventAttendee;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class EventAttendeeController extends Controller
{
    /**
     * Toggle attendance for the authenticated user.
     * If user is attending, remove them. If not, add them.
     */
    public function toggle(CalendarEvent $calendarEvent): JsonResponse
    {
        $user = Auth::user();

        $existingAttendee = EventAttendee::where('event_id', $calendarEvent->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existingAttendee) {
            // User is already attending, remove them
            $existingAttendee->delete();

            return response()->json([
                'message' => 'You are no longer attending this event.',
                'is_attending' => false,
            ]);
        } else {
            // User is not attending, add them
            EventAttendee::create([
                'event_id' => $calendarEvent->id,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'message' => 'You are now attending this event.',
                'is_attending' => true,
            ]);
        }
    }

    /**
     * Check if the authenticated user is attending the event.
     */
    public function check(CalendarEvent $calendarEvent): JsonResponse
    {
        $user = Auth::user();

        $isAttending = EventAttendee::where('event_id', $calendarEvent->id)
            ->where('user_id', $user->id)
            ->exists();

        return response()->json([
            'is_attending' => $isAttending,
        ]);
    }
}
