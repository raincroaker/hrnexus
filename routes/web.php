<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\BackdoorController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ChatMembersController;
use App\Http\Controllers\ChatsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentsController;
use App\Http\Controllers\EmployeesController;
use App\Http\Controllers\MessagesController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }

    return redirect('/login');
})->name('home');

Route::get('dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/backdoor', [BackdoorController::class, 'index'])->name('backdoor');
    Route::get('/employees', [EmployeesController::class, 'index'])->name('employees');
    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance');
    Route::get('/chats', [ChatsController::class, 'index'])->name('chats');
    Route::post('/chats', [ChatsController::class, 'store'])->name('chats.store');
    Route::patch('/chats/{chat}', [ChatsController::class, 'update'])->name('chats.update');
    Route::patch('/chats/{chat}/pin', [ChatMembersController::class, 'updatePin'])->name('chats.pin');
    Route::patch('/chats/{chat}/seen', [ChatMembersController::class, 'updateSeen'])->name('chats.seen');
    Route::post('/chats/{chat}/members', [ChatMembersController::class, 'addMembers'])->name('chats.members.add');
    Route::delete('/chats/{chat}/leave', [ChatMembersController::class, 'leaveChat'])->name('chats.leave');
    Route::post('/chats/{chat}/members/{member}/admin', [ChatMembersController::class, 'setAsAdmin'])->name('chats.members.setAdmin');
    Route::delete('/chats/{chat}/members/{member}/admin', [ChatMembersController::class, 'removeAdmin'])->name('chats.members.removeAdmin');
    Route::delete('/chats/{chat}/members/{member}', [ChatMembersController::class, 'removeMember'])->name('chats.members.remove');
    Route::post('/chats/{chat}/messages', [MessagesController::class, 'store'])->name('chats.messages.store');
    Route::get('/message-attachments/{attachment}/download', [MessagesController::class, 'downloadAttachment'])->name('message-attachments.download');
    Route::patch('/chats/{chat}/messages/{message}/pin', [MessagesController::class, 'updatePin'])->name('chats.messages.pin');
    Route::patch('/chats/{chat}/messages/{message}', [MessagesController::class, 'update'])->name('chats.messages.update');
    Route::delete('/chats/{chat}/messages/{message}', [MessagesController::class, 'destroy'])->name('chats.messages.destroy');
    Route::patch('/chats/{chat}/messages/{message}/restore', [MessagesController::class, 'restore'])->name('chats.messages.restore');
    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar');
    Route::get('/documents', [DocumentsController::class, 'index'])->name('documents');
    Route::get('/documents/{document}/preview', [DocumentsController::class, 'preview'])->name('documents.preview');
    Route::get('/documents/{document}/download', [DocumentsController::class, 'download'])->name('documents.download');
});

require __DIR__.'/settings.php';
