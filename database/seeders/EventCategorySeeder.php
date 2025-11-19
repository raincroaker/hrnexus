<?php

namespace Database\Seeders;

use App\Models\EventCategory;
use App\Models\User;
use Illuminate\Database\Seeder;

class EventCategorySeeder extends Seeder
{
    public function run(): void
    {
        // Get the admin user by email (admin@hrnexus.com from DatabaseSeeder)
        $adminUser = User::where('email', 'admin@hrnexus.com')->first() ?? User::first();

        if (! $adminUser) {
            $this->command->warn('No users found. Please run DatabaseSeeder first.');

            return;
        }

        $categories = [
            [
                'name' => 'Holidays',
                'color' => '#ef4444', // red
            ],
            [
                'name' => 'Meetings',
                'color' => '#3b82f6', // blue
            ],
            [
                'name' => 'Training',
                'color' => '#22c55e', // green
            ],
            [
                'name' => 'Deadlines',
                'color' => '#f59e0b', // amber
            ],
            [
                'name' => 'Presentations',
                'color' => '#10b981', // emerald
            ],
            [
                'name' => 'Reviews',
                'color' => '#a855f7', // purple
            ],
            [
                'name' => 'Planning',
                'color' => '#eab308', // yellow
            ],
            [
                'name' => 'Social',
                'color' => '#ec4899', // pink
            ],
        ];

        foreach ($categories as $category) {
            EventCategory::updateOrCreate(
                [
                    'name' => $category['name'],
                    'user_id' => $adminUser->id,
                ],
                [
                    'color' => $category['color'],
                ]
            );
        }

        $this->command->info('Event categories seeded successfully!');
    }
}
