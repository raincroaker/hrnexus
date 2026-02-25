<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreChatRequest;
use App\Http\Requests\UpdateChatRequest;
use App\Events\ChatRenamed;
use App\Events\MessageSent;
use App\Models\Chat;
use App\Models\ChatMember;
use App\Models\Employee;
use App\Models\Message;
use App\Models\MessageAttachment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ChatsController extends Controller
{
    public function index()
    {
        $currentUser = Auth::user();

        // Get all chats where current user is a member
        $chatIds = ChatMember::where('user_id', $currentUser->id)
            ->pluck('chat_id');

        // Get all employees with their info (for potential members and user lookups)
        $allEmployees = Employee::with(['department', 'position'])
            ->get()
            ->keyBy('email');

        // Get all chats with full details
        $chats = Chat::whereIn('id', $chatIds)
            ->with([
                'members' => function ($query) {
                    $query->with('user')->orderBy('is_admin', 'desc');
                },
            ])
            ->withCount('members')
            ->get();

        // Preload messages once to avoid per-chat queries in loops.
        $messagesByChat = Message::whereIn('chat_id', $chatIds)
            ->with([
                'user',
                'editor',
                'deleter',
                'attachments' => function ($query) {
                    $query->withoutGlobalScopes();
                },
            ])
            ->orderBy('created_at', 'asc')
            ->get()
            ->groupBy('chat_id');

        // Preload visible attachments once for all chats.
        $attachmentsByChat = MessageAttachment::whereIn('chat_id', $chatIds)
            ->whereHas('message', function ($query) {
                $query->where('is_deleted', false);
            })
            ->get()
            ->groupBy('chat_id');

        $chats = $chats->map(function ($chat) use ($currentUser, $allEmployees, $messagesByChat, $attachmentsByChat) {
                // Get current user's membership info from loaded members
                $userMembership = $chat->members->firstWhere('user_id', $currentUser->id);

                // Separate admins and members
                $allMembers = $chat->members;
                $admins = $allMembers->where('is_admin', true);
                $regularMembers = $allMembers->where('is_admin', false);

                // Format admins
                $formattedAdmins = $admins->map(function ($member) use ($allEmployees) {
                    $user = $member->user;
                    $employee = $user && $allEmployees->has($user->email)
                        ? $allEmployees->get($user->email)
                        : null;

                    return [
                        'id' => $user->id,
                        'name' => $employee
                            ? "{$employee->first_name} {$employee->last_name}"
                            : $user->name,
                        'email' => $user->email,
                        'avatar' => $employee && $employee->avatar
                            ? Storage::url($employee->avatar)
                            : null,
                        'position' => $employee && $employee->position
                            ? $employee->position->name
                            : null,
                        'department' => $employee && $employee->department
                            ? $employee->department->name
                            : null,
                        'department_code' => $employee && $employee->department
                            ? $employee->department->code
                            : null,
                    ];
                })->values();

                // Format regular members
                $formattedMembers = $regularMembers->map(function ($member) use ($allEmployees) {
                    $user = $member->user;
                    $employee = $user && $allEmployees->has($user->email)
                        ? $allEmployees->get($user->email)
                        : null;

                    return [
                        'id' => $user->id,
                        'name' => $employee
                            ? "{$employee->first_name} {$employee->last_name}"
                            : $user->name,
                        'email' => $user->email,
                        'avatar' => $employee && $employee->avatar
                            ? Storage::url($employee->avatar)
                            : null,
                        'position' => $employee && $employee->position
                            ? $employee->position->name
                            : null,
                        'department' => $employee && $employee->department
                            ? $employee->department->name
                            : null,
                        'department_code' => $employee && $employee->department
                            ? $employee->department->code
                            : null,
                    ];
                })->values();

                $chatMessages = $messagesByChat->get($chat->id, collect());

                // Get last visible message for chat list from preloaded messages
                $lastMessage = $chatMessages->where('is_deleted', false)->last();

                // Format last message
                $lastMessageFormatted = null;
                if ($lastMessage) {
                    $lastMessageUser = $lastMessage->user;
                    $lastMessageEmployee = $lastMessageUser && $allEmployees->has($lastMessageUser->email)
                        ? $allEmployees->get($lastMessageUser->email)
                        : null;

                    $lastMessageFormatted = [
                        'content' => $lastMessage->content,
                        'user_name' => $lastMessageEmployee
                            ? "{$lastMessageEmployee->first_name} {$lastMessageEmployee->last_name}"
                            : ($lastMessageUser->name ?? 'System'),
                        'created_at' => $lastMessage->created_at->format('Y-m-d H:i:s'),
                    ];
                }

                // Get all messages with attachments (including soft-deleted ones)
                $messages = $chatMessages
                    ->map(function ($message) use ($allEmployees) {
                        $user = $message->user;
                        $employee = $user && $allEmployees->has($user->email)
                            ? $allEmployees->get($user->email)
                            : null;

                        // Get editor info if message was edited
                        $editor = $message->editor;
                        $editorEmployee = $editor && $allEmployees->has($editor->email)
                            ? $allEmployees->get($editor->email)
                            : null;
                        $editorName = $editor
                            ? ($editorEmployee
                                ? "{$editorEmployee->first_name} {$editorEmployee->last_name}"
                                : $editor->name)
                            : null;

                        return [
                            'id' => $message->id,
                            'chat_id' => $message->chat_id,
                            'user_id' => $message->user_id,
                            'content' => $message->content,
                            'message_type' => $message->message_type,
                            'is_pinned' => $message->is_pinned,
                            'is_edited' => $message->is_edited,
                            'is_deleted' => $message->is_deleted,
                            'edited_by' => $editorName,
                            'created_at' => $message->created_at->toISOString(),
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
                            'attachments' => $message->attachments->map(function ($attachment) {
                                return [
                                    'id' => $attachment->id,
                                    'name' => $attachment->file_name,
                                    'file_name' => $attachment->file_name,
                                    'file_size' => $attachment->file_size,
                                    'mime_type' => $attachment->mime_type,
                                ];
                            })->toArray(),
                        ];
                    })->values()->toArray();

                // Get pinned messages (including soft-deleted ones)
                $pinnedMessages = $chatMessages
                    ->where('is_pinned', true)
                    ->sortByDesc('created_at')
                    ->map(function ($message) use ($allEmployees) {
                        $user = $message->user;
                        $employee = $user && $allEmployees->has($user->email)
                            ? $allEmployees->get($user->email)
                            : null;

                        // Get editor info if message was edited
                        $editor = $message->editor;
                        $editorEmployee = $editor && $allEmployees->has($editor->email)
                            ? $allEmployees->get($editor->email)
                            : null;
                        $editorName = $editor
                            ? ($editorEmployee
                                ? "{$editorEmployee->first_name} {$editorEmployee->last_name}"
                                : $editor->name)
                            : null;

                        return [
                            'id' => $message->id,
                            'content' => $message->content,
                            'user_name' => $employee
                                ? "{$employee->first_name} {$employee->last_name}"
                                : ($user->name ?? 'System'),
                            'created_at' => $message->created_at->toISOString(),
                            'has_attachments' => $message->has_attachments,
                            'is_deleted' => $message->is_deleted,
                            'is_edited' => $message->is_edited,
                            'edited_by' => $editorName,
                            'attachments' => $message->attachments->map(function ($attachment) {
                                return [
                                    'id' => $attachment->id,
                                    'name' => $attachment->file_name,
                                    'file_name' => $attachment->file_name,
                                    'file_size' => $attachment->file_size,
                                    'mime_type' => $attachment->mime_type,
                                ];
                            })->toArray(),
                        ];
                    })->values()->toArray();

                // Get all attachments for the chat
                $attachments = $attachmentsByChat
                    ->get($chat->id, collect())
                    ->map(function ($attachment) {
                        return [
                            'id' => $attachment->id,
                            'name' => $attachment->file_name,
                            'file_path' => Storage::url($attachment->stored_name),
                            'file_size' => $attachment->file_size,
                            'mime_type' => $attachment->mime_type,
                        ];
                    })->toArray();

                return [
                    'id' => $chat->id,
                    'name' => $chat->name,
                    'description' => null,
                    'created_by' => $chat->created_by,
                    'created_at' => $chat->created_at->toISOString(),
                    'updated_at' => $chat->updated_at->toISOString(),
                    'last_message' => $lastMessageFormatted,
                    'member_count' => $chat->members_count,
                    'total_members' => $chat->members_count,
                    'is_pinned' => $userMembership?->is_pinned ?? false,
                    'is_seen' => $userMembership?->is_seen ?? true,
                    // Full details for frontend rendering
                    'admins' => $formattedAdmins->toArray(),
                    'members' => $formattedMembers->toArray(),
                    'messages' => $messages,
                    'pinnedMessages' => $pinnedMessages,
                    'attachments' => $attachments,
                ];
            });

        // Format all employees for potential members
        $formattedEmployees = $allEmployees->map(function ($employee) {
            return [
                'id' => $employee->id,
                'name' => "{$employee->first_name} {$employee->last_name}",
                'email' => $employee->email,
                'avatar' => $employee->avatar
                    ? Storage::url($employee->avatar)
                    : null,
                'position' => $employee->position?->name ?? null,
                'department' => $employee->department?->name ?? null,
                'department_code' => $employee->department?->code ?? null,
            ];
        })->values();

        // Get current user info
        $currentEmployee = $allEmployees->has($currentUser->email)
            ? $allEmployees->get($currentUser->email)
            : null;
        $currentUserFormatted = [
            'id' => $currentUser->id,
            'name' => $currentEmployee
                ? "{$currentEmployee->first_name} {$currentEmployee->last_name}"
                : $currentUser->name,
            'email' => $currentUser->email,
            'avatar' => $currentEmployee && $currentEmployee->avatar
                ? Storage::url($currentEmployee->avatar)
                : null,
        ];

        return Inertia::render('Chats', [
            'currentUser' => $currentUserFormatted,
            'chats' => $chats->values()->toArray(),
            'selectedChatData' => null, // Frontend will handle selection
            'potentialMembers' => $formattedEmployees->toArray(),
        ]);
    }

    public function store(StoreChatRequest $request)
    {
        $currentUser = Auth::user();
        $validated = $request->validated();

        // Get current user's employee info for full name
        $currentEmployee = Employee::where('email', $currentUser->email)->first();
        $creatorFullName = $currentEmployee
            ? "{$currentEmployee->first_name} {$currentEmployee->last_name}"
            : $currentUser->name;

        // Get member IDs (excluding creator to avoid duplicates)
        $memberIds = array_unique(array_diff($validated['member_ids'], [$currentUser->id]));

        DB::beginTransaction();

        try {
            // Create the chat
            $chat = Chat::create([
                'name' => $validated['name'],
                'created_by' => $currentUser->id,
            ]);

            // Add creator as admin
            ChatMember::create([
                'chat_id' => $chat->id,
                'user_id' => $currentUser->id,
                'is_admin' => true,
                'is_pinned' => false,
                'is_seen' => true,
            ]);

            // Add other members as non-admin
            if (count($memberIds) > 0) {
                $membersToInsert = [];
                foreach ($memberIds as $memberId) {
                    $membersToInsert[] = [
                        'chat_id' => $chat->id,
                        'user_id' => $memberId,
                        'is_admin' => false,
                        'is_pinned' => false,
                        'is_seen' => false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                ChatMember::insert($membersToInsert);
            }

            // Create system message: "creatorfullname created this group"
            Message::create([
                'chat_id' => $chat->id,
                'user_id' => $currentUser->id,
                'content' => "{$creatorFullName} created this group",
                'message_type' => 'system',
                'has_attachments' => false,
                'is_pinned' => false,
                'is_deleted' => false,
                'is_edited' => false,
            ]);

            // Create system message: "creatorfullname added X members" (if there are members)
            if (count($memberIds) > 0) {
                // Get member names for the message
                $memberUsers = \App\Models\User::whereIn('id', $memberIds)->get();
                $memberEmployees = Employee::whereIn('email', $memberUsers->pluck('email'))->get()->keyBy('email');

                $memberNames = $memberUsers->map(function ($user) use ($memberEmployees) {
                    $employee = $memberEmployees->get($user->email);

                    return $employee
                        ? "{$employee->first_name} {$employee->last_name}"
                        : $user->name;
                })->toArray();

                $memberCount = count($memberNames);
                $memberList = $memberCount <= 5
                    ? implode(', ', $memberNames)
                    : implode(', ', array_slice($memberNames, 0, 5)).' and '.($memberCount - 5).' more';

                Message::create([
                    'chat_id' => $chat->id,
                    'user_id' => $currentUser->id,
                    'content' => "{$creatorFullName} added {$memberList}",
                    'message_type' => 'system',
                    'has_attachments' => false,
                    'is_pinned' => false,
                    'is_deleted' => false,
                    'is_edited' => false,
                ]);
            }

            DB::commit();

            // Redirect back to chats index to refresh the list
            return redirect()->route('chats')->with('success', 'Group chat created successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);

            return back()->withErrors([
                'message' => 'Failed to create group chat.',
            ]);
        }
    }

    public function update(int $chatId, UpdateChatRequest $request)
    {
        $currentUser = Auth::user();
        $validated = $request->validated();

        try {
            // Check if chat exists
            $chat = Chat::findOrFail($chatId);

            // Check if current user is an admin of this chat
            $chatMember = ChatMember::where('chat_id', $chatId)
                ->where('user_id', $currentUser->id)
                ->first();

            if (! $chatMember || ! $chatMember->is_admin) {
                return back()->withErrors([
                    'message' => 'You must be an admin to rename this chat.',
                ]);
            }

            // Get current user's employee info for full name
            $currentEmployee = Employee::where('email', $currentUser->email)->first();
            $currentUserFullName = $currentEmployee
                ? "{$currentEmployee->first_name} {$currentEmployee->last_name}"
                : $currentUser->name;

            $newName = $validated['name'];

            // Update the chat name
            $chat->update([
                'name' => $newName,
            ]);

            // Create system message: "currentuser updated the group name to newname"
            $systemMessage = Message::create([
                'chat_id' => $chat->id,
                'user_id' => $currentUser->id,
                'content' => "{$currentUserFullName} updated the group name to {$newName}",
                'message_type' => 'system',
                'has_attachments' => false,
                'is_pinned' => false,
                'is_deleted' => false,
                'is_edited' => false,
            ]);

            // Mark chat as unread for all other members (excluding current user)
            ChatMember::where('chat_id', $chat->id)
                ->where('user_id', '!=', $currentUser->id)
                ->update(['is_seen' => false]);

            $systemMessage->load([
                'user',
                'editor',
                'deleter',
                'attachments' => function ($query) {
                    $query->withoutGlobalScopes();
                },
            ]);

            broadcast(new MessageSent($systemMessage))->toOthers();
            broadcast(new ChatRenamed($chat))->toOthers();

            return back();
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return back()->withErrors([
                'message' => 'Chat not found.',
            ]);
        } catch (\Exception $e) {
            report($e);
            return back()->withErrors([
                'message' => 'Failed to rename chat.',
            ]);
        }
    }
}
