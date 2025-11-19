<?php

namespace Database\Seeders;

use App\Models\CalendarEvent;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EventCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class CalendarEventSeeder extends Seeder
{
    public function run(): void
    {
        // Get users and categories
        $users = User::all();
        $categories = EventCategory::all();

        if ($users->isEmpty()) {
            $this->command->warn('No users found. Please run DatabaseSeeder first.');

            return;
        }

        if ($categories->isEmpty()) {
            $this->command->warn('No event categories found. Please run EventCategorySeeder first.');

            return;
        }

        // Get the admin user (admin@hrnexus.com from DatabaseSeeder)
        $adminUser = User::where('email', 'admin@hrnexus.com')->first() ?? $users->first();

        // 5 events for everyone (visibility = 'everyone')
        $everyoneEvents = [
            [
                'title' => 'Company Holiday - New Year',
                'description' => 'New Year celebration. Office will be closed.',
                'location' => 'Office',
                'is_all_day' => true,
                'start_date' => Carbon::now()->addMonths(1)->startOfMonth()->format('Y-m-d'),
                'end_date' => Carbon::now()->addMonths(1)->startOfMonth()->format('Y-m-d'),
                'category_name' => 'Holidays',
            ],
            [
                'title' => 'All-Hands Meeting',
                'description' => 'Quarterly all-hands meeting to discuss company updates and achievements.',
                'location' => 'Main Conference Hall',
                'is_all_day' => false,
                'start_date' => Carbon::now()->addWeeks(2)->format('Y-m-d'),
                'start_time' => '14:00',
                'end_time' => '16:00',
                'category_name' => 'Meetings',
            ],
            [
                'title' => 'Company Training - Leadership Development',
                'description' => 'Mandatory leadership development training for all employees.',
                'location' => 'Training Room',
                'is_all_day' => false,
                'start_date' => Carbon::now()->addWeeks(3)->format('Y-m-d'),
                'start_time' => '09:00',
                'end_time' => '17:00',
                'category_name' => 'Training',
            ],
            [
                'title' => 'Q4 Project Deadline',
                'description' => 'Final deadline for all Q4 project submissions.',
                'location' => null,
                'is_all_day' => false,
                'start_date' => Carbon::now()->addMonths(2)->format('Y-m-d'),
                'start_time' => '17:00',
                'end_time' => '17:00',
                'category_name' => 'Deadlines',
            ],
            [
                'title' => 'Company Retreat',
                'description' => 'Annual company retreat for team building and networking.',
                'location' => 'Mountain Resort',
                'is_all_day' => true,
                'start_date' => Carbon::now()->addMonths(3)->format('Y-m-d'),
                'end_date' => Carbon::now()->addMonths(3)->addDays(2)->format('Y-m-d'),
                'category_name' => 'Social',
            ],
        ];

        foreach ($everyoneEvents as $eventData) {
            $category = $categories->where('name', $eventData['category_name'])->first();

            CalendarEvent::create([
                'user_id' => $adminUser->id, // Admin is the organizer for "everyone" events
                'category_id' => $category?->id,
                'department_id' => null,
                'visibility' => 'everyone',
                'title' => $eventData['title'],
                'description' => $eventData['description'],
                'location' => $eventData['location'],
                'is_all_day' => $eventData['is_all_day'],
                'start_date' => $eventData['start_date'],
                'end_date' => $eventData['end_date'] ?? null,
                'start_time' => $eventData['start_time'] ?? null,
                'end_time' => $eventData['end_time'] ?? null,
            ]);
        }

        // 2 events per department (visibility = 'department')
        $departments = Department::all();

        foreach ($departments as $department) {
            // Get employees for this department
            $departmentEmployees = Employee::where('department_id', $department->id)->get();

            if ($departmentEmployees->isEmpty()) {
                $this->command->warn("No employees found for department {$department->name}. Skipping department events.");

                continue;
            }

            // Get users by matching email with employee emails
            $employeeEmails = $departmentEmployees->pluck('email')->toArray();
            $departmentUsers = User::whereIn('email', $employeeEmails)->get();

            if ($departmentUsers->isEmpty()) {
                $this->command->warn("No users found matching employee emails for department {$department->name}. Skipping department events.");

                continue;
            }

            $departmentCreator = $departmentUsers->random();

            $departmentEvents = [
                [
                    'title' => "{$department->name} Team Meeting",
                    'description' => "Monthly team meeting for {$department->name} department.",
                    'location' => 'Department Conference Room',
                    'is_all_day' => false,
                    'start_date' => Carbon::now()->addWeeks(1)->format('Y-m-d'),
                    'start_time' => '10:00',
                    'end_time' => '11:30',
                    'category_name' => 'Meetings',
                ],
                [
                    'title' => "{$department->name} Training Session",
                    'description' => "Department-specific training session for {$department->name}.",
                    'location' => 'Training Room',
                    'is_all_day' => false,
                    'start_date' => Carbon::now()->addWeeks(4)->format('Y-m-d'),
                    'start_time' => '13:00',
                    'end_time' => '15:00',
                    'category_name' => 'Training',
                ],
            ];

            foreach ($departmentEvents as $eventData) {
                $category = $categories->where('name', $eventData['category_name'])->first();

                CalendarEvent::create([
                    'user_id' => $departmentCreator->id,
                    'category_id' => $category?->id,
                    'department_id' => $department->id,
                    'visibility' => 'department',
                    'title' => $eventData['title'],
                    'description' => $eventData['description'],
                    'location' => $eventData['location'],
                    'is_all_day' => $eventData['is_all_day'],
                    'start_date' => $eventData['start_date'],
                    'end_date' => null,
                    'start_time' => $eventData['start_time'],
                    'end_time' => $eventData['end_time'],
                ]);
            }
        }

        // Additional: Admin-created department-specific events (at least 1 per department)
        // These are created by admin but are department-specific
        foreach ($departments as $department) {
            // Get employees for this department to ensure it exists
            $departmentEmployees = Employee::where('department_id', $department->id)->get();

            if ($departmentEmployees->isEmpty()) {
                $this->command->warn("No employees found for department {$department->name}. Skipping admin-created department event.");

                continue;
            }

            // Admin-created department events
            $adminDepartmentEvents = [
                [
                    'title' => "{$department->name} - Important Announcement",
                    'description' => "Important announcement from management for {$department->name} department.",
                    'location' => 'Department Office',
                    'is_all_day' => false,
                    'start_date' => Carbon::now()->addWeeks(2)->format('Y-m-d'),
                    'start_time' => '15:00',
                    'end_time' => '16:00',
                    'category_name' => 'Meetings',
                ],
            ];

            foreach ($adminDepartmentEvents as $eventData) {
                $category = $categories->where('name', $eventData['category_name'])->first();

                CalendarEvent::create([
                    'user_id' => $adminUser->id, // Admin is the creator/organizer
                    'category_id' => $category?->id,
                    'department_id' => $department->id, // But event is department-specific
                    'visibility' => 'department',
                    'title' => $eventData['title'],
                    'description' => $eventData['description'],
                    'location' => $eventData['location'],
                    'is_all_day' => $eventData['is_all_day'],
                    'start_date' => $eventData['start_date'],
                    'end_date' => null,
                    'start_time' => $eventData['start_time'],
                    'end_time' => $eventData['end_time'],
                ]);
            }
        }

        $this->command->info('Calendar events seeded successfully!');
    }
}
