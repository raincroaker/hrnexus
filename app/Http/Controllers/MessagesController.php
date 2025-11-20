<?php

namespace App\Http\Controllers;

use App\Events\MessageDeleted;
use App\Events\MessagePinUpdated;
use App\Events\MessageRestored;
use App\Events\MessageSent;
use App\Events\MessageUpdated;
use App\Http\Requests\StoreMessageRequest;
use App\Http\Requests\UpdateMessageContentRequest;
use App\Http\Requests\UpdateMessageRequest;
use App\Models\Chat;
use App\Models\ChatMember;
use App\Models\Employee;
use App\Models\Message;
use App\Models\MessageAttachment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MessagesController extends Controller
{
    /**
     * Store a new message with optional attachments
     */
    public function store(int $chatId, StoreMessageRequest $request)
    {
        $currentUser = Auth::user();
        $validated = $request->validated();

        try {
            // Check if chat exists
            $chat = Chat::findOrFail($chatId);

            // Check if current user is a member of this chat
            $chatMember = ChatMember::where('chat_id', $chatId)
                ->where('user_id', $currentUser->id)
                ->first();

            if (! $chatMember) {
                return back()->withErrors([
                    'message' => 'You are not a member of this chat.',
                ]);
            }

            DB::beginTransaction();

            // Get attachments if any
            $attachments = $request->file('attachments', []);
            $hasAttachments = ! empty($attachments);

            // Determine message type
            $messageType = $hasAttachments ? 'attachment' : 'text';

            // Create the message
            $message = Message::create([
                'chat_id' => $chatId,
                'user_id' => $currentUser->id,
                'content' => $validated['content'] ?? null,
                'message_type' => $messageType,
                'has_attachments' => $hasAttachments,
                'is_pinned' => false,
                'is_deleted' => false,
                'is_edited' => false,
            ]);

            // Handle file uploads
            if ($hasAttachments) {
                // Ensure message-attachments directory exists
                $attachmentsDir = 'message-attachments';
                if (! Storage::disk('local')->exists($attachmentsDir)) {
                    Storage::disk('local')->makeDirectory($attachmentsDir);
                }

                foreach ($attachments as $file) {
                    // Generate UUID filename
                    $extension = strtolower($file->getClientOriginalExtension());
                    $storedName = Str::uuid()->toString().'.'.$extension;
                    $path = $attachmentsDir.'/'.$storedName;

                    // Store file in private storage
                    Storage::disk('local')->putFileAs($attachmentsDir, $file, $storedName);

                    // Create attachment record
                    MessageAttachment::create([
                        'chat_id' => $chatId,
                        'message_id' => $message->id,
                        'stored_name' => $storedName,
                        'file_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'file_size' => $file->getSize(),
                    ]);
                }
            }

            // Set is_seen = 1 for sender, is_seen = 0 for all other members
            $chatMember->update(['is_seen' => true]);

            ChatMember::where('chat_id', $chatId)
                ->where('user_id', '!=', $currentUser->id)
                ->update(['is_seen' => false]);

            DB::commit();

            // Reload message with relationships for broadcasting
            $message->load(['user', 'editor', 'attachments']);

            // Broadcast the message to all chat members
            broadcast(new MessageSent($message))->toOthers();

            return back();
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();

            return back()->withErrors([
                'message' => 'Chat not found.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withErrors([
                'message' => 'Failed to send message: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Download a message attachment
     */
    public function downloadAttachment(int $attachmentId)
    {
        $currentUser = Auth::user();

        try {
            // Find attachment
            $attachment = MessageAttachment::findOrFail($attachmentId);

            // Check if current user is a member of this chat
            $chatMember = ChatMember::where('chat_id', $attachment->chat_id)
                ->where('user_id', $currentUser->id)
                ->first();

            if (! $chatMember) {
                abort(403, 'You are not authorized to download this file.');
            }

            // Check if message is deleted (attachments should be hidden)
            $message = Message::where('id', $attachment->message_id)
                ->where('is_deleted', false)
                ->first();

            if (! $message) {
                abort(404, 'File not found.');
            }

            // Build file path
            $filePath = 'message-attachments/'.$attachment->stored_name;

            // Check if file exists
            if (! Storage::disk('local')->exists($filePath)) {
                abort(404, 'File not found.');
            }

            // Return file download response
            return Storage::disk('local')->download(
                $filePath,
                $attachment->file_name,
                [
                    'Content-Type' => $attachment->mime_type,
                ]
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            abort(404, 'Attachment not found.');
        } catch (\Exception $e) {
            abort(500, 'Failed to download file: '.$e->getMessage());
        }
    }

    /**
     * Update the pin status of a message
     */
    public function updatePin(int $chatId, int $messageId, UpdateMessageRequest $request)
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
                    'message' => 'You must be an admin to pin/unpin messages.',
                ]);
            }

            // Check if message exists and belongs to this chat
            $message = Message::where('chat_id', $chatId)
                ->where('id', $messageId)
                ->where('is_deleted', false)
                ->first();

            if (! $message) {
                return back()->withErrors([
                    'message' => 'Message not found.',
                ]);
            }

            // Get current user's employee info for full name
            $currentEmployee = Employee::where('email', $currentUser->email)->first();
            $currentUserFullName = $currentEmployee
                ? "{$currentEmployee->first_name} {$currentEmployee->last_name}"
                : $currentUser->name;

            $isPinning = (bool) $validated['is_pinned'];
            $wasPinned = $message->is_pinned;

            // Only create system message if the pin status is actually changing
            $shouldCreateSystemMessage = $isPinning !== $wasPinned;

            DB::beginTransaction();

            // Update the message pin status
            $message->update([
                'is_pinned' => $isPinning,
            ]);

            // Create system message if pin status changed
            if ($shouldCreateSystemMessage) {
                $action = $isPinning ? 'pinned' : 'unpinned';
                $systemMessage = Message::create([
                    'chat_id' => $chatId,
                    'user_id' => $currentUser->id,
                    'content' => "{$currentUserFullName} {$action} a message",
                    'message_type' => 'system',
                    'has_attachments' => false,
                    'is_pinned' => false,
                    'is_deleted' => false,
                    'is_edited' => false,
                ]);

                $systemMessage->load([
                    'user',
                    'editor',
                    'attachments' => function ($query) {
                        $query->withoutGlobalScopes();
                    },
                ]);

                broadcast(new MessageSent($systemMessage));
            }

            // Mark chat as unread for all other members (excluding current user)
            ChatMember::where('chat_id', $chatId)
                ->where('user_id', '!=', $currentUser->id)
                ->update(['is_seen' => false]);

            DB::commit();

            // Reload message with relationships for broadcasting
            $message->load([
                'user',
                'editor',
                'attachments' => function ($query) {
                    $query->withoutGlobalScopes();
                },
            ]);

            broadcast(new MessagePinUpdated($message))->toOthers();

            return back();
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();

            return back()->withErrors([
                'message' => 'Chat or message not found.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withErrors([
                'message' => 'Failed to update message pin status: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Update the content of a message
     */
    public function update(int $chatId, int $messageId, UpdateMessageContentRequest $request)
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
                    'message' => 'You must be an admin to edit messages.',
                ]);
            }

            // Check if message exists and belongs to this chat
            $message = Message::where('chat_id', $chatId)
                ->where('id', $messageId)
                ->where('is_deleted', false)
                ->first();

            if (! $message) {
                return back()->withErrors([
                    'message' => 'Message not found.',
                ]);
            }

            DB::beginTransaction();

            // Update the message content and set edited flags
            $message->update([
                'content' => $validated['content'],
                'is_edited' => true,
                'edited_by' => $currentUser->id,
            ]);

            // Mark chat as unread for all other members (excluding current user)
            ChatMember::where('chat_id', $chatId)
                ->where('user_id', '!=', $currentUser->id)
                ->update(['is_seen' => false]);

            DB::commit();

            $message->load([
                'user',
                'editor',
                'deleter',
                'attachments' => function ($query) {
                    $query->withoutGlobalScopes();
                },
            ]);

            broadcast(new MessageUpdated($message))->toOthers();

            return back();
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();

            return back()->withErrors([
                'message' => 'Chat or message not found.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withErrors([
                'message' => 'Failed to update message: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Soft delete a message
     */
    public function destroy(int $chatId, int $messageId)
    {
        $currentUser = Auth::user();

        try {
            // Check if chat exists
            $chat = Chat::findOrFail($chatId);

            // Check if current user is an admin of this chat
            $chatMember = ChatMember::where('chat_id', $chatId)
                ->where('user_id', $currentUser->id)
                ->first();

            if (! $chatMember || ! $chatMember->is_admin) {
                return back()->withErrors([
                    'message' => 'You must be an admin to delete messages.',
                ]);
            }

            // Check if message exists and belongs to this chat
            $message = Message::where('chat_id', $chatId)
                ->where('id', $messageId)
                ->where('is_deleted', false)
                ->first();

            if (! $message) {
                return back()->withErrors([
                    'message' => 'Message not found.',
                ]);
            }

            DB::beginTransaction();

            // Soft delete the message
            $message->update([
                'is_deleted' => true,
                'deleted_by' => $currentUser->id,
            ]);

            // Mark chat as unread for all other members (excluding current user)
            ChatMember::where('chat_id', $chatId)
                ->where('user_id', '!=', $currentUser->id)
                ->update(['is_seen' => false]);

            DB::commit();

            $message->load([
                'user',
                'editor',
                'deleter',
                'attachments' => function ($query) {
                    $query->withoutGlobalScopes();
                },
            ]);

            broadcast(new MessageDeleted($message))->toOthers();

            return back();
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();

            return back()->withErrors([
                'message' => 'Chat or message not found.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withErrors([
                'message' => 'Failed to delete message: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Restore a soft-deleted message
     */
    public function restore(int $chatId, int $messageId)
    {
        $currentUser = Auth::user();

        try {
            // Check if chat exists
            $chat = Chat::findOrFail($chatId);

            // Check if current user is an admin of this chat
            $chatMember = ChatMember::where('chat_id', $chatId)
                ->where('user_id', $currentUser->id)
                ->first();

            if (! $chatMember || ! $chatMember->is_admin) {
                return back()->withErrors([
                    'message' => 'You must be an admin to restore messages.',
                ]);
            }

            // Check if message exists and belongs to this chat (including soft-deleted)
            $message = Message::where('chat_id', $chatId)
                ->where('id', $messageId)
                ->where('is_deleted', true)
                ->first();

            if (! $message) {
                return back()->withErrors([
                    'message' => 'Deleted message not found.',
                ]);
            }

            DB::beginTransaction();

            // Restore the message
            $message->update([
                'is_deleted' => false,
                'deleted_by' => null,
            ]);

            // Mark chat as unread for all other members (excluding current user)
            ChatMember::where('chat_id', $chatId)
                ->where('user_id', '!=', $currentUser->id)
                ->update(['is_seen' => false]);

            DB::commit();

            $message->load([
                'user',
                'editor',
                'deleter',
                'attachments' => function ($query) {
                    $query->withoutGlobalScopes();
                },
            ]);

            broadcast(new MessageRestored($message))->toOthers();

            return back();
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();

            return back()->withErrors([
                'message' => 'Chat or message not found.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withErrors([
                'message' => 'Failed to restore message: '.$e->getMessage(),
            ]);
        }
    }
}
