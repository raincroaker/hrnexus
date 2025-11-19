<?php

namespace Database\Seeders;

use App\Models\CalendarEvent;
use App\Models\Employee;
use App\Models\EventAttendee;
use App\Models\User;
use Illuminate\Database\Seeder;

class EventAttendeeSeeder extends Seeder
{
    public function run(): void
    {
        $events = CalendarEvent::all();

        if ($events->isEmpty()) {
            $this->command->warn('No calendar events found. Please run CalendarEventSeeder first.');

            return;
        }

        foreach ($events as $event) {
            $attendees = [];

            if ($event->visibility === 'everyone') {
                // For 'everyone' events, randomly select from all users
                $allUsers = User::all();
                $attendeeCount = min(fake()->numberBetween(3, 8), $allUsers->count());

                if ($attendeeCount > 0) {
                    $attendees = $allUsers->random($attendeeCount);
                }
            } else {
                // For 'department' events, only select users whose employees belong to that department
                if ($event->department_id) {
                    $departmentEmployees = Employee::where('department_id', $event->department_id)->get();

                    if ($departmentEmployees->isEmpty()) {
                        $this->command->warn("No employees found for department ID {$event->department_id} for event '{$event->title}'. Skipping attendees.");

                        continue;
                    }

                    // Get users by matching email with employee emails
                    $employeeEmails = $departmentEmployees->pluck('email')->toArray();
                    $departmentUsers = User::whereIn('email', $employeeEmails)->get();

                    if ($departmentUsers->isEmpty()) {
                        $this->command->warn("No users found matching employee emails for department ID {$event->department_id} for event '{$event->title}'. Skipping attendees.");

                        continue;
                    }

                    $attendeeCount = min(fake()->numberBetween(2, 6), $departmentUsers->count());

                    if ($attendeeCount > 0) {
                        $attendees = $departmentUsers->random($attendeeCount);
                    }
                } else {
                    $this->command->warn("Event '{$event->title}' has visibility 'department' but no department_id. Skipping attendees.");

                    continue;
                }
            }

            // Create event attendees
            foreach ($attendees as $user) {
                EventAttendee::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'event_id' => $event->id,
                    ]
                );
            }
        }

        $this->command->info('Event attendees seeded successfully!');
    }
}
