<script setup lang="ts">
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogFooter
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { InputGroupButton } from '@/components/ui/input-group';
import { Label } from '@/components/ui/label';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Separator } from '@/components/ui/separator';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import {
    Bold,
    ChevronLeft,
    ChevronRight,
    CirclePlus,
    Download,
    EllipsisVertical,
    Eye,
    EyeOff,
    FileText,
    Italic,
    LogOut,
    Paperclip,
    Pin,
    PinOff,
    Pencil,
    RefreshCw,
    Search,
    SendHorizontal,
    Settings,
    SquarePen,
    Strikethrough,
    Trash2,
    CodeXml,
    Underline,
    Users,
    X,
} from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const breadcrumbs = [{ title: 'Chats', href: 'chats' }];

// Props from backend
interface Props {
    currentUser?: {
        id: number;
        name: string;
        email: string;
        avatar: string | null;
    };
    chats?: Array<{
        id: number;
        name: string;
        description: string | null;
        created_by: number;
        created_at: string;
        updated_at: string;
        last_message: {
            content: string;
            user_name: string;
            created_at: string;
        } | null;
        member_count: number;
        total_members: number;
        is_pinned: boolean;
        is_seen: boolean;
        admins: Array<{
            id: number;
            name: string;
            email: string;
            avatar: string | null;
            position: string | null;
            department: string | null;
            department_code: string | null;
        }>;
        members: Array<{
            id: number;
            name: string;
            email: string;
            avatar: string | null;
            position: string | null;
            department: string | null;
            department_code: string | null;
        }>;
        messages: Array<{
            id: number;
            chat_id: number;
            user_id: number;
            content: string;
            message_type: string;
            is_pinned: boolean;
            created_at: string;
            user: {
                id: number;
                name: string;
                email: string;
                avatar: string | null;
            } | null;
            attachments: Array<{
                id: number;
                name: string;
                file_path: string;
                file_size: number;
                mime_type: string;
            }>;
        }>;
        pinnedMessages: Array<{
            id: number;
            content: string;
            user_name: string;
            created_at: string;
            has_attachments: boolean;
            attachments: Array<{
                id: number;
                name: string;
            }>;
        }>;
        attachments: Array<{
            id: number;
            name: string;
            file_path: string;
            file_size: number;
            mime_type: string;
        }>;
    }>;
    selectedChatData?: any;
    potentialMembers?: Array<{
        id: number;
        name: string;
        email: string;
        avatar: string | null;
        position: string | null;
        department: string | null;
        department_code: string | null;
    }>;
}

const props = defineProps<Props>();

// ✅ Laravel Echo setup for real-time updates
// Get the global Echo instance (set by configureEcho in app.ts)
// According to @laravel/echo-vue, configureEcho sets up a global Echo instance
const getEchoInstance = (): any => {
    if (typeof window !== 'undefined' && (window as any).Echo) {
        return (window as any).Echo;
    }
    // Fallback: try to get from useEcho composable
    const echoComposable = useEcho('default') as any;
    if (echoComposable?.echo) return echoComposable.echo;
    if (echoComposable?.$echo) return echoComposable.$echo;
    return echoComposable;
};

const echoSubscriptions = ref<Array<{ channel: any; chatId: number; channelName: string }>>([]);

// ✅ Loading states for backend operations
const isLoading = ref({
    sendMessage: false,
    createGroup: false,
    addMembers: false,
    removeMember: false,
    setAdmin: false,
    removeAdmin: false,
    renameChat: false,
    leaveChat: false,
    pinChat: false,
    unpinChat: false,
    pinMessage: false,
    unpinMessage: false,
    toggleSeen: false,
    deleteMessage: false,
    restoreMessage: false,
});

// ✅ API Helper Functions (Placeholders - to be implemented in backend)
// These functions structure the data and prepare for API calls

/**
 * Send a message to a chat
 * @param chatId - The ID of the chat
 * @param content - The message content
 * @param files - Array of files to attach
 */
const apiSendMessage = async (chatId: number, content: string, files: File[] = []) => {
    const formData = new FormData();
    
    // Append content (can be empty if files exist)
    if (content.trim()) {
        formData.append('content', content.trim());
    }
    
    // Append attachments as array
    files.forEach((file) => {
        formData.append('attachments[]', file);
    });
    
    return new Promise<void>((resolve, reject) => {
        router.post(`/chats/${chatId}/messages`, formData, {
            preserveScroll: true,
            preserveState: true,
            forceFormData: true,
            onSuccess: () => {
                resolve();
            },
            onError: (errors) => {
                reject(errors);
            },
        });
    });
};

/**
 * Create a new group chat
 * @param name - The name of the group chat
 * @param memberIds - Array of user IDs to add as members
 * @param description - Optional description
 */
const apiCreateGroupChat = async (name: string, memberIds: number[], description?: string) => {
    const payload = {
        name: name.trim(),
        description: description?.trim() || null,
        member_ids: memberIds,
    };
    
    return new Promise<void>((resolve, reject) => {
        router.post('/chats', payload, {
            preserveScroll: true,
            onSuccess: () => {
                resolve();
            },
            onError: (errors) => {
                reject(errors);
            },
        });
    });
};

/**
 * Add members to a chat
 * @param chatId - The ID of the chat
 * @param memberIds - Array of user IDs to add
 */
const apiAddMembers = async (chatId: number, memberIds: number[]) => {
    const payload = {
        member_ids: memberIds,
    };
    
    return new Promise<void>((resolve, reject) => {
        router.post(`/chats/${chatId}/members`, payload, {
            preserveScroll: true,
            onSuccess: () => {
                resolve();
            },
            onError: (errors) => {
                reject(errors);
            },
        });
    });
};

/**
 * Remove a member from a chat
 * @param chatId - The ID of the chat
 * @param memberId - The ID of the member to remove
 */
const apiRemoveMember = async (chatId: number, memberId: number) => {
    return new Promise<void>((resolve, reject) => {
        router.delete(`/chats/${chatId}/members/${memberId}`, {
            preserveScroll: true,
            onSuccess: () => {
                resolve();
            },
            onError: (errors) => {
                reject(errors);
            },
        });
    });
};

/**
 * Set a member as admin
 * @param chatId - The ID of the chat
 * @param memberId - The ID of the member to set as admin
 */
const apiSetAsAdmin = async (chatId: number, memberId: number) => {
    return new Promise<void>((resolve, reject) => {
        router.post(`/chats/${chatId}/members/${memberId}/admin`, {}, {
            preserveScroll: true,
            onSuccess: () => {
                resolve();
            },
            onError: (errors) => {
                reject(errors);
            },
        });
    });
};

/**
 * Remove admin status from a member
 * @param chatId - The ID of the chat
 * @param memberId - The ID of the admin to remove admin status from
 */
const apiRemoveAdminStatus = async (chatId: number, memberId: number) => {
    return new Promise<void>((resolve, reject) => {
        router.delete(`/chats/${chatId}/members/${memberId}/admin`, {
            preserveScroll: true,
            onSuccess: () => {
                resolve();
            },
            onError: (errors) => {
                reject(errors);
            },
        });
    });
};

/**
 * Rename a chat
 * @param chatId - The ID of the chat
 * @param name - The new name
 */
const apiRenameChat = async (chatId: number, name: string) => {
    const payload = {
        name: name.trim(),
    };
    
    return new Promise<void>((resolve, reject) => {
        router.patch(`/chats/${chatId}`, payload, {
            preserveScroll: true,
            onSuccess: () => {
                resolve();
            },
            onError: (errors) => {
                reject(errors);
            },
        });
    });
};

/**
 * Leave a chat
 * @param chatId - The ID of the chat
 */
const apiLeaveChat = async (chatId: number) => {
    return new Promise<void>((resolve, reject) => {
        router.delete(`/chats/${chatId}/leave`, {
            preserveScroll: true,
            onSuccess: () => {
                resolve();
            },
            onError: (errors) => {
                reject(errors);
            },
        });
    });
};

/**
 * Pin a chat
 * @param chatId - The ID of the chat
 */
const apiPinChat = async (chatId: number) => {
    const payload = {
        is_pinned: true,
    };
    
    return new Promise<void>((resolve, reject) => {
        router.patch(`/chats/${chatId}/pin`, payload, {
            preserveScroll: true,
            onSuccess: () => {
                resolve();
            },
            onError: (errors) => {
                reject(errors);
            },
        });
    });
};

/**
 * Unpin a chat
 * @param chatId - The ID of the chat
 */
const apiUnpinChat = async (chatId: number) => {
    const payload = {
        is_pinned: false,
    };
    
    return new Promise<void>((resolve, reject) => {
        router.patch(`/chats/${chatId}/pin`, payload, {
            preserveScroll: true,
            onSuccess: () => {
                resolve();
            },
            onError: (errors) => {
                reject(errors);
            },
        });
    });
};

/**
 * Pin a message
 * @param chatId - The ID of the chat
 * @param messageId - The ID of the message
 */
const apiPinMessage = async (chatId: number, messageId: number) => {
    const payload = {
        is_pinned: true,
    };
    
    return new Promise<void>((resolve, reject) => {
        router.patch(`/chats/${chatId}/messages/${messageId}/pin`, payload, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                resolve();
            },
            onError: (errors) => {
                reject(errors);
            },
        });
    });
};

/**
 * Unpin a message
 * @param chatId - The ID of the chat
 * @param messageId - The ID of the message
 */
const apiUnpinMessage = async (chatId: number, messageId: number) => {
    const payload = {
        is_pinned: false,
    };
    
    return new Promise<void>((resolve, reject) => {
        router.patch(`/chats/${chatId}/messages/${messageId}/pin`, payload, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                resolve();
            },
            onError: (errors) => {
                reject(errors);
            },
        });
    });
};

/**
 * Toggle seen status of a chat
 * @param chatId - The ID of the chat
 * @param isSeen - The new seen status
 */
const apiToggleSeenStatus = async (chatId: number, isSeen: boolean) => {
    console.log('[apiToggleSeenStatus] Called with:', { chatId, isSeen });
    console.log('[apiToggleSeenStatus] Current selectedChat before request:', selectedChat.value?.id);
    
    const payload = {
        is_seen: isSeen,
    };
    
    console.log('[apiToggleSeenStatus] Sending PATCH request to:', `/chats/${chatId}/seen`, 'with payload:', payload);
    
    return new Promise<void>((resolve, reject) => {
        router.patch(`/chats/${chatId}/seen`, payload, {
            preserveScroll: true,
            preserveState: true, // Preserve component state to keep selectedChat
            onStart: () => {
                console.log('[apiToggleSeenStatus] Request started');
                console.log('[apiToggleSeenStatus] selectedChat during start:', selectedChat.value?.id);
            },
            onProgress: () => {
                console.log('[apiToggleSeenStatus] Request in progress');
            },
            onSuccess: () => {
                console.log('[apiToggleSeenStatus] Request succeeded');
                console.log('[apiToggleSeenStatus] selectedChat after success:', selectedChat.value?.id);
                resolve();
            },
            onError: (errors) => {
                console.error('[apiToggleSeenStatus] Request failed with errors:', errors);
                console.log('[apiToggleSeenStatus] selectedChat after error:', selectedChat.value?.id);
                reject(errors);
            },
        });
    });
};

// ✅ Track screen size
const isDesktop = ref(window.innerWidth >= 768);
const showSidebar = ref(true); // Sidebar is visible initially on desktop

// ✅ Dialog states
const showMembersDialog = ref(false);
const showPinnedDialog = ref(false);
const showSearchDialog = ref(false);
const messageSearchQuery = ref('');
const showAttachmentsDialog = ref(false);
const showAddMemberDialog = ref(false);
const addMemberSelectedIds = ref<number[]>([]);
const showRenameDialog = ref(false);
const chatToRename = ref<typeof recentChats.value[0] | null>(null);
const renameChatName = ref('');
const showLeaveConfirmDialog = ref(false);
const showDeleteMessageDialog = ref(false);
const deleteMessageCountdown = ref(2);
const deleteMessageCountdownInterval = ref<ReturnType<typeof setInterval> | null>(null);
const messageToDelete = ref<{ id: number; chatId: number } | null>(null);
const chatToLeave = ref<typeof recentChats.value[0] | null>(null);
const leaveCountdown = ref(5);
const leaveCountdownInterval = ref<number | null>(null);
const showLeaveErrorDialog = ref(false);
const leaveErrorMessage = ref('');
const showRemoveConfirmDialog = ref(false);
const memberToRemove = ref<{ id: number; name: string } | null>(null);
const showCreateGroupDialog = ref(false);
const createGroupForm = ref({
    name: '',
    memberIds: [] as number[],
    search: '',
});
const createGroupErrors = ref({
    name: '',
    memberIds: '',
});

type EmployeeProfile = {
    id: number;
    name: string;
    email: string;
    avatar: string;
    position: string;
    department: string;
    departmentCode: string;
};

const mediaQuery = window.matchMedia('(min-width: 768px)');

const handleMediaChange = (e: MediaQueryListEvent | MediaQueryList) => {
    isDesktop.value = e.matches;
    if (e.matches) {
        showSidebar.value = true; // Always show sidebar on desktop
    }
};

// ✅ Subscribe to all chats for real-time updates
const subscribeToChats = () => {
    if (!props.chats || props.chats.length === 0) {
        console.log('[subscribeToChats] No chats to subscribe to');
        return;
    }

    console.log('[subscribeToChats] Starting subscription for', props.chats.length, 'chats');

    // Unsubscribe from existing channels first
    if (echoSubscriptions.value.length > 0) {
        const echoInstance = getEchoInstance();
        if (echoInstance && typeof echoInstance.leaveChannel === 'function') {
            console.log('[subscribeToChats] Leaving', echoSubscriptions.value.length, 'existing channels');
            echoInstance.leaveChannel(true); // Leave all channels
        }
    }
    echoSubscriptions.value = [];

    // Subscribe to each chat channel
    props.chats.forEach((chat) => {
        // For private channels, use 'chat.{id}' - Echo will add 'private-' prefix automatically
        const channelName = `chat.${chat.id}`;
        try {
            const echoInstance = getEchoInstance();
            
            if (!echoInstance) {
                console.error('[subscribeToChats] Echo instance is not initialized');
                return;
            }
            
            console.log('[subscribeToChats] Echo instance:', echoInstance);
            console.log('[subscribeToChats] Echo instance methods:', Object.keys(echoInstance));
            
            // Use private() method for private channels
            // The private() method should exist on the global Echo instance
            let channel: any = null;
            if (typeof echoInstance.private === 'function') {
                console.log(`[subscribeToChats] Using private() method for channel: ${channelName}`);
                channel = echoInstance.private(channelName);
                console.log(`[subscribeToChats] Channel from private():`, channel);
                console.log(`[subscribeToChats] Channel name:`, channel?.name || channel?.channel || 'unknown');
            } else {
                console.error('[subscribeToChats] Echo instance does not have private() method');
                console.error('[subscribeToChats] Available methods:', Object.keys(echoInstance));
                // Try to access the underlying pusher instance
                const pusherInstance = (echoInstance as any).pusher || (echoInstance as any).connector?.pusher;
                if (pusherInstance) {
                    console.log('[subscribeToChats] Found Pusher instance:', pusherInstance);
                }
                return;
            }
            
            if (!channel) {
                console.error(`[subscribeToChats] Failed to get channel for ${channelName}`);
                return;
            }
            
            // Listen for MessageSent event
            // Laravel Echo uses the event class name, which is 'MessageSent'
            if (channel && typeof channel.listen === 'function') {
                channel.listen('.MessageSent', (event: any) => {
                    console.log(`[subscribeToChats] ✅ Received MessageSent event on channel ${channelName}:`, event);
                    handleMessageSent(event);
                });
                channel.listen('.MessagePinUpdated', (event: any) => {
                    console.log(`[subscribeToChats] ✅ Received MessagePinUpdated event on channel ${channelName}:`, event);
                    handleMessagePinUpdated(event);
                });
                channel.listen('.MessageUpdated', (event: any) => {
                    console.log(`[subscribeToChats] ✅ Received MessageUpdated event on channel ${channelName}:`, event);
                    handleMessageUpdated(event);
                });
                channel.listen('.MessageDeleted', (event: any) => {
                    console.log(`[subscribeToChats] ✅ Received MessageDeleted event on channel ${channelName}:`, event);
                    handleMessageDeleted(event);
                });
                channel.listen('.MessageRestored', (event: any) => {
                    console.log(`[subscribeToChats] ✅ Received MessageRestored event on channel ${channelName}:`, event);
                    handleMessageRestored(event);
                });
                channel.listen('.ChatMembershipUpdated', (event: any) => {
                    console.log(`[subscribeToChats] ✅ Received ChatMembershipUpdated event on channel ${channelName}:`, event);
                    handleChatMembershipUpdated(event);
                });
                channel.listen('.ChatRenamed', (event: any) => {
                    console.log(`[subscribeToChats] ✅ Received ChatRenamed event on channel ${channelName}:`, event);
                    handleChatRenamed(event);
                });

                echoSubscriptions.value.push({ channel, chatId: chat.id, channelName: `private-${channelName}` });
                console.log(`[subscribeToChats] ✅ Successfully subscribed to ${channelName}`);
            } else {
                console.error('[subscribeToChats] Channel object does not have listen() method', channel);
                console.error('[subscribeToChats] Channel methods:', channel ? Object.keys(channel) : 'null');
            }
        } catch (error) {
            console.error(`[subscribeToChats] Failed to subscribe to channel ${channelName}:`, error);
        }
    });
    
    console.log('[subscribeToChats] Total subscriptions:', echoSubscriptions.value.length);
};

// ✅ Handle MessageSent event
const extractChatEventPayload = (event: any) => {
    const messageData = event?.message || event;
    const chatId = event?.chat_id ?? messageData?.chat_id ?? null;

    return { chatId, messageData };
};

const handleMessageSent = (event: any) => {
    console.log('[handleMessageSent] Event received:', event);
    
    // Event structure from Laravel: { chat_id, message: { id, content, ... } }
    const chatId = event.chat_id || event.message?.chat_id;
    const messageData = event.message || event; // Fallback to event itself if message is not nested
    
    if (!chatId) {
        console.error('[handleMessageSent] No chat_id found in event:', event);
        return;
    }
    
    console.log('[handleMessageSent] Processing message for chat:', chatId, 'message:', messageData);
    
    // 1. ALWAYS update chat card in list
    updateChatCard(chatId, messageData);
    
    // 2. ONLY update messages if this chat is currently open
    if (selectedChat.value?.id === chatId) {
        console.log('[handleMessageSent] Chat is open, adding message to chat');
        addMessageToChat(messageData);
        scrollToBottom();
    } else {
        console.log('[handleMessageSent] Chat is not open (selectedChat:', selectedChat.value?.id, '), only updating card');
    }
};

const handleMessagePinUpdated = (event: any) => {
    const { chatId, messageData } = extractChatEventPayload(event);
    if (!chatId || !messageData?.id) {
        console.error('[handleMessagePinUpdated] Invalid event payload', event);
        return;
    }

    if (selectedChat.value?.id === chatId) {
        const updatedMessage = applyMessageBroadcastUpdate(messageData);
        if (updatedMessage) {
            refreshPinnedMessagesFromCurrentMessages();
        }
    }
};

const handleMessageUpdated = (event: any) => {
    const { chatId, messageData } = extractChatEventPayload(event);
    if (!chatId || !messageData?.id) {
        console.error('[handleMessageUpdated] Invalid event payload', event);
        return;
    }

    updateChatCard(chatId, messageData);

    if (selectedChat.value?.id === chatId) {
        const updatedMessage = applyMessageBroadcastUpdate(messageData);
        if (updatedMessage) {
            refreshPinnedMessagesFromCurrentMessages();
        }
    }
};

const handleMessageDeleted = (event: any) => {
    const { chatId, messageData } = extractChatEventPayload(event);
    if (!chatId || !messageData?.id) {
        console.error('[handleMessageDeleted] Invalid event payload', event);
        return;
    }

    const previewPayload = {
        ...messageData,
        content: 'deleted a message',
    };
    updateChatCard(chatId, previewPayload);

    if (selectedChat.value?.id === chatId) {
        const updatedMessage = applyMessageBroadcastUpdate(messageData);
        if (updatedMessage) {
            refreshPinnedMessagesFromCurrentMessages();
            const attachmentIds = attachmentIdsFromMessage(messageData);
            removeAttachmentsByIds(attachmentIds);
        }
    }
};

const handleMessageRestored = (event: any) => {
    const { chatId, messageData } = extractChatEventPayload(event);
    if (!chatId || !messageData?.id) {
        console.error('[handleMessageRestored] Invalid event payload', event);
        return;
    }

    updateChatCard(chatId, messageData);

    if (selectedChat.value?.id === chatId) {
        const updatedMessage = applyMessageBroadcastUpdate(messageData);
        if (updatedMessage) {
            refreshPinnedMessagesFromCurrentMessages();
            addAttachmentsFromMessage(messageData);
        }
    }
};

const updateChatMemberCounts = (chatId: number, totalMembers: number) => {
    const updateCollection = (collection: ChatListItem[]) => {
        const chat = collection.find((c) => c.id === chatId);
        if (chat) {
            chat.totalMembers = totalMembers;
            chat.total_members = totalMembers;
        }
    };

    updateCollection(pinnedChats.value);
    updateCollection(recentChats.value);
};

const removeChatFromLists = (chatId: number) => {
    pinnedChats.value = pinnedChats.value.filter((chat) => chat.id !== chatId);
    recentChats.value = recentChats.value.filter((chat) => chat.id !== chatId);
};

const clearSelectedChatState = () => {
    selectedChat.value = null;
    showMembersDialog.value = false;
    showPinnedDialog.value = false;
    showAttachmentsDialog.value = false;
    showSearchDialog.value = false;
    showAddMemberDialog.value = false;
    showRemoveConfirmDialog.value = false;
    editingMessage.value = null;
    messageText.value = '';
    selectedFiles.value = [];
    messages.value = [];
    pinnedMessages.value = [];
    attachments.value = [];

    if (!isDesktop.value) {
        showSidebar.value = true;
    }
};

const handleChatMembershipUpdated = (event: any) => {
    const chatId = event?.chat_id;
    if (!chatId) {
        console.error('[handleChatMembershipUpdated] Missing chat_id in event payload', event);
        return;
    }

    const adminPayload = Array.isArray(event?.admins) ? event.admins : [];
    const memberPayload = Array.isArray(event?.members) ? event.members : [];

    const currentUserId = props.currentUser?.id;
    const stillMember = typeof currentUserId === 'number'
        ? adminPayload.some((member: any) => member?.id === currentUserId) ||
            memberPayload.some((member: any) => member?.id === currentUserId)
        : true;

    if (!stillMember) {
        removeChatFromLists(chatId);
        if (selectedChat.value && selectedChat.value.id === chatId) {
            clearSelectedChatState();
        }
        return;
    }

    if (selectedChat.value && selectedChat.value.id === chatId) {
        admins.value = transformMembers(adminPayload, true);
        members.value = transformMembers(memberPayload, false);

        const totalMembersCount = typeof event?.total_members === 'number'
            ? event.total_members
            : adminPayload.length + memberPayload.length;

        selectedChat.value.total_members = totalMembersCount;
        selectedChat.value.totalMembers = totalMembersCount;
    }

    const totalCount = typeof event?.total_members === 'number'
        ? event.total_members
        : adminPayload.length + memberPayload.length;

    updateChatMemberCounts(chatId, totalCount);
};

const updateChatNameInLists = (chatId: number, newName: string) => {
    const applyUpdate = (collection: ChatListItem[]) => {
        const chat = collection.find((c) => c.id === chatId);
        if (chat) {
            chat.name = newName;
        }
    };

    applyUpdate(pinnedChats.value);
    applyUpdate(recentChats.value);
};

const handleChatRenamed = (event: any) => {
    const chatId = event?.chat_id;
    const name = event?.name;

    if (!chatId || typeof name !== 'string') {
        console.error('[handleChatRenamed] Invalid payload', event);
        return;
    }

    updateChatNameInLists(chatId, name);

    if (selectedChat.value && selectedChat.value.id === chatId) {
        selectedChat.value.name = name;
    }
};

// ✅ Update chat card in the list
const updateChatCard = (chatId: number, messageData: any) => {
    // Find chat in pinned or recent chats
    const pinnedIndex = pinnedChats.value.findIndex((c) => c.id === chatId);
    const recentIndex = recentChats.value.findIndex((c) => c.id === chatId);
    
    const chatToUpdate = pinnedIndex !== -1 
        ? pinnedChats.value[pinnedIndex]
        : recentIndex !== -1 
            ? recentChats.value[recentIndex]
            : null;
    
    if (!chatToUpdate) {
        return;
    }
    
    // Update last message preview
    const userName = messageData.user?.name || 'User';
    const messageContent = messageData.content || '';
    chatToUpdate.lastMessage = `${userName}: ${messageContent}`;
    chatToUpdate.lastMessageTime = formatLastMessageTime(messageData.created_at);
    chatToUpdate.lastMessageTimestamp = messageData.created_at;
    
    // Mark as unread if not current user's message
    const currentUserId = props.currentUser?.id;
    if (messageData.user_id !== currentUserId) {
        chatToUpdate.is_seen = false;
    }
    
    // Re-sort: move to top (most recent first)
    if (pinnedIndex !== -1) {
        // Remove from current position
        const chat = pinnedChats.value.splice(pinnedIndex, 1)[0];
        // Add to beginning
        pinnedChats.value.unshift(chat);
    } else if (recentIndex !== -1) {
        // Remove from current position
        const chat = recentChats.value.splice(recentIndex, 1)[0];
        // Add to beginning
        recentChats.value.unshift(chat);
    }
};

// ✅ Add message to messages array (only if chat is open)
const addMessageToChat = (messageData: any) => {
    // Check for duplicate (by message.id)
    const existingMessage = messages.value.find((m) => m.id === messageData.id);
    if (existingMessage) {
        return; // Message already exists, skip
    }
    
    // Transform message data to match frontend format
    const currentUserId = props.currentUser?.id;
    const transformedMessage = transformMessages([messageData], currentUserId)[0];
    
    if (transformedMessage) {
        messages.value.push(transformedMessage);
    }
};

onMounted(() => {
    handleMediaChange(mediaQuery); // Set initial state
    mediaQuery.addEventListener('change', handleMediaChange);
    
    // Scroll to bottom on initial load
    scrollToBottom();
    
    // Wait a bit for Echo to be ready, then subscribe
    nextTick(() => {
        // Subscribe to all chats for real-time updates
        subscribeToChats();
    });
});

onBeforeUnmount(() => {
    mediaQuery.removeEventListener('change', handleMediaChange);
    if (leaveCountdownInterval.value) {
        clearInterval(leaveCountdownInterval.value);
    }
    
    // Unsubscribe from all Echo channels
    if (echoSubscriptions.value.length > 0) {
        const echoInstance = getEchoInstance();
        if (echoInstance && typeof echoInstance.leaveChannel === 'function') {
            echoInstance.leaveChannel(true); // Leave all channels
        }
    }
    echoSubscriptions.value = [];
});

// Helper function to format time ago (for messages)
const formatTimeAgo = (dateString: string): string => {
    const date = new Date(dateString);
    const now = new Date();
    const diffInSeconds = Math.floor((now.getTime() - date.getTime()) / 1000);
    
    if (diffInSeconds < 60) return 'Just now';
    if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`;
    if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`;
    if (diffInSeconds < 604800) return `${Math.floor(diffInSeconds / 86400)}d ago`;
    return date.toLocaleDateString();
};

// Helper function to format last message time for chat list
// Shows 24hr time if today, date if not today
const formatLastMessageTime = (dateString: string | null): string => {
    if (!dateString) {
        return 'No messages yet';
    }
    
    const messageDate = new Date(dateString);
    const now = new Date();
    
    // Check if same day
    const isToday = 
        messageDate.getDate() === now.getDate() &&
        messageDate.getMonth() === now.getMonth() &&
        messageDate.getFullYear() === now.getFullYear();
    
    if (isToday) {
        // Return 24hr format time (HH:MM)
        const hours = messageDate.getHours().toString().padStart(2, '0');
        const minutes = messageDate.getMinutes().toString().padStart(2, '0');
        return `${hours}:${minutes}`;
    } else {
        // Return date (YYYY-MM-DD format)
        const year = messageDate.getFullYear();
        const month = (messageDate.getMonth() + 1).toString().padStart(2, '0');
        const day = messageDate.getDate().toString().padStart(2, '0');
        return `${year}-${month}-${day}`;
    }
};

// Chat list item type
type ChatListItem = {
    id: number;
    name: string;
    lastMessage: string;
    lastMessageTime: string;
    description: string;
    is_seen: boolean;
    lastMessageTimestamp?: string | null; // For sorting
    totalMembers?: number;
    total_members?: number;
};

// Transform backend chat data to frontend format
const transformChats = (backendChats: Props['chats'] = []): { pinned: ChatListItem[]; recent: ChatListItem[] } => {
    if (!Array.isArray(backendChats) || backendChats.length === 0) {
        return { pinned: [], recent: [] };
    }
    
    const pinned: ChatListItem[] = [];
    const recent: ChatListItem[] = [];
    
    backendChats.forEach((chat) => {
        const lastMessageText = chat.last_message
            ? `${chat.last_message.user_name}: ${chat.last_message.content}`
            : 'No messages yet';
        
        const lastMessageTime = formatLastMessageTime(chat.last_message?.created_at || null);
        
        const chatItem = {
            id: chat.id,
            name: chat.name,
            lastMessage: lastMessageText,
            lastMessageTime: lastMessageTime,
            description: chat.description || '',
            is_seen: chat.is_seen,
            lastMessageTimestamp: chat.last_message?.created_at || null, // For sorting
        };
        
        if (chat.is_pinned) {
            pinned.push(chatItem);
        } else {
            recent.push(chatItem);
        }
    });
    
    // Sort by latest message timestamp (most recent first)
    const sortByLatestMessage = (a: ChatListItem, b: ChatListItem) => {
        const aTimestamp = a.lastMessageTimestamp;
        const bTimestamp = b.lastMessageTimestamp;
        
        // Chats with messages come first
        if (aTimestamp && !bTimestamp) return -1;
        if (!aTimestamp && bTimestamp) return 1;
        // If both have messages, sort by timestamp (newest first)
        if (aTimestamp && bTimestamp) {
            return new Date(bTimestamp).getTime() - new Date(aTimestamp).getTime();
        }
        // If neither has messages, maintain original order
        return 0;
    };
    
    pinned.sort(sortByLatestMessage);
    recent.sort(sortByLatestMessage);
    
    return { pinned, recent };
};

// Transform backend messages to frontend format
const transformMessages = (backendMessages: any[] = [], currentUserId?: number) => {
    if (!Array.isArray(backendMessages)) {
        return [];
    }
    
    return backendMessages.map((msg) => {
        const isCurrentUser = msg.user?.id === currentUserId;
        const hasAttachment = msg.attachments && msg.attachments.length > 0;
        
        const isDeleted = msg.is_deleted || false;
        const createdAtIso = msg.created_at;
        const createdAtTimestamp = createdAtIso ? new Date(createdAtIso).getTime() : Date.now();
        
        return {
            id: msg.id,
            type: msg.message_type === 'system' ? 'system' : 'text',
            text: isDeleted ? `${msg.user?.name || 'User'} deleted a message` : (msg.content || ''),
            timestamp: formatTimeAgo(msg.created_at),
            createdAtIso,
            createdAtTimestamp,
            userId: msg.user_id,
            user: msg.user?.name || 'System',
            avatar: msg.user?.avatar || '',
            isPinned: isDeleted ? false : (msg.is_pinned || false), // Hide pinned badge if deleted
            isEdited: isDeleted ? false : (msg.is_edited || false), // Hide edited badge if deleted
            editedBy: msg.edited_by || null,
            isDeleted: isDeleted,
            isCurrentUser,
            hasAttachment: isDeleted ? false : hasAttachment, // Hide attachments if deleted
            attachments: isDeleted ? [] : (msg.attachments || []),
            attachmentName: isDeleted ? undefined : (hasAttachment ? msg.attachments[0]?.name : undefined),
            attachmentType: isDeleted ? undefined : (hasAttachment ? 'file' : undefined),
        };
    });
};

const applyMessageBroadcastUpdate = (messageData: any) => {
    const currentUserId = props.currentUser?.id;
    const transformed = transformMessages([messageData], currentUserId)[0];

    if (!transformed) {
        return null;
    }

    const existingIndex = messages.value.findIndex((m) => m.id === transformed.id);
    if (existingIndex !== -1) {
        messages.value[existingIndex] = {
            ...messages.value[existingIndex],
            ...transformed,
        };
    } else {
        messages.value.push(transformed as any);
    }

    return transformed;
};

const formatPinnedMessageFromChatMessage = (message: any) => {
    const createdAtIso = message.createdAtIso || new Date().toISOString();

    return {
        id: message.id,
        text: message.text,
        user: message.user,
        time: formatTimeAgo(createdAtIso),
        timestamp: formatTimeAgo(createdAtIso),
        createdAtTimestamp: message.createdAtTimestamp ?? new Date(createdAtIso).getTime(),
        avatar: message.avatar,
        hasAttachment:
            message.hasAttachment ||
            ((message.attachments ?? []).length > 0),
        attachments: (message.attachments ?? []).map((attachment: any) => ({
            id: attachment.id,
            name: attachment.name ?? attachment.file_name ?? 'Attachment',
            file_name: attachment.file_name ?? attachment.name,
            file_size: attachment.file_size,
            mime_type: attachment.mime_type,
            downloadUrl: attachment.downloadUrl,
        })),
    };
};

const refreshPinnedMessagesFromCurrentMessages = () => {
    const pinnedFromMessages = messages.value
        .filter((message: any) => message.isPinned && !message.isDeleted)
        .sort(
            (a: any, b: any) =>
                (b.createdAtTimestamp ?? 0) - (a.createdAtTimestamp ?? 0),
        );

    pinnedMessages.value = pinnedFromMessages.map((message: any) =>
        formatPinnedMessageFromChatMessage(message),
    );
};

// Transform backend pinned messages to frontend format
const transformPinnedMessages = (backendPinned: any[] = [], transformedMessages: any[] = []) => {
    if (!Array.isArray(backendPinned)) {
        return [];
    }
    
    return backendPinned
        .map((pinned) => {
        // Find the transformed message to get avatar (same structure as search messages)
        // Transformed messages have avatar directly on the message object
        const transformedMessage = transformedMessages.find((msg) => msg.id === pinned.id);
        const avatar = transformedMessage?.avatar || '';
        const createdAtIso = pinned.created_at;
        const createdAtTimestamp = createdAtIso ? new Date(createdAtIso).getTime() : Date.now();
        
        return {
            id: pinned.id,
            text: pinned.content,
            user: pinned.user_name,
            time: formatTimeAgo(pinned.created_at),
            timestamp: formatTimeAgo(pinned.created_at),
            createdAtTimestamp,
            avatar: avatar,
            hasAttachment: pinned.has_attachments || false,
            attachments: pinned.attachments || [],
        };
    })
        .sort((a, b) => (b.createdAtTimestamp ?? 0) - (a.createdAtTimestamp ?? 0));
};

// Transform backend attachments to frontend format
// Helper to format file size
const formatFileSize = (bytes: number): string => {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

const formatDialogAttachment = (attachment: any) => ({
    id: attachment.id,
    name: attachment.name || attachment.file_name,
    type: attachment.mime_type?.split('/')[1] || 'file',
    size: formatFileSize(attachment.file_size || 0),
    downloadUrl: `/message-attachments/${attachment.id}/download`,
});

const transformAttachments = (backendAttachments: any[] = []) => {
    if (!Array.isArray(backendAttachments)) {
        return [];
    }
    
    return backendAttachments.map((att) => formatDialogAttachment(att));
};

const attachmentIdsFromMessage = (messageData: any): number[] => {
    if (!Array.isArray(messageData?.attachments)) {
        return [];
    }

    return messageData.attachments
        .map((att: any) => Number(att.id))
        .filter((id: number) => Number.isFinite(id));
};

const removeAttachmentsByIds = (attachmentIds: number[]) => {
    if (!Array.isArray(attachmentIds) || attachmentIds.length === 0) {
        return;
    }

    const idsSet = new Set(attachmentIds);
    attachments.value = attachments.value.filter((att) => !idsSet.has(att.id));
};

const addAttachmentsFromMessage = (messageData: any) => {
    if (!Array.isArray(messageData?.attachments) || messageData.attachments.length === 0) {
        return;
    }

    const formatted = messageData.attachments.map((att: any) =>
        formatDialogAttachment(att),
    );

    const currentIds = new Set(attachments.value.map((att: { id: number }) => att.id));
    const newOnes = formatted.filter((att: { id: number }) => !currentIds.has(att.id));

    if (newOnes.length > 0) {
        attachments.value = [...attachments.value, ...newOnes];
    }
};

// Transform backend members/admins to frontend format
const transformMembers = (backendMembers: any[] = [], isAdmin: boolean = false) => {
    if (!Array.isArray(backendMembers)) {
        return [];
    }
    
    return backendMembers.map((member) => ({
        id: member.id,
        name: member.name,
        email: member.email,
        avatar: member.avatar || '',
        role: (isAdmin ? 'Admin' : 'Member') as 'Admin' | 'Member',
        position: member.position || '',
        department: member.department || '',
        departmentCode: member.department_code || '',
    }));
};

// ✅ Open chat on click
const openChat = async (chat: ChatListItem) => {
    console.log('[openChat] Chat clicked:', { id: chat.id, name: chat.name, is_seen: chat.is_seen });
    console.log('[openChat] Current selectedChat before setting:', selectedChat.value?.id);
    
    // Store chat data before setting to preserve it
    const chatToSelect = { ...chat };
    selectedChat.value = chatToSelect;
    console.log('[openChat] selectedChat set to:', selectedChat.value?.id);
    
    // Optimistic UI update - mark as seen immediately
    const pinnedIndex = pinnedChats.value.findIndex((c) => c.id === chat.id);
    const recentIndex = recentChats.value.findIndex((c) => c.id === chat.id);
    
    console.log('[openChat] Found in lists:', { pinnedIndex, recentIndex });
    
    if (pinnedIndex !== -1 && !pinnedChats.value[pinnedIndex].is_seen) {
        console.log('[openChat] Updating pinned chat is_seen to true');
        pinnedChats.value[pinnedIndex].is_seen = true;
    } else if (recentIndex !== -1 && !recentChats.value[recentIndex].is_seen) {
        console.log('[openChat] Updating recent chat is_seen to true');
        recentChats.value[recentIndex].is_seen = true;
    }
    
    // Load chat-specific data from backend props
    const backendChat = props.chats?.find((c) => c.id === chat.id);
    
    if (backendChat) {
        const currentUserId = props.currentUser?.id;
        
        admins.value = transformMembers(backendChat.admins, true);
        members.value = transformMembers(backendChat.members, false);
        messages.value = transformMessages(backendChat.messages, currentUserId) as any;
        // Use transformed messages to get avatar (same as search messages)
        pinnedMessages.value = transformPinnedMessages(backendChat.pinnedMessages, messages.value);
        attachments.value = transformAttachments(backendChat.attachments);
    }
    
    // Scroll to bottom when opening chat
    scrollToBottom();
    
    if (!isDesktop.value) {
        showSidebar.value = false; // Hide chat list on mobile
    }
    
    // Always mark as seen when opening a chat (regardless of current state)
    // This ensures opening a chat always updates the seen status
    console.log('[openChat] Always marking chat as seen when opening, calling API for chat:', chat.id);
    console.log('[openChat] selectedChat before API call:', selectedChat.value?.id);
    
    // Store selected chat ID to restore if needed
    const selectedChatIdToPreserve = chat.id;
    
    // Call backend API to update is_seen to true (don't await, let it run in background)
    // Don't reload to avoid resetting the selected chat - just update locally
    apiToggleSeenStatus(chat.id, true)
        .then(() => {
            console.log('[openChat] API call succeeded, updating local state...');
            console.log('[openChat] selectedChat after API success (before update):', selectedChat.value?.id);
            
            // Ensure selectedChat is still set
            if (!selectedChat.value || selectedChat.value.id !== selectedChatIdToPreserve) {
                console.log('[openChat] selectedChat was lost, restoring it...');
                const chatToRestore = [...pinnedChats.value, ...recentChats.value].find((c) => c.id === selectedChatIdToPreserve);
                if (chatToRestore) {
                    selectedChat.value = chatToRestore;
                    // Reload chat data
                    const backendChat = props.chats?.find((c) => c.id === selectedChatIdToPreserve);
                    if (backendChat) {
                        const currentUserId = props.currentUser?.id;
                        admins.value = transformMembers(backendChat.admins, true);
                        members.value = transformMembers(backendChat.members, false);
                        messages.value = transformMessages(backendChat.messages, currentUserId) as any;
                        pinnedMessages.value = transformPinnedMessages(backendChat.pinnedMessages, messages.value);
                        attachments.value = transformAttachments(backendChat.attachments);
                        scrollToBottom();
                    }
                }
            }
            
            // Update the chat list item to reflect the backend change
            if (pinnedIndex !== -1) {
                pinnedChats.value[pinnedIndex].is_seen = true;
            } else if (recentIndex !== -1) {
                recentChats.value[recentIndex].is_seen = true;
            }
            // Update selectedChat if it's the same chat
            if (selectedChat.value?.id === chat.id) {
                selectedChat.value.is_seen = true;
            }
            console.log('[openChat] selectedChat after update:', selectedChat.value?.id);
        })
        .catch((error) => {
            // Silently handle error - restore optimistic update
            console.error('[openChat] Failed to mark chat as seen:', error);
            console.log('[openChat] selectedChat after error:', selectedChat.value?.id);
            
            // Ensure selectedChat is still set even on error
            if (!selectedChat.value || selectedChat.value.id !== selectedChatIdToPreserve) {
                console.log('[openChat] selectedChat was lost on error, restoring it...');
                const chatToRestore = [...pinnedChats.value, ...recentChats.value].find((c) => c.id === selectedChatIdToPreserve);
                if (chatToRestore) {
                    selectedChat.value = chatToRestore;
                }
            }
            
            if (pinnedIndex !== -1) {
                pinnedChats.value[pinnedIndex].is_seen = false;
            } else if (recentIndex !== -1) {
                recentChats.value[recentIndex].is_seen = false;
            }
        });
};

// ✅ Back button for mobile
const goBackToList = () => {
    selectedChat.value = null;
    showSidebar.value = true;
};

// ✅ Pin/Unpin chat
const pinChat = async (chat: ChatListItem) => {
    const chatId = chat.id;

    // Optimistic UI update
    const index = recentChats.value.findIndex((c: ChatListItem) => c.id === chatId);
    if (index > -1) {
        const chatToPin = recentChats.value[index];
        recentChats.value.splice(index, 1);
        pinnedChats.value.push(chatToPin);
    }

    // Backend API call
    isLoading.value.pinChat = true;
    try {
        await apiPinChat(chatId);
        // TODO: On success, backend should confirm pin status
    } catch (error) {
        // TODO: Handle error - restore optimistic update, show error message
        console.error('Failed to pin chat:', error);
        // Restore previous state
        const pinnedIndex = pinnedChats.value.findIndex((c) => c.id === chatId);
        if (pinnedIndex > -1) {
            const chatToUnpin = pinnedChats.value[pinnedIndex];
            pinnedChats.value.splice(pinnedIndex, 1);
            recentChats.value.splice(index, 0, chatToUnpin);
        }
    } finally {
        isLoading.value.pinChat = false;
    }
};

const unpinChat = async (chat: ChatListItem) => {
    const chatId = chat.id;

    // Optimistic UI update
    const index = pinnedChats.value.findIndex((c: ChatListItem) => c.id === chatId);
    if (index > -1) {
        const chatToUnpin = pinnedChats.value[index];
        pinnedChats.value.splice(index, 1);
        recentChats.value.unshift(chatToUnpin);
    }

    // Backend API call
    isLoading.value.unpinChat = true;
    try {
        await apiUnpinChat(chatId);
        // TODO: On success, backend should confirm unpin status
    } catch (error) {
        // TODO: Handle error - restore optimistic update, show error message
        console.error('Failed to unpin chat:', error);
        // Restore previous state
        const recentIndex = recentChats.value.findIndex((c) => c.id === chatId);
        if (recentIndex > -1) {
            const chatToPin = recentChats.value[recentIndex];
            recentChats.value.splice(recentIndex, 1);
            pinnedChats.value.splice(index, 0, chatToPin);
        }
    } finally {
        isLoading.value.unpinChat = false;
    }
};

// Open rename chat dialog
const openRenameDialog = (chat: ChatListItem) => {
    chatToRename.value = chat;
    renameChatName.value = chat.name;
    showRenameDialog.value = true;
};

// Save renamed chat
const saveRenameChat = async () => {
    if (!chatToRename.value || !renameChatName.value.trim()) {
        return;
    }

    const chatId = chatToRename.value.id;
    const newName = renameChatName.value.trim();
    const oldName = chatToRename.value.name;

    // Optimistic UI update
    chatToRename.value.name = newName;
    
    // Update in pinned or recent chats
    const pinnedIndex = pinnedChats.value.findIndex((c) => c.id === chatId);
    if (pinnedIndex !== -1) {
        pinnedChats.value[pinnedIndex].name = newName;
    } else {
        const recentIndex = recentChats.value.findIndex((c) => c.id === chatId);
        if (recentIndex !== -1) {
            recentChats.value[recentIndex].name = newName;
        }
    }
    
        // If this is the selected chat, update it
    if (selectedChat.value?.id === chatId) {
        selectedChat.value.name = newName;
    }

    // Reset and close
    const chat = chatToRename.value;
    chatToRename.value = null;
    const nameValue = renameChatName.value;
    renameChatName.value = '';
    showRenameDialog.value = false;

    // Backend API call
    isLoading.value.renameChat = true;
    const wasChatSelected = selectedChat.value?.id === chatId;
    try {
        await apiRenameChat(chatId, nameValue);
        
        // Reload chat data to get the new system message and updated last message
        // Use Inertia reload to refresh only the chats prop
        router.reload({
            only: ['chats'],
            onSuccess: () => {
                // Wait for props to update and watch to process
                nextTick(() => {
                    // Update the chat list items (watch should handle this automatically)
                    const backendChat = props.chats?.find((c) => c.id === chatId);
                    if (backendChat) {
                        // Update the selected chat if it's the renamed one
                        if (wasChatSelected && selectedChat.value) {
                            const currentUserId = props.currentUser?.id;
                            admins.value = transformMembers(backendChat.admins, true);
                            members.value = transformMembers(backendChat.members, false);
                            messages.value = transformMessages(backendChat.messages, currentUserId) as any;
                            pinnedMessages.value = transformPinnedMessages(backendChat.pinnedMessages, messages.value);
                            attachments.value = transformAttachments(backendChat.attachments);
                            
                            // Update selectedChat reference to point to the updated item from the lists
                            // This ensures lastMessage and lastMessageTime are updated
                            const updatedChatItem = [...pinnedChats.value, ...recentChats.value].find((c) => c.id === chatId);
                            if (updatedChatItem) {
                                selectedChat.value = updatedChatItem;
                            }
                            
                            // Scroll to bottom to show the new system message
                            scrollToBottom();
                        }
                    }
                });
            },
        });
    } catch (error) {
        // Handle error - restore optimistic update, show error message
        console.error('Failed to rename chat:', error);
        // Restore old name
        chat.name = oldName;
        const pinnedIdx = pinnedChats.value.findIndex((c) => c.id === chatId);
        if (pinnedIdx !== -1) {
            pinnedChats.value[pinnedIdx].name = oldName;
        } else {
            const recentIdx = recentChats.value.findIndex((c) => c.id === chatId);
            if (recentIdx !== -1) {
                recentChats.value[recentIdx].name = oldName;
            }
        }
        if (selectedChat.value?.id === chatId) {
            selectedChat.value.name = oldName;
        }
        // Re-open dialog with error
        showRenameDialog.value = true;
        chatToRename.value = chat;
        renameChatName.value = nameValue;
        alert('Failed to rename chat. Please try again.');
    } finally {
        isLoading.value.renameChat = false;
    }
};

// Open leave chat confirmation
const openLeaveConfirm = (chat: ChatListItem) => {
    chatToLeave.value = chat;
    leaveCountdown.value = 5;
    showLeaveConfirmDialog.value = true;
    
    if (leaveCountdownInterval.value) {
        clearInterval(leaveCountdownInterval.value);
    }
    
    leaveCountdownInterval.value = window.setInterval(() => {
        leaveCountdown.value--;
        if (leaveCountdown.value <= 0) {
            if (leaveCountdownInterval.value) {
                clearInterval(leaveCountdownInterval.value);
                leaveCountdownInterval.value = null;
            }
        }
    }, 1000);
};

// Confirm leave chat
const confirmLeaveChat = async () => {
    if (!chatToLeave.value) {
        return;
    }

    const chatId = chatToLeave.value.id;
    const chatName = chatToLeave.value.name;
    const originalChat = chatToLeave.value; // Preserve original chat object

    // Reset countdown and close dialog
    if (leaveCountdownInterval.value) {
        clearInterval(leaveCountdownInterval.value);
        leaveCountdownInterval.value = null;
    }
    leaveCountdown.value = 5;
    showLeaveConfirmDialog.value = false;

    // Clear selected chat if it's the one being left
    if (selectedChat.value?.id === chatId) {
            selectedChat.value = null;
        }

    // Backend API call
    isLoading.value.leaveChat = true;
    try {
        await apiLeaveChat(chatId);
        // On success, backend redirects to chats index and reloads
        // Chat list will be updated automatically after reload
        // selectedChat is already cleared above, so "Select a Chat" will show
    } catch (error: any) {
        // Handle error - show error message
        console.error('Failed to leave chat:', error);
        
        // Extract error message from Inertia error object
        const errorMessage = error?.message || error?.errors?.message || '';
        console.log('[confirmLeaveChat] Error message:', errorMessage);
        
        // Restore chat object for re-opening dialog
        chatToLeave.value = originalChat;
        
        // Set error message and show error dialog
        if (errorMessage.includes('only admin') || errorMessage.includes('Add another admin')) {
            leaveErrorMessage.value = 'You cannot leave. You are the only admin. Add another admin to leave the group.';
        } else if (errorMessage) {
            leaveErrorMessage.value = errorMessage;
        } else {
            leaveErrorMessage.value = `Failed to leave ${chatName}. Please try again.`;
        }
        
        showLeaveErrorDialog.value = true;
    } finally {
        isLoading.value.leaveChat = false;
    }
};

// Cancel leave chat
const cancelLeaveChat = () => {
    showLeaveConfirmDialog.value = false;
    chatToLeave.value = null;
    leaveCountdown.value = 5;
    if (leaveCountdownInterval.value) {
        clearInterval(leaveCountdownInterval.value);
        leaveCountdownInterval.value = null;
    }
};

// Close leave error dialog
const closeLeaveErrorDialog = () => {
    showLeaveErrorDialog.value = false;
    leaveErrorMessage.value = '';
    // Clear chat to leave so it doesn't reopen
    chatToLeave.value = null;
};

// Toggle seen/unseen status
const toggleSeenStatus = async (chat: ChatListItem) => {
    const chatId = chat.id;

    // Find chat in pinned or recent and toggle is_seen
    const pinnedIndex = pinnedChats.value.findIndex((c) => c.id === chatId);
    const recentIndex = recentChats.value.findIndex((c) => c.id === chatId);
    
    let currentSeenStatus = false;
    if (pinnedIndex !== -1) {
        currentSeenStatus = pinnedChats.value[pinnedIndex].is_seen;
        pinnedChats.value[pinnedIndex].is_seen = !currentSeenStatus;
    } else if (recentIndex !== -1) {
        currentSeenStatus = recentChats.value[recentIndex].is_seen;
        recentChats.value[recentIndex].is_seen = !currentSeenStatus;
    } else {
        return;
    }

    const newSeenStatus = !currentSeenStatus;

    // Backend API call
    isLoading.value.toggleSeen = true;
    try {
        await apiToggleSeenStatus(chatId, newSeenStatus);
        // TODO: On success, backend should confirm seen status
    } catch (error) {
        // TODO: Handle error - restore optimistic update, show error message
        console.error('Failed to toggle seen status:', error);
        // Restore previous state
        if (pinnedIndex !== -1) {
            pinnedChats.value[pinnedIndex].is_seen = currentSeenStatus;
        } else if (recentIndex !== -1) {
            recentChats.value[recentIndex].is_seen = currentSeenStatus;
        }
    } finally {
        isLoading.value.toggleSeen = false;
    }
};

// Start editing a message
const startEditMessage = (message: any) => {
    if (!message || message.type === 'system') {
        return;
    }
    
    // Set the message being edited
    editingMessage.value = {
        id: message.id,
        text: message.text || '',
    };
    
    // Populate textarea with message text
    messageText.value = message.text || '';
    
    // Auto-resize textarea
    nextTick(() => {
        autoResize();
        // Focus the textarea
        if (textareaRef.value) {
            textareaRef.value.focus();
            // Move cursor to end
            const length = messageText.value.length;
            textareaRef.value.setSelectionRange(length, length);
        }
        
        // Scroll to the message being edited
        const messageElement = document.querySelector(`[data-message-id="${message.id}"]`);
        if (messageElement) {
            messageElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
};

// Cancel editing
const cancelEdit = () => {
    editingMessage.value = null;
    messageText.value = '';
    nextTick(() => {
        autoResize();
        // Force reset to minimum height
        if (textareaRef.value) {
            textareaRef.value.style.height = 'auto';
            textareaRef.value.style.height = '64px'; // min-h-16 = 4rem = 64px
        }
    });
};

// Toggle pin status for a message
const togglePinMessage = async (message: any) => {
    if (!selectedChat.value) {
        return;
    }

    const chatId = selectedChat.value.id;
    const messageId = message.id;

    // Find message in messages array and toggle isPinned
    const messageIndex = messages.value.findIndex((m) => m.id === messageId);
    if (messageIndex === -1) {
        return;
    }

    const wasPinned = messages.value[messageIndex].isPinned;
    const previousPinnedState = messages.value[messageIndex].isPinned;

    // Backend API call
    const loadingKey = wasPinned ? 'unpinMessage' : 'pinMessage';
    isLoading.value[loadingKey] = true;
    try {
        if (wasPinned) {
            await apiUnpinMessage(chatId, messageId);
        } else {
            await apiPinMessage(chatId, messageId);
        }
        
    } catch (error) {
        // Handle error - restore optimistic update, show error message
        console.error(`Failed to ${wasPinned ? 'unpin' : 'pin'} message:`, error);
        messages.value[messageIndex].isPinned = previousPinnedState;
        alert(`Failed to ${wasPinned ? 'unpin' : 'pin'} message. Please try again.`);
    } finally {
        isLoading.value[loadingKey] = false;
    }
};


// ✅ Chat input configuration
const textareaRef = ref<HTMLTextAreaElement>();
const messageText = ref('');
const fileInputRef = ref<HTMLInputElement>();
const selectedFiles = ref<File[]>([]);
const editingMessage = ref<{ id: number; text: string } | null>(null);

// File validation constants
const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB in bytes
const ALLOWED_FILE_TYPES = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation', // .pptx
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx
];

const ALLOWED_EXTENSIONS = ['.pdf', '.doc', '.docx', '.ppt', '.pptx', '.xls', '.xlsx'];

// Validate file
const validateFile = (file: File): string | null => {
    // Check file size
    if (file.size > MAX_FILE_SIZE) {
        return `File "${file.name}" exceeds the maximum size of 10MB`;
    }

    // Check file type
    const fileExtension = '.' + file.name.split('.').pop()?.toLowerCase();
    const isValidType = ALLOWED_FILE_TYPES.includes(file.type) || ALLOWED_EXTENSIONS.includes(fileExtension);

    if (!isValidType) {
        return `File "${file.name}" is not allowed. Only PDF, DOC, DOCX, PPT, PPTX, XLS, and XLSX files are supported`;
    }

    return null;
};

// Handle file selection
const handleFileSelect = (event: Event) => {
    const input = event.target as HTMLInputElement;
    if (!input.files || input.files.length === 0) {
        return;
    }

    const files = Array.from(input.files);
    const validFiles: File[] = [];
    const errors: string[] = [];

    // Check total file count (max 4)
    const totalFiles = selectedFiles.value.length + files.length;
    if (totalFiles > 4) {
        errors.push(`You can only upload a maximum of 4 files. You currently have ${selectedFiles.value.length} file(s) selected.`);
        if (fileInputRef.value) {
            fileInputRef.value.value = '';
        }
        if (errors.length > 0) {
            alert(errors.join('\n'));
        }
        return;
    }

    files.forEach((file) => {
        const error = validateFile(file);
        if (error) {
            errors.push(error);
        } else {
            validFiles.push(file);
        }
    });

    // Show errors if any
    if (errors.length > 0) {
        alert(errors.join('\n'));
    }

    // Add valid files to selected files
    if (validFiles.length > 0) {
        selectedFiles.value = [...selectedFiles.value, ...validFiles];
    }

    // Reset input
    if (fileInputRef.value) {
        fileInputRef.value.value = '';
    }
};

// Trigger file input
const triggerFileInput = () => {
    fileInputRef.value?.click();
};

// Remove selected file
const removeFile = (index: number) => {
    selectedFiles.value.splice(index, 1);
};

const isSendDisabled = computed(() => messageText.value.trim().length === 0 && selectedFiles.value.length === 0);
const isEditing = computed(() => editingMessage.value !== null);

const handleKeyDown = (event: KeyboardEvent) => {
    const isCtrlOrCmd = event.ctrlKey || event.metaKey;

    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        if (isEditing.value) {
            handleSendOrSave();
        } else {
        sendMessage();
        }
        return;
    }

    if (!isCtrlOrCmd) {
        return;
    }

    const lowercaseKey = event.key.toLowerCase();

    switch (lowercaseKey) {
        case 'b':
            if (event.shiftKey) {
                event.preventDefault();
                applyFormat('bold');
            }
            break;
        case 'i':
            event.preventDefault();
            applyFormat('italic');
            break;
        case 'u':
            event.preventDefault();
            applyFormat('underline');
            break;
        case '`':
            event.preventDefault();
            applyFormat('monospace');
            break;
        case 's':
            if (event.shiftKey) {
                event.preventDefault();
                applyFormat('strike');
            }
            break;
    }
};

const autoResize = () => {
    if (textareaRef.value) {
        textareaRef.value.style.height = 'auto';
        textareaRef.value.style.height = `${Math.min(textareaRef.value.scrollHeight, 128)}px`;
    }
};

// Handle send or save based on edit mode
const handleSendOrSave = async () => {
    if (isEditing.value) {
        await saveEditMessage();
    } else {
        await sendMessage();
    }
};

// Save edited message
const saveEditMessage = async () => {
    if (isSendDisabled.value || !selectedChat.value || !editingMessage.value) {
        return;
    }

    const content = messageText.value.trim();
    const chatId = selectedChat.value.id;
    const messageId = editingMessage.value.id;

    if (!content) {
        return;
    }

    // Backend API call
    isLoading.value.sendMessage = true;
    try {
        const payload = {
            content: content,
        };
        
        await new Promise<void>((resolve, reject) => {
            router.patch(`/chats/${chatId}/messages/${messageId}`, payload, {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    resolve();
                },
                onError: (errors) => {
                    reject(errors);
                },
            });
        });
        
        // Clear edit mode
        editingMessage.value = null;
    messageText.value = '';
        
        // Reset textarea height
        nextTick(() => {
    autoResize();
            // Force reset to minimum height
            if (textareaRef.value) {
                textareaRef.value.style.height = 'auto';
                textareaRef.value.style.height = '64px'; // min-h-16 = 4rem = 64px
            }
        });
        
        // Reload chat data to get updated message and edited_by info
        router.reload({
            only: ['chats'],
            onSuccess: () => {
                nextTick(() => {
                    // Reload chat-specific data if chat is selected
                    if (selectedChat.value) {
                        const backendChat = props.chats?.find((c) => c.id === chatId);
                        if (backendChat) {
                            const currentUserId = props.currentUser?.id;
                            admins.value = transformMembers(backendChat.admins, true);
                            members.value = transformMembers(backendChat.members, false);
                            messages.value = transformMessages(backendChat.messages, currentUserId) as any;
                            pinnedMessages.value = transformPinnedMessages(backendChat.pinnedMessages, messages.value);
                            attachments.value = transformAttachments(backendChat.attachments);
                        }
                    }
                    // Ensure textarea is reset after reload
                    if (textareaRef.value) {
                        textareaRef.value.style.height = 'auto';
                        textareaRef.value.style.height = '64px';
                    }
                });
            },
        });
    } catch (error) {
        console.error('Failed to save edited message:', error);
        alert('Failed to save edited message. Please try again.');
    } finally {
        isLoading.value.sendMessage = false;
    }
};

// Cancel delete message dialog
const cancelDeleteMessage = () => {
    showDeleteMessageDialog.value = false;
    if (deleteMessageCountdownInterval.value) {
        clearInterval(deleteMessageCountdownInterval.value);
        deleteMessageCountdownInterval.value = null;
    }
    deleteMessageCountdown.value = 2;
    messageToDelete.value = null;
};

// Open delete message dialog
const openDeleteMessageDialog = (message: any) => {
    if (!selectedChat.value || !message) {
        return;
    }
    
    messageToDelete.value = {
        id: message.id,
        chatId: selectedChat.value.id,
    };
    
    deleteMessageCountdown.value = 2;
    showDeleteMessageDialog.value = true;
    
    // Start countdown
    if (deleteMessageCountdownInterval.value) {
        clearInterval(deleteMessageCountdownInterval.value);
    }
    
    deleteMessageCountdownInterval.value = setInterval(() => {
        deleteMessageCountdown.value--;
        if (deleteMessageCountdown.value <= 0) {
            if (deleteMessageCountdownInterval.value) {
                clearInterval(deleteMessageCountdownInterval.value);
                deleteMessageCountdownInterval.value = null;
            }
        }
    }, 1000);
};

// Delete message
const deleteMessage = async () => {
    if (!messageToDelete.value || !selectedChat.value) {
        return;
    }
    
    const chatId = messageToDelete.value.chatId;
    const messageId = messageToDelete.value.id;
    
    // Clear countdown
    if (deleteMessageCountdownInterval.value) {
        clearInterval(deleteMessageCountdownInterval.value);
        deleteMessageCountdownInterval.value = null;
    }
    deleteMessageCountdown.value = 2;
    showDeleteMessageDialog.value = false;
    
    // Backend API call
    isLoading.value.deleteMessage = true;
    try {
        await new Promise<void>((resolve, reject) => {
            router.delete(`/chats/${chatId}/messages/${messageId}`, {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    resolve();
                },
                onError: (errors) => {
                    reject(errors);
                },
            });
        });
        
        // Reload chat data to get updated message
        router.reload({
            only: ['chats'],
            onSuccess: () => {
                nextTick(() => {
                    if (selectedChat.value) {
                        const backendChat = props.chats?.find((c) => c.id === chatId);
                        if (backendChat) {
                            const currentUserId = props.currentUser?.id;
                            admins.value = transformMembers(backendChat.admins, true);
                            members.value = transformMembers(backendChat.members, false);
                            messages.value = transformMessages(backendChat.messages, currentUserId) as any;
                            pinnedMessages.value = transformPinnedMessages(backendChat.pinnedMessages, messages.value);
                            attachments.value = transformAttachments(backendChat.attachments);
                        }
                    }
                });
            },
        });
    } catch (error) {
        console.error('Failed to delete message:', error);
        alert('Failed to delete message. Please try again.');
    } finally {
        isLoading.value.deleteMessage = false;
        messageToDelete.value = null;
    }
};

// Restore message
const restoreMessage = async (message: any) => {
    if (!selectedChat.value || !message) {
        return;
    }
    
    const chatId = selectedChat.value.id;
    const messageId = message.id;
    
    // Backend API call
    isLoading.value.restoreMessage = true;
    try {
        await new Promise<void>((resolve, reject) => {
            router.patch(`/chats/${chatId}/messages/${messageId}/restore`, {}, {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    resolve();
                },
                onError: (errors) => {
                    reject(errors);
                },
            });
        });
        
        // Reload chat data to get restored message
        router.reload({
            only: ['chats'],
            onSuccess: () => {
                nextTick(() => {
                    if (selectedChat.value) {
                        const backendChat = props.chats?.find((c) => c.id === chatId);
                        if (backendChat) {
                            const currentUserId = props.currentUser?.id;
                            admins.value = transformMembers(backendChat.admins, true);
                            members.value = transformMembers(backendChat.members, false);
                            messages.value = transformMessages(backendChat.messages, currentUserId) as any;
                            pinnedMessages.value = transformPinnedMessages(backendChat.pinnedMessages, messages.value);
                            attachments.value = transformAttachments(backendChat.attachments);
                        }
                    }
                });
            },
        });
    } catch (error) {
        console.error('Failed to restore message:', error);
        alert('Failed to restore message. Please try again.');
    } finally {
        isLoading.value.restoreMessage = false;
    }
};

const sendMessage = async () => {
    if (isSendDisabled.value || !selectedChat.value) {
        return;
    }

    // Don't send if we're in edit mode
    if (isEditing.value) {
        return;
    }

    const content = messageText.value.trim();
    const files = [...selectedFiles.value];
    const chatId = selectedChat.value.id;

    if (!content && files.length === 0) {
        return;
    }

    // Clear input immediately; realtime update will arrive via broadcast
    const originalContent = content;
    const originalFiles = [...files];
    messageText.value = '';
    selectedFiles.value = [];
    autoResize();

    // Backend API call
    isLoading.value.sendMessage = true;
    try {
        await apiSendMessage(chatId, content, files);
    } catch (error: any) {
        console.error('Failed to send message:', error);

        // Restore input so user can retry
        messageText.value = originalContent;
        selectedFiles.value = originalFiles;
        autoResize();

        // Show error message
        const errorMessage = error?.message || error?.errors?.attachments?.[0] || 'Failed to send message. Please try again.';
        alert(errorMessage);
    } finally {
        isLoading.value.sendMessage = false;
    }
};

const applyFormat = (type: 'bold' | 'italic' | 'underline' | 'monospace' | 'strike') => {
    const textarea = textareaRef.value;
    if (!textarea) {
        return;
    }

    let start = textarea.selectionStart;
    let end = textarea.selectionEnd;
    
    // Do nothing if no text is selected
    if (start === end) {
        return;
    }

    let selected = messageText.value.slice(start, end);
    
    // Trim leading spaces and adjust start position
    const leadingSpaces = selected.match(/^\s*/)?.[0].length || 0;
    if (leadingSpaces > 0) {
        start += leadingSpaces;
        selected = selected.slice(leadingSpaces);
    }
    
    // Trim trailing spaces and adjust end position
    const trailingSpaces = selected.match(/\s*$/)?.[0].length || 0;
    if (trailingSpaces > 0) {
        end -= trailingSpaces;
        selected = selected.slice(0, -trailingSpaces);
    }
    
    // If nothing left after trimming, do nothing
    if (!selected) {
        return;
    }

    const before = messageText.value.slice(0, start);
    const after = messageText.value.slice(end);
    let wrapper = '';

    switch (type) {
        case 'bold':
            wrapper = '*';
            break;
        case 'italic':
            wrapper = '\\';
            break;
        case 'underline':
            wrapper = '_';
            break;
        case 'monospace':
            wrapper = '`';
            break;
        case 'strike':
            wrapper = '~';
            break;
    }

    // Check if the selected text is already wrapped (toggle off)
    const startsWithWrapper = selected.startsWith(wrapper);
    const endsWithWrapper = selected.endsWith(wrapper);
    
    if (startsWithWrapper && endsWithWrapper && selected.length > wrapper.length * 2) {
        // Remove wrapper from selected text
        const unwrapped = selected.slice(wrapper.length, -wrapper.length);
        messageText.value = before + unwrapped + after;
        
        // Position cursor
        requestAnimationFrame(() => {
            textarea.selectionStart = start;
            textarea.selectionEnd = start + unwrapped.length;
            textarea.focus();
        });
    } else {
        // Add wrapper
        const formatted = wrapper + selected + wrapper;
        messageText.value = before + formatted + after;

        // Highlight the entire wrapped text
        requestAnimationFrame(() => {
            textarea.selectionStart = start;
            textarea.selectionEnd = start + formatted.length;
            textarea.focus();
        });
    }
};


// All company employees (potential members for any chat) - from backend
const DUMMY_EMPLOYEE_DIRECTORY: Record<number, EmployeeProfile> = {
    1: {
        id: 1,
        name: 'Sarah Connor',
        email: 'sarah.connor@company.com',
        avatar: 'https://i.pravatar.cc/150?img=5',
        position: 'HR Manager',
        department: 'Human Resources',
        departmentCode: 'HR',
    },
    2: {
        id: 2,
        name: 'Michael Brown',
        email: 'michael.brown@company.com',
        avatar: 'https://i.pravatar.cc/150?img=12',
        position: 'HR Specialist',
        department: 'Human Resources',
        departmentCode: 'HR',
    },
    3: {
        id: 3,
        name: 'Jennifer Taylor',
        email: 'jennifer.taylor@company.com',
        avatar: 'https://i.pravatar.cc/150?img=1',
        position: 'People Coordinator',
        department: 'Human Resources',
        departmentCode: 'HR',
    },
    4: {
        id: 4,
        name: 'Robert Johnson',
        email: 'robert.johnson@company.com',
        avatar: 'https://i.pravatar.cc/150?img=13',
        position: 'Benefits Specialist',
        department: 'Human Resources',
        departmentCode: 'HR',
    },
    5: {
        id: 5,
        name: 'Lisa Anderson',
        email: 'lisa.anderson@company.com',
        avatar: 'https://i.pravatar.cc/150?img=9',
        position: 'Employee Relations Specialist',
        department: 'Human Resources',
        departmentCode: 'HR',
    },
    6: {
        id: 6,
        name: 'Thomas Wilson',
        email: 'thomas.wilson@company.com',
        avatar: 'https://i.pravatar.cc/150?img=18',
        position: 'HR Analyst',
        department: 'Human Resources',
        departmentCode: 'HR',
    },
    7: {
        id: 7,
        name: 'David Miller',
        email: 'david.miller@company.com',
        avatar: 'https://i.pravatar.cc/150?img=13',
        position: 'Finance Director',
        department: 'Finance',
        departmentCode: 'FIN',
    },
    8: {
        id: 8,
        name: 'Patricia Lee',
        email: 'patricia.lee@company.com',
        avatar: 'https://i.pravatar.cc/150?img=20',
        position: 'Senior Accountant',
        department: 'Finance',
        departmentCode: 'FIN',
    },
    9: {
        id: 9,
        name: 'James Martinez',
        email: 'james.martinez@company.com',
        avatar: 'https://i.pravatar.cc/150?img=15',
        position: 'Financial Analyst',
        department: 'Finance',
        departmentCode: 'FIN',
    },
    10: {
        id: 10,
        name: 'Karen White',
        email: 'karen.white@company.com',
        avatar: 'https://i.pravatar.cc/150?img=47',
        position: 'Accounts Payable Specialist',
        department: 'Finance',
        departmentCode: 'FIN',
    },
    11: {
        id: 11,
        name: 'Emma Watson',
        email: 'emma.watson@company.com',
        avatar: 'https://i.pravatar.cc/150?img=9',
        position: 'Marketing Director',
        department: 'Marketing',
        departmentCode: 'MKT',
    },
    12: {
        id: 12,
        name: 'Christopher Davis',
        email: 'chris.davis@company.com',
        avatar: 'https://i.pravatar.cc/150?img=33',
        position: 'Creative Lead',
        department: 'Marketing',
        departmentCode: 'MKT',
    },
    13: {
        id: 13,
        name: 'Amanda Clark',
        email: 'amanda.clark@company.com',
        avatar: 'https://i.pravatar.cc/150?img=10',
        position: 'Content Strategist',
        department: 'Marketing',
        departmentCode: 'MKT',
    },
    14: {
        id: 14,
        name: 'Daniel Rodriguez',
        email: 'daniel.rodriguez@company.com',
        avatar: 'https://i.pravatar.cc/150?img=17',
        position: 'Campaign Manager',
        department: 'Marketing',
        departmentCode: 'MKT',
    },
    15: {
        id: 15,
        name: 'Michelle Thompson',
        email: 'michelle.thompson@company.com',
        avatar: 'https://i.pravatar.cc/150?img=24',
        position: 'Brand Designer',
        department: 'Marketing',
        departmentCode: 'MKT',
    },
    16: {
        id: 16,
        name: 'Steven Garcia',
        email: 'steven.garcia@company.com',
        avatar: 'https://i.pravatar.cc/150?img=29',
        position: 'Marketing Analyst',
        department: 'Marketing',
        departmentCode: 'MKT',
    },
    17: {
        id: 17,
        name: 'Rachel Adams',
        email: 'rachel.adams@company.com',
        avatar: 'https://i.pravatar.cc/150?img=35',
        position: 'Social Media Specialist',
        department: 'Marketing',
        departmentCode: 'MKT',
    },
    18: {
        id: 18,
        name: 'James Wilson',
        email: 'james.wilson@company.com',
        avatar: 'https://i.pravatar.cc/150?img=18',
        position: 'IT Manager',
        department: 'Information Technology',
        departmentCode: 'IT',
    },
    19: {
        id: 19,
        name: 'Andrew Chen',
        email: 'andrew.chen@company.com',
        avatar: 'https://i.pravatar.cc/150?img=52',
        position: 'Systems Engineer',
        department: 'Information Technology',
        departmentCode: 'IT',
    },
    20: {
        id: 20,
        name: 'Laura Martinez',
        email: 'laura.martinez@company.com',
        avatar: 'https://i.pravatar.cc/150?img=40',
        position: 'Support Specialist',
        department: 'Information Technology',
        departmentCode: 'IT',
    },
    21: {
        id: 21,
        name: 'Kevin Park',
        email: 'kevin.park@company.com',
        avatar: 'https://i.pravatar.cc/150?img=59',
        position: 'Network Administrator',
        department: 'Information Technology',
        departmentCode: 'IT',
    },
    22: {
        id: 22,
        name: 'Alice Johnson',
        email: 'alice.johnson@company.com',
        avatar: 'https://i.pravatar.cc/150?img=1',
        position: 'Product Manager',
        department: 'Product Development',
        departmentCode: 'PD',
    },
    23: {
        id: 23,
        name: 'Richard Kim',
        email: 'richard.kim@company.com',
        avatar: 'https://i.pravatar.cc/150?img=68',
        position: 'Engineering Lead',
        department: 'Product Development',
        departmentCode: 'PD',
    },
    24: {
        id: 24,
        name: 'Sophia Lee',
        email: 'sophia.lee@company.com',
        avatar: 'https://i.pravatar.cc/150?img=45',
        position: 'UX Designer',
        department: 'Product Development',
        departmentCode: 'PD',
    },
    25: {
        id: 25,
        name: 'Brian Cooper',
        email: 'brian.cooper@company.com',
        avatar: 'https://i.pravatar.cc/150?img=56',
        position: 'Backend Engineer',
        department: 'Product Development',
        departmentCode: 'PD',
    },
    26: {
        id: 26,
        name: 'Victoria Hughes',
        email: 'victoria.hughes@company.com',
        avatar: 'https://i.pravatar.cc/150?img=48',
        position: 'QA Analyst',
        department: 'Product Development',
        departmentCode: 'PD',
    },
    27: {
        id: 27,
        name: 'Nathan Brooks',
        email: 'nathan.brooks@company.com',
        avatar: 'https://i.pravatar.cc/150?img=61',
        position: 'Frontend Engineer',
        department: 'Product Development',
        departmentCode: 'PD',
    },
    28: {
        id: 28,
        name: 'Emily Foster',
        email: 'emily.foster@company.com',
        avatar: 'https://i.pravatar.cc/150?img=27',
        position: 'Product Analyst',
        department: 'Product Development',
        departmentCode: 'PD',
    },
    29: {
        id: 29,
        name: 'Marcus Reed',
        email: 'marcus.reed@company.com',
        avatar: 'https://i.pravatar.cc/150?img=70',
        position: 'DevOps Engineer',
        department: 'Product Development',
        departmentCode: 'PD',
    },
    30: {
        id: 30,
        name: 'Michael Chen',
        email: 'michael.chen@company.com',
        avatar: 'https://github.com/maxleiter.png',
        position: 'Sales Director',
        department: 'Sales',
        departmentCode: 'SLS',
    },
    31: {
        id: 31,
        name: 'Jessica Parker',
        email: 'jessica.parker@company.com',
        avatar: 'https://i.pravatar.cc/150?img=28',
        position: 'Account Executive',
        department: 'Sales',
        departmentCode: 'SLS',
    },
    32: {
        id: 32,
        name: 'Anthony Morris',
        email: 'anthony.morris@company.com',
        avatar: 'https://i.pravatar.cc/150?img=67',
        position: 'Sales Strategist',
        department: 'Sales',
        departmentCode: 'SLS',
    },
    33: {
        id: 33,
        name: 'Nicole Evans',
        email: 'nicole.evans@company.com',
        avatar: 'https://i.pravatar.cc/150?img=32',
        position: 'Sales Operations Manager',
        department: 'Sales',
        departmentCode: 'SLS',
    },
    34: {
        id: 34,
        name: 'Brandon Scott',
        email: 'brandon.scott@company.com',
        avatar: 'https://i.pravatar.cc/150?img=64',
        position: 'Business Development Representative',
        department: 'Sales',
        departmentCode: 'SLS',
    },
    35: {
        id: 35,
        name: 'Christopher Lee',
        email: 'christopher.lee@company.com',
        avatar: 'https://i.pravatar.cc/150?img=33',
        position: 'Operations Coordinator',
        department: 'Operations',
        departmentCode: 'OPS',
    },
    36: {
        id: 36,
        name: 'Samantha King',
        email: 'samantha.king@company.com',
        avatar: 'https://i.pravatar.cc/150?img=44',
        position: 'People Operations Specialist',
        department: 'People Operations',
        departmentCode: 'POP',
    },
};

const DUMMY_ALL_EMPLOYEES = Object.values(DUMMY_EMPLOYEE_DIRECTORY).sort((a, b) => a.id - b.id);

// Potential members to add (users not yet in the group) - from backend
const potentialMembers = computed(() => {
    const backendMembers = props.potentialMembers || [];
    if (backendMembers.length > 0) {
        return backendMembers.map((member) => ({
            id: member.id,
            name: member.name,
            email: member.email,
            avatar: member.avatar || '',
            position: member.position || '',
            department: member.department || '',
            departmentCode: member.department_code || '',
        }));
    }
    return DUMMY_ALL_EMPLOYEES;
});

// Search
const memberSearch = ref('');
const addMemberSearch = ref('');
const chatSearch = ref('');

// Dummy chat data for reference (kept for fallback)
const DUMMY_PINNED_CHATS: ChatListItem[] = [
    {
        id: 1,
        name: 'Human Resources',
        lastMessage: 'Sarah: The new employee handbook has been updated',
        lastMessageTime: '14:30',
        description: 'HR team communications and updates',
        is_seen: true,
    },
];

const DUMMY_RECENT_CHATS: ChatListItem[] = [
    {
        id: 2,
        name: 'Finance Department',
        lastMessage: 'David: Q4 budget review scheduled for next week',
        lastMessageTime: '2024-01-15',
        description: 'Financial planning and reporting discussions',
        is_seen: true,
    },
    {
        id: 3,
        name: 'Marketing Team',
        lastMessage: 'Emma: Campaign launch is confirmed for Monday!',
        lastMessageTime: '09:15',
        description: 'Marketing campaigns and brand strategy',
        is_seen: false,
    },
    {
        id: 4,
        name: 'IT Support',
        lastMessage: 'James: Server maintenance tonight at 11 PM',
        lastMessageTime: '16:45',
        description: 'Technical support and infrastructure',
        is_seen: true,
    },
    {
        id: 5,
        name: 'Product Development',
        lastMessage: 'Alice: Sprint planning meeting tomorrow at 10 AM',
        lastMessageTime: '2024-01-14',
        description: 'Product roadmap and development updates',
        is_seen: false,
    },
    {
        id: 6,
        name: 'Sales Team',
        lastMessage: 'Michael: Great job hitting targets this month!',
        lastMessageTime: '11:20',
        description: 'Sales strategies and client updates',
        is_seen: true,
    },
];

// Chat list data from backend (transformedChats is computed but not directly used, 
// we use the watch to update refs instead)

// Mutable refs for chat lists (initialized from backend, can be mutated locally)
const pinnedChats = ref<ChatListItem[]>([]);
const recentChats = ref<ChatListItem[]>([]);

// ✅ Selected chat state (declared before watch to avoid initialization error)
const selectedChat = ref<ChatListItem | null>(null);

// Watch backend data and update refs
watch(() => props.chats, (newChats: Props['chats']) => {
    console.log('[watch props.chats] Props updated, preserving selectedChat:', selectedChat.value?.id);
    
    // Preserve the currently selected chat ID before updating
    const selectedChatId = selectedChat.value?.id;
    
    if (newChats && newChats.length > 0) {
        const transformed = transformChats(newChats);
        pinnedChats.value = transformed.pinned;
        recentChats.value = transformed.recent;
        
        // Restore selectedChat reference if it was set
        if (selectedChatId) {
            const updatedChat = [...transformed.pinned, ...transformed.recent].find((c) => c.id === selectedChatId);
            if (updatedChat) {
                console.log('[watch props.chats] Restoring selectedChat:', updatedChat.id);
                selectedChat.value = updatedChat;
                // Reload chat data for the selected chat
                const backendChat = newChats.find((c) => c.id === selectedChatId);
                if (backendChat) {
                    const currentUserId = props.currentUser?.id;
                    admins.value = transformMembers(backendChat.admins, true);
                    members.value = transformMembers(backendChat.members, false);
                    messages.value = transformMessages(backendChat.messages, currentUserId) as any;
                    pinnedMessages.value = transformPinnedMessages(backendChat.pinnedMessages, messages.value);
                    attachments.value = transformAttachments(backendChat.attachments);
                }
            } else {
                console.log('[watch props.chats] Selected chat not found in updated lists, keeping current selection');
            }
        }
    } else {
        pinnedChats.value = DUMMY_PINNED_CHATS;
        recentChats.value = DUMMY_RECENT_CHATS;
    }
    
    // Re-subscribe to chats when they change
    subscribeToChats();
}, { immediate: true });

// Filter chats based on search
const filteredPinnedChats = computed(() => {
    if (!chatSearch.value.trim()) {
        return pinnedChats.value;
    }
    return pinnedChats.value.filter((chat) =>
        chat.name.toLowerCase().includes(chatSearch.value.toLowerCase()),
    );
});

const filteredRecentChats = computed(() => {
    if (!chatSearch.value.trim()) {
        return recentChats.value;
    }
    return recentChats.value.filter((chat) =>
        chat.name.toLowerCase().includes(chatSearch.value.toLowerCase()),
    );
});

const filteredAdmins = computed(() =>
    admins.value.filter((a) => {
        const term = memberSearch.value.toLowerCase();

        return (
            a.name.toLowerCase().includes(term) ||
            a.email.toLowerCase().includes(term) ||
            a.position.toLowerCase().includes(term) ||
            (a.departmentCode ?? '').toLowerCase().includes(term)
        );
    }),
);

const filteredMembers = computed(() =>
    members.value.filter((m) => {
        const term = memberSearch.value.toLowerCase();

        return (
            m.name.toLowerCase().includes(term) ||
            m.email.toLowerCase().includes(term) ||
            m.position.toLowerCase().includes(term) ||
            (m.departmentCode ?? '').toLowerCase().includes(term)
        );
    }),
);

// Helper function to get initials from name
const getInitials = (name: string): string => {
    return name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
};

// Helper function to format message text with markdown-like syntax
const formatMessageText = (text: string): string => {
    if (!text) {
        return '';
    }

    // Escape HTML to prevent XSS
    let formatted = text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

    // Convert newlines to <br> tags (after escaping, before formatting)
    formatted = formatted.replace(/\n/g, '<br>');

    // Process formatting in order (longer patterns first)
    // Use placeholders to handle nested/overlapping patterns
    
    // Step 1: Bold: *text*
    formatted = formatted.replace(/\*([^*]+)\*/g, '<span class="font-bold">$1</span>');
    
    // Step 2: Italic: \text\
    formatted = formatted.replace(/\\([^\\]+)\\/g, '<span class="italic">$1</span>');
    
    // Step 3: Underline: _text_
    formatted = formatted.replace(/_([^_]+)_/g, '<span class="underline">$1</span>');
    
    // Step 4: Strikethrough: ~text~
    formatted = formatted.replace(/~([^~]+)~/g, '<span class="line-through">$1</span>');
    
    // Step 5: Code/Monospace: `text`
    formatted = formatted.replace(/`([^`]+)`/g, '<code class="font-mono bg-muted px-1 py-0.5 rounded text-xs break-words break-all">$1</code>');

    return formatted;
};


// Filter potential members (exclude already added members and current user)
const filteredPotentialMembers = computed(() => {
    const allMemberIds = [
        ...admins.value.map((a) => a.id),
        ...members.value.map((m) => m.id),
    ];
    const currentUserId = props.currentUser?.id;

    return potentialMembers.value.filter((user) => {
        // Exclude current user
        if (currentUserId && user.id === currentUserId) {
            return false;
        }
        
        const term = addMemberSearch.value.toLowerCase();
        const matchesSearch =
            user.name.toLowerCase().includes(term) ||
            user.email.toLowerCase().includes(term) ||
            user.position.toLowerCase().includes(term) ||
            (user.departmentCode ?? '').toLowerCase().includes(term);
        const notAlreadyAdded = !allMemberIds.includes(user.id);
        return matchesSearch && notAlreadyAdded;
    });
});

// Filter members for create group dialog
const filteredCreateGroupMembers = computed(() => {
    const searchTerm = (createGroupForm.value.search ?? '').trim().toLowerCase();
    const selectedIds = new Set(createGroupForm.value.memberIds);
    const currentUserId = props.currentUser?.id;

    // First, exclude the current user from the list
    const membersExcludingCurrent = potentialMembers.value.filter((user) => user.id !== currentUserId);

    if (!searchTerm.length) {
        return membersExcludingCurrent;
    }

    return membersExcludingCurrent.filter((user) => {
        if (selectedIds.has(user.id)) {
            return true;
        }

        const departmentCode = (user.departmentCode ?? '').toLowerCase();
        return (
            user.name.toLowerCase().includes(searchTerm) ||
            user.email.toLowerCase().includes(searchTerm) ||
            user.position.toLowerCase().includes(searchTerm) ||
            departmentCode.includes(searchTerm)
        );
    });
});

const selectedMembers = computed(() =>
    createGroupForm.value.memberIds
        .map((id) => potentialMembers.value.find((user) => user.id === id))
        .filter((user): user is typeof potentialMembers.value[0] => Boolean(user)),
);

const latestPinnedMessage = computed(() => {
    return pinnedMessages.value.length > 0 ? pinnedMessages.value[0] : null;
});

// Check if current user is an admin of the selected chat
const isCurrentUserAdmin = computed(() => {
    if (!selectedChat.value || !props.currentUser?.id) {
        return false;
    }
    return admins.value.some((admin) => admin.id === props.currentUser?.id);
});

// Check if current user is an admin of the chat being renamed
const isAdminOfChatToRename = computed(() => {
    if (!chatToRename.value || !props.currentUser?.id) {
        return false;
    }
    
    // Find the chat in props.chats to get admin list
    const backendChat = props.chats?.find((c) => c.id === chatToRename.value?.id);
    if (!backendChat) {
        return false;
    }
    
    // Check if current user is in the admins array
    return backendChat.admins.some((admin) => admin.id === props.currentUser?.id);
});

// Filter messages based on search query
const filteredSearchMessages = computed(() => {
    if (!messageSearchQuery.value.trim() || !selectedChat.value) {
        return [];
    }
    
    const query = messageSearchQuery.value.toLowerCase().trim();
    return messages.value.filter((message) => {
        // Skip system messages from search
        if (message.type === 'system') {
            return false;
        }
        
        // Search in message text
        const messageText = (message.text || '').toLowerCase();
        const userName = (message.user || '').toLowerCase();
        
        return messageText.includes(query) || userName.includes(query);
    });
});

// Highlight search term in text (applies highlighting after formatting)
const highlightSearchTerm = (text: string, query: string): string => {
    if (!query.trim()) {
        return formatMessageText(text);
    }
    
    // First format the message text (applies markdown formatting)
    const formatted = formatMessageText(text);
    
    // Escape special regex characters in query
    const escapedQuery = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    
    // Split the formatted HTML into parts: HTML tags and text content
    // Then highlight the query in text content only
    const parts = formatted.split(/(<[^>]+>)/g);
    
    return parts.map((part) => {
        // If it's an HTML tag, leave it as is
        if (part.startsWith('<')) {
            return part;
        }
        
        // If it's text content, highlight the query
        const regex = new RegExp(`(${escapedQuery})`, 'gi');
        return part.replace(regex, '<mark class="bg-yellow-200 dark:bg-yellow-800 px-1 rounded">$1</mark>');
    }).join('');
};

// Helper function to download attachment
const downloadAttachment = (url: string) => {
    if (typeof window !== 'undefined') {
        window.open(url, '_blank');
    }
};

// Scroll to message when clicked in search results or pinned messages
const scrollToMessage = (messageId: number) => {
    showSearchDialog.value = false;
    showPinnedDialog.value = false;
    messageSearchQuery.value = '';
    
    nextTick(() => {
        const messageElement = document.querySelector(`[data-message-id="${messageId}"]`);
        if (messageElement) {
            messageElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
            
            // Find the message bubble (the div with rounded-xl class)
            const messageBubble = messageElement.querySelector('.rounded-xl');
            if (messageBubble) {
                // Add highlight ring to the bubble
                messageBubble.classList.add('ring-2', 'ring-primary', 'ring-offset-2');
                setTimeout(() => {
                    messageBubble.classList.remove('ring-2', 'ring-primary', 'ring-offset-2');
                }, 1000);
            }
        }
    });
};

const createChatMember = (id: number, role: 'Admin' | 'Member') => {
    // Try to find in potential members first (from backend)
    const employee = potentialMembers.value.find((e) => e.id === id);

    // Fallback to dummy data if not found
    if (!employee) {
        const dummyEmployee = DUMMY_EMPLOYEE_DIRECTORY[id];
        if (!dummyEmployee) {
        throw new Error(`Employee with id ${id} not found.`);
        }
        return {
            id: dummyEmployee.id,
            name: dummyEmployee.name,
            email: dummyEmployee.email,
            avatar: dummyEmployee.avatar,
            role,
            position: dummyEmployee.position,
            department: dummyEmployee.department,
            departmentCode: dummyEmployee.departmentCode,
        };
    }

    return {
        id: employee.id,
        name: employee.name,
        email: employee.email,
        avatar: employee.avatar,
        role,
        position: employee.position,
        department: employee.department,
        departmentCode: employee.departmentCode,
    };
};

const toggleMemberSelection = (memberId: number) => {
    if (createGroupForm.value.memberIds.includes(memberId)) {
        createGroupForm.value.memberIds = createGroupForm.value.memberIds.filter((id) => id !== memberId);
    } else {
        createGroupForm.value.memberIds = [...createGroupForm.value.memberIds, memberId];
    }
};

// Create group chat
const validateCreateGroupForm = (): boolean => {
    let isValid = true;
    createGroupErrors.value.name = '';
    createGroupErrors.value.memberIds = '';

    if (!createGroupForm.value.name.trim()) {
        createGroupErrors.value.name = 'Group name is required';
        isValid = false;
    }

    if (createGroupForm.value.memberIds.length === 0) {
        createGroupErrors.value.memberIds = 'Select at least one member';
        isValid = false;
    }

    return isValid;
};

const createGroupChat = async () => {
    if (!validateCreateGroupForm()) {
        return;
    }

    const name = createGroupForm.value.name.trim();
    const memberIds = createGroupForm.value.memberIds;
    const description = 'New group chat'; // Optional: can be added to form later

    // Optimistic UI update
    const tempChatId = Date.now();
    const newChat: ChatListItem = {
        id: tempChatId,
        name: name,
        lastMessage: 'Group created',
        lastMessageTime: formatLastMessageTime(new Date().toISOString()),
        description: description,
        is_seen: true,
    };

    recentChats.value.unshift(newChat);

    // Reset form
    createGroupForm.value = {
        name: '',
        memberIds: [],
        search: '',
    };
    createGroupErrors.value.name = '';
    createGroupErrors.value.memberIds = '';
    showCreateGroupDialog.value = false;

    // Backend API call
    isLoading.value.createGroup = true;
    try {
        await apiCreateGroupChat(name, memberIds, description);
        // TODO: On success, replace temp chat with actual chat from backend
        // The backend response should include the actual chat with proper ID
        // After success, you may want to automatically open the new chat
    } catch (error) {
        // TODO: Handle error - remove optimistic update, show error message
        console.error('Failed to create group chat:', error);
        // Remove the optimistic chat
        const index = recentChats.value.findIndex((c) => c.id === tempChatId);
        if (index > -1) {
            recentChats.value.splice(index, 1);
        }
        // Re-open dialog with error
        showCreateGroupDialog.value = true;
        createGroupForm.value = {
            name: name,
            memberIds: memberIds,
            search: '',
        };
    } finally {
        isLoading.value.createGroup = false;
    }
};

// Open create group dialog
const openCreateGroupDialog = () => {
    showCreateGroupDialog.value = true;
    createGroupForm.value = {
        name: '',
        memberIds: [],
        search: '',
    };
    createGroupErrors.value.name = '';
    createGroupErrors.value.memberIds = '';
};

// Toggle member selection for adding
const toggleAddMemberSelection = (memberId: number) => {
    if (addMemberSelectedIds.value.includes(memberId)) {
        addMemberSelectedIds.value = addMemberSelectedIds.value.filter((id) => id !== memberId);
    } else {
        addMemberSelectedIds.value = [...addMemberSelectedIds.value, memberId];
    }
};

// Selected members to add (computed)
const selectedMembersToAdd = computed(() =>
    addMemberSelectedIds.value
        .map((id) => potentialMembers.value.find((user) => user.id === id))
        .filter((user): user is typeof potentialMembers.value[0] => Boolean(user)),
);

// Open add member dialog
const openAddMemberDialog = () => {
    addMemberSelectedIds.value = [];
    addMemberSearch.value = '';
    showAddMemberDialog.value = true;
};

// Add members function (batch add)
const addMembers = async () => {
    console.log('[addMembers] Starting add members process');
    console.log('[addMembers] Selected IDs:', addMemberSelectedIds.value);
    console.log('[addMembers] Selected chat:', selectedChat.value?.id, selectedChat.value?.name);
    
    if (addMemberSelectedIds.value.length === 0 || !selectedChat.value) {
        console.log('[addMembers] Early return - no selected IDs or no chat');
        return;
    }

    const chatId = selectedChat.value.id;
    
    // Get the latest chat data from props (most up-to-date source)
    const backendChat = props.chats?.find((c) => c.id === chatId);
    
    // Filter out members that are already in the chat (admins or members)
    // Use backend data if available, fallback to local state
    const backendAdminIds = backendChat?.admins?.map((a) => a.id) || [];
    const backendMemberIds = backendChat?.members?.map((m) => m.id) || [];
    const localAdminIds = admins.value.map((a) => a.id);
    const localMemberIds = members.value.map((m) => m.id);
    
    // Combine backend and local data to get most complete list
    const allExistingMemberIds = [
        ...new Set([...backendAdminIds, ...backendMemberIds, ...localAdminIds, ...localMemberIds]),
    ];
    
    console.log('[addMembers] Backend chat data:', backendChat ? 'found' : 'not found');
    console.log('[addMembers] Backend admin IDs:', backendAdminIds);
    console.log('[addMembers] Backend member IDs:', backendMemberIds);
    console.log('[addMembers] Local admin IDs:', localAdminIds);
    console.log('[addMembers] Local member IDs:', localMemberIds);
    console.log('[addMembers] All existing member IDs (combined):', allExistingMemberIds);
    
    // Also exclude current user
    const currentUserId = props.currentUser?.id;
    console.log('[addMembers] Current user ID:', currentUserId);
    
    // Filter selected IDs to only include new members
    const memberIdsToAdd = addMemberSelectedIds.value.filter((id) => {
        const isExisting = allExistingMemberIds.includes(id);
        const isCurrentUser = id === currentUserId;
        const shouldInclude = !isExisting && !isCurrentUser;
        
        console.log(`[addMembers] Member ID ${id}: isExisting=${isExisting}, isCurrentUser=${isCurrentUser}, shouldInclude=${shouldInclude}`);
        
        return shouldInclude;
    });

    console.log('[addMembers] Filtered member IDs to add:', memberIdsToAdd);
    console.log('[addMembers] Original selected count:', addMemberSelectedIds.value.length);
    console.log('[addMembers] Filtered count:', memberIdsToAdd.length);

    if (memberIdsToAdd.length === 0) {
        console.warn('[addMembers] No valid members to add after filtering');
        console.warn('[addMembers] This means all selected members are already in the chat or are the current user');
        // Show error message
        alert('All selected members are already in this chat.');
        return;
    }

    // Optimistic UI update - add members to UI immediately
    const addedMembers: any[] = [];
    memberIdsToAdd.forEach((memberId) => {
        const user = potentialMembers.value.find((u) => u.id === memberId);
        if (user && !members.value.find((m) => m.id === memberId) && !admins.value.find((a) => a.id === memberId)) {
            const newMember = createChatMember(memberId, 'Member');
            members.value.push(newMember);
            addedMembers.push(newMember);
        }
    });

    // Reset and close
    const selectedIds = [...memberIdsToAdd];
    addMemberSelectedIds.value = [];
    addMemberSearch.value = '';
    showAddMemberDialog.value = false;

    // Backend API call
    isLoading.value.addMembers = true;
    try {
        await apiAddMembers(chatId, selectedIds);
        
        // Reload chat data to get the new system message and updated member list
        router.reload({
            only: ['chats'],
            onSuccess: () => {
                nextTick(() => {
                    // Reload chat-specific data if chat is selected
                    if (selectedChat.value) {
                        const backendChat = props.chats?.find((c) => c.id === chatId);
                        if (backendChat) {
                            const currentUserId = props.currentUser?.id;
                            admins.value = transformMembers(backendChat.admins, true);
                            members.value = transformMembers(backendChat.members, false);
                            messages.value = transformMessages(backendChat.messages, currentUserId) as any;
                            pinnedMessages.value = transformPinnedMessages(backendChat.pinnedMessages, messages.value);
                            attachments.value = transformAttachments(backendChat.attachments);
                            scrollToBottom();
                        }
                    }
                });
            },
        });
    } catch (error) {
        // Handle error - remove optimistic updates, show error message
        console.error('Failed to add members:', error);
        // Remove the optimistic members
        addedMembers.forEach((member) => {
            const index = members.value.findIndex((m) => m.id === member.id);
            if (index > -1) {
                members.value.splice(index, 1);
            }
        });
        // Re-open dialog with error
        showAddMemberDialog.value = true;
        addMemberSelectedIds.value = selectedIds;
    } finally {
        isLoading.value.addMembers = false;
    }
};

// Remove member function
const openRemoveConfirm = (member: { id: number; name: string }) => {
    memberToRemove.value = member;
    showRemoveConfirmDialog.value = true;
};

const confirmRemoveMember = async () => {
    if (!memberToRemove.value || !selectedChat.value) {
        return;
    }

    const chatId = selectedChat.value.id;
    const memberId = memberToRemove.value.id;
    const memberName = memberToRemove.value.name;

    // Optimistic UI update - remove member from UI immediately
    const wasInMembers = members.value.find((m) => m.id === memberId);
    const wasInAdmins = admins.value.find((a) => a.id === memberId);
    
    members.value = members.value.filter((m) => m.id !== memberId);
    admins.value = admins.value.filter((a) => a.id !== memberId);
    
        memberToRemove.value = null;
        showRemoveConfirmDialog.value = false;

    // Backend API call
    isLoading.value.removeMember = true;
    try {
        await apiRemoveMember(chatId, memberId);
        
        // Reload chat data to get the new system message and updated member list
        router.reload({
            only: ['chats'],
            onSuccess: () => {
                nextTick(() => {
                    // Reload chat-specific data if chat is selected
                    if (selectedChat.value) {
                        const backendChat = props.chats?.find((c) => c.id === chatId);
                        if (backendChat) {
                            const currentUserId = props.currentUser?.id;
                            admins.value = transformMembers(backendChat.admins, true);
                            members.value = transformMembers(backendChat.members, false);
                            messages.value = transformMessages(backendChat.messages, currentUserId) as any;
                            pinnedMessages.value = transformPinnedMessages(backendChat.pinnedMessages, messages.value);
                            attachments.value = transformAttachments(backendChat.attachments);
                            scrollToBottom();
                        }
                    }
                });
            },
        });
    } catch (error) {
        // Handle error - restore optimistic update, show error message
        console.error('Failed to remove member:', error);
        // Restore the member
        if (wasInMembers) {
            const user = potentialMembers.value.find((u) => u.id === memberId);
            if (user) {
                members.value.push(createChatMember(memberId, 'Member'));
            }
        }
        if (wasInAdmins) {
            const user = potentialMembers.value.find((u) => u.id === memberId);
            if (user) {
                admins.value.push(createChatMember(memberId, 'Admin'));
            }
        }
        // Show error notification
        alert(`Failed to remove ${memberName}. Please try again.`);
    } finally {
        isLoading.value.removeMember = false;
    }
};

// Set user as admin
const setAsAdmin = async (user: { id: number }) => {
    if (!selectedChat.value) {
        return;
    }

    const chatId = selectedChat.value.id;
    const memberId = user.id;

    // Optimistic UI update
    const wasInMembers = members.value.find((m) => m.id === memberId);
    members.value = members.value.filter((m) => m.id !== memberId);
    
    if (!admins.value.find((a) => a.id === memberId)) {
        admins.value.push(createChatMember(memberId, 'Admin'));
    }

    // Backend API call
    isLoading.value.setAdmin = true;
    try {
        await apiSetAsAdmin(chatId, memberId);
        
        // Reload chat data to get the new system message and updated member list
        router.reload({
            only: ['chats'],
            onSuccess: () => {
                nextTick(() => {
                    // Reload chat-specific data if chat is selected
                    if (selectedChat.value) {
                        const backendChat = props.chats?.find((c) => c.id === chatId);
                        if (backendChat) {
                            const currentUserId = props.currentUser?.id;
                            admins.value = transformMembers(backendChat.admins, true);
                            members.value = transformMembers(backendChat.members, false);
                            messages.value = transformMessages(backendChat.messages, currentUserId) as any;
                            pinnedMessages.value = transformPinnedMessages(backendChat.pinnedMessages, messages.value);
                            attachments.value = transformAttachments(backendChat.attachments);
                            scrollToBottom();
                        }
                    }
                });
            },
        });
    } catch (error) {
        // Handle error - restore optimistic update, show error message
        console.error('Failed to set as admin:', error);
        // Restore previous state
        if (wasInMembers) {
            members.value.push(createChatMember(memberId, 'Member'));
        }
        admins.value = admins.value.filter((a) => a.id !== memberId);
        alert('Failed to set user as admin. Please try again.');
    } finally {
        isLoading.value.setAdmin = false;
    }
};

// Remove admin status
const removeAdminStatus = async (admin: { id: number }) => {
    if (!selectedChat.value) {
        return;
    }

    const chatId = selectedChat.value.id;
    const memberId = admin.id;

    // Optimistic UI update
    admins.value = admins.value.filter((a) => a.id !== memberId);
    
    if (!members.value.find((m) => m.id === memberId)) {
        members.value.push(createChatMember(memberId, 'Member'));
    }

    // Backend API call
    isLoading.value.removeAdmin = true;
    try {
        await apiRemoveAdminStatus(chatId, memberId);
        
        // Reload chat data to get the new system message and updated member list
        router.reload({
            only: ['chats'],
            onSuccess: () => {
                nextTick(() => {
                    // Reload chat-specific data if chat is selected
                    if (selectedChat.value) {
                        const backendChat = props.chats?.find((c) => c.id === chatId);
                        if (backendChat) {
                            const currentUserId = props.currentUser?.id;
                            admins.value = transformMembers(backendChat.admins, true);
                            members.value = transformMembers(backendChat.members, false);
                            messages.value = transformMessages(backendChat.messages, currentUserId) as any;
                            pinnedMessages.value = transformPinnedMessages(backendChat.pinnedMessages, messages.value);
                            attachments.value = transformAttachments(backendChat.attachments);
                            scrollToBottom();
                        }
                    }
                });
            },
        });
    } catch (error) {
        // Handle error - restore optimistic update, show error message
        console.error('Failed to remove admin status:', error);
        // Restore previous state
        members.value = members.value.filter((m) => m.id !== memberId);
        admins.value.push(createChatMember(memberId, 'Admin'));
        alert('Failed to remove admin status. Please try again.');
    } finally {
        isLoading.value.removeAdmin = false;
    }
};

// Chat-specific data structure (DUMMY - kept for reference, now using backend data)
/*
const DUMMY_CHAT_DATA = {
    1: {
        admins: [createChatMember(1, 'Admin'), createChatMember(2, 'Admin')],
        members: [
            createChatMember(3, 'Member'),
            createChatMember(4, 'Member'),
            createChatMember(5, 'Member'),
            createChatMember(6, 'Member'),
        ],
        pinnedMessages: [
            { id: 1, text: 'New employee handbook is now available on the portal', user: 'Sarah Connor', time: '2h ago' },
            { id: 2, text: 'Benefits enrollment deadline: December 15th', user: 'Michael Brown', time: '1 day ago' },
        ],
        attachments: [
            { id: 1, name: 'Employee_Handbook_2024.pdf', type: 'pdf', size: '3.2 MB' },
            { id: 2, name: 'Benefits_Overview.pdf', type: 'pdf', size: '1.8 MB' },
            { id: 3, name: 'Leave_Policy.docx', type: 'doc', size: '245 KB' },
        ],
        messages: [
            { id: 1, type: 'system', text: 'Sarah Connor created this group', timestamp: '2024-01-15 09:00' },
            { id: 2, type: 'system', text: 'Sarah Connor added Michael Brown, Jennifer Taylor, Robert Johnson, Lisa Anderson, and Thomas Wilson', timestamp: '2024-01-15 09:01' },
            { id: 3, type: 'system', text: 'Sarah Connor set the group name to Human Resources', timestamp: '2024-01-15 09:02' },
            { id: 4, userId: 1, user: 'Sarah Connor', avatar: 'https://i.pravatar.cc/150?img=5', text: 'Welcome everyone! This group is for *HR team communications* and _best practices_.', timestamp: '2024-01-15 09:05', isPinned: false },
            { id: 5, userId: 2, user: 'Michael Brown', avatar: 'https://i.pravatar.cc/150?img=12', text: 'Thanks for setting this up, Sarah! \\Looking forward\\ to better coordination.', timestamp: '2024-01-15 09:10', isPinned: false },
            { id: 6, userId: 3, user: 'Jennifer Taylor', avatar: 'https://i.pravatar.cc/150?img=1', text: 'Quick reminder: Please use `@mentions` when you need someone\'s attention urgently.', timestamp: '2024-01-15 10:30', isPinned: false },
            { id: 7, userId: 1, user: 'Sarah Connor', avatar: 'https://i.pravatar.cc/150?img=5', text: 'New employee handbook is now available on the portal', timestamp: '2024-01-15 14:20', isPinned: true, hasAttachment: true, attachmentName: 'Employee_Handbook_2024.pdf' },
            { id: 8, userId: 4, user: 'Robert Johnson', avatar: 'https://i.pravatar.cc/150?img=13', text: 'Thanks! I\'ll review it *today* and share feedback.', timestamp: '2024-01-15 14:25', isPinned: false },
            { id: 9, type: 'system', text: 'Sarah Connor set Michael Brown as admin', timestamp: '2024-01-15 15:00' },
            { id: 10, userId: 5, user: 'Lisa Anderson', avatar: 'https://i.pravatar.cc/150?img=9', text: 'Team, the _benefits enrollment deadline_ is approaching. Make sure to submit by *December 15th*.', timestamp: '2024-01-16 09:00', isPinned: false },
            { id: 11, userId: 2, user: 'Michael Brown', avatar: 'https://i.pravatar.cc/150?img=12', text: 'Benefits enrollment deadline: *December 15th*', timestamp: '2024-01-16 09:05', isPinned: true },
            { id: 12, userId: 6, user: 'Thomas Wilson', avatar: 'https://i.pravatar.cc/150?img=18', text: 'Just uploaded the benefits overview document for reference.', timestamp: '2024-01-16 09:30', isPinned: false, hasAttachment: true, attachmentName: 'Benefits_Overview.pdf' },
            { id: 13, userId: 3, user: 'Jennifer Taylor', avatar: 'https://i.pravatar.cc/150?img=1', text: 'Great! Also sharing our ~outdated~ updated leave policy for 2024.', timestamp: '2024-01-16 10:00', isPinned: false, hasAttachment: true, attachmentName: 'Leave_Policy.docx' },
            { id: 14, userId: 1, user: 'Sarah Connor', avatar: 'https://i.pravatar.cc/150?img=5', text: '*Important:* All team members must complete the compliance training by end of month.\nLink: `https://training.company.com/compliance`\n\nPlease confirm once completed!', timestamp: '2024-01-16 11:00', isPinned: false },
            { id: 15, userId: 4, user: 'Robert Johnson', avatar: 'https://i.pravatar.cc/150?img=13', text: 'I\'ve completed mine already.\nIt took about _30 minutes_ to finish.\n\nGreat training materials!', timestamp: '2024-01-16 11:15', isPinned: false },
            { id: 16, type: 'system', text: 'Lisa Anderson left the group', timestamp: '2024-01-16 15:00' },
            { id: 17, type: 'system', text: 'Sarah Connor added Lisa Anderson', timestamp: '2024-01-16 15:05' },
            { id: 18, userId: 5, user: 'Lisa Anderson', avatar: 'https://i.pravatar.cc/150?img=9', text: 'Oops, left by accident! 😅', timestamp: '2024-01-16 15:06', isPinned: false },
            { id: 19, userId: 2, user: 'Michael Brown', avatar: 'https://i.pravatar.cc/150?img=12', text: 'Quick update: We\'re hiring *2 new HR specialists*. JDs will be posted on the careers page soon.', timestamp: '2024-01-17 10:00', isPinned: false },
            { id: 20, userId: 6, user: 'Thomas Wilson', avatar: 'https://i.pravatar.cc/150?img=18', text: 'Excellent news! Will we be using the same interview process as before or trying something new?', timestamp: '2024-01-17 10:15', isPinned: false },
            { id: 21, userId: 1, user: 'Sarah Connor', avatar: 'https://i.pravatar.cc/150?img=5', text: 'We\'ll stick with the current process but add a `technical assessment` for better evaluation.', timestamp: '2024-01-17 10:30', isPinned: false },
            { id: 22, userId: 3, user: 'Jennifer Taylor', avatar: 'https://i.pravatar.cc/150?img=1', text: 'Here\'s the new office layout design for the HR department:', timestamp: '2024-01-17 14:00', isPinned: false, hasAttachment: true, attachmentName: 'Office_Layout_Design.xlsx', attachmentType: 'file' },
            { id: 23, userId: 4, user: 'Robert Johnson', avatar: 'https://i.pravatar.cc/150?img=13', text: 'Love the design! The open workspace looks great.', timestamp: '2024-01-17 14:10', isPinned: false },
            { id: 24, isCurrentUser: true, user: 'Jerick Cruza', avatar: '', text: 'I agree! The _natural lighting_ looks *amazing*.\n\nCan we implement this by `Q2 2024`?\n\nLet me know what you think!', timestamp: '2024-01-17 14:15', isPinned: false },
            { id: 25, userId: 5, user: 'Lisa Anderson', avatar: 'https://i.pravatar.cc/150?img=9', text: 'Team meeting notes from yesterday\'s training session:', timestamp: '2024-01-17 16:00', isPinned: false, hasAttachment: true, attachmentName: 'HR_Team_Training_Notes.docx', attachmentType: 'file' },
            { id: 26, isCurrentUser: true, user: 'Jerick Cruza', avatar: '', text: 'Great session! 🎉', timestamp: '2024-01-17 16:05', isPinned: false },
            { id: 27, userId: 6, user: 'Thomas Wilson', avatar: 'https://i.pravatar.cc/150?img=18', text: 'Thanks everyone for participating.', timestamp: '2024-01-17 16:15', isPinned: false },
            { id: 28, isCurrentUser: true, user: 'Jerick Cruza', avatar: '', text: 'The compliance training materials have been updated.\n\n_Please review_ by *end of week*.\n\nKey changes:\n- Updated policies\n- New procedures\n- Revised guidelines', timestamp: '2024-01-18 09:00', isPinned: true },
            { id: 29, isCurrentUser: true, user: 'Jerick Cruza', avatar: '', text: 'Updated org chart for the department:', timestamp: '2024-01-18 10:30', isPinned: false, hasAttachment: true, attachmentName: 'HR_Org_Chart_2024.pdf', attachmentType: 'file' },
            { id: 30, userId: 2, user: 'Michael Brown', avatar: 'https://i.pravatar.cc/150?img=12', text: 'Q1 Performance Review Template:', timestamp: '2024-01-18 14:00', isPinned: false, hasAttachment: true, attachmentName: 'Q1_Performance_Review_Template.pptx', attachmentType: 'file' },
        ],
    },
    2: {
        admins: [createChatMember(7, 'Admin')],
        members: [
            createChatMember(8, 'Member'),
            createChatMember(9, 'Member'),
            createChatMember(10, 'Member'),
        ],
        pinnedMessages: [
            { id: 1, text: 'Q4 financial reports due by Friday', user: 'David Miller', time: '3h ago' },
            { id: 2, text: 'Budget approval meeting rescheduled to Tuesday', user: 'Patricia Lee', time: '5h ago' },
        ],
        attachments: [
            { id: 1, name: 'Q4_Budget_Report.xlsx', type: 'excel', size: '4.1 MB' },
            { id: 2, name: 'Expense_Breakdown.xlsx', type: 'excel', size: '2.7 MB' },
            { id: 3, name: 'Financial_Forecast_2025.pdf', type: 'pdf', size: '1.5 MB' },
            { id: 4, name: 'Invoice_Template.xlsx', type: 'excel', size: '892 KB' },
        ],
        messages: [],
    },
    3: {
        admins: [createChatMember(11, 'Admin'), createChatMember(12, 'Admin')],
        members: [
            createChatMember(13, 'Member'),
            createChatMember(14, 'Member'),
            createChatMember(15, 'Member'),
            createChatMember(16, 'Member'),
            createChatMember(17, 'Member'),
        ],
        pinnedMessages: [
            { id: 1, text: 'Social media campaign launch - Monday 9 AM', user: 'Emma Watson', time: '1h ago' },
            { id: 2, text: 'Brand guidelines updated, please review', user: 'Christopher Davis', time: '4h ago' },
            { id: 3, text: 'Client presentation deck ready for review', user: 'Amanda Clark', time: '6h ago' },
        ],
        attachments: [
            { id: 1, name: 'Campaign_Strategy_Q1.pdf', type: 'pdf', size: '5.3 MB' },
            { id: 2, name: 'Brand_Guidelines_2024.pdf', type: 'pdf', size: '8.7 MB' },
            { id: 3, name: 'Social_Media_Calendar.xlsx', type: 'excel', size: '1.2 MB' },
            { id: 4, name: 'Product_Launch_Mockup.png', type: 'image', size: '3.4 MB' },
            { id: 5, name: 'Marketing_Budget.xlsx', type: 'excel', size: '967 KB' },
        ],
        messages: [],
    },
    4: {
        admins: [createChatMember(18, 'Admin')],
        members: [
            createChatMember(19, 'Member'),
            createChatMember(20, 'Member'),
            createChatMember(21, 'Member'),
        ],
        pinnedMessages: [
            { id: 1, text: 'Server maintenance scheduled for tonight 11 PM - 2 AM', user: 'James Wilson', time: '30m ago' },
            { id: 2, text: 'New security patch available, please update ASAP', user: 'Andrew Chen', time: '2h ago' },
        ],
        attachments: [
            { id: 1, name: 'Network_Diagram.pdf', type: 'pdf', size: '2.1 MB' },
            { id: 2, name: 'Security_Protocol.pdf', type: 'pdf', size: '1.6 MB' },
            { id: 3, name: 'IT_Helpdesk_Guide.pdf', type: 'pdf', size: '3.8 MB' },
            { id: 4, name: 'Server_Specs.xlsx', type: 'excel', size: '456 KB' },
        ],
        messages: [],
    },
    5: {
        admins: [createChatMember(22, 'Admin'), createChatMember(23, 'Admin')],
        members: [
            createChatMember(24, 'Member'),
            createChatMember(25, 'Member'),
            createChatMember(26, 'Member'),
            createChatMember(27, 'Member'),
            createChatMember(28, 'Member'),
            createChatMember(29, 'Member'),
        ],
        pinnedMessages: [
            { id: 1, text: 'Sprint planning tomorrow at 10 AM', user: 'Alice Johnson', time: '1h ago' },
            { id: 2, text: 'New feature specs uploaded to shared drive', user: 'Richard Kim', time: '3h ago' },
            { id: 3, text: 'Code review session scheduled for Thursday', user: 'Sophia Lee', time: '5h ago' },
        ],
        attachments: [
            { id: 1, name: 'Product_Roadmap_2024.pdf', type: 'pdf', size: '6.2 MB' },
            { id: 2, name: 'Feature_Specifications.pdf', type: 'pdf', size: '4.5 MB' },
            { id: 3, name: 'Sprint_Backlog.xlsx', type: 'excel', size: '1.8 MB' },
            { id: 4, name: 'User_Stories.docx', type: 'doc', size: '723 KB' },
            { id: 5, name: 'Wireframes_v3.pdf', type: 'pdf', size: '9.4 MB' },
            { id: 6, name: 'API_Documentation.pdf', type: 'pdf', size: '2.9 MB' },
        ],
        messages: [],
    },
    6: {
        admins: [createChatMember(30, 'Admin')],
        members: [
            createChatMember(31, 'Member'),
            createChatMember(32, 'Member'),
            createChatMember(33, 'Member'),
            createChatMember(34, 'Member'),
        ],
        pinnedMessages: [
            { id: 1, text: 'Congratulations team! We hit 120% of our monthly target!', user: 'Michael Chen', time: '2h ago' },
            { id: 2, text: 'New sales playbook available in resources', user: 'Jessica Parker', time: '1 day ago' },
        ],
        attachments: [
            { id: 1, name: 'Sales_Report_November.xlsx', type: 'excel', size: '2.3 MB' },
            { id: 2, name: 'Client_Proposal_Template.pptx', type: 'powerpoint', size: '5.1 MB' },
            { id: 3, name: 'Sales_Playbook_2024.pdf', type: 'pdf', size: '7.6 MB' },
            { id: 4, name: 'Pricing_Strategy.xlsx', type: 'excel', size: '1.4 MB' },
        ],
        messages: [],
    },
};
*/

// Current chat members, pinned messages, attachments, and messages (dynamic based on selected chat)
// Initialize with empty arrays, will be populated when a chat is selected
const admins = ref<ReturnType<typeof transformMembers>>([]);
const members = ref<ReturnType<typeof transformMembers>>([]);
const pinnedMessages = ref<ReturnType<typeof transformPinnedMessages>>([]);
const attachments = ref<ReturnType<typeof transformAttachments>>([]);
const messages = ref<any[]>([]);

// Scroll to bottom ref
const messagesEndRef = ref<HTMLDivElement>();

const scrollToBottom = () => {
    nextTick(() => {
        nextTick(() => {
            if (messagesEndRef.value) {
                messagesEndRef.value.scrollIntoView({ behavior: 'auto', block: 'end', inline: 'nearest' });
            }
        });
    });
};

// Watch messages and scroll to bottom when they change
watch(messages, () => {
    scrollToBottom();
}, { deep: true });


</script>

<template>
    <Head title="Chats" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-1 flex flex-1 min-h-0 max-h-[calc(100vh-5.5rem)] rounded-b-sm border overflow-hidden">
            <!-- Sidebar -->
            <div
                v-if="showSidebar || isDesktop"
                class="flex min-h-0 w-full flex-col border-r p-4 md:w-[340px] overflow-hidden"
            >
                <!-- Header -->
                <div class="mb-4 flex shrink-0 items-center justify-between">
                    <h2 class="text-2xl font-semibold">Chats</h2>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="h-8 w-8 text-muted-foreground hover:text-foreground"
                        @click="openCreateGroupDialog"
                    >
                        <SquarePen class="size-5" />
                    </Button>
                </div>

                <!-- Search -->
                <div class="relative mb-4 shrink-0">
                    <span
                        class="absolute inset-y-0 left-0 flex items-center pl-2"
                    >
                        <Search class="size-4 text-muted-foreground" />
                    </span>
                    <Input
                        v-model="chatSearch"
                        type="text"
                        placeholder="Search chats..."
                        class="pl-8"
                    />
                </div>

                <!-- Scrollable Chat List -->
                <ScrollArea class="flex-1 min-h-0 max-h-[calc(100vh-200px)]">
                    <div class="space-y-4">
                        <!-- Pinned Section -->
                        <div
                            v-if="filteredPinnedChats.length > 0"
                            class="text-sm font-medium text-muted-foreground"
                        >
                            Pinned
                        </div>

                        <div v-if="filteredPinnedChats.length > 0" class="space-y-2">
                            <div
                                v-for="chat in filteredPinnedChats"
                                :key="chat.id"
                                :class="[
                                    'group relative w-full cursor-pointer rounded-md border pl-4 pr-2 py-2 transition flex items-center gap-1',
                                    selectedChat?.id === chat.id
                                        ? 'bg-primary/10 border-primary text-primary dark:bg-primary/20'
                                        : ''
                                ]"
                                @click="openChat(chat)"
                            >
                                <div class="flex-1 min-w-0">
                                    <div
                                        class="flex items-center justify-between text-sm font-medium"
                                    >
                                        <span class="truncate text-[16px] flex-1 min-w-0">
                                            {{ chat.name }}
                                        </span>
                                        <span class="text-xs text-muted-foreground shrink-0 ml-2">
                                            {{ chat.lastMessageTime }}
                                        </span>
                                    </div>

                                    <div class="flex items-center justify-between">
                                        <p
                                            :class="[
                                                'flex-1 truncate text-sm',
                                                chat.is_seen ? 'text-muted-foreground' : 'text-foreground font-medium'
                                            ]"
                                        >
                                            {{ chat.lastMessage }}
                                        </p>
                                    </div>
                                </div>

                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child @click.stop>
                                        <button
                                            type="button"
                                            class="h-8 w-8 shrink-0 flex items-center justify-center bg-transparent border-0 p-0 cursor-pointer"
                                        >
                                            <EllipsisVertical class="size-6 text-foreground" />
                                        </button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent side="bottom" align="end">
                                        <DropdownMenuItem @click.stop="unpinChat(chat)">
                                            <PinOff class="mr-1 size-4" />
                                            Unpin
                                        </DropdownMenuItem>
                                        <DropdownMenuItem @click.stop="openRenameDialog(chat)">
                                            <Pencil class="mr-1 size-4" />
                                            Rename
                                        </DropdownMenuItem>
                                        <DropdownMenuItem @click.stop="toggleSeenStatus(chat)">
                                            <EyeOff v-if="chat.is_seen" class="mr-1 size-4" />
                                            <Eye v-else class="mr-1 size-4" />
                                            {{ chat.is_seen ? 'Mark as Unread' : 'Mark as Read' }}
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem class="text-destructive focus:text-destructive" @click.stop="openLeaveConfirm(chat)">
                                            <LogOut class="mr-1 size-4" />
                                            Leave
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                        </div>

                        <!-- Recent Section -->
                        <div
                            v-if="filteredRecentChats.length > 0"
                            class="text-sm font-medium text-muted-foreground"
                        >
                            Recent
                        </div>

                        <div v-if="filteredRecentChats.length > 0" class="space-y-2">
                            <div
                                v-for="chat in filteredRecentChats"
                                :key="chat.id"
                                :class="[
                                    'group relative w-full cursor-pointer rounded-md border pl-4 pr-2 py-2 transition flex items-center gap-1',
                                    selectedChat?.id === chat.id
                                        ? 'bg-primary/10 border-primary text-primary dark:bg-primary/20'
                                        : ''
                                ]"
                                @click="openChat(chat)"
                            >
                                <div class="flex-1 min-w-0">
                                    <div
                                        class="flex items-center justify-between text-sm font-medium"
                                    >
                                        <span class="truncate text-[16px] flex-1 min-w-0">
                                            {{ chat.name }}
                                        </span>
                                        <span class="text-xs text-muted-foreground shrink-0 ml-2">
                                            {{ chat.lastMessageTime }}
                                        </span>
                                    </div>

                                    <div class="flex items-center justify-between">
                                        <p
                                            :class="[
                                                'flex-1 truncate text-sm',
                                                chat.is_seen ? 'text-muted-foreground' : 'text-foreground font-medium'
                                            ]"
                                        >
                                            {{ chat.lastMessage }}
                                        </p>
                                    </div>
                                </div>

                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child @click.stop>
                                        <button
                                            type="button"
                                            class="h-8 w-8 shrink-0 flex items-center justify-center bg-transparent border-0 p-0 cursor-pointer"
                                        >
                                            <EllipsisVertical class="size-6 text-foreground" />
                                        </button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent side="bottom" align="end">
                                        <DropdownMenuItem @click.stop="pinChat(chat)">
                                            <Pin class="mr-1 size-4" />
                                            Pin
                                        </DropdownMenuItem>
                                        <DropdownMenuItem @click.stop="openRenameDialog(chat)">
                                            <Pencil class="mr-1 size-4" />
                                            Rename
                                        </DropdownMenuItem>
                                        <DropdownMenuItem @click.stop="toggleSeenStatus(chat)">
                                            <EyeOff v-if="chat.is_seen" class="mr-1 size-4" />
                                            <Eye v-else class="mr-1 size-4" />
                                            {{ chat.is_seen ? 'Mark as Unread' : 'Mark as Read' }}
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem class="text-destructive focus:text-destructive" @click.stop="openLeaveConfirm(chat)">
                                            <LogOut class="mr-1 size-4" />
                                            Leave
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                        </div>

                        <!-- Empty State -->
                        <div
                            v-if="filteredPinnedChats.length === 0 && filteredRecentChats.length === 0"
                            class="flex flex-col items-center justify-center py-12 text-center"
                        >
                            <Search class="mb-3 size-10 text-muted-foreground" />
                            <p class="text-sm font-medium text-muted-foreground">
                                No chats found
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{
                                    chatSearch
                                        ? 'Try adjusting your search terms'
                                        : 'No chats available'
                                }}
                            </p>
                        </div>
                    </div>
                </ScrollArea>
            </div>

            <!-- Main Chat Area -->
            <div
                class="flex flex-1 flex-col min-h-0 min-w-0 overflow-hidden"
                v-show="!showSidebar || isDesktop"
            >
                <!-- Empty State - No Chat Selected -->
                <div
                    v-if="!selectedChat"
                    class="flex flex-1 flex-col items-center justify-center p-8 text-center"
                >
                    <div class="rounded-full bg-muted p-6 mb-4">
                        <SquarePen class="size-12 text-muted-foreground" />
                    </div>
                    <h2 class="text-2xl font-semibold mb-2">Select a Chat</h2>
                    <p class="text-muted-foreground max-w-sm">
                        Choose a group chat from the list to start messaging, or create a new group to get started.
                    </p>
                </div>

                <!-- Chat Area - When Chat is Selected -->
                <template v-else>
                    <!-- Header -->
                    <div class="flex items-center border-b py-4 pr-2 pl-2 md:px-6 overflow-x-hidden min-w-0">
                        <!-- Back button only visible on small screens -->
                        <Button
                            class="ml-0 md:ml-2 md:hidden shrink-0"
                            variant="outline"
                            size="icon"
                            v-if="!isDesktop"
                            @click="goBackToList"
                        >
                            <ChevronLeft class="size-6" />
                        </Button>

                        <div class="w-0 flex-1 overflow-hidden min-w-0">
                            <h2 class="ml-2 truncate text-2xl font-semibold">
                                {{ selectedChat.name }}
                            </h2>
                        </div>

                    <div
                        class="ml-2 sm:ml-4 flex flex-shrink-0 items-center gap-1 sm:gap-2 text-muted-foreground"
                    >
                        <!-- ✅ Desktop: show all individual buttons -->
                        <template v-if="isDesktop">
                            <Button
                                variant="ghost"
                                size="icon"
                                class="h-10 w-10 hover:text-foreground"
                                @click="showMembersDialog = true"
                            >
                                <Users class="size-6" />
                            </Button>

                            <Button
                                variant="ghost"
                                size="icon"
                                class="h-10 w-10 hover:text-foreground"
                                @click="showPinnedDialog = true"
                            >
                                <Pin class="size-6" />
                            </Button>

                            <Button
                                variant="ghost"
                                size="icon"
                                class="h-10 w-10 hover:text-foreground"
                                @click="showSearchDialog = true"
                            >
                                <Search class="size-6" />
                            </Button>

                            <Button
                                variant="ghost"
                                size="icon"
                                class="h-10 w-10 hover:text-foreground"
                                @click="showAttachmentsDialog = true"
                            >
                                <Paperclip class="size-6" />
                            </Button>

                            <Separator orientation="vertical" class="!h-4" />

                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="h-10 w-10 hover:text-foreground"
                                    >
                                        <Settings class="size-6" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent side="bottom" align="end">
                                    <DropdownMenuItem
                                        v-if="selectedChat"
                                        @click="openRenameDialog(selectedChat)"
                                    >
                                        <Pencil class="size-4" />
                                        Rename
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        v-if="selectedChat"
                                        @click="openLeaveConfirm(selectedChat)"
                                    >
                                        <LogOut class="size-4" />
                                        Leave
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </template>

                        <!-- ✅ Mobile: Replace buttons with kebab (EllipsisVertical) -->
                        <template v-else>
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button variant="ghost" size="icon" class="shrink-0">
                                        <EllipsisVertical class="size-6" />
                                    </Button>
                                </DropdownMenuTrigger>

                                <DropdownMenuContent side="bottom" align="end">
                                    <DropdownMenuItem
                                        @click="showMembersDialog = true"
                                    >
                                        <Users class="mr-2 size-5" /> Members
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        @click="showPinnedDialog = true"
                                    >
                                        <Pin class="mr-2 size-5" /> Pinned
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        @click="showSearchDialog = true"
                                    >
                                        <Search class="mr-2 size-5" /> Search
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        @click="showAttachmentsDialog = true"
                                    >
                                        <Paperclip class="mr-2 size-5" />
                                        Attachments
                                    </DropdownMenuItem>

                                    <DropdownMenuSeparator />

                                    <DropdownMenuItem
                                        v-if="selectedChat"
                                        @click="openRenameDialog(selectedChat)"
                                    >
                                        <Pencil class="mr-2 size-5" />
                                        Rename
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        v-if="selectedChat"
                                        @click="openLeaveConfirm(selectedChat)"
                                    >
                                        <LogOut class="mr-2 size-5" />
                                        Leave
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </template>
                    </div>
                </div>

                <!-- Pinned Messages Bar -->
                <div
                    v-if="latestPinnedMessage"
                    class="border-b bg-muted/30 dark:bg-muted/20 px-4 py-2.5 cursor-pointer hover:bg-muted/50 dark:hover:bg-muted/30 transition-colors"
                    @click="showPinnedDialog = true"
                >
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center rounded-lg border border-border bg-muted/50 dark:bg-muted/30 p-2 shrink-0">
                            <Pin class="size-5 text-muted-foreground" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                <span class="font-medium">{{ latestPinnedMessage.user }}</span>
                                <span>·</span>
                                <span>{{ latestPinnedMessage.time }}</span>
                                <span v-if="(latestPinnedMessage as any).hasAttachment && ((latestPinnedMessage as any).attachments?.length || 0) > 0" class="flex items-center gap-1 text-primary">
                                    <span>·</span>
                                    <FileText class="size-3" />
                                    <span>+{{ ((latestPinnedMessage as any).attachments?.length || 1) }} Attachment{{ (((latestPinnedMessage as any).attachments?.length || 1) > 1) ? 's' : '' }}</span>
                                </span>
                            </div>
                            <p class="text-sm text-foreground line-clamp-1 mt-0.5" v-html="formatMessageText(latestPinnedMessage.text)"></p>
                        </div>
                        <ChevronRight class="size-5 text-muted-foreground shrink-0" />
                    </div>
                </div>

                <!-- Chat Area -->
                <div class="flex flex-1 flex-col min-h-0 min-w-0 overflow-hidden">
                    <!-- Main Messages Area -->
                    <div class="flex-1 min-h-0 min-w-0 overflow-hidden flex flex-col">
                        <ScrollArea class="flex-1 min-h-0 w-full p-2 sm:p-6">
                            <div class="space-y-2 sm:space-y-4 min-w-0">
                                <!-- Messages -->
                                <div
                                    v-for="message in messages"
                                    :key="message.id"
                                    :data-message-id="message.id"
                                    :class="[
                                        message.type === 'system'
                                            ? 'flex justify-center min-w-0'
                                            : message.isCurrentUser
                                            ? 'flex gap-1.5 sm:gap-3 justify-end min-w-0'
                                            : 'flex gap-1.5 sm:gap-3 min-w-0'
                                    ]"
                                >
                                    <!-- System Message -->
                                    <div
                                        v-if="message.type === 'system'"
                                        class="rounded-full bg-muted px-4 py-1.5 text-xs text-muted-foreground"
                                    >
                                        {{ message.text }}
                                    </div>

                                    <!-- Current User Message (Right Side) -->
                                    <template v-else-if="message.isCurrentUser">
                                        <div class="flex items-start gap-1.5 sm:gap-3 justify-end min-w-0 w-full">
                                            <div class="flex flex-col items-end gap-1.5 sm:gap-2 min-w-0 max-w-[85%] sm:max-w-[75%]">
                                                <div class="flex items-center gap-1.5 sm:gap-2 text-[9px] sm:text-xs text-muted-foreground">
                                                    <Badge
                                                        v-if="message.isPinned"
                                                        variant="secondary"
                                                        class="text-[8px] sm:text-[10px] px-1 py-0"
                                                    >
                                                        Pinned
                                                    </Badge>
                                                    <TooltipProvider v-if="message.isEdited">
                                                        <Tooltip>
                                                            <TooltipTrigger as-child>
                                                                <Badge
                                                                    variant="outline"
                                                                    class="text-[8px] sm:text-[10px] px-1 py-0 border-blue-500/50 text-blue-600 dark:text-blue-400 dark:border-blue-400/50 cursor-help"
                                                                >
                                                                    Edited
                                                                </Badge>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                <p>Edited by {{ message.editedBy || 'Unknown' }}</p>
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    </TooltipProvider>
                                                    <span>{{ message.timestamp }}</span>
                                                    <span class="text-[10px] sm:text-sm font-semibold text-foreground">{{ message.user }}</span>
                                                </div>
                                                <div class="flex items-center gap-1.5 sm:gap-2 w-full">
                                                    <DropdownMenu v-if="isCurrentUserAdmin">
                                                        <DropdownMenuTrigger as-child>
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                class="h-7 w-7 sm:h-8 sm:w-8 shrink-0 opacity-60 hover:opacity-100"
                                                            >
                                                                <EllipsisVertical class="size-4" />
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end">
                                                            <template v-if="message.isDeleted">
                                                                <DropdownMenuItem @click="restoreMessage(message)">
                                                                    <RefreshCw class="mr-2 size-4" />
                                                                    Restore
                                                                </DropdownMenuItem>
                                                            </template>
                                                            <template v-else>
                                                                <DropdownMenuItem @click="togglePinMessage(message)">
                                                                    <PinOff v-if="message.isPinned" class="mr-2 size-4" />
                                                                    <Pin v-else class="mr-2 size-4" />
                                                                    {{ message.isPinned ? 'Unpin' : 'Pin' }}
                                                                </DropdownMenuItem>
                                                                <DropdownMenuItem @click="startEditMessage(message)">
                                                                    <Pencil class="mr-2 size-4" />
                                                                    Edit
                                                                </DropdownMenuItem>
                                                                <DropdownMenuItem @click="openDeleteMessageDialog(message)" class="text-destructive focus:text-destructive">
                                                                    <Trash2 class="mr-2 size-4" />
                                                                    Delete
                                                                </DropdownMenuItem>
                                                            </template>
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                    <div :class="[
                                                        message.isDeleted 
                                                            ? 'rounded-xl border border-border/50 bg-muted/30 dark:bg-muted/20 px-2.5 py-2 sm:px-4 sm:py-3 shadow-sm flex flex-col gap-2 sm:gap-3 text-muted-foreground/70 min-w-0'
                                                            : 'rounded-xl border border-primary/30 dark:border-primary/40 bg-primary/15 dark:bg-primary/20 px-2.5 py-2 sm:px-4 sm:py-3 shadow-sm flex flex-col gap-2 sm:gap-3 text-foreground min-w-0',
                                                        editingMessage?.id === message.id ? 'ring-2 ring-primary ring-offset-2' : ''
                                                    ]">
                                                    <div :class="[
                                                        'text-[11px] sm:text-sm break-words',
                                                        message.isDeleted ? 'italic' : ''
                                                    ]" v-html="formatMessageText(message.text)"></div>
                                                    <div
                                                        v-if="message.hasAttachment && !message.isDeleted"
                                                        class="flex flex-wrap gap-2"
                                                    >
                                                        <a
                                                            v-for="attachment in (Array.isArray((message as any).attachments) ? (message as any).attachments : [])"
                                                            :key="attachment.id || attachment.name"
                                                            :href="attachment.downloadUrl || `/message-attachments/${attachment.id}/download`"
                                                            class="flex items-center gap-1.5 sm:gap-2 rounded-lg border border-primary/30 dark:border-primary/40 bg-primary/25 dark:bg-primary/30 px-2 py-1.5 sm:px-3 sm:py-2 w-full sm:w-auto max-w-full text-foreground hover:bg-primary/30 dark:hover:bg-primary/35 transition cursor-pointer"
                                                            @click.prevent="downloadAttachment(attachment.downloadUrl || `/message-attachments/${attachment.id}/download`)"
                                                        >
                                                            <FileText class="size-3 sm:size-4 text-foreground/70 shrink-0" />
                                                            <div class="flex flex-col min-w-0">
                                                                <span class="text-[10px] sm:text-xs font-medium truncate max-w-[200px] text-foreground">{{ attachment.name ?? 'Attachment' }}</span>
                                                                <span v-if="attachment.size" class="text-[9px] sm:text-[10px] text-foreground/60">{{ attachment.size }}</span>
                                                    </div>
                                                        </a>
                                                </div>
                                            </div>
                                        </div>
                                            </div>
                                            <Avatar class="size-7 sm:size-10 shrink-0 self-start">
                                            <AvatarImage
                                                :src="message.avatar || ''"
                                                :alt="message.user"
                                                    class="object-cover"
                                            />
                                            <AvatarFallback>
                                                {{ getInitials(message.user) }}
                                            </AvatarFallback>
                                        </Avatar>
                                        </div>
                                    </template>

                                    <!-- Other User Message (Left Side) -->
                                    <template v-else>
                                        <div class="flex gap-1.5 sm:gap-3 items-start min-w-0 w-full">
                                            <Avatar class="size-7 sm:size-10 shrink-0 self-start">
                                            <AvatarImage
                                                :src="message.avatar || ''"
                                                :alt="message.user || 'User'"
                                                    class="object-cover"
                                            />
                                            <AvatarFallback>
                                                {{ getInitials(message.user || 'U') }}
                                            </AvatarFallback>
                                        </Avatar>
                                            <div class="flex flex-col gap-1.5 sm:gap-2 min-w-0 max-w-[85%] sm:max-w-[75%]">
                                                <div class="flex items-center gap-1.5 sm:gap-2 text-[9px] sm:text-xs text-muted-foreground">
                                                    <span class="text-[10px] sm:text-sm font-semibold text-foreground">{{ message.user || 'User' }}</span>
                                                    <span>{{ message.timestamp }}</span>
                                                <TooltipProvider v-if="message.isEdited">
                                                    <Tooltip>
                                                        <TooltipTrigger as-child>
                                                            <Badge
                                                                variant="outline"
                                                                class="text-[8px] sm:text-[10px] px-1 py-0 border-blue-500/50 text-blue-600 dark:text-blue-400 dark:border-blue-400/50 cursor-help"
                                                            >
                                                                Edited
                                                            </Badge>
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            <p>Edited by {{ message.editedBy || 'Unknown' }}</p>
                                                        </TooltipContent>
                                                    </Tooltip>
                                                </TooltipProvider>
                                                <Badge
                                                    v-if="message.isPinned"
                                                    variant="secondary"
                                                        class="text-[8px] sm:text-[10px] px-1 py-0"
                                                >
                                                    Pinned
                                                </Badge>
                                            </div>
                                                <div class="flex items-center gap-1.5 sm:gap-2 w-full">
                                                    <div :class="[
                                                        message.isDeleted 
                                                            ? 'rounded-xl border border-border/50 bg-muted/30 dark:bg-muted/20 px-2.5 py-2 sm:px-4 sm:py-3 shadow-sm flex flex-col gap-2 sm:gap-3 text-muted-foreground/70 min-w-0'
                                                            : 'rounded-xl border border-border bg-muted/50 dark:bg-muted/30 px-2.5 py-2 sm:px-4 sm:py-3 shadow-sm flex flex-col gap-2 sm:gap-3 min-w-0',
                                                        editingMessage?.id === message.id ? 'ring-2 ring-primary ring-offset-2' : ''
                                                    ]">
                                                    <div :class="[
                                                        'text-[11px] sm:text-sm break-words',
                                                        message.isDeleted ? 'italic text-muted-foreground/70' : 'text-foreground'
                                                    ]" v-html="formatMessageText(message.text)"></div>
                                                <div
                                                    v-if="message.hasAttachment && !message.isDeleted"
                                                        class="flex flex-wrap gap-2"
                                                    >
                                                        <a
                                                            v-for="attachment in (Array.isArray((message as any).attachments) ? (message as any).attachments : [])"
                                                            :key="attachment.id || attachment.name"
                                                            :href="attachment.downloadUrl || `/message-attachments/${attachment.id}/download`"
                                                            class="flex items-center gap-1.5 sm:gap-2 rounded-lg border border-border bg-muted/60 dark:bg-muted/40 px-2 py-1.5 sm:px-3 sm:py-2 w-full sm:w-auto max-w-full text-foreground hover:bg-muted/70 dark:hover:bg-muted/50 transition cursor-pointer"
                                                            @click.prevent="downloadAttachment(attachment.downloadUrl || `/message-attachments/${attachment.id}/download`)"
                                                        >
                                                            <FileText class="size-3 sm:size-4 text-foreground/70 shrink-0" />
                                                            <div class="flex flex-col min-w-0">
                                                                <span class="text-[10px] sm:text-xs font-medium truncate max-w-[200px] text-foreground">{{ attachment.name ?? 'Attachment' }}</span>
                                                                <span v-if="attachment.size" class="text-[9px] sm:text-[10px] text-foreground/60">{{ attachment.size }}</span>
                                                </div>
                                                        </a>
                                            </div>
                                        </div>
                                                    <DropdownMenu v-if="isCurrentUserAdmin">
                                                        <DropdownMenuTrigger as-child>
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                class="h-7 w-7 sm:h-8 sm:w-8 shrink-0 opacity-60 hover:opacity-100"
                                                            >
                                                                <EllipsisVertical class="size-4" />
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="start">
                                                            <template v-if="message.isDeleted">
                                                                <DropdownMenuItem @click="restoreMessage(message)">
                                                                    <RefreshCw class="mr-2 size-4" />
                                                                    Restore
                                                                </DropdownMenuItem>
                                                            </template>
                                                            <template v-else>
                                                                <DropdownMenuItem @click="togglePinMessage(message)">
                                                                    <PinOff v-if="message.isPinned" class="mr-2 size-4" />
                                                                    <Pin v-else class="mr-2 size-4" />
                                                                    {{ message.isPinned ? 'Unpin' : 'Pin' }}
                                                                </DropdownMenuItem>
                                                                <DropdownMenuItem @click="startEditMessage(message)">
                                                                    <Pencil class="mr-2 size-4" />
                                                                    Edit
                                                                </DropdownMenuItem>
                                                                <DropdownMenuItem class="text-destructive focus:text-destructive" @click="openDeleteMessageDialog(message)">
                                                                    <Trash2 class="mr-2 size-4" />
                                                                    Delete
                                                                </DropdownMenuItem>
                                                            </template>
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                                <!-- Scroll to bottom anchor -->
                                <div ref="messagesEndRef"></div>
                            </div>
                        </ScrollArea>
                    </div>

                    <!-- Fixed Input Area (only visible for admins) -->
                    <div v-if="isCurrentUserAdmin" class="shrink-0 border-t bg-background p-2 sm:p-4 min-w-0 overflow-hidden">
                            <!-- Selected Files Preview -->
                            <div
                                v-if="selectedFiles.length > 0"
                                class="mb-2 flex flex-wrap gap-2"
                            >
                                <div
                                    v-for="(file, index) in selectedFiles"
                                    :key="index"
                                    class="flex items-center gap-2 rounded-lg border border-border bg-muted/50 px-3 py-1.5 text-sm"
                                >
                                    <FileText class="size-4 text-muted-foreground shrink-0" />
                                    <span class="truncate max-w-[200px]">{{ file.name }}</span>
                                    <button
                                        type="button"
                                        @click="removeFile(index)"
                                        class="ml-1 text-muted-foreground hover:text-destructive transition-colors"
                                    >
                                        <X class="size-4" />
                                    </button>
                                </div>
                            </div>
                            <div
                                class="flex w-full flex-col rounded-md border bg-background shadow-sm focus-within:mb-1 focus-within:border-b-4 focus-within:border-b-primary min-w-0"
                            >
                                <textarea
                                    ref="textareaRef"
                                    v-model="messageText"
                                    class="max-h-60 min-h-16 w-full resize-none rounded-md bg-transparent px-3 py-2.5 text-base outline-none focus-visible:ring-0 md:text-sm"
                                    :placeholder="isEditing ? 'Edit your message...' : 'Type a message...'"
                                    rows="1"
                                    @input="autoResize"
                                    @keydown="handleKeyDown"
                                />
                                <div
                                    class="flex items-center justify-between border-t px-2 py-1"
                                >
                                    <!-- Left formatting buttons -->
                                    <div class="flex items-center gap-1">
                                        <InputGroupButton
                                            variant="ghost"
                                            size="xs"
                                            class="h-8 w-8"
                                            @click="applyFormat('bold')"
                                            :title="'Bold (Ctrl+Shift+B)'"
                                        >
                                            <Bold class="size-5" />
                                        </InputGroupButton>
                                        <InputGroupButton
                                            variant="ghost"
                                            size="xs"
                                            class="h-8 w-8"
                                            @click="applyFormat('italic')"
                                            :title="'Italic (Ctrl+I)'"
                                        >
                                            <Italic class="size-5" />
                                        </InputGroupButton>
                                        <InputGroupButton
                                            variant="ghost"
                                            size="xs"
                                            class="h-8 w-8"
                                            @click="applyFormat('underline')"
                                            :title="'Underline (Ctrl+U)'"
                                        >
                                            <Underline class="size-5" />
                                        </InputGroupButton>
                                        <InputGroupButton
                                            variant="ghost"
                                            size="xs"
                                            class="h-8 w-8"
                                            @click="applyFormat('monospace')"
                                            :title="'Code (Ctrl+`)'"
                                        >
                                            <CodeXml class="size-5" />
                                        </InputGroupButton>
                                        <InputGroupButton
                                            variant="ghost"
                                            size="xs"
                                            class="h-8 w-8"
                                            @click="applyFormat('strike')"
                                            :title="'Strikethrough (Ctrl+Shift+S)'"
                                        >
                                            <Strikethrough class="size-5" />
                                        </InputGroupButton>
                                    </div>

                                    <!-- Right side: Attach + Send/Cancel -->
                                    <div class="flex items-center gap-1 sm:gap-2 shrink-0">
                                        <input
                                            ref="fileInputRef"
                                            type="file"
                                            multiple
                                            accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx"
                                            class="hidden"
                                            @change="handleFileSelect"
                                        />
                                        <TooltipProvider v-if="!isEditing">
                                            <Tooltip>
                                                <TooltipTrigger as-child>
                                        <InputGroupButton
                                            variant="ghost"
                                            size="xs"
                                            class="h-10 w-10 text-muted-foreground hover:text-foreground"
                                                        @click="triggerFileInput"
                                        >
                                            <Paperclip class="size-6" />
                                        </InputGroupButton>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p class="text-xs">Upload files (Max 10MB)</p>
                                                    <p class="text-xs text-muted-foreground">PDF, DOC, DOCX, PPT, PPTX, XLS, XLSX</p>
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                        <Separator
                                            v-if="!isEditing"
                                            orientation="vertical"
                                            class="!h-4"
                                        />
                                        <InputGroupButton
                                            v-if="isEditing"
                                            size="sm"
                                            variant="ghost"
                                            class="h-10 w-10 rounded-sm"
                                            @click="cancelEdit"
                                        >
                                            <X class="size-6" />
                                        </InputGroupButton>
                                        <InputGroupButton
                                            size="sm"
                                            variant="ghost"
                                            class="h-10 w-10 rounded-sm"
                                            :disabled="isSendDisabled"
                                            @click="handleSendOrSave"
                                        >
                                            <SendHorizontal v-if="!isEditing" class="size-6" />
                                            <Pencil v-else class="size-6" />
                                        </InputGroupButton>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Members Dialog -->
        <Dialog :open="showMembersDialog" @update:open="showMembersDialog = $event">
            <DialogContent class="max-w-lg max-h-[90vh] overflow-hidden">
                <DialogHeader>
                    <DialogTitle>Members ({{ admins.length + members.length }})</DialogTitle>
                </DialogHeader>
                <div class="flex flex-col gap-4 max-h-[calc(90vh-120px)] overflow-hidden">
                    <div class="relative shrink-0">
                        <span
                            class="absolute inset-y-0 left-0 flex items-center pl-3"
                        >
                            <Search class="size-4 text-muted-foreground" />
                        </span>
                        <Input
                            v-model="memberSearch"
                            type="text"
                            placeholder="Search members..."
                            class="w-full pl-9"
                        />
                    </div>

                    <ScrollArea class="flex-1 min-h-0 pr-4">
                        <div class="space-y-6">
                            <!-- Admins Section -->
                            <div v-if="filteredAdmins.length > 0">
                                <h4
                                    class="mb-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground"
                                >
                                    Admins ({{ filteredAdmins.length }})
                                </h4>
                                <div class="space-y-2">
                                    <div
                                        v-for="admin in filteredAdmins"
                                        :key="admin.id"
                                        class="group flex items-center gap-3 rounded-lg border p-3 transition-colors hover:bg-accent"
                                    >
                                        <Avatar class="size-10">
                                            <AvatarImage
                                                :src="admin.avatar"
                                                :alt="admin.name"
                                                class="object-cover"
                                            />
                                            <AvatarFallback>
                                                {{ getInitials(admin.name) }}
                                            </AvatarFallback>
                                        </Avatar>
                                        <div class="flex flex-1 flex-col gap-1 overflow-hidden">
                                            <div class="flex flex-wrap items-center gap-1">
                                                <p class="flex items-center gap-1 text-sm font-medium truncate">
                                                    <span class="truncate">{{ admin.name }}</span>
                                                    <span class="text-xs text-muted-foreground max-w-[160px] truncate">| {{ admin.position }}</span>
                                                </p>
                                                <Badge
                                                    variant="secondary"
                                                    class="bg-muted/60 text-[10px] font-medium uppercase tracking-wide text-muted-foreground"
                                                >
                                                    {{ admin.departmentCode ?? admin.department }}
                                                </Badge>
                                            </div>
                                            <p class="truncate text-xs text-muted-foreground">
                                                {{ admin.email }}
                                            </p>
                                        </div>
                                        <DropdownMenu v-if="isCurrentUserAdmin && admin.id !== props.currentUser?.id">
                                            <DropdownMenuTrigger as-child>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    class="h-8 w-8 shrink-0"
                                                >
                                                    <EllipsisVertical class="size-6" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem
                                                    @click="removeAdminStatus(admin)"
                                                >
                                                    <Users class="size-4" />
                                                    Remove Admin
                                                </DropdownMenuItem>
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem
                                                    class="text-destructive focus:text-destructive"
                                                    @click="openRemoveConfirm(admin)"
                                                >
                                                    <Trash2 class="size-4" />
                                                    Remove Member
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                </div>
                            </div>

                            <!-- Members Section -->
                            <div v-if="filteredMembers.length > 0">
                                <h4
                                    class="mb-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground"
                                >
                                    Members ({{ filteredMembers.length }})
                                </h4>
                                <div class="space-y-2">
                                    <div
                                        v-for="member in filteredMembers"
                                        :key="member.id"
                                        class="group flex items-center gap-3 rounded-lg border p-3 transition-colors hover:bg-accent"
                                    >
                                        <Avatar class="size-10">
                                            <AvatarImage
                                                :src="member.avatar"
                                                :alt="member.name"
                                                class="object-cover"
                                            />
                                            <AvatarFallback>
                                                {{ getInitials(member.name) }}
                                            </AvatarFallback>
                                        </Avatar>
                                        <div class="flex flex-1 flex-col gap-1 overflow-hidden">
                                            <div class="flex flex-wrap items-center gap-1 text-sm font-medium">
                                                <span class="truncate">{{ member.name }}</span>
                                                <span class="text-xs text-muted-foreground max-w-[160px] truncate">| {{ member.position }}</span>
                                                <Badge
                                                    variant="secondary"
                                                    class="shrink-0 bg-muted/60 text-[10px] font-medium uppercase tracking-wide text-muted-foreground"
                                                >
                                                    {{ member.departmentCode ?? member.department }}
                                                </Badge>
                                            </div>
                                            <p class="truncate text-xs text-muted-foreground">
                                                {{ member.email }}
                                            </p>
                                        </div>
                                        <DropdownMenu v-if="isCurrentUserAdmin && member.id !== props.currentUser?.id">
                                            <DropdownMenuTrigger as-child>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    class="h-8 w-8 shrink-0"
                                                >
                                                    <EllipsisVertical class="size-6" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem
                                                    @click="setAsAdmin(member)"
                                                >
                                                    <Users class="mr-2 size-4" />
                                                    Set as Admin
                                                </DropdownMenuItem>
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem
                                                    class="text-destructive focus:text-destructive"
                                                    @click="openRemoveConfirm(member)"
                                                >
                                                    <Trash2 class="mr-2 size-4" />
                                                    Remove Member
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                </div>
                            </div>

                            <!-- Empty State -->
                            <div
                                v-if="filteredAdmins.length === 0 && filteredMembers.length === 0"
                                class="flex flex-col items-center justify-center py-12 text-center"
                            >
                                <Users class="mb-3 size-10 text-muted-foreground" />
                                <p class="text-sm font-medium text-muted-foreground">
                                    No members found
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Try adjusting your search terms
                                </p>
                            </div>
                        </div>
                    </ScrollArea>

                    <div v-if="isCurrentUserAdmin" class="flex justify-end border-t pt-4">
                        <Button
                            variant="default"
                            size="sm"
                            class="h-9"
                            @click="openAddMemberDialog"
                        >
                            <CirclePlus class="size-4" />
                            Add Member
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>

        <!-- Pinned Dialog -->
        <Dialog :open="showPinnedDialog" @update:open="showPinnedDialog = $event">
            <DialogContent class="max-w-2xl max-h-[90vh] overflow-hidden">
                <DialogHeader>
                    <DialogTitle>Pinned Messages</DialogTitle>
                    <DialogDescription>
                        {{ selectedChat?.name || 'This chat' }} · {{ pinnedMessages.length }} pinned
                    </DialogDescription>
                </DialogHeader>
                <ScrollArea class="flex-1 min-h-0 max-h-[calc(90vh-120px)]">
                    <template v-if="pinnedMessages.length > 0">
                        <div class="pr-4">
                        <div
                                v-for="(pinned, index) in pinnedMessages"
                            :key="pinned.id"
                                class="p-3 cursor-pointer hover:bg-accent transition-colors"
                                :class="{ 'border-t': index > 0 }"
                                @click="scrollToMessage(pinned.id)"
                        >
                                <div class="flex items-start gap-3">
                                    <Avatar class="size-8 shrink-0">
                                        <AvatarImage
                                            :src="pinned.avatar || ''"
                                            :alt="pinned.user"
                                            class="object-cover"
                                        />
                                        <AvatarFallback>
                                            {{ getInitials(pinned.user) }}
                                        </AvatarFallback>
                                    </Avatar>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 mb-1">
                                            <span class="text-sm font-medium">{{ pinned.user }}</span>
                                            <span class="text-xs text-muted-foreground">{{ pinned.timestamp || pinned.time }}</span>
                                            <Badge
                                                variant="secondary"
                                                class="text-[10px] px-1 py-0"
                                            >
                                                Pinned
                                            </Badge>
                                </div>
                                        <p 
                                            class="text-sm text-foreground break-words" 
                                            v-html="formatMessageText(pinned.text)"
                                        ></p>
                                        <div
                                            v-if="pinned.hasAttachment"
                                            class="mt-2 flex flex-wrap gap-2"
                                        >
                                            <div
                                                v-for="attachment in (Array.isArray(pinned.attachments) ? pinned.attachments : [])"
                                                :key="attachment.name ?? attachment?.id ?? attachment"
                                                class="flex items-center gap-2 rounded-lg border border-border bg-muted/50 px-2 py-1 text-xs"
                                >
                                                <FileText class="size-3 text-muted-foreground" />
                                                <span class="truncate max-w-[200px]">{{ (typeof attachment === 'string' ? attachment : attachment?.name) ?? 'Attachment' }}</span>
                            </div>
                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                        <div
                        v-else
                            class="py-8 text-center text-sm text-muted-foreground"
                        >
                            No pinned messages
                    </div>
                </ScrollArea>
            </DialogContent>
        </Dialog>

        <!-- Search Dialog -->
        <Dialog :open="showSearchDialog" @update:open="(value) => { showSearchDialog = value; if (!value) messageSearchQuery = ''; }">
            <DialogContent class="max-w-2xl max-h-[90vh] overflow-hidden">
                <DialogHeader>
                    <DialogTitle>Search Messages</DialogTitle>
                    <DialogDescription>
                        Search through messages in {{ selectedChat?.name || 'this chat' }}
                    </DialogDescription>
                </DialogHeader>
                <div class="flex flex-col gap-4 max-h-[calc(90vh-120px)] overflow-hidden">
                    <div v-if="!selectedChat" class="py-8 text-center text-sm text-muted-foreground">
                        Please select a chat to search messages
                    </div>
                    <template v-else>
                        <div class="relative shrink-0">
                        <span
                            class="absolute inset-y-0 left-0 flex items-center pl-2"
                        >
                            <Search class="size-4 text-muted-foreground" />
                        </span>
                        <Input
                                v-model="messageSearchQuery"
                            type="text"
                            placeholder="Search in conversation..."
                            class="w-full pl-8"
                                autofocus
                        />
                    </div>
                        
                        <ScrollArea class="flex-1 min-h-0">
                            <template v-if="filteredSearchMessages.length > 0">
                                <div class="space-y-2 pr-4">
                                    <div
                                        v-for="message in filteredSearchMessages"
                                        :key="message.id"
                                        class="rounded-md border p-3 cursor-pointer hover:bg-accent transition-colors"
                                        @click="scrollToMessage(message.id)"
                                    >
                                        <div class="flex items-start gap-3">
                                            <Avatar class="size-8 shrink-0">
                                                <AvatarImage
                                                    :src="message.avatar || ''"
                                                    :alt="message.user"
                                                    class="object-cover"
                                                />
                                                <AvatarFallback>
                                                    {{ getInitials(message.user) }}
                                                </AvatarFallback>
                                            </Avatar>
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-2 mb-1">
                                                    <span class="text-sm font-medium">{{ message.user }}</span>
                                                    <span class="text-xs text-muted-foreground">{{ message.timestamp }}</span>
                                                    <Badge
                                                        v-if="message.isPinned"
                                                        variant="secondary"
                                                        class="text-[10px] px-1 py-0"
                                                    >
                                                        Pinned
                                                    </Badge>
                    </div>
                                                <p 
                                                    class="text-sm text-foreground break-words" 
                                                    v-html="highlightSearchTerm(message.text, messageSearchQuery)"
                                                ></p>
                                                <div
                                                    v-if="message.hasAttachment"
                                                    class="mt-2 flex flex-wrap gap-2"
                                                >
                                                    <div
                                                        v-for="attachment in (Array.isArray((message as any).attachments) ? (message as any).attachments : [{ name: message.attachmentName }])"
                                                        :key="attachment.name ?? attachment"
                                                        class="flex items-center gap-2 rounded-lg border border-border bg-muted/50 px-2 py-1 text-xs"
                                                    >
                                                        <FileText class="size-3 text-muted-foreground" />
                                                        <span class="truncate max-w-[200px]">{{ attachment.name ?? 'Attachment' }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <div
                                v-else-if="messageSearchQuery.trim()"
                                class="py-8 text-center text-sm text-muted-foreground"
                            >
                                No messages found matching "{{ messageSearchQuery }}"
                            </div>
                            <div
                                v-else
                                class="py-8 text-center text-sm text-muted-foreground"
                            >
                                Start typing to search messages...
                            </div>
                        </ScrollArea>
                    </template>
                </div>
            </DialogContent>
        </Dialog>

        <!-- Attachments Dialog -->
        <Dialog :open="showAttachmentsDialog" @update:open="showAttachmentsDialog = $event">
            <DialogContent class="max-w-md max-h-[90vh] overflow-hidden">
                <DialogHeader>
                    <DialogTitle>Attachments</DialogTitle>
                </DialogHeader>
                <ScrollArea class="flex-1 min-h-0 max-h-[calc(90vh-120px)]">
                    <div class="space-y-2">
                        <div
                            v-for="attachment in attachments"
                            :key="attachment.id"
                            class="flex items-center justify-between rounded-md border p-3"
                        >
                            <div class="flex items-center gap-3">
                                <div class="flex size-10 items-center justify-center rounded-md bg-muted">
                                    <Paperclip class="size-5 text-muted-foreground" />
                                </div>
                                <div>
                                    <p class="text-sm font-medium">{{ attachment.name }}</p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ attachment.size }}
                                    </p>
                                </div>
                            </div>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="h-8 w-8 shrink-0"
                                @click="downloadAttachment(attachment.downloadUrl)"
                            >
                                <Download class="size-4" />
                            </Button>
                        </div>
                        <div
                            v-if="attachments.length === 0"
                            class="py-8 text-center text-sm text-muted-foreground"
                        >
                            No attachments
                        </div>
                    </div>
                </ScrollArea>
            </DialogContent>
        </Dialog>

        <!-- Add Member Dialog -->
        <Dialog :open="showAddMemberDialog" @update:open="showAddMemberDialog = $event">
            <DialogContent class="sm:max-w-lg w-[95vw] max-h-[90vh] overflow-hidden">
                <DialogHeader>
                    <DialogTitle>Add Members</DialogTitle>
                    <DialogDescription>
                        Search for people to add to this group chat
                    </DialogDescription>
                </DialogHeader>

                <div class="flex flex-col gap-4 max-h-[65vh] overflow-hidden">
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <Label class="text-sm font-medium">Selected Members</Label>
                            <Button
                                v-if="selectedMembersToAdd.length"
                                variant="ghost"
                                size="sm"
                                class="text-xs text-muted-foreground"
                                @click="addMemberSelectedIds = []"
                            >
                                Clear
                            </Button>
                        </div>
                        <div class="min-h-[44px] rounded-md border bg-muted/30">
                            <template v-if="selectedMembersToAdd.length">
                                <ScrollArea class="h-[70px] w-full px-2 py-2">
                                    <div class="flex flex-wrap gap-2 pr-4">
                                        <div
                                            v-for="member in selectedMembersToAdd"
                                            :key="member.id"
                                            class="flex items-center gap-1 rounded-full border bg-background pl-2.5 px-2 py-1 text-xs"
                                        >
                                            <span class="truncate max-w-[140px] font-medium">{{ member.name }}</span>
                                            <button
                                                type="button"
                                                class="text-muted-foreground hover:text-foreground"
                                                @click="toggleAddMemberSelection(member.id)"
                                            >
                                                <X class="size-3.5" />
                                            </button>
                                        </div>
                                    </div>
                                </ScrollArea>
                            </template>
                            <div v-else class="p-2 min-h-[44px] flex items-center text-xs text-muted-foreground">
                                Select members to add them here
                            </div>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label class="text-sm font-medium">Add Members</Label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                            <Search class="size-4 text-muted-foreground" />
                        </span>
                        <Input
                            v-model="addMemberSearch"
                            type="text"
                                placeholder="Search by name, email, or department"
                                class="pl-9"
                        />
                        </div>
                    </div>

                    <ScrollArea class="flex-1 min-h-[100px] max-h-[300px] pr-3">
                        <div v-if="filteredPotentialMembers.length > 0" class="space-y-2">
                            <label
                                v-for="user in filteredPotentialMembers"
                                :key="user.id"
                                class="flex items-center gap-3 rounded-lg border p-3 transition hover:bg-muted/40 cursor-pointer"
                            >
                                <input
                                    type="checkbox"
                                    class="h-4 w-4 accent-primary"
                                    :checked="addMemberSelectedIds.includes(user.id)"
                                    @change="toggleAddMemberSelection(user.id)"
                                />
                                <Avatar class="size-10">
                                    <AvatarImage
                                        :src="user.avatar"
                                        :alt="user.name"
                                        class="object-cover"
                                    />
                                    <AvatarFallback>
                                        {{ getInitials(user.name) }}
                                    </AvatarFallback>
                                </Avatar>
                                <div class="flex flex-1 flex-col gap-1 overflow-hidden">
                                    <div class="flex items-center gap-2">
                                        <p class="text-sm font-medium truncate">{{ user.name }}</p>
                                        <Badge variant="secondary" class="text-[10px] uppercase">
                                            {{ user.departmentCode ?? user.department }}
                                        </Badge>
                                    </div>
                                    <p class="text-xs text-muted-foreground truncate">
                                        {{ user.position }} · {{ user.email }}
                                    </p>
                                </div>
                            </label>
                        </div>
                        <div
                            v-else
                            class="flex flex-col items-center justify-center py-12 text-center text-sm text-muted-foreground"
                        >
                            <Users class="mb-3 size-10" />
                            <p>
                                {{
                                    addMemberSearch
                                        ? 'No members match your search'
                                        : 'Start typing to search for members'
                                }}
                            </p>
                        </div>
                    </ScrollArea>
                </div>

                <DialogFooter class="justify-end">
                    <Button
                        variant="outline"
                        @click="
                            showAddMemberDialog = false;
                            addMemberSelectedIds = [];
                            addMemberSearch = '';
                        "
                    >
                        Cancel
                    </Button>
                    <Button
                        :disabled="selectedMembersToAdd.length === 0"
                        @click="addMembers"
                    >
                        Add Members ({{ selectedMembersToAdd.length }})
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Leave Chat Confirmation Dialog -->
        <AlertDialog
            :open="showLeaveConfirmDialog"
            @update:open="showLeaveConfirmDialog = $event"
        >
            <AlertDialogContent class="max-w-md !duration-0 data-[state=open]:!animate-none data-[state=closed]:!animate-none data-[state=open]:!fade-in-0 data-[state=closed]:!fade-out-0 data-[state=open]:!zoom-in-100 data-[state=closed]:!zoom-out-100 data-[state=open]:!translate-x-[-50%] data-[state=open]:!translate-y-[-50%] data-[state=closed]:!translate-x-[-50%] data-[state=closed]:!translate-y-[-50%]">
                <AlertDialogHeader>
                    <AlertDialogTitle>Leave Chat</AlertDialogTitle>
                    <AlertDialogDescription>
                        Are you sure you want to leave
                        <strong>{{ chatToLeave?.name }}</strong>? This action cannot be undone.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter class="gap-2 sm:gap-0">
                    <AlertDialogCancel
                        @click="cancelLeaveChat"
                        :disabled="isLoading.leaveChat"
                    >
                        Cancel
                    </AlertDialogCancel>
                    <AlertDialogAction
                        class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        :disabled="isLoading.leaveChat || leaveCountdown > 0"
                        @click="confirmLeaveChat"
                    >
                        <LogOut class="mr-2 size-4" />
                        {{ isLoading.leaveChat ? 'Leaving...' : (leaveCountdown > 0 ? `Leave (${leaveCountdown}s)` : 'Leave Chat') }}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>

        <!-- Leave Chat Error Dialog -->
        <AlertDialog
            :open="showLeaveErrorDialog"
            @update:open="showLeaveErrorDialog = $event"
        >
            <AlertDialogContent class="max-w-md !duration-0 data-[state=open]:!animate-none data-[state=closed]:!animate-none data-[state=open]:!fade-in-0 data-[state=closed]:!fade-out-0 data-[state=open]:!zoom-in-100 data-[state=closed]:!zoom-out-100 data-[state=open]:!translate-x-[-50%] data-[state=open]:!translate-y-[-50%] data-[state=closed]:!translate-x-[-50%] data-[state=closed]:!translate-y-[-50%]">
                <AlertDialogHeader>
                    <AlertDialogTitle>Cannot Leave Chat</AlertDialogTitle>
                    <AlertDialogDescription>
                        {{ leaveErrorMessage }}
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter class="gap-2 sm:gap-0">
                    <AlertDialogAction
                        @click="closeLeaveErrorDialog"
                    >
                        OK
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>

        <!-- Delete Message Confirmation Dialog -->
        <AlertDialog
            :open="showDeleteMessageDialog"
            @update:open="showDeleteMessageDialog = $event"
        >
            <AlertDialogContent class="max-w-md !duration-0 data-[state=open]:!animate-none data-[state=closed]:!animate-none data-[state=open]:!fade-in-0 data-[state=closed]:!fade-out-0 data-[state=open]:!zoom-in-100 data-[state=closed]:!zoom-out-100 data-[state=open]:!translate-x-[-50%] data-[state=open]:!translate-y-[-50%] data-[state=closed]:!translate-x-[-50%] data-[state=closed]:!translate-y-[-50%]">
                <AlertDialogHeader>
                    <AlertDialogTitle>Delete Message</AlertDialogTitle>
                    <AlertDialogDescription>
                        Are you sure you want to delete this message? This action cannot be undone, but you can restore it later.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter class="gap-2 sm:gap-0">
                    <AlertDialogCancel
                        @click="cancelDeleteMessage"
                        :disabled="isLoading.deleteMessage"
                    >
                        Cancel
                    </AlertDialogCancel>
                    <AlertDialogAction
                        class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        :disabled="isLoading.deleteMessage || deleteMessageCountdown > 0"
                        @click="deleteMessage"
                    >
                        <Trash2 class="mr-2 size-4" />
                        {{ isLoading.deleteMessage ? 'Deleting...' : (deleteMessageCountdown > 0 ? `Delete (${deleteMessageCountdown}s)` : 'Delete') }}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>

        <!-- Remove Member Confirmation Dialog -->
        <AlertDialog
            :open="showRemoveConfirmDialog"
            @update:open="showRemoveConfirmDialog = $event"
        >
            <AlertDialogContent class="max-w-md !duration-0 data-[state=open]:!animate-none data-[state=closed]:!animate-none data-[state=open]:!fade-in-0 data-[state=closed]:!fade-out-0 data-[state=open]:!zoom-in-100 data-[state=closed]:!zoom-out-100 data-[state=open]:!translate-x-[-50%] data-[state=open]:!translate-y-[-50%] data-[state=closed]:!translate-x-[-50%] data-[state=closed]:!translate-y-[-50%]">
                <AlertDialogHeader>
                    <AlertDialogTitle>Remove Member</AlertDialogTitle>
                    <AlertDialogDescription>
                        Are you sure you want to remove
                        <strong>{{ memberToRemove?.name }}</strong> from this group?
                        This action cannot be undone.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter class="gap-2 sm:gap-0">
                    <AlertDialogCancel
                        @click="
                            showRemoveConfirmDialog = false;
                            memberToRemove = null;
                        "
                    >
                        Cancel
                    </AlertDialogCancel>
                    <AlertDialogAction
                        class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        @click="confirmRemoveMember"
                    >
                        <Trash2 class="mr-2 size-4" />
                        Remove Member
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>

        <!-- Create Group Chat Dialog -->
        <Dialog
            :open="showCreateGroupDialog"
            @update:open="showCreateGroupDialog = $event"
        >
            <DialogContent class="sm:max-w-lg w-[95vw] max-h-[90vh] overflow-hidden">
                <DialogHeader>
                    <DialogTitle>Create Group Chat</DialogTitle>
                    <DialogDescription>
                        Create a new group chat and add members
                    </DialogDescription>
                </DialogHeader>

                <div class="flex flex-col gap-4 max-h-[65vh] overflow-hidden">
                    <div class="space-y-2">
                        <Label for="group-name">Group Name</Label>
                        <Input
                            id="group-name"
                            v-model="createGroupForm.name"
                            type="text"
                            placeholder="Enter group chat name"
                        />
                        <p v-if="createGroupErrors.name" class="text-xs text-destructive">
                            {{ createGroupErrors.name }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <Label class="text-sm font-medium">Selected Members</Label>
                            <Button
                                v-if="selectedMembers.length"
                                variant="ghost"
                                size="sm"
                                class="text-xs text-muted-foreground"
                                @click="createGroupForm.memberIds = []"
                            >
                                Clear
                            </Button>
                        </div>
                        <div class="min-h-[44px] rounded-md border bg-muted/30">
                            <template v-if="selectedMembers.length">
                                <ScrollArea class="h-[80px] w-full px-2 py-2">
                                    <div class="flex flex-wrap gap-2 pr-4">
                                <div
                                    v-for="member in selectedMembers"
                                    :key="member.id"
                                            class="flex items-center gap-1 rounded-full border bg-background pl-2.5 px-2 py-1 text-xs"
                                >
                                            <span class="truncate max-w-[140px] font-medium">{{ member.name }}</span>
                                            <button
                                                type="button"
                                                class="text-muted-foreground hover:text-foreground"
                                                @click="toggleMemberSelection(member.id)"
                                    >
                                                <X class="size-3.5" />
                                            </button>
                                </div>
                            </div>
                                </ScrollArea>
                            </template>
                            <div v-else class="p-2 min-h-[44px] flex items-center text-xs text-muted-foreground">
                                Select members to add them here
                        </div>
                        </div>
                        <p v-if="createGroupErrors.memberIds" class="text-xs text-destructive">
                            {{ createGroupErrors.memberIds }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label class="text-sm font-medium">Add Members</Label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                                <Search class="size-4 text-muted-foreground" />
                            </span>
                            <Input
                                v-model="createGroupForm.search"
                                type="text"
                                placeholder="Search by name, email, or department"
                                class="pl-9"
                            />
                        </div>
                    </div>

                    <ScrollArea class="flex-1 min-h-[100px] max-h-[300px] pr-3">
                        <div v-if="filteredCreateGroupMembers.length" class="space-y-2">
                            <label
                                v-for="member in filteredCreateGroupMembers"
                                :key="member.id"
                                class="flex items-center gap-3 rounded-lg border p-3 transition hover:bg-muted/40 cursor-pointer"
                            >
                                <input
                                    type="checkbox"
                                    class="h-4 w-4 accent-primary"
                                    :checked="createGroupForm.memberIds.includes(member.id)"
                                    @change="toggleMemberSelection(member.id)"
                                />
                                    <Avatar class="size-10">
                                    <AvatarImage :src="member.avatar" :alt="member.name" class="object-cover" />
                                    <AvatarFallback>{{ getInitials(member.name) }}</AvatarFallback>
                                    </Avatar>
                                <div class="flex flex-1 flex-col gap-1 overflow-hidden">
                                    <div class="flex items-center gap-2">
                                        <p class="text-sm font-medium truncate">{{ member.name }}</p>
                                        <Badge variant="secondary" class="text-[10px] uppercase">
                                            {{ member.departmentCode ?? member.department }}
                                        </Badge>
                                    </div>
                                    <p class="text-xs text-muted-foreground truncate">
                                        {{ member.position }} · {{ member.email }}
                                    </p>
                                </div>
                            </label>
                        </div>
                        <div
                            v-else
                            class="flex flex-col items-center justify-center py-12 text-center text-sm text-muted-foreground"
                        >
                            <Users class="mb-3 size-10" />
                            <p>
                                {{
                                    createGroupForm.search
                                        ? 'No members match your search'
                                        : 'Start typing to search for members'
                                }}
                            </p>
                        </div>
                    </ScrollArea>
                </div>

                <DialogFooter class="justify-end">
                    <Button
                        variant="outline"
                        @click="
                            showCreateGroupDialog = false;
                            createGroupForm = { name: '', memberIds: [], search: '' };
                            createGroupErrors = { name: '', memberIds: '' };
                        "
                    >
                        Cancel
                    </Button>
                    <Button
                        :disabled="!createGroupForm.name.trim() || selectedMembers.length === 0"
                        @click="createGroupChat"
                    >
                        Create Group
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Rename Chat Dialog -->
        <Dialog :open="showRenameDialog" @update:open="showRenameDialog = $event">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Rename Chat</DialogTitle>
                    <DialogDescription>
                        Enter a new name for this group chat
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4">
                    <div class="space-y-2">
                        <Label for="rename-chat-name">Chat Name</Label>
                        <Input
                            id="rename-chat-name"
                            v-model="renameChatName"
                            type="text"
                            placeholder="Enter chat name"
                            :disabled="!isAdminOfChatToRename"
                            @keyup.enter="saveRenameChat"
                            autofocus
                        />
                    </div>
                </div>
                <DialogFooter class="justify-end">
                    <Button
                        variant="outline"
                        @click="
                            showRenameDialog = false;
                            chatToRename = null;
                            renameChatName = '';
                        "
                    >
                        Cancel
                    </Button>
                    <Button
                        v-if="isAdminOfChatToRename"
                        :disabled="!renameChatName.trim()"
                        @click="saveRenameChat"
                    >
                        Save
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
