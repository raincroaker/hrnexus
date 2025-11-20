<?php

namespace App\Events;

use App\Models\Chat;
use App\Models\Employee;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ChatMembershipUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public Chat $chat)
    {
        $this->chat->load(['members.user']);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.'.$this->chat->id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'ChatMembershipUpdated';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $members = $this->chat->members;

        $emails = $members
            ->pluck('user.email')
            ->filter()
            ->unique()
            ->values();

        $employees = Employee::whereIn('email', $emails)->get()->keyBy('email');

        $formatMember = function ($member) use ($employees) {
            $user = $member->user;

            if (! $user) {
                return [
                    'id' => null,
                    'name' => 'User',
                    'email' => null,
                    'avatar' => null,
                    'position' => null,
                    'department' => null,
                    'department_code' => null,
                ];
            }

            $employee = $employees->get($user->email);

            return [
                'id' => $user->id,
                'name' => $employee
                    ? "{$employee->first_name} {$employee->last_name}"
                    : $user->name,
                'email' => $user->email,
                'avatar' => $employee && $employee->avatar
                    ? Storage::url($employee->avatar)
                    : null,
                'position' => $employee->position->name ?? null,
                'department' => $employee->department->name ?? null,
                'department_code' => $employee->department->code ?? null,
            ];
        };

        $admins = $members
            ->where('is_admin', true)
            ->map($formatMember)
            ->values();

        $regularMembers = $members
            ->where('is_admin', false)
            ->map($formatMember)
            ->values();

        return [
            'chat_id' => $this->chat->id,
            'admins' => $admins,
            'members' => $regularMembers,
            'total_members' => $members->count(),
        ];
    }
}

