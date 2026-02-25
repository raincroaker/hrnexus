<?php

use App\Models\Employee;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    Employee::query()->create([
        'employee_code' => 'EMP-DASH-001',
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => $user->email,
        'role' => 'employee',
    ]);
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertStatus(200);
});