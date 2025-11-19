<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAttendanceSettingRequest;
use App\Http\Requests\UpdateAttendanceSettingRequest;
use App\Models\AttendanceSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class AttendanceSettingsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            AttendanceSetting::query()->latest()->first()
        );
    }

    public function store(StoreAttendanceSettingRequest $request): JsonResponse
    {
        if (AttendanceSetting::query()->exists()) {
            return response()->json([
                'message' => 'Attendance settings already exist. Please update the existing record.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = $request->validated();
        unset($data['password']);
        Log::debug('AttendanceSettingsController@store payload', $data);

        $attendanceSetting = AttendanceSetting::query()->create($data);

        return response()->json($attendanceSetting, Response::HTTP_CREATED);
    }

    public function show(AttendanceSetting $attendanceSetting): JsonResponse
    {
        return response()->json($attendanceSetting);
    }

    public function update(
        UpdateAttendanceSettingRequest $request,
        AttendanceSetting $attendanceSetting
    ): JsonResponse {
        $data = $request->validated();
        unset($data['password']);
        Log::debug('AttendanceSettingsController@update payload', $data);

        $attendanceSetting->update($data);

        return response()->json($attendanceSetting->refresh());
    }

    public function destroy(): Response
    {
        return response()->noContent(Response::HTTP_METHOD_NOT_ALLOWED);
    }
}
