<?php

use App\Models\AttendanceSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('requires the current password when creating attendance settings', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password123'),
    ]);

    $this->actingAs($user, 'web');

    $response = $this->postJson('/api/attendance-settings', [
        'required_time_in' => '08:00',
        'required_time_out' => '22:00',
        'break_duration_minutes' => 30,
        'break_is_counted' => false,
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['password']);
});

it('requires the current password when updating attendance settings', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password123'),
    ]);

    $setting = AttendanceSetting::factory()->create();

    $this->actingAs($user, 'web');

    $response = $this->putJson("/api/attendance-settings/{$setting->id}", [
        'required_time_in' => '09:00',
        'required_time_out' => '21:00',
        'break_duration_minutes' => 45,
        'break_is_counted' => true,
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['password']);
});

it('updates attendance settings when the correct password is provided', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password123'),
    ]);

    $setting = AttendanceSetting::factory()->create([
        'required_time_in' => '08:00',
        'required_time_out' => '22:00',
        'break_duration_minutes' => 15,
        'break_is_counted' => false,
    ]);

    $this->actingAs($user, 'web');

    $response = $this->putJson("/api/attendance-settings/{$setting->id}", [
        'required_time_in' => '09:00',
        'required_time_out' => '21:00',
        'break_duration_minutes' => 30,
        'break_is_counted' => true,
        'password' => 'password123',
    ]);

    $response->assertOk()
        ->assertJsonPath('required_time_in', '09:00')
        ->assertJsonPath('break_is_counted', true);

    $this->assertDatabaseHas('attendance_settings', [
        'id' => $setting->id,
        'required_time_in' => '09:00',
        'required_time_out' => '21:00',
        'break_duration_minutes' => 30,
        'break_is_counted' => true,
    ]);
});
