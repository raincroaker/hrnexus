<?php

namespace Database\Seeders;

use App\Models\Document;
use App\Models\DocumentDownload;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DocumentDownloadSeeder extends Seeder
{
    public function run(): void
    {
        // Clear existing downloads
        DB::table('document_downloads')->truncate();
        // Only approved documents can be downloaded
        $approvedDocs = Document::where('status', 'approved')->get();

        if ($approvedDocs->isEmpty()) {
            $this->command->warn('No approved documents found. Please run DocumentSeeder first.');

            return;
        }

        $users = User::all();

        if ($users->isEmpty()) {
            $this->command->warn('No users found. Please run DatabaseSeeder first.');

            return;
        }

        $downloads = [];
        $now = now();

        // Create 12-15 download records
        $numDownloads = rand(12, 15);

        for ($i = 0; $i < $numDownloads; $i++) {
            $doc = $approvedDocs->random();
            $user = $users->random();

            // Check if user has access to this document
            $hasAccess = false;

            // Public documents - everyone has access
            if ($doc->accessibility === 'public') {
                $hasAccess = true;
            }
            // Department documents - user must be in same department
            elseif ($doc->accessibility === 'department' && $doc->department_id) {
                $employee = \App\Models\Employee::where('email', $user->email)->first();
                if ($employee && $employee->department_id === $doc->department_id) {
                    $hasAccess = true;
                }
            }
            // Private documents - only owner or approved access request
            elseif ($doc->accessibility === 'private') {
                if ($doc->user_id === $user->id) {
                    $hasAccess = true;
                } else {
                    // Check if user has approved access request
                    $hasAccess = $doc->accessRequests()
                        ->where('user_id', $user->id)
                        ->where('status', 'approved')
                        ->exists();
                }
            }

            // Admin can download anything
            $adminUser = User::where('email', 'admin@hrnexus.com')->first();
            if ($user->id === $adminUser?->id) {
                $hasAccess = true;
            }

            if ($hasAccess) {
                $downloads[] = [
                    'document_id' => $doc->id,
                    'user_id' => $user->id,
                    'downloaded_at' => $now->copy()->subDays(rand(1, 30)),
                ];
            }
        }

        // Create all downloads
        foreach ($downloads as $downloadData) {
            DocumentDownload::create($downloadData);
        }

        $this->command->info('Document downloads seeded successfully!');
    }
}
