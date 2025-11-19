<?php

use App\Models\Attendance;
use App\Models\AttendanceSetting;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('rejects scans outside the accepted window', function () {
    $user = User::factory()->create();
    $this->actingAs($user, 'web');

    $response = $this->postJson(route('api.biometric-logs.store'), [
        'employee_code' => 'EMP1000',
        'scan_date' => '2025-01-01',
        'scan_time' => '05:30',
    ]);

    $response->assertStatus(422);
    expect(Attendance::count())->toBe(0);
    expect(\App\Models\BiometricLog::count())->toBe(0);
});

it('saves the biometric log even when the employee is missing', function () {
    $user = User::factory()->create();
    $this->actingAs($user, 'web');

    $response = $this->postJson(route('api.biometric-logs.store'), [
        'employee_code' => 'EMP404',
        'scan_date' => '2025-01-01',
        'scan_time' => '08:15',
    ]);

    $response->assertCreated()
        ->assertJsonMissing(['attendance']);

    $this->assertDatabaseHas('biometric_logs', [
        'employee_code' => 'EMP404',
    ]);
});

it('updates attendance when the employee exists', function () {
    $user = User::factory()->create();
    $this->actingAs($user, 'web');

    AttendanceSetting::factory()->create([
        'required_time_in' => '08:00',
        'required_time_out' => '22:00',
        'break_duration_minutes' => 60,
        'break_is_counted' => false,
    ]);

    $employee = Employee::query()->create([
        'employee_code' => 'EMP001',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john@example.com',
        'role' => 'admin',
    ]);

    $this->postJson(route('api.biometric-logs.store'), [
        'employee_code' => $employee->employee_code,
        'scan_date' => '2025-01-01',
        'scan_time' => '08:05',
    ])->assertCreated();

    $this->postJson(route('api.biometric-logs.store'), [
        'employee_code' => $employee->employee_code,
        'scan_date' => '2025-01-01',
        'scan_time' => '18:30',
    ])->assertCreated()
        ->assertJsonPath('attendance.status', 'Late');

    $attendance = Attendance::query()->where('employee_id', $employee->id)->first();

    expect($attendance->time_in)->toBe('08:05:00');
    expect($attendance->time_out)->toBe('18:30:00');
    expect($attendance->status)->toBe('Late');
    expect($attendance->remarks)->toBe('Complete');
    expect($attendance->total_hours)->toBe(9.42);
});
