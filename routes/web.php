<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ChatsController;
use App\Http\Controllers\DepartmentsController;
use App\Http\Controllers\DocumentsController;
use App\Http\Controllers\EmployeesController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/employees', [EmployeesController::class, 'index'])->name('employees');
    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance');
    Route::get('/chats', [ChatsController::class, 'index'])->name('chats');
    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar');
    Route::get('/documents', [DocumentsController::class, 'index'])->name('documents');
});

require __DIR__.'/settings.php';
