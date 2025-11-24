<?php

namespace Database\Seeders;

use App\Models\Document;
use App\Models\DocumentAccessRequest;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;

class DocumentAccessRequestSeeder extends Seeder
{
    public function run(): void
    {
        // Clear existing access requests (including soft deleted)
        DocumentAccessRequest::withTrashed()->forceDelete();

        $hrDept = \App\Models\Department::where('code', 'HR')->first();
        $itDept = \App\Models\Department::where('code', 'IT')->first();

        if (! $hrDept || ! $itDept) {
            $this->command->warn('HR or IT departments not found. Please run DatabaseSeeder first.');

            return;
        }

        $adminUser = User::where('email', 'admin@hrnexus.com')->first();
        $hrManager = Employee::where('role', 'department_manager')->where('department_id', $hrDept->id)->first();
        $itManager = Employee::where('role', 'department_manager')->where('department_id', $itDept->id)->first();
        $hrEmployees = Employee::where('role', 'employee')->where('department_id', $hrDept->id)->get();
        $itEmployees = Employee::where('role', 'employee')->where('department_id', $itDept->id)->get();

        if (! $adminUser || ! $hrManager || ! $itManager || $hrEmployees->isEmpty() || $itEmployees->isEmpty()) {
            $this->command->warn('Missing required data. Please run DatabaseSeeder first.');

            return;
        }

        // Get only private and department documents (exclude public)
        $hrRestrictedDocs = Document::where('department_id', $hrDept->id)
            ->whereIn('accessibility', ['private', 'department'])
            ->get();
        $itRestrictedDocs = Document::where('department_id', $itDept->id)
            ->whereIn('accessibility', ['private', 'department'])
            ->get();

        if ($hrRestrictedDocs->isEmpty() && $itRestrictedDocs->isEmpty()) {
            $this->command->warn('No private or department documents found. Please run DocumentSeeder first.');

            return;
        }

        $requests = [];
        $now = now();

        $hrManagerUser = User::where('email', $hrManager->email)->first();
        $itManagerUser = User::where('email', $itManager->email)->first();

        // ============================================================================
        // SCENARIO 1: IT DEPT MANAGER REQUESTS FROM HR (2-3 requests)
        // ============================================================================
        if ($itManagerUser && $hrRestrictedDocs->isNotEmpty()) {
            $numRequests = rand(2, 3);
            $targetDocs = $hrRestrictedDocs->shuffle()->take(min($numRequests, $hrRestrictedDocs->count()));

            foreach ($targetDocs as $index => $targetDoc) {
                // Status: 50% approved, 30% pending, 20% rejected
                $statusRand = rand(1, 10);
                $status = match (true) {
                    $statusRand <= 5 => 'approved',
                    $statusRand <= 8 => 'pending',
                    default => 'rejected',
                };

                $requestedAt = $now->copy()->subDays(rand(1, 30));
                $requestMessage = fake()->sentence();

                $reviewedBy = null;
                $reviewedAt = null;
                $reviewMessage = null;

                if ($status !== 'pending') {
                    $reviewedBy = $hrManagerUser?->id ?? $adminUser->id;
                    $reviewedAt = $requestedAt->copy()->addDays(rand(1, 7));
                    $reviewMessage = $status === 'approved' ? 'Access granted.' : 'Access denied.';
                }

                $requests[] = [
                    'user_id' => $itManagerUser->id,
                    'document_id' => $targetDoc->id,
                    'status' => $status,
                    'requested_at' => $requestedAt,
                    'request_message' => $requestMessage,
                    'reviewed_by' => $reviewedBy,
                    'reviewed_at' => $reviewedAt,
                    'review_message' => $reviewMessage,
                    'created_at' => $requestedAt,
                    'updated_at' => $reviewedAt ?? $requestedAt,
                ];
            }
        }

        // ============================================================================
        // SCENARIO 2: IT EMPLOYEES REQUEST FROM HR (2-3 requests from different employees)
        // ============================================================================
        if ($hrRestrictedDocs->isNotEmpty()) {
            $numRequests = rand(2, 3);
            $selectedItEmployees = $itEmployees->shuffle()->take(min($numRequests, $itEmployees->count()));
            $targetDocs = $hrRestrictedDocs->shuffle()->take($numRequests);

            foreach ($selectedItEmployees as $index => $employee) {
                $employeeUser = User::where('email', $employee->email)->first();
                if (! $employeeUser || ! isset($targetDocs[$index])) {
                    continue;
                }

                $targetDoc = $targetDocs[$index];

                // Status: 50% approved, 30% pending, 20% rejected
                $statusRand = rand(1, 10);
                $status = match (true) {
                    $statusRand <= 5 => 'approved',
                    $statusRand <= 8 => 'pending',
                    default => 'rejected',
                };

                $requestedAt = $now->copy()->subDays(rand(1, 30));
                $requestMessage = fake()->sentence();

                $reviewedBy = null;
                $reviewedAt = null;
                $reviewMessage = null;

                if ($status !== 'pending') {
                    $reviewedBy = $hrManagerUser?->id ?? $adminUser->id;
                    $reviewedAt = $requestedAt->copy()->addDays(rand(1, 7));
                    $reviewMessage = $status === 'approved' ? 'Access granted.' : 'Access denied.';
                }

                $requests[] = [
                    'user_id' => $employeeUser->id,
                    'document_id' => $targetDoc->id,
                    'status' => $status,
                    'requested_at' => $requestedAt,
                    'request_message' => $requestMessage,
                    'reviewed_by' => $reviewedBy,
                    'reviewed_at' => $reviewedAt,
                    'review_message' => $reviewMessage,
                    'created_at' => $requestedAt,
                    'updated_at' => $reviewedAt ?? $requestedAt,
                ];
            }
        }

        // ============================================================================
        // SCENARIO 3: HR EMPLOYEES REQUEST FROM IT (2-3 requests from different employees)
        // ============================================================================
        if ($itRestrictedDocs->isNotEmpty()) {
            $numRequests = rand(2, 3);
            $selectedHrEmployees = $hrEmployees->shuffle()->take(min($numRequests, $hrEmployees->count()));
            $targetDocs = $itRestrictedDocs->shuffle()->take($numRequests);

            foreach ($selectedHrEmployees as $index => $employee) {
                $employeeUser = User::where('email', $employee->email)->first();
                if (! $employeeUser || ! isset($targetDocs[$index])) {
                    continue;
                }

                $targetDoc = $targetDocs[$index];

                // Status: 50% approved, 30% pending, 20% rejected
                $statusRand = rand(1, 10);
                $status = match (true) {
                    $statusRand <= 5 => 'approved',
                    $statusRand <= 8 => 'pending',
                    default => 'rejected',
                };

                $requestedAt = $now->copy()->subDays(rand(1, 30));
                $requestMessage = fake()->sentence();

                $reviewedBy = null;
                $reviewedAt = null;
                $reviewMessage = null;

                if ($status !== 'pending') {
                    $reviewedBy = $itManagerUser?->id ?? $adminUser->id;
                    $reviewedAt = $requestedAt->copy()->addDays(rand(1, 7));
                    $reviewMessage = $status === 'approved' ? 'Access granted.' : 'Access denied.';
                }

                $requests[] = [
                    'user_id' => $employeeUser->id,
                    'document_id' => $targetDoc->id,
                    'status' => $status,
                    'requested_at' => $requestedAt,
                    'request_message' => $requestMessage,
                    'reviewed_by' => $reviewedBy,
                    'reviewed_at' => $reviewedAt,
                    'review_message' => $reviewMessage,
                    'created_at' => $requestedAt,
                    'updated_at' => $reviewedAt ?? $requestedAt,
                ];
            }
        }

        // ============================================================================
        // SCENARIO 4: HR EMPLOYEES REQUEST PRIVATE FROM OWN DEPARTMENT (1-2 requests)
        // ============================================================================
        $hrPrivateDocs = $hrRestrictedDocs->where('accessibility', 'private');
        if ($hrPrivateDocs->isNotEmpty()) {
            $numRequests = rand(1, 2);
            $selectedHrEmployees = $hrEmployees->shuffle()->take(min($numRequests, $hrEmployees->count()));

            foreach ($selectedHrEmployees as $employee) {
                $employeeUser = User::where('email', $employee->email)->first();
                if (! $employeeUser) {
                    continue;
                }

                // Find private documents from HR that are NOT uploaded by this employee
                $eligibleDocs = $hrPrivateDocs->filter(function ($doc) use ($employeeUser) {
                    return $doc->user_id !== $employeeUser->id;
                });

                if ($eligibleDocs->isEmpty()) {
                    continue;
                }

                $targetDoc = $eligibleDocs->random();

                // Most likely pending for own department requests
                $statusRand = rand(1, 10);
                $status = match (true) {
                    $statusRand <= 5 => 'pending',
                    $statusRand <= 8 => 'approved',
                    default => 'rejected',
                };

                $requestedAt = $now->copy()->subDays(rand(1, 30));
                $requestMessage = fake()->sentence();

                $reviewedBy = null;
                $reviewedAt = null;
                $reviewMessage = null;

                if ($status !== 'pending') {
                    $reviewedBy = $hrManagerUser?->id ?? $adminUser->id;
                    $reviewedAt = $requestedAt->copy()->addDays(rand(1, 7));
                    $reviewMessage = $status === 'approved' ? 'Access granted.' : 'Access denied.';
                }

                $requests[] = [
                    'user_id' => $employeeUser->id,
                    'document_id' => $targetDoc->id,
                    'status' => $status,
                    'requested_at' => $requestedAt,
                    'request_message' => $requestMessage,
                    'reviewed_by' => $reviewedBy,
                    'reviewed_at' => $reviewedAt,
                    'review_message' => $reviewMessage,
                    'created_at' => $requestedAt,
                    'updated_at' => $reviewedAt ?? $requestedAt,
                ];
            }
        }

        // ============================================================================
        // SCENARIO 5: IT EMPLOYEES REQUEST PRIVATE FROM OWN DEPARTMENT (1-2 requests)
        // ============================================================================
        $itPrivateDocs = $itRestrictedDocs->where('accessibility', 'private');
        if ($itPrivateDocs->isNotEmpty()) {
            $numRequests = rand(1, 2);
            $selectedItEmployees = $itEmployees->shuffle()->take(min($numRequests, $itEmployees->count()));

            foreach ($selectedItEmployees as $employee) {
                $employeeUser = User::where('email', $employee->email)->first();
                if (! $employeeUser) {
                    continue;
                }

                // Find private documents from IT that are NOT uploaded by this employee
                $eligibleDocs = $itPrivateDocs->filter(function ($doc) use ($employeeUser) {
                    return $doc->user_id !== $employeeUser->id;
                });

                if ($eligibleDocs->isEmpty()) {
                    continue;
                }

                $targetDoc = $eligibleDocs->random();

                // Most likely pending for own department requests
                $statusRand = rand(1, 10);
                $status = match (true) {
                    $statusRand <= 5 => 'pending',
                    $statusRand <= 8 => 'approved',
                    default => 'rejected',
                };

                $requestedAt = $now->copy()->subDays(rand(1, 30));
                $requestMessage = fake()->sentence();

                $reviewedBy = null;
                $reviewedAt = null;
                $reviewMessage = null;

                if ($status !== 'pending') {
                    $reviewedBy = $itManagerUser?->id ?? $adminUser->id;
                    $reviewedAt = $requestedAt->copy()->addDays(rand(1, 7));
                    $reviewMessage = $status === 'approved' ? 'Access granted.' : 'Access denied.';
                }

                $requests[] = [
                    'user_id' => $employeeUser->id,
                    'document_id' => $targetDoc->id,
                    'status' => $status,
                    'requested_at' => $requestedAt,
                    'request_message' => $requestMessage,
                    'reviewed_by' => $reviewedBy,
                    'reviewed_at' => $reviewedAt,
                    'review_message' => $reviewMessage,
                    'created_at' => $requestedAt,
                    'updated_at' => $reviewedAt ?? $requestedAt,
                ];
            }
        }

        // ============================================================================
        // CREATE ALL REQUESTS
        // ============================================================================
        foreach ($requests as $requestData) {
            DocumentAccessRequest::create($requestData);
        }

        $this->command->info('Document access requests seeded successfully!');
        $this->command->info('Created '.count($requests).' access requests');
    }
}
