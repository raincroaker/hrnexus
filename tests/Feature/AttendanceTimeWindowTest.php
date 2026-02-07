<?php

use App\Http\Requests\StoreAttendanceRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

use function Pest\Laravel\postJson;
use function Pest\Laravel\withoutMiddleware;

it('accepts attendance times within 06:00-20:00', function () {
    $request = new StoreAttendanceRequest;

    $rules = $request->rules();
    $rules['employee_id'] = ['required']; // avoid DB dependency for exists

    $validator = Validator::make([
        'employee_id' => 1,
        'date' => '2025-01-01',
        'time_in' => '06:00',
        'time_out' => '20:00',
    ], $rules);

    $request->withValidator($validator);

    expect($validator->fails())->toBeFalse();
});

it('rejects attendance times outside 06:00-20:00', function () {
    $request = new StoreAttendanceRequest;

    $rules = $request->rules();
    $rules['employee_id'] = ['required']; // avoid DB dependency for exists

    $validator = Validator::make([
        'employee_id' => 1,
        'date' => '2025-01-01',
        'time_in' => '05:59',
        'time_out' => '20:01',
    ], $rules);

    $request->withValidator($validator);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->toArray())
        ->toHaveKey('time_in')
        ->toHaveKey('time_out');
});

it('rejects biometric scans outside 06:00-20:00', function () {
    withoutMiddleware();

    $payload = [
        'employee_code' => 'EMP-001',
        'scan_date' => Carbon::today()->format('Y-m-d'),
        'scan_time' => '05:59',
    ];

    $response = postJson('/api/biometric-logs', $payload);

    $response->assertStatus(422)
        ->assertJsonFragment(['message' => 'Scan falls outside the supported time window (06:00-20:00).']);
});
