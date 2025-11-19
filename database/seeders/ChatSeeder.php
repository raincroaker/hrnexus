<?php

namespace Database\Seeders;

use App\Models\Chat;
use App\Models\ChatMember;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ChatSeeder extends Seeder
{
    public function run(): void
    {
        $adminUser = User::where('email', 'admin@hrnexus.com')->first();

        if (! $adminUser) {
            $this->command?->warn('Admin user not found. Skipping chat seeding.');

            return;
        }

        $departments = Department::with(['employees'])->get();

        if ($departments->isEmpty()) {
            $this->command?->warn('No departments found. Skipping chat seeding.');

            return;
        }

        // Clear existing data (disable foreign key checks for truncation)
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('message_attachments')->truncate();
        DB::table('messages')->truncate();
        ChatMember::truncate();
        Chat::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->seedGlobalChat($adminUser, $departments);
        $this->seedDepartmentChats($adminUser, $departments);
    }

    private function seedGlobalChat(User $adminUser, Collection $departments): void
    {
        $adminEmployee = Employee::where('email', $adminUser->email)->first();
        $adminFullName = $adminEmployee ? "{$adminEmployee->first_name} {$adminEmployee->last_name}" : $adminUser->name;

        $globalChat = Chat::create([
            'name' => 'Everyone',
            'created_by' => $adminUser->id,
        ]);

        // Get all employees (including dept managers and regular employees)
        $allEmployees = Employee::all();
        $allEmployeeUserIds = $this->getUserIdsFromEmployees($allEmployees);

        // Add admin as admin member
        ChatMember::create([
            'chat_id' => $globalChat->id,
            'user_id' => $adminUser->id,
            'is_admin' => true,
        ]);

        // Add all employees as regular members (excluding admin if they're also an employee)
        foreach ($allEmployeeUserIds as $userId) {
            if ($userId !== $adminUser->id) {
                ChatMember::create([
                    'chat_id' => $globalChat->id,
                    'user_id' => $userId,
                    'is_admin' => false,
                ]);
            }
        }

        // Count members added (excluding admin from employee count)
        $membersAdded = $allEmployeeUserIds->filter(fn ($userId) => $userId !== $adminUser->id)->count();

        // System message: Admin created this group
        Message::create([
            'chat_id' => $globalChat->id,
            'user_id' => $adminUser->id,
            'content' => "{$adminFullName} created this group",
            'message_type' => 'system',
        ]);

        // System message: Admin added X members
        Message::create([
            'chat_id' => $globalChat->id,
            'user_id' => $adminUser->id,
            'content' => "{$adminFullName} added {$membersAdded} members",
            'message_type' => 'system',
        ]);

        // Welcome message from admin (with formatting examples)
        $welcomeMessages = [
            "Hello everyone! *Welcome* to the Everyone group! 👋\n\nThis is our main communication channel for company-wide updates and discussions.\n\nFeel free to share ideas, ask questions, or just say _hello_! We're all here to support each other.\n\nLet's make this a great place to collaborate! 🚀",
            "Hi team! *Welcome* to our Everyone group! 🎉\n\nThis space is for:\n- Company-wide announcements\n- General discussions\n- Team updates\n- _Collaboration_ and support\n\nLooking forward to great conversations with all of you! 💬",
        ];

        Message::create([
            'chat_id' => $globalChat->id,
            'user_id' => $adminUser->id,
            'content' => $welcomeMessages[array_rand($welcomeMessages)],
            'message_type' => 'text',
            'created_at' => now()->addSeconds(5),
        ]);
    }

    private function seedDepartmentChats(User $adminUser, Collection $departments): void
    {
        $adminEmployee = Employee::where('email', $adminUser->email)->first();
        $adminFullName = $adminEmployee ? "{$adminEmployee->first_name} {$adminEmployee->last_name}" : $adminUser->name;

        foreach ($departments as $department) {
            $deptManager = $department->employees->where('role', 'department_manager')->first();

            if (! $deptManager) {
                continue;
            }

            $deptManagerUser = User::where('email', $deptManager->email)->first();

            if (! $deptManagerUser) {
                continue;
            }

            $deptManagerFullName = "{$deptManager->first_name} {$deptManager->last_name}";

            // Create chat with dept manager as creator
            $departmentChat = Chat::create([
                'name' => "{$department->name} Chat",
                'created_by' => $deptManagerUser->id,
            ]);

            // Get all employees in this department
            $departmentEmployees = $department->employees;
            $departmentEmployeeUserIds = $this->getUserIdsFromEmployees($departmentEmployees);

            // Add admin as admin member
            ChatMember::create([
                'chat_id' => $departmentChat->id,
                'user_id' => $adminUser->id,
                'is_admin' => true,
            ]);

            // Add dept manager as admin member
            ChatMember::create([
                'chat_id' => $departmentChat->id,
                'user_id' => $deptManagerUser->id,
                'is_admin' => true,
            ]);

            // Add all department employees as regular members (excluding admin and dept manager if already added)
            $regularMemberCount = 0;
            foreach ($departmentEmployeeUserIds as $userId) {
                if ($userId !== $adminUser->id && $userId !== $deptManagerUser->id) {
                    ChatMember::create([
                        'chat_id' => $departmentChat->id,
                        'user_id' => $userId,
                        'is_admin' => false,
                    ]);
                    $regularMemberCount++;
                }
            }

            // Members added = admin (1) + regular employees (excluding creator)
            $membersAdded = 1 + $regularMemberCount; // 1 for admin + regular employees

            // System message: Dept Manager created this group
            Message::create([
                'chat_id' => $departmentChat->id,
                'user_id' => $deptManagerUser->id,
                'content' => "{$deptManagerFullName} created this group",
                'message_type' => 'system',
            ]);

            // System message: Dept Manager added X members
            Message::create([
                'chat_id' => $departmentChat->id,
                'user_id' => $deptManagerUser->id,
                'content' => "{$deptManagerFullName} added {$membersAdded} members",
                'message_type' => 'system',
            ]);

            // System message: Dept Manager set Admin to Admin
            Message::create([
                'chat_id' => $departmentChat->id,
                'user_id' => $deptManagerUser->id,
                'content' => "{$deptManagerFullName} set {$adminFullName} to Admin",
                'message_type' => 'system',
            ]);

            // Welcome message from dept manager (with formatting examples)
            $welcomeMessages = [
                "Hello *{$department->name}* team! Welcome to our department chat! 👋\n\nThis is our space for:\n- Department updates\n- Team discussions\n- _Collaboration_ on projects\n- Sharing ideas and feedback\n\nLet's work together to achieve great things! 🚀",
                "Hi everyone! *Welcome* to the {$department->name} group! 🎉\n\nI'm excited to have you all here. This chat is for:\n- Daily updates and check-ins\n- Project discussions\n- _Team_ coordination\n- Support and help\n\nLooking forward to productive conversations! 💬",
                "Welcome to the *{$department->name}* chat, team! 👋\n\nThis is where we'll:\n- Share important updates\n- Coordinate on tasks\n- _Collaborate_ effectively\n- Support each other\n\nLet's make this a great communication channel! 🌟",
            ];

            Message::create([
                'chat_id' => $departmentChat->id,
                'user_id' => $deptManagerUser->id,
                'content' => $welcomeMessages[array_rand($welcomeMessages)],
                'message_type' => 'text',
                'created_at' => now()->addSeconds(10),
            ]);
        }
    }

    private function getUserIdsFromEmployees(Collection $employees): Collection
    {
        return $employees
            ->map(fn (Employee $employee) => User::where('email', $employee->email)->value('id'))
            ->filter();
    }
}
