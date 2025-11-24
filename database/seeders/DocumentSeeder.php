<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Document;
use App\Models\Employee;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DocumentSeeder extends Seeder
{
    public function run(): void
    {
        // Clear existing documents (including soft deleted)
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('document_tags')->truncate();
        DB::table('document_downloads')->truncate();
        DB::table('document_access_requests')->truncate();
        Document::withTrashed()->forceDelete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Get only HR and IT departments
        $hrDept = Department::where('code', 'HR')->first();
        $itDept = Department::where('code', 'IT')->first();

        if (! $hrDept || ! $itDept) {
            $this->command->warn('HR or IT departments not found. Please run DatabaseSeeder first.');

            return;
        }

        $tags = Tag::all();

        // Get employees by role and department
        $adminEmployee = Employee::where('email', 'admin@hrnexus.com')->first();
        $adminUser = $adminEmployee ? User::where('email', 'admin@hrnexus.com')->first() : null;
        $hrManager = Employee::where('role', 'department_manager')->where('department_id', $hrDept->id)->first();
        $itManager = Employee::where('role', 'department_manager')->where('department_id', $itDept->id)->first();
        $hrEmployees = Employee::where('role', 'employee')->where('department_id', $hrDept->id)->get();
        $itEmployees = Employee::where('role', 'employee')->where('department_id', $itDept->id)->get();

        if (! $adminUser || ! $hrManager || ! $itManager || $hrEmployees->isEmpty() || $itEmployees->isEmpty()) {
            $this->command->warn('Missing admin, managers, or employees. Please run DatabaseSeeder first.');

            return;
        }

        // File types
        $fileTypes = [
            ['mime' => 'application/pdf', 'ext' => 'pdf'],
            ['mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'ext' => 'docx'],
            ['mime' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'ext' => 'pptx'],
            ['mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'ext' => 'xlsx'],
        ];

        $fileNames = [
            'Employee Handbook',
            'Company Policy Manual',
            'Training Materials',
            'Quarterly Report',
            'Budget Analysis',
            'Project Proposal',
            'Meeting Minutes',
            'Safety Guidelines',
            'Code of Conduct',
            'Performance Review Template',
            'Onboarding Checklist',
            'Leave Request Form',
            'Expense Report',
            'Technical Documentation',
            'User Guide',
            'Process Flow Chart',
            'Compliance Report',
            'Annual Review',
            'Strategic Plan',
            'Team Guidelines',
        ];

        $documents = [];
        $now = now();
        $fileNameIndex = 0;

        // ============================================================================
        // ADMIN DOCUMENTS (4-6 documents)
        // Department: Admin's own department (HR)
        // File types: PDF and Word (at least 1 of each)
        // ============================================================================
        $adminUserId = $adminUser->id;
        $adminDeptId = $adminEmployee->department_id ?? $hrDept->id;
        $numAdminDocs = rand(4, 6);

        // Ensure at least 1 PDF and 1 Word
        $adminFileTypes = ['pdf', 'docx', 'pdf', 'docx']; // Weighted to ensure both types
        shuffle($adminFileTypes);

        for ($i = 0; $i < $numAdminDocs; $i++) {
            // First is PDF, second is Word, then random
            $ext = match ($i) {
                0 => 'pdf',
                1 => 'docx',
                default => $adminFileTypes[$i % count($adminFileTypes)],
            };
            $fileType = collect($fileTypes)->firstWhere('ext', $ext);
            $mimeType = $fileType['mime'];
            $extension = $fileType['ext'];

            $fileNameBase = $fileNames[$fileNameIndex % count($fileNames)];
            $fileName = $fileNameBase.'.'.$extension;
            $storedName = 'doc_'.uniqid().'.'.$extension;
            $fileNameIndex++;

            // Mix of accessibilities
            $accessibility = match ($i % 3) {
                0 => 'public',
                1 => 'department',
                default => 'private',
            };

            // Admin's own department
            $departmentId = $adminDeptId;

            // All admin documents are approved
            $reviewMessage = "File uploaded by {$adminEmployee->first_name} {$adminEmployee->last_name} - Admin";

            $doc = [
                'user_id' => $adminUserId,
                'department_id' => $departmentId,
                'file_name' => $fileName,
                'stored_name' => $storedName,
                'mime_type' => $mimeType,
                'size' => rand(100000, 5000000),
                'description' => fake()->sentence(),
                'content' => null,
                'embedding' => null,
                'accessibility' => $accessibility,
                'status' => 'approved',
                'reviewed_by' => $adminUserId,
                'reviewed_at' => $now->copy()->subDays(rand(1, 30)),
                'review_message' => $reviewMessage,
                'created_at' => $now->copy()->subDays(rand(1, 60)),
                'updated_at' => $now->copy()->subDays(rand(1, 60)),
            ];

            $documents[] = $doc;
        }

        // ============================================================================
        // HR DEPARTMENT MANAGER DOCUMENTS (3-5 documents)
        // Department: HR only
        // File types: PDF, Excel, Word, PPT (at least 1 PDF)
        // ============================================================================
        $hrManagerUser = User::where('email', $hrManager->email)->first();
        if ($hrManagerUser) {
            $numHrManagerDocs = rand(3, 5);
            $hrManagerFileTypes = ['pdf', 'xlsx', 'docx', 'pptx'];
            shuffle($hrManagerFileTypes);

            for ($i = 0; $i < $numHrManagerDocs; $i++) {
                // First is always PDF
                $ext = $i === 0 ? 'pdf' : $hrManagerFileTypes[$i % count($hrManagerFileTypes)];
                $fileType = collect($fileTypes)->firstWhere('ext', $ext);
                $mimeType = $fileType['mime'];
                $extension = $fileType['ext'];

                $fileNameBase = $fileNames[$fileNameIndex % count($fileNames)];
                $fileName = $fileNameBase.'.'.$extension;
                $storedName = 'doc_'.uniqid().'.'.$extension;
                $fileNameIndex++;

                // Mix of accessibilities
                $accessibility = match (rand(0, 2)) {
                    0 => 'public',
                    1 => 'department',
                    default => 'private',
                };

                // All dept manager documents are approved
                $reviewMessage = "File uploaded by {$hrManager->first_name} {$hrManager->last_name} - Department Manager";

                $doc = [
                    'user_id' => $hrManagerUser->id,
                    'department_id' => $hrDept->id,
                    'file_name' => $fileName,
                    'stored_name' => $storedName,
                    'mime_type' => $mimeType,
                    'size' => rand(100000, 5000000),
                    'description' => fake()->sentence(),
                    'content' => null,
                    'embedding' => null,
                    'accessibility' => $accessibility,
                    'status' => 'approved',
                    'reviewed_by' => $hrManagerUser->id,
                    'reviewed_at' => $now->copy()->subDays(rand(1, 30)),
                    'review_message' => $reviewMessage,
                    'created_at' => $now->copy()->subDays(rand(1, 60)),
                    'updated_at' => $now->copy()->subDays(rand(1, 60)),
                ];

                $documents[] = $doc;
            }
        }

        // ============================================================================
        // HR EMPLOYEE DOCUMENTS (2-4 per employee)
        // Department: HR only
        // Status: Mix - at least 3 employees have pending, some rejected, rest approved
        // ============================================================================
        $hrEmployeesWithPending = [];
        foreach ($hrEmployees as $employee) {
            $employeeUser = User::where('email', $employee->email)->first();
            if (! $employeeUser) {
                continue;
            }

            $numEmployeeDocs = rand(2, 4);
            $employeeFileTypes = ['pdf', 'docx', 'xlsx', 'pptx'];
            shuffle($employeeFileTypes);

            for ($i = 0; $i < $numEmployeeDocs; $i++) {
                // First is always PDF
                $ext = $i === 0 ? 'pdf' : $employeeFileTypes[$i % count($employeeFileTypes)];
                $fileType = collect($fileTypes)->firstWhere('ext', $ext);
                $mimeType = $fileType['mime'];
                $extension = $fileType['ext'];

                $fileNameBase = $fileNames[$fileNameIndex % count($fileNames)];
                $fileName = $fileNameBase.'.'.$extension;
                $storedName = 'doc_'.uniqid().'.'.$extension;
                $fileNameIndex++;

                // Mix of accessibilities
                $accessibility = match (rand(0, 2)) {
                    0 => 'private',
                    1 => 'department',
                    default => 'public',
                };

                // Determine status
                $status = 'pending';
                $reviewedBy = null;
                $reviewedAt = null;
                $reviewMessage = null;

                $needsPending = count($hrEmployeesWithPending) < 3;
                $randomStatus = rand(1, 10);

                if ($needsPending && $randomStatus <= 3) {
                    $status = 'pending';
                    if (! in_array($employee->id, $hrEmployeesWithPending)) {
                        $hrEmployeesWithPending[] = $employee->id;
                    }
                } elseif ($randomStatus <= 2) {
                    // 20% rejected
                    $status = 'rejected';
                    $hrManagerUser = User::where('email', $hrManager->email)->first();
                    $reviewedBy = $hrManagerUser?->id ?? $adminUserId;
                    $reviewedAt = $now->copy()->subDays(rand(1, 30));
                    $reviewMessage = 'Document does not meet company standards. Please review and resubmit.';
                } else {
                    // Rest approved
                    $status = 'approved';
                    $hrManagerUser = User::where('email', $hrManager->email)->first();
                    $reviewedBy = $hrManagerUser?->id ?? $adminUserId;
                    $reviewedAt = $now->copy()->subDays(rand(1, 30));
                    $reviewMessage = 'Document approved and ready for use.';
                }

                $doc = [
                    'user_id' => $employeeUser->id,
                    'department_id' => $hrDept->id,
                    'file_name' => $fileName,
                    'stored_name' => $storedName,
                    'mime_type' => $mimeType,
                    'size' => rand(100000, 5000000),
                    'description' => fake()->sentence(),
                    'content' => null,
                    'embedding' => null,
                    'accessibility' => $accessibility,
                    'status' => $status,
                    'reviewed_by' => $reviewedBy,
                    'reviewed_at' => $reviewedAt,
                    'review_message' => $reviewMessage,
                    'created_at' => $now->copy()->subDays(rand(1, 60)),
                    'updated_at' => $now->copy()->subDays(rand(1, 60)),
                ];

                $documents[] = $doc;
            }
        }

        // ============================================================================
        // IT DEPARTMENT MANAGER DOCUMENTS (3-5 documents)
        // Department: IT only
        // File types: PDF, PPT, Excel, Word (at least 1 PDF)
        // ============================================================================
        $itManagerUser = User::where('email', $itManager->email)->first();
        if ($itManagerUser) {
            $numItManagerDocs = rand(3, 5);
            $itManagerFileTypes = ['pdf', 'pptx', 'xlsx', 'docx'];
            shuffle($itManagerFileTypes);

            for ($i = 0; $i < $numItManagerDocs; $i++) {
                // First is always PDF
                $ext = $i === 0 ? 'pdf' : $itManagerFileTypes[$i % count($itManagerFileTypes)];
                $fileType = collect($fileTypes)->firstWhere('ext', $ext);
                $mimeType = $fileType['mime'];
                $extension = $fileType['ext'];

                $fileNameBase = $fileNames[$fileNameIndex % count($fileNames)];
                $fileName = $fileNameBase.'.'.$extension;
                $storedName = 'doc_'.uniqid().'.'.$extension;
                $fileNameIndex++;

                // Mix of accessibilities
                $accessibility = match (rand(0, 2)) {
                    0 => 'public',
                    1 => 'department',
                    default => 'private',
                };

                // All dept manager documents are approved
                $reviewMessage = "File uploaded by {$itManager->first_name} {$itManager->last_name} - Department Manager";

                $doc = [
                    'user_id' => $itManagerUser->id,
                    'department_id' => $itDept->id,
                    'file_name' => $fileName,
                    'stored_name' => $storedName,
                    'mime_type' => $mimeType,
                    'size' => rand(100000, 5000000),
                    'description' => fake()->sentence(),
                    'content' => null,
                    'embedding' => null,
                    'accessibility' => $accessibility,
                    'status' => 'approved',
                    'reviewed_by' => $itManagerUser->id,
                    'reviewed_at' => $now->copy()->subDays(rand(1, 30)),
                    'review_message' => $reviewMessage,
                    'created_at' => $now->copy()->subDays(rand(1, 60)),
                    'updated_at' => $now->copy()->subDays(rand(1, 60)),
                ];

                $documents[] = $doc;
            }
        }

        // ============================================================================
        // IT EMPLOYEE DOCUMENTS (2-4 per employee)
        // Department: IT only
        // Status: Mix - at least 3 employees have pending, some rejected, rest approved
        // ============================================================================
        $itEmployeesWithPending = [];
        foreach ($itEmployees as $employee) {
            $employeeUser = User::where('email', $employee->email)->first();
            if (! $employeeUser) {
                continue;
            }

            $numEmployeeDocs = rand(2, 4);
            $employeeFileTypes = ['pdf', 'docx', 'xlsx', 'pptx'];
            shuffle($employeeFileTypes);

            for ($i = 0; $i < $numEmployeeDocs; $i++) {
                // First is always PDF
                $ext = $i === 0 ? 'pdf' : $employeeFileTypes[$i % count($employeeFileTypes)];
                $fileType = collect($fileTypes)->firstWhere('ext', $ext);
                $mimeType = $fileType['mime'];
                $extension = $fileType['ext'];

                $fileNameBase = $fileNames[$fileNameIndex % count($fileNames)];
                $fileName = $fileNameBase.'.'.$extension;
                $storedName = 'doc_'.uniqid().'.'.$extension;
                $fileNameIndex++;

                // Mix of accessibilities
                $accessibility = match (rand(0, 2)) {
                    0 => 'private',
                    1 => 'department',
                    default => 'public',
                };

                // Determine status
                $status = 'pending';
                $reviewedBy = null;
                $reviewedAt = null;
                $reviewMessage = null;

                $needsPending = count($itEmployeesWithPending) < 3;
                $randomStatus = rand(1, 10);

                if ($needsPending && $randomStatus <= 3) {
                    $status = 'pending';
                    if (! in_array($employee->id, $itEmployeesWithPending)) {
                        $itEmployeesWithPending[] = $employee->id;
                    }
                } elseif ($randomStatus <= 2) {
                    // 20% rejected
                    $status = 'rejected';
                    $itManagerUser = User::where('email', $itManager->email)->first();
                    $reviewedBy = $itManagerUser?->id ?? $adminUserId;
                    $reviewedAt = $now->copy()->subDays(rand(1, 30));
                    $reviewMessage = 'Document does not meet company standards. Please review and resubmit.';
                } else {
                    // Rest approved
                    $status = 'approved';
                    $itManagerUser = User::where('email', $itManager->email)->first();
                    $reviewedBy = $itManagerUser?->id ?? $adminUserId;
                    $reviewedAt = $now->copy()->subDays(rand(1, 30));
                    $reviewMessage = 'Document approved and ready for use.';
                }

                $doc = [
                    'user_id' => $employeeUser->id,
                    'department_id' => $itDept->id,
                    'file_name' => $fileName,
                    'stored_name' => $storedName,
                    'mime_type' => $mimeType,
                    'size' => rand(100000, 5000000),
                    'description' => fake()->sentence(),
                    'content' => null,
                    'embedding' => null,
                    'accessibility' => $accessibility,
                    'status' => $status,
                    'reviewed_by' => $reviewedBy,
                    'reviewed_at' => $reviewedAt,
                    'review_message' => $reviewMessage,
                    'created_at' => $now->copy()->subDays(rand(1, 60)),
                    'updated_at' => $now->copy()->subDays(rand(1, 60)),
                ];

                $documents[] = $doc;
            }
        }

        // ============================================================================
        // CREATE ALL DOCUMENTS
        // ============================================================================
        $createdDocuments = [];
        foreach ($documents as $docData) {
            $doc = Document::create($docData);
            $createdDocuments[] = $doc;

            // Attach 0-4 random tags
            if ($tags->isNotEmpty() && rand(0, 4) > 0) {
                $numTags = rand(1, min(4, $tags->count()));
                $randomTags = $tags->random($numTags);
                $doc->tags()->attach($randomTags->pluck('id'));
            }
        }

        // ============================================================================
        // TRASH (3-5 documents from different departments)
        // ============================================================================
        $docsToDelete = collect($createdDocuments)
            ->where('status', 'approved')
            ->shuffle()
            ->take(rand(3, 5));

        foreach ($docsToDelete as $doc) {
            $doc->deleted_by = rand(0, 1) ? $adminUserId : $doc->user_id;
            $doc->save();
            $doc->delete();
        }

        $this->command->info('Documents seeded successfully!');
        $this->command->info('Created '.count($createdDocuments).' documents');
        $this->command->info('Trashed '.$docsToDelete->count().' documents');
    }
}
