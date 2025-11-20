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

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Message $message
    ) {
        //
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.'.$this->message->chat_id),
        ];
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'MessageSent';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        // Load relationships (attachments without global scopes to include soft-deleted ones)
        $this->message->load([
            'user',
            'editor',
            'attachments' => function ($query) {
                $query->withoutGlobalScopes();
            },
        ]);

        $user = $this->message->user;
        $allEmployees = Employee::with(['department', 'position'])
            ->get()
            ->keyBy('email');
        $employee = $user && $allEmployees->has($user->email)
            ? $allEmployees->get($user->email)
            : null;

        // Get editor info if message was edited
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
            'chat_id' => $this->message->chat_id,
            'message' => [
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
            ],
        ];
    }
}
