<?php

use App\Models\CalendarEvent;
use App\Models\EventCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('permanently deletes unused event categories', function () {
    $user = User::factory()->create();

    $category = EventCategory::create([
        'user_id' => $user->id,
        'name' => 'Temporary Category',
        'color' => '#abcdef',
    ]);

    $this->actingAs($user, 'web');

    $response = $this->deleteJson(route('api.event-categories.destroy', $category));

    $response->assertOk()
        ->assertJsonFragment(['message' => 'Category deleted successfully.']);

    $this->assertDatabaseMissing('event_categories', [
        'id' => $category->id,
    ]);
});

it('prevents deleting categories that are in use', function () {
    $user = User::factory()->create();

    $category = EventCategory::create([
        'user_id' => $user->id,
        'name' => 'Used Category',
        'color' => '#123456',
    ]);

    CalendarEvent::create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'department_id' => null,
        'visibility' => 'everyone',
        'title' => 'Planning Session',
        'description' => null,
        'location' => null,
        'is_all_day' => false,
        'start_date' => now()->toDateString(),
        'end_date' => null,
        'start_time' => '09:00',
        'end_time' => '10:00',
    ]);

    $this->actingAs($user, 'web');

    $response = $this->deleteJson(route('api.event-categories.destroy', $category));

    $response->assertStatus(422)
        ->assertJsonFragment([
            'message' => 'Cannot delete category. It is currently being used by one or more events.',
        ]);

    $this->assertDatabaseHas('event_categories', [
        'id' => $category->id,
    ]);
});
