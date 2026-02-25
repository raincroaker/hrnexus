<?php

namespace App\Http\Controllers;

use App\Events\ChatMembershipUpdated;
use App\Events\MessageSent;
use App\Http\Requests\AddChatMembersRequest;
use App\Http\Requests\UpdateChatMemberRequest;
use App\Models\Chat;
use App\Models\ChatMember;
use App\Models\Employee;
use App\Models\Message;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChatMembersController extends Controller
{
    /**
     * Update the pin status of a chat for the current user
     */
    public function updatePin(int $chatId, UpdateChatMemberRequest $request)
    {
        $currentUser = Auth::user();
        $validated = $request->validated();

        if (! isset($validated['is_pinned'])) {
            return back();
        }

        try {
            $chatMember = ChatMember::where('chat_id', $chatId)
                ->where('user_id', $currentUser->id)
                ->first();

            if ($chatMember) {
                $chatMember->update([
                    'is_pinned' => $validated['is_pinned'],
                ]);
            }

            return back();
        } catch (\Exception $e) {
            report($e);
            return back();
        }
    }

    /**
     * Update the seen status of a chat for the current user
     */
    public function updateSeen(int $chatId, UpdateChatMemberRequest $request)
    {
        $currentUser = Auth::user();
        $validated = $request->validated();

        if (! isset($validated['is_seen'])) {
            return back()->withErrors([
                'message' => 'is_seen field is required',
            ]);
        }

        try {
            $chatMember = ChatMember::where('chat_id', $chatId)
                ->where('user_id', $currentUser->id)
                ->first();

            if (! $chatMember) {
                return back()->withErrors([
                    'message' => 'Chat membership not found',
                ]);
            }

            $chatMember->update([
                'is_seen' => (bool) $validated['is_seen'],
            ]);

            return back();
        } catch (\Exception $e) {
            report($e);
            return back()->withErrors([
                'message' => 'Failed to update seen status.',
            ]);
        }
    }

    /**
     * Set a member as admin
     */
    public function setAsAdmin(int $chatId, int $memberId)
    {
        $currentUser = Auth::user();

        try {
            // Check if current user is an admin of this chat
            $currentUserMember = ChatMember::where('chat_id', $chatId)
                ->where('user_id', $currentUser->id)
                ->first();

            if (! $currentUserMember || ! $currentUserMember->is_admin) {
                return back()->withErrors([
                    'message' => 'You must be an admin to perform this action.',
                ]);
            }

            // Get the target member
            $targetMember = ChatMember::with('user')
                ->where('chat_id', $chatId)
                ->where('user_id', $memberId)
                ->first();

            if (! $targetMember) {
                return back()->withErrors([
                    'message' => 'Member not found.',
                ]);
            }

            // Check if already an admin
            if ($targetMember->is_admin) {
                return back()->withErrors([
                    'message' => 'Member is already an admin.',
                ]);
            }

            // Get current user's full name
            $currentEmployee = Employee::where('email', $currentUser->email)->first();
            $currentUserFullName = $currentEmployee
                ? "{$currentEmployee->first_name} {$currentEmployee->last_name}"
                : $currentUser->name;

            // Get target user's full name
            $targetUser = $targetMember->user;
            $targetEmployee = $targetUser ? Employee::where('email', $targetUser->email)->first() : null;
            $targetUserFullName = $targetEmployee
                ? "{$targetEmployee->first_name} {$targetEmployee->last_name}"
                : ($targetUser->name ?? 'User');

            DB::beginTransaction();

            // Set as admin
            $targetMember->update([
                'is_admin' => true,
            ]);

            // Create system message: "currentuser set user as Admin"
            $systemMessage = Message::create([
                'chat_id' => $chatId,
                'user_id' => $currentUser->id,
                'content' => "{$currentUserFullName} set {$targetUserFullName} as Admin",
                'message_type' => 'system',
                'has_attachments' => false,
                'is_pinned' => false,
                'is_deleted' => false,
                'is_edited' => false,
            ]);

            // Mark chat as unread for all other members (excluding current user)
            ChatMember::where('chat_id', $chatId)
                ->where('user_id', '!=', $currentUser->id)
                ->update(['is_seen' => false]);

            DB::commit();

            $systemMessage->load([
                'user',
                'editor',
                'deleter',
                'attachments' => function ($query) {
                    $query->withoutGlobalScopes();
                },
            ]);

            broadcast(new MessageSent($systemMessage))->toOthers();

            $chatWithMembers = Chat::with(['members.user'])->find($chatId);
            if ($chatWithMembers) {
                broadcast(new ChatMembershipUpdated($chatWithMembers))->toOthers();
            }

            return back();
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);

            return back()->withErrors([
                'message' => 'Failed to set as admin.',
            ]);
        }
    }

    /**
     * Remove admin status from a member
     */
    public function removeAdmin(int $chatId, int $memberId)
    {
        $currentUser = Auth::user();

        try {
            // Check if current user is an admin of this chat
            $currentUserMember = ChatMember::where('chat_id', $chatId)
                ->where('user_id', $currentUser->id)
                ->first();

            if (! $currentUserMember || ! $currentUserMember->is_admin) {
                return back()->withErrors([
                    'message' => 'You must be an admin to perform this action.',
                ]);
            }

            // Get the target member
            $targetMember = ChatMember::where('chat_id', $chatId)
                ->where('user_id', $memberId)
                ->first();

            if (! $targetMember || ! $targetMember->is_admin) {
                return back()->withErrors([
                    'message' => 'Member not found or is not an admin.',
                ]);
            }

            // Prevent demoting the last admin to avoid adminless chats.
            $adminCount = ChatMember::where('chat_id', $chatId)
                ->where('is_admin', true)
                ->count();
            if ($adminCount <= 1) {
                return back()->withErrors([
                    'message' => 'Cannot remove admin status from the last admin.',
                ]);
            }

            // Get current user's full name
            $currentEmployee = Employee::where('email', $currentUser->email)->first();
            $currentUserFullName = $currentEmployee
                ? "{$currentEmployee->first_name} {$currentEmployee->last_name}"
                : $currentUser->name;

            // Get target user's full name
            $targetUser = $targetMember->user;
            $targetEmployee = $targetUser ? Employee::where('email', $targetUser->email)->first() : null;
            $targetUserFullName = $targetEmployee
                ? "{$targetEmployee->first_name} {$targetEmployee->last_name}"
                : ($targetUser->name ?? 'User');

            DB::beginTransaction();

            // Remove admin status
            $targetMember->update([
                'is_admin' => false,
            ]);

            // Create system message: "currentuser removed user as Admin"
            $systemMessage = Message::create([
                'chat_id' => $chatId,
                'user_id' => $currentUser->id,
                'content' => "{$currentUserFullName} removed {$targetUserFullName} as Admin",
                'message_type' => 'system',
                'has_attachments' => false,
                'is_pinned' => false,
                'is_deleted' => false,
                'is_edited' => false,
            ]);

            // Mark chat as unread for all other members (excluding current user)
            ChatMember::where('chat_id', $chatId)
                ->where('user_id', '!=', $currentUser->id)
                ->update(['is_seen' => false]);

            DB::commit();

            $systemMessage->load([
                'user',
                'editor',
                'deleter',
                'attachments' => function ($query) {
                    $query->withoutGlobalScopes();
                },
            ]);

            broadcast(new MessageSent($systemMessage))->toOthers();

            $chatWithMembers = Chat::with(['members.user'])->find($chatId);
            if ($chatWithMembers) {
                broadcast(new ChatMembershipUpdated($chatWithMembers))->toOthers();
            }

            return back();
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);

            return back()->withErrors([
                'message' => 'Failed to remove admin status.',
            ]);
        }
    }

    /**
     * Remove a member from the chat
     */
    public function removeMember(int $chatId, int $memberId)
    {
        $currentUser = Auth::user();

        try {
            // Check if current user is an admin of this chat
            $currentUserMember = ChatMember::where('chat_id', $chatId)
                ->where('user_id', $currentUser->id)
                ->first();

            if (! $currentUserMember || ! $currentUserMember->is_admin) {
                return back()->withErrors([
                    'message' => 'You must be an admin to perform this action.',
                ]);
            }

            // Get the target member
            $targetMember = ChatMember::with('user')
                ->where('chat_id', $chatId)
                ->where('user_id', $memberId)
                ->first();

            if (! $targetMember) {
                return back()->withErrors([
                    'message' => 'Member not found.',
                ]);
            }

            // Prevent removing yourself
            if ($memberId === $currentUser->id) {
                return back()->withErrors([
                    'message' => 'You cannot remove yourself. Use the Leave option instead.',
                ]);
            }

            // Get current user's full name
            $currentEmployee = Employee::where('email', $currentUser->email)->first();
            $currentUserFullName = $currentEmployee
                ? "{$currentEmployee->first_name} {$currentEmployee->last_name}"
                : $currentUser->name;

            // Get target user's full name
            $targetUser = $targetMember->user;
            $targetEmployee = $targetUser ? Employee::where('email', $targetUser->email)->first() : null;
            $targetUserFullName = $targetEmployee
                ? "{$targetEmployee->first_name} {$targetEmployee->last_name}"
                : ($targetUser->name ?? 'User');

            DB::beginTransaction();

            // Soft delete member so they can be restored if re-added later.
            $targetMember->delete();

            // Create system message: "currentuser removed user from the group chat"
            $systemMessage = Message::create([
                'chat_id' => $chatId,
                'user_id' => $currentUser->id,
                'content' => "{$currentUserFullName} removed {$targetUserFullName} from the group chat",
                'message_type' => 'system',
                'has_attachments' => false,
                'is_pinned' => false,
                'is_deleted' => false,
                'is_edited' => false,
            ]);

            // Mark chat as unread for all other members (excluding current user)
            ChatMember::where('chat_id', $chatId)
                ->where('user_id', '!=', $currentUser->id)
                ->update(['is_seen' => false]);

            DB::commit();

            $systemMessage->load([
                'user',
                'editor',
                'deleter',
                'attachments' => function ($query) {
                    $query->withoutGlobalScopes();
                },
            ]);

            broadcast(new MessageSent($systemMessage))->toOthers();

            $chatWithMembers = Chat::with(['members.user'])->find($chatId);
            if ($chatWithMembers) {
                broadcast(new ChatMembershipUpdated($chatWithMembers))->toOthers();
            }

            return back();
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);

            return back()->withErrors([
                'message' => 'Failed to remove member.',
            ]);
        }
    }

    /**
     * Add members to a chat
     */
    public function addMembers(int $chatId, AddChatMembersRequest $request)
    {
        $currentUser = Auth::user();
        $validated = $request->validated();

        try {
            // Check if current user is an admin of this chat
            $currentUserMember = ChatMember::where('chat_id', $chatId)
                ->where('user_id', $currentUser->id)
                ->first();

            if (! $currentUserMember || ! $currentUserMember->is_admin) {
                return back()->withErrors([
                    'message' => 'You must be an admin to add members.',
                ]);
            }

            // Get member IDs to add and ensure they're unique
            $memberIdsToAdd = array_values(array_unique($validated['member_ids']));

            Log::info('[addMembers] Request received', [
                'chat_id' => $chatId,
                'current_user_id' => $currentUser->id,
                'member_ids_to_add' => $memberIdsToAdd,
            ]);

            // Get active members only; soft-deleted members should be restored.
            $existingActiveMemberIds = ChatMember::where('chat_id', $chatId)
                ->pluck('user_id')
                ->toArray();

            Log::info('[addMembers] Existing active members in chat', [
                'existing_member_ids' => $existingActiveMemberIds,
            ]);

            // Filter out members that are already active in the chat.
            $newMemberIds = array_values(array_diff($memberIdsToAdd, $existingActiveMemberIds));

            Log::info('[addMembers] After filtering', [
                'original_count' => count($memberIdsToAdd),
                'existing_count' => count($existingActiveMemberIds),
                'new_member_ids' => $newMemberIds,
                'new_count' => count($newMemberIds),
            ]);

            if (count($newMemberIds) === 0) {
                Log::warning('[addMembers] No new members to add', [
                    'member_ids_to_add' => $memberIdsToAdd,
                    'existing_member_ids' => $existingActiveMemberIds,
                ]);

                return back()->withErrors([
                    'message' => 'All selected members are already in this chat.',
                ]);
            }

            // Get current user's full name
            $currentEmployee = Employee::where('email', $currentUser->email)->first();
            $currentUserFullName = $currentEmployee
                ? "{$currentEmployee->first_name} {$currentEmployee->last_name}"
                : $currentUser->name;

            DB::beginTransaction();

            // Add new members one by one, restoring soft-deleted ones if they exist
            $successfullyAddedIds = [];
            foreach ($newMemberIds as $memberId) {
                try {
                    $existingMember = ChatMember::withTrashed()
                        ->where('chat_id', $chatId)
                        ->where('user_id', $memberId)
                        ->first();

                    if ($existingMember && $existingMember->trashed()) {
                        // Restore the soft-deleted member
                        $existingMember->restore();
                        $existingMember->update([
                            'is_admin' => false,
                            'is_pinned' => false,
                            'is_seen' => false,
                        ]);
                        $successfullyAddedIds[] = $memberId;
                        Log::info('[addMembers] Restored soft-deleted member', [
                            'chat_id' => $chatId,
                            'user_id' => $memberId,
                        ]);
                    } elseif ($existingMember) {
                        // Already active in chat; no-op.
                        continue;
                    } else {
                        ChatMember::create([
                            'chat_id' => $chatId,
                            'user_id' => $memberId,
                            'is_admin' => false,
                            'is_pinned' => false,
                            'is_seen' => false,
                        ]);

                        $successfullyAddedIds[] = $memberId;
                        Log::info('[addMembers] Created new member', [
                            'chat_id' => $chatId,
                            'user_id' => $memberId,
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('[addMembers] Error adding member', [
                        'chat_id' => $chatId,
                        'user_id' => $memberId,
                        'error' => $e->getMessage(),
                    ]);

                    // Skip this member if there's an error (likely duplicate)
                    continue;
                }
            }

            if (count($successfullyAddedIds) === 0) {
                DB::rollBack();

                return back()->withErrors([
                    'message' => 'All selected members are already in this chat.',
                ]);
            }

            // Get member names for the system message (use successfully added IDs)
            $memberUsers = \App\Models\User::whereIn('id', $successfullyAddedIds)->get();
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

            // Create system message: "currentuser added X members"
            $systemMessage = Message::create([
                'chat_id' => $chatId,
                'user_id' => $currentUser->id,
                'content' => "{$currentUserFullName} added {$memberList}",
                'message_type' => 'system',
                'has_attachments' => false,
                'is_pinned' => false,
                'is_deleted' => false,
                'is_edited' => false,
            ]);

            // Mark chat as unread for all other existing members (excluding current user)
            ChatMember::where('chat_id', $chatId)
                ->where('user_id', '!=', $currentUser->id)
                ->update(['is_seen' => false]);

            DB::commit();

            $systemMessage->load([
                'user',
                'editor',
                'deleter',
                'attachments' => function ($query) {
                    $query->withoutGlobalScopes();
                },
            ]);

            broadcast(new MessageSent($systemMessage))->toOthers();

            $chatWithMembers = Chat::with(['members.user'])->find($chatId);
            if ($chatWithMembers) {
                broadcast(new ChatMembershipUpdated($chatWithMembers))->toOthers();
            }

            return back();
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);

            return back()->withErrors([
                'message' => 'Failed to add members.',
            ]);
        }
    }

    /**
     * Leave a chat
     */
    public function leaveChat(int $chatId)
    {
        $currentUser = Auth::user();

        try {
            // Get current user's membership
            $currentUserMember = ChatMember::where('chat_id', $chatId)
                ->where('user_id', $currentUser->id)
                ->first();

            if (! $currentUserMember) {
                return back()->withErrors([
                    'message' => 'You are not a member of this chat.',
                ]);
            }

            // Check if current user is an admin
            $isAdmin = $currentUserMember->is_admin;

            // Check total member count (including current user)
            $totalMemberCount = ChatMember::where('chat_id', $chatId)->count();

            // If user is the only member left, allow them to leave (chat will be soft deleted)
            if ($totalMemberCount === 1) {
                // User is the last member - allow leaving
            } elseif ($isAdmin) {
                // Check if there are other admins in the chat
                $otherAdminCount = ChatMember::where('chat_id', $chatId)
                    ->where('user_id', '!=', $currentUser->id)
                    ->where('is_admin', true)
                    ->count();

                if ($otherAdminCount === 0) {
                    // User is the only admin (but there are other members) - cannot leave
                    return back()->withErrors([
                        'message' => 'You cannot leave. You are the only admin. Add another admin to leave the group.',
                    ]);
                }
            }

            // Get current user's full name for system message
            $currentEmployee = Employee::where('email', $currentUser->email)->first();
            $currentUserFullName = $currentEmployee
                ? "{$currentEmployee->first_name} {$currentEmployee->last_name}"
                : $currentUser->name;

            DB::beginTransaction();

            // Soft delete member so they can be restored if re-added later.
            $currentUserMember->delete();

            // Check if this was the last member
            $remainingMemberCount = ChatMember::where('chat_id', $chatId)->count();

            // Create system message: "user left the group chat"
            $systemMessage = Message::create([
                'chat_id' => $chatId,
                'user_id' => $currentUser->id,
                'content' => "{$currentUserFullName} left the group chat",
                'message_type' => 'system',
                'has_attachments' => false,
                'is_pinned' => false,
                'is_deleted' => false,
                'is_edited' => false,
            ]);

            // Mark chat as unread for all remaining members
            ChatMember::where('chat_id', $chatId)
                ->update(['is_seen' => false]);

            // If this was the last member, soft delete the chat
            if ($remainingMemberCount === 0) {
                $chat = Chat::find($chatId);
                if ($chat) {
                    $chat->delete(); // Soft delete
                }
            }

            DB::commit();

            $systemMessage->load([
                'user',
                'editor',
                'deleter',
                'attachments' => function ($query) {
                    $query->withoutGlobalScopes();
                },
            ]);

            broadcast(new MessageSent($systemMessage))->toOthers();

            if ($remainingMemberCount > 0) {
                $chatWithMembers = Chat::with(['members.user'])->find($chatId);
                if ($chatWithMembers) {
                    broadcast(new ChatMembershipUpdated($chatWithMembers))->toOthers();
                }
            }

            // Redirect to chats index after leaving
            return redirect()->route('chats')->with('success', 'You have left the group chat.');
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);

            return back()->withErrors([
                'message' => 'Failed to leave chat.',
            ]);
        }
    }
}
