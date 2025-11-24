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

        // Welcome message from admin (role-based, professional tone)
        $welcomeMessages = [
            "Good morning team. This is the company-wide communication channel for official announcements, policy updates, and organization-wide information.\n\nPlease use this space for:\n- Company-wide announcements\n- Policy and procedure updates\n- Important organizational communications\n- Cross-departmental coordination\n\nAll members are expected to maintain professionalism in all communications. For department-specific matters, please use your respective department channels.",
            "Welcome to the Everyone group. This channel serves as the primary platform for company-wide communications and official announcements.\n\nThis space is designated for:\n- Organizational announcements\n- Policy updates and changes\n- Company-wide initiatives\n- Important cross-functional communications\n\nPlease ensure all communications remain professional and aligned with company standards. Department-specific discussions should be conducted in your respective department channels.",
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

            // Welcome message from dept manager (role-based, professional tone)
            $welcomeMessages = [
                "Good morning {$department->name} team. This department channel is established for official department communications, project coordination, and team updates.\n\nThis channel is designated for:\n- Department-specific announcements\n- Project updates and coordination\n- Team task assignments\n- Department policy and procedure communications\n\nPlease maintain a professional tone in all communications. For urgent matters, please contact me directly.",
                "Welcome to the {$department->name} department channel. This space is reserved for official department communications and operational coordination.\n\nThis channel serves the following purposes:\n- Department announcements and updates\n- Project status and coordination\n- Team task management\n- Department-specific policy communications\n\nAll team members are expected to maintain professionalism and respond to communications in a timely manner.",
                "This is the {$department->name} department communication channel. This platform is designated for official department business and team coordination.\n\nUse this channel for:\n- Department-wide announcements\n- Project coordination and updates\n- Task assignments and status updates\n- Department policy and procedure matters\n\nPlease ensure all communications are professional and relevant to department operations.",
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
