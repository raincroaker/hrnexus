<?php

namespace Database\Seeders;

use App\Models\Document;
use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DocumentTagSeeder extends Seeder
{
    public function run(): void
    {
        // Clear existing document tags
        DB::table('document_tags')->truncate();
        $documents = Document::all();
        $tags = Tag::all();

        if ($documents->isEmpty() || $tags->isEmpty()) {
            $this->command->warn('No documents or tags found. Please run DocumentSeeder and TagSeeder first.');

            return;
        }

        // Attach tags to documents
        foreach ($documents as $document) {
            // Each document gets 0-3 tags
            $numTags = rand(0, 3);
            if ($numTags > 0) {
                $randomTags = $tags->random(min($numTags, $tags->count()));
                $document->tags()->attach($randomTags->pluck('id')->toArray());
            }
        }

        // Ensure some tags are used by multiple documents
        $popularTags = $tags->random(min(3, $tags->count()));
        $untaggedDocs = Document::doesntHave('tags')->get();

        foreach ($popularTags as $tag) {
            if ($untaggedDocs->isNotEmpty()) {
                $docsToTag = $untaggedDocs->random(min(2, $untaggedDocs->count()));
                $tag->documents()->attach($docsToTag->pluck('id')->toArray());
            }
        }

        $this->command->info('Document tags seeded successfully!');
    }
}
