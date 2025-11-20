<?php

namespace App\Events;

use App\Models\Employee;
use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class MessageRestored implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message)
    {
        $this->message->load([
            'user',
            'editor',
            'deleter',
            'attachments' => function ($query) {
                $query->withoutGlobalScopes();
            },
        ]);
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.'.$this->message->chat_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'MessageRestored';
    }

    public function broadcastWith(): array
    {
        $allEmployees = Employee::all()->keyBy('email');

        $user = $this->message->user;
        $employee = $user && $allEmployees->has($user->email)
            ? $allEmployees->get($user->email)
            : null;

        $editor = $this->message->editor;
        $editorEmployee = $editor && $allEmployees->has($editor->email)
            ? $allEmployees->get($editor->email)
            : null;
        $editorName = $editor
            ? ($editorEmployee
                ? "{$editorEmployee->first_name} {$editorEmployee->last_name}"
                : $editor->name)
            : null;

        return [
            'id' => $this->message->id,
            'chat_id' => $this->message->chat_id,
            'user_id' => $this->message->user_id,
            'content' => $this->message->content,
            'message_type' => $this->message->message_type,
            'is_pinned' => $this->message->is_pinned,
            'is_edited' => $this->message->is_edited,
            'is_deleted' => $this->message->is_deleted,
            'edited_by' => $editorName,
            'created_at' => $this->message->created_at->toISOString(),
            'user' => $user ? [
                'id' => $user->id,
                'name' => $employee
                    ? "{$employee->first_name} {$employee->last_name}"
                    : $user->name,
                'email' => $user->email,
                'avatar' => $employee && $employee->avatar
                    ? Storage::url($employee->avatar)
                    : null,
            ] : null,
            'attachments' => $this->message->attachments->map(function ($attachment) {
                return [
                    'id' => $attachment->id,
                    'name' => $attachment->file_name,
                    'file_name' => $attachment->file_name,
                    'file_size' => $attachment->file_size,
                    'mime_type' => $attachment->mime_type,
                ];
            })->toArray(),
        ];
    }
}

