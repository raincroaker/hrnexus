<?php

use App\Models\ChatMember;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('chat.{chatId}', function ($user, $chatId) {
    // Check if user is a member of this chat (excluding soft-deleted members)
    return ChatMember::where('chat_id', $chatId)
        ->where('user_id', $user->id)
        ->exists();
});

Broadcast::channel('admin.pdf-extraction', function ($user) {
    // Only allow admin users to listen to PDF extraction notifications
    $employee = \App\Models\Employee::where('email', $user->email)->first();

    return $employee && $employee->role === 'admin';
});
