<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TagSeeder extends Seeder
{
    public function run(): void
    {
        // Clear existing tags (including soft deleted)
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('document_tags')->truncate();
        Tag::withTrashed()->forceDelete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        $tags = [
            'Policy',
            'Training',
            'HR',
            'Finance',
            'Technical',
            'Confidential',
            'Public',
            'Internal',
            'Procedure',
            'Guideline',
            'Form',
            'Report',
        ];

        foreach ($tags as $tagName) {
            Tag::create([
                'name' => $tagName,
            ]);
        }

        // Soft delete 2-3 tags to test soft delete functionality
        $tagsToDelete = Tag::whereIn('name', ['Form', 'Report', 'Guideline'])->get();
        foreach ($tagsToDelete as $tag) {
            $tag->delete();
        }

        $this->command->info('Tags seeded successfully!');
    }
}
