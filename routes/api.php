<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceSettingsController;
use App\Http\Controllers\BackdoorController;
use App\Http\Controllers\BiometricLogController;
use App\Http\Controllers\CalendarEventController;
use App\Http\Controllers\DepartmentsController;
use App\Http\Controllers\DocumentsController;
use App\Http\Controllers\EmployeesController;
use App\Http\Controllers\EmployeeWarningMemoController;
use App\Http\Controllers\EventAttendeeController;
use App\Http\Controllers\EventCategoryController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\LeaveTypesController;
use App\Http\Controllers\PositionsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:web');

// Attendance settings
Route::middleware('auth:web')->group(function () {
    Route::post('/attendance-settings', [AttendanceSettingsController::class, 'store'])
        ->name('api.attendance-settings.store');
    Route::put('/attendance-settings/{attendanceSetting}', [AttendanceSettingsController::class, 'update'])
        ->whereNumber('attendanceSetting')
        ->name('api.attendance-settings.update');

    Route::post('/attendance', [AttendanceController::class, 'store'])
        ->name('api.attendance.store');
    Route::post('/attendance/sync', [AttendanceController::class, 'sync'])
        ->name('api.attendance.sync');
    Route::get('/attendance/export-csv', [AttendanceController::class, 'exportCsv'])
        ->name('api.attendance.export-csv');
    Route::get('/attendance/{employee}/export-pdf', [AttendanceController::class, 'exportEmployeePdf'])
        ->whereNumber('employee')
        ->name('api.attendance.export-pdf');
    Route::get('/employees/{employee}/summary', [AttendanceController::class, 'getEmployeeSummary'])
        ->whereNumber('employee')
        ->name('api.employees.summary');
    Route::put('/attendance/{attendance}', [AttendanceController::class, 'update'])
        ->whereNumber('attendance')
        ->name('api.attendance.update');
    Route::delete('/attendance/{attendance}', [AttendanceController::class, 'destroy'])
        ->whereNumber('attendance')
        ->name('api.attendance.destroy');
    Route::post('/attendance/{attendance}/toggle-overtime', [AttendanceController::class, 'toggleOvertime'])
        ->whereNumber('attendance')
        ->name('api.attendance.toggle-overtime');
    Route::post('/attendance/bulk-overtime', [AttendanceController::class, 'bulkToggleOvertime'])
        ->name('api.attendance.bulk-overtime');

    Route::post('/employee-leaves', [AttendanceController::class, 'storeEmployeeLeave'])
        ->name('api.employee-leaves.store');
    Route::put('/employee-leaves/{employeeLeave}', [AttendanceController::class, 'updateEmployeeLeave'])
        ->whereNumber('employeeLeave')
        ->name('api.employee-leaves.update');
    Route::delete('/employee-leaves/{employeeLeave}', [AttendanceController::class, 'destroyEmployeeLeave'])
        ->whereNumber('employeeLeave')
        ->name('api.employee-leaves.destroy');

    Route::post('/employee-overtime', [AttendanceController::class, 'storeEmployeeOvertime'])
        ->name('api.employee-overtime.store');
    Route::put('/employee-overtime/{employeeOvertime}', [AttendanceController::class, 'updateEmployeeOvertime'])
        ->whereNumber('employeeOvertime')
        ->name('api.employee-overtime.update');
    Route::delete('/employee-overtime/{employeeOvertime}', [AttendanceController::class, 'destroyEmployeeOvertime'])
        ->whereNumber('employeeOvertime')
        ->name('api.employee-overtime.destroy');

    Route::post('/leave-types', [LeaveTypesController::class, 'store'])
        ->name('api.leave-types.store');
    Route::put('/leave-types/{leaveType}', [LeaveTypesController::class, 'update'])
        ->whereNumber('leaveType')
        ->name('api.leave-types.update');
    Route::delete('/leave-types/{leaveType}', [LeaveTypesController::class, 'destroy'])
        ->whereNumber('leaveType')
        ->name('api.leave-types.destroy');

    Route::get('/holidays', [HolidayController::class, 'index'])->name('api.holidays.index');
    Route::post('/holidays', [HolidayController::class, 'store'])->name('api.holidays.store');
    Route::put('/holidays/{holiday}', [HolidayController::class, 'update'])
        ->whereNumber('holiday')
        ->name('api.holidays.update');
    Route::delete('/holidays/{holiday}', [HolidayController::class, 'destroy'])
        ->whereNumber('holiday')
        ->name('api.holidays.destroy');

    Route::post('/biometric-logs', [BiometricLogController::class, 'store'])
        ->name('api.biometric-logs.store');
    Route::delete('/biometric-logs/{biometricLog}', [BiometricLogController::class, 'destroy'])
        ->whereNumber('biometricLog')
        ->name('api.biometric-logs.destroy');

    // Employee Warnings & Memos API routes
    Route::get('/employee-warnings-memos', [EmployeeWarningMemoController::class, 'index'])
        ->name('api.employee-warnings-memos.index');
    Route::get('/employee-warnings-memos/check-exists', [EmployeeWarningMemoController::class, 'checkExists'])
        ->name('api.employee-warnings-memos.check-exists');
    Route::post('/employee-warnings-memos', [EmployeeWarningMemoController::class, 'store'])
        ->name('api.employee-warnings-memos.store');
    Route::post('/employee-warnings-memos/bulk', [EmployeeWarningMemoController::class, 'bulkStore'])
        ->name('api.employee-warnings-memos.bulk-store');
    Route::get('/employee-warnings-memos/{warningMemo}', [EmployeeWarningMemoController::class, 'show'])
        ->whereNumber('warningMemo')
        ->name('api.employee-warnings-memos.show');
    Route::post('/employee-warnings-memos/{warningMemo}/acknowledge', [EmployeeWarningMemoController::class, 'acknowledge'])
        ->whereNumber('warningMemo')
        ->name('api.employee-warnings-memos.acknowledge');
    Route::put('/employee-warnings-memos/{warningMemo}/reply', [EmployeeWarningMemoController::class, 'reply'])
        ->whereNumber('warningMemo')
        ->name('api.employee-warnings-memos.reply');
    Route::post('/employee-warnings-memos/{warningMemo}/resolve', [EmployeeWarningMemoController::class, 'resolve'])
        ->whereNumber('warningMemo')
        ->name('api.employee-warnings-memos.resolve');
    Route::get('/employee-warnings-memos/{warningMemo}/export-pdf', [EmployeeWarningMemoController::class, 'exportMemoPdf'])
        ->whereNumber('warningMemo')
        ->name('api.employee-warnings-memos.export-pdf');
});

// Departments API routes (require authentication)
Route::middleware('auth:web')->group(function () {
    Route::post('/departments', [DepartmentsController::class, 'store']);
    Route::put('/departments/{department}', [DepartmentsController::class, 'update']);
    Route::delete('/departments/{department}', [DepartmentsController::class, 'destroy']);
});

// Positions API routes (require authentication)
Route::middleware('auth:web')->group(function () {
    Route::post('/positions', [PositionsController::class, 'store']);
    Route::put('/positions/{position}', [PositionsController::class, 'update']);
    Route::delete('/positions/{position}', [PositionsController::class, 'destroy']);
});

// Employees API routes (require authentication)
Route::middleware('auth:web')->group(function () {
    Route::post('/employees', [EmployeesController::class, 'store'])->name('api.employees.store');
    Route::put('/employees/{employee}', [EmployeesController::class, 'update'])
        ->where('employee', '[0-9]+')
        ->name('api.employees.update');
    Route::delete('/employees/{employee}', [EmployeesController::class, 'destroy'])
        ->where('employee', '[0-9]+')
        ->name('api.employees.destroy');
});

// Event Categories API routes (require authentication)
Route::middleware('auth:web')->group(function () {
    Route::post('/event-categories', [EventCategoryController::class, 'store'])
        ->name('api.event-categories.store');
    Route::put('/event-categories/{category}', [EventCategoryController::class, 'update'])
        ->whereNumber('category')
        ->name('api.event-categories.update');
    Route::delete('/event-categories/{category}', [EventCategoryController::class, 'destroy'])
        ->whereNumber('category')
        ->name('api.event-categories.destroy');
});

// Calendar Events API routes (require authentication)
Route::middleware('auth:web')->group(function () {
    Route::post('/calendar-events', [CalendarEventController::class, 'store'])
        ->name('api.calendar-events.store');
    Route::put('/calendar-events/{calendarEvent}', [CalendarEventController::class, 'update'])
        ->whereNumber('calendarEvent')
        ->name('api.calendar-events.update');
    Route::delete('/calendar-events/{calendarEvent}', [CalendarEventController::class, 'destroy'])
        ->whereNumber('calendarEvent')
        ->name('api.calendar-events.destroy');
});

// Event Attendees API routes (require authentication)
Route::middleware('auth:web')->group(function () {
    Route::post('/calendar-events/{calendarEvent}/attend', [EventAttendeeController::class, 'toggle'])
        ->whereNumber('calendarEvent')
        ->name('api.event-attendees.toggle');
    Route::get('/calendar-events/{calendarEvent}/attend-status', [EventAttendeeController::class, 'check'])
        ->whereNumber('calendarEvent')
        ->name('api.event-attendees.check');
});

// Documents API routes (require authentication)
Route::middleware('auth:web')->group(function () {
    Route::post('/documents', [DocumentsController::class, 'store'])
        ->name('api.documents.store');
    Route::get('/documents', [DocumentsController::class, 'indexApi'])
        ->name('api.documents.index');
    Route::put('/documents/{document}', [DocumentsController::class, 'update'])
        ->whereNumber('document')
        ->name('api.documents.update');
    Route::delete('/documents/{document}', [DocumentsController::class, 'destroy'])
        ->whereNumber('document')
        ->name('api.documents.destroy');
    Route::post('/documents/{document}/approve', [DocumentsController::class, 'approve'])
        ->whereNumber('document')
        ->name('api.documents.approve');
    Route::post('/documents/{document}/reject', [DocumentsController::class, 'reject'])
        ->whereNumber('document')
        ->name('api.documents.reject');
    Route::post('/documents/{document}/request-access', [DocumentsController::class, 'requestAccess'])
        ->whereNumber('document')
        ->name('api.documents.request-access');
    Route::post('/documents/{document}/restore', [DocumentsController::class, 'restore'])
        ->whereNumber('document')
        ->name('api.documents.restore');
    Route::delete('/documents/{document}/force-delete', [DocumentsController::class, 'forceDelete'])
        ->whereNumber('document')
        ->name('api.documents.force-delete');
    Route::post('/documents/restore-all', [DocumentsController::class, 'restoreAll'])
        ->name('api.documents.restore-all');
    Route::delete('/documents/force-delete-all', [DocumentsController::class, 'forceDeleteAll'])
        ->name('api.documents.force-delete-all');
    Route::put('/documents/{document}/content', [DocumentsController::class, 'updateContent'])
        ->whereNumber('document')
        ->name('api.documents.update-content');
    Route::get('/documents/search', [DocumentsController::class, 'search'])
        ->name('api.documents.search');
    Route::get('/documents/{document}/extraction-status', [DocumentsController::class, 'extractionStatus'])
        ->whereNumber('document')
        ->name('api.documents.extraction-status');
});

// Backdoor API routes (require authentication)
Route::middleware('auth:web')->group(function () {
    Route::post('/backdoor/documents', [BackdoorController::class, 'store'])
        ->name('api.backdoor.documents.store');
});

// Document Access Requests API routes (require authentication)
Route::middleware('auth:web')->group(function () {
    Route::post('/document-access-requests/{accessRequest}/approve', [\App\Http\Controllers\DocumentAccessRequestsController::class, 'approve'])
        ->whereNumber('accessRequest')
        ->name('api.document-access-requests.approve');
    Route::post('/document-access-requests/{accessRequest}/reject', [\App\Http\Controllers\DocumentAccessRequestsController::class, 'reject'])
        ->whereNumber('accessRequest')
        ->name('api.document-access-requests.reject');
    Route::put('/document-access-requests/{accessRequest}', [\App\Http\Controllers\DocumentAccessRequestsController::class, 'update'])
        ->whereNumber('accessRequest')
        ->name('api.document-access-requests.update');
});
