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
import { Head } from '@inertiajs/vue3';
import {
    Bold,
    ChevronLeft,
    ChevronRight,
    CirclePlus,
    EllipsisVertical,
    Eye,
    EyeOff,
    FileText,
    Italic,
    LogOut,
    Paperclip,
    Pin,
    Pencil,
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

// ✅ Track screen size
const isDesktop = ref(window.innerWidth >= 768);
const showSidebar = ref(true); // Sidebar is visible initially on desktop

// ✅ Dialog states
const showMembersDialog = ref(false);
const showPinnedDialog = ref(false);
const showSearchDialog = ref(false);
const showAttachmentsDialog = ref(false);
const showAddMemberDialog = ref(false);
const addMemberSelectedIds = ref<number[]>([]);
const showRenameDialog = ref(false);
const chatToRename = ref<typeof recentChats.value[0] | null>(null);
const renameChatName = ref('');
const showLeaveConfirmDialog = ref(false);
const chatToLeave = ref<typeof recentChats.value[0] | null>(null);
const leaveCountdown = ref(5);
const leaveCountdownInterval = ref<number | null>(null);
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

onMounted(() => {
    handleMediaChange(mediaQuery); // Set initial state
    mediaQuery.addEventListener('change', handleMediaChange);
    
    // Scroll to bottom on initial load
    scrollToBottom();
});

onBeforeUnmount(() => {
    mediaQuery.removeEventListener('change', handleMediaChange);
    if (leaveCountdownInterval.value) {
        clearInterval(leaveCountdownInterval.value);
    }
});

// ✅ Open chat on click
const openChat = (chat: typeof recentChats.value[0]) => {
    selectedChat.value = chat;
    
    // Load chat-specific data
    const chatInfo = chatData[chat.id as keyof typeof chatData];
    if (chatInfo) {
        admins.value = chatInfo.admins;
        members.value = chatInfo.members;
        pinnedMessages.value = chatInfo.pinnedMessages;
        attachments.value = chatInfo.attachments;
        messages.value = chatInfo.messages || [];
    }
    
    // Scroll to bottom when opening chat
    scrollToBottom();
    
    if (!isDesktop.value) {
        showSidebar.value = false; // Hide chat list on mobile
    }
};

// ✅ Back button for mobile
const goBackToList = () => {
    selectedChat.value = null;
    showSidebar.value = true;
};

// ✅ Pin/Unpin chat
const pinChat = (chat: typeof recentChats.value[0]) => {
    // Remove from recent chats
    const index = recentChats.value.findIndex((c) => c.id === chat.id);
    if (index > -1) {
        recentChats.value.splice(index, 1);
        // Add to pinned chats
        pinnedChats.value.push(chat);
    }
};

const unpinChat = (chat: typeof pinnedChats.value[0]) => {
    // Remove from pinned chats
    const index = pinnedChats.value.findIndex((c) => c.id === chat.id);
    if (index > -1) {
        pinnedChats.value.splice(index, 1);
        // Add to recent chats at the top
        recentChats.value.unshift(chat);
    }
};

// Open rename chat dialog
const openRenameDialog = (chat: typeof recentChats.value[0]) => {
    chatToRename.value = chat;
    renameChatName.value = chat.name;
    showRenameDialog.value = true;
};

// Save renamed chat
const saveRenameChat = () => {
    if (!chatToRename.value || !renameChatName.value.trim()) {
        return;
    }

    const newName = renameChatName.value.trim();
    chatToRename.value.name = newName;
    
    // If this is the selected chat, update it
    if (selectedChat.value?.id === chatToRename.value.id) {
        selectedChat.value.name = newName;
    }

    // Reset and close
    chatToRename.value = null;
    renameChatName.value = '';
    showRenameDialog.value = false;
};

// Open leave chat confirmation
const openLeaveConfirm = (chat: typeof recentChats.value[0]) => {
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
const confirmLeaveChat = () => {
    if (!chatToLeave.value) {
        return;
    }

    // Remove from both pinned and recent
    pinnedChats.value = pinnedChats.value.filter((c) => c.id !== chatToLeave.value!.id);
    recentChats.value = recentChats.value.filter((c) => c.id !== chatToLeave.value!.id);
    
    // If this was the selected chat, clear it
    if (selectedChat.value?.id === chatToLeave.value.id) {
        selectedChat.value = null;
    }

    // Reset and close
    chatToLeave.value = null;
    leaveCountdown.value = 5;
    showLeaveConfirmDialog.value = false;
    if (leaveCountdownInterval.value) {
        clearInterval(leaveCountdownInterval.value);
        leaveCountdownInterval.value = null;
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

// Toggle seen/unseen status
const toggleSeenStatus = (chat: typeof recentChats.value[0]) => {
    // Find chat in pinned or recent and toggle is_seen
    const pinnedIndex = pinnedChats.value.findIndex((c) => c.id === chat.id);
    if (pinnedIndex !== -1) {
        pinnedChats.value[pinnedIndex].is_seen = !pinnedChats.value[pinnedIndex].is_seen;
    } else {
        const recentIndex = recentChats.value.findIndex((c) => c.id === chat.id);
        if (recentIndex !== -1) {
            recentChats.value[recentIndex].is_seen = !recentChats.value[recentIndex].is_seen;
        }
    }
};

// Toggle pin status for a message
const togglePinMessage = (message: any) => {
    // Find message in messages array and toggle isPinned
    const messageIndex = messages.value.findIndex((m) => m.id === message.id);
    if (messageIndex !== -1) {
        const wasPinned = messages.value[messageIndex].isPinned;
        messages.value[messageIndex].isPinned = !wasPinned;

        if (!wasPinned) {
            // Pin: Add to pinnedMessages at the beginning
            // Format: { id, text, user, time, hasAttachment, attachments }
            const messageToPin = messages.value[messageIndex];
            const userName = messageToPin.isCurrentUser 
                ? messageToPin.user 
                : (messageToPin.user || 'Unknown User');
            
            // Format time (simplified - you might want to use a date formatter)
            const timeStr = messageToPin.timestamp || 'Just now';
            
            // Get attachments
            const attachments = Array.isArray((messageToPin as any).attachments) 
                ? (messageToPin as any).attachments 
                : ((messageToPin as any).hasAttachment && (messageToPin as any).attachmentName 
                    ? [{ name: (messageToPin as any).attachmentName }] 
                    : []);
            
            (pinnedMessages.value as any[]).unshift({
                id: messageToPin.id,
                text: messageToPin.text,
                user: userName,
                time: timeStr,
                hasAttachment: (messageToPin as any).hasAttachment || attachments.length > 0,
                attachments: attachments,
            });
        } else {
            // Unpin: Remove from pinnedMessages
            pinnedMessages.value = pinnedMessages.value.filter((m) => m.id !== message.id);
        }
    }
};

// Helper function to get attachments from pinned message
const getPinnedAttachments = (pinned: any) => {
    return (pinned?.attachments || []) as Array<{ name?: string } | string>;
};

// Helper function to check if pinned message has attachments
const hasPinnedAttachments = (pinned: any) => {
    return pinned?.hasAttachment && (pinned?.attachments?.length || 0) > 0;
};

// ✅ Chat input configuration
const textareaRef = ref<HTMLTextAreaElement>();
const messageText = ref('');
const fileInputRef = ref<HTMLInputElement>();
const selectedFiles = ref<File[]>([]);

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

const isSendDisabled = computed(() => messageText.value.trim().length === 0);

const handleKeyDown = (event: KeyboardEvent) => {
    const isCtrlOrCmd = event.ctrlKey || event.metaKey;

    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        sendMessage();
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

const sendMessage = () => {
    // Clear selected files after sending (for now, just clear them)
    // In a real implementation, you would upload files and attach them to the message
    selectedFiles.value = [];
    if (isSendDisabled.value) return;
    console.log('Message:', messageText.value);
    messageText.value = '';
    autoResize();
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

// All company employees (potential members for any chat)
const employeeDirectory: Record<number, EmployeeProfile> = {
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

const allEmployees = Object.values(employeeDirectory).sort((a, b) => a.id - b.id);

// Potential members to add (users not yet in the group) - use all employees
const potentialMembers = ref([...allEmployees]);

// Search
const memberSearch = ref('');
const addMemberSearch = ref('');
const chatSearch = ref('');

// Chat list data with comprehensive details
const pinnedChats = ref([
    {
        id: 1,
        name: 'Human Resources',
        lastMessage: 'Sarah: The new employee handbook has been updated',
        memberCount: '12/50',
        description: 'HR team communications and updates',
        is_seen: true,
    },
]);

const recentChats = ref([
    {
        id: 2,
        name: 'Finance Department',
        lastMessage: 'David: Q4 budget review scheduled for next week',
        memberCount: '8/30',
        description: 'Financial planning and reporting discussions',
        is_seen: true,
    },
    {
        id: 3,
        name: 'Marketing Team',
        lastMessage: 'Emma: Campaign launch is confirmed for Monday!',
        memberCount: '15/40',
        description: 'Marketing campaigns and brand strategy',
        is_seen: false,
    },
    {
        id: 4,
        name: 'IT Support',
        lastMessage: 'James: Server maintenance tonight at 11 PM',
        memberCount: '6/25',
        description: 'Technical support and infrastructure',
        is_seen: true,
    },
    {
        id: 5,
        name: 'Product Development',
        lastMessage: 'Alice: Sprint planning meeting tomorrow at 10 AM',
        memberCount: '18/45',
        description: 'Product roadmap and development updates',
        is_seen: false,
    },
    {
        id: 6,
        name: 'Sales Team',
        lastMessage: 'Michael: Great job hitting targets this month!',
        memberCount: '10/35',
        description: 'Sales strategies and client updates',
        is_seen: true,
    },
]);

// ✅ Selected chat state
const selectedChat = ref<typeof recentChats.value[0] | null>(null);

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


// Filter potential members (exclude already added members)
const filteredPotentialMembers = computed(() => {
    const allMemberIds = [
        ...admins.value.map((a) => a.id),
        ...members.value.map((m) => m.id),
    ];

    return potentialMembers.value.filter((user) => {
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

    if (!searchTerm.length) {
        return potentialMembers.value;
    }

    return potentialMembers.value.filter((user) => {
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

const createChatMember = (id: number, role: 'Admin' | 'Member') => {
    const employee = employeeDirectory[id];

    if (!employee) {
        throw new Error(`Employee with id ${id} not found.`);
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

const createGroupChat = () => {
    if (!validateCreateGroupForm()) {
        return;
    }

    const newChat = {
        id: recentChats.value.length + pinnedChats.value.length + 1,
        name: createGroupForm.value.name.trim(),
        lastMessage: 'Group created',
        memberCount: `${createGroupForm.value.memberIds.length + 1}/${createGroupForm.value.memberIds.length + 1}`,
        description: 'New group chat',
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
const addMembers = () => {
    if (addMemberSelectedIds.value.length === 0) {
        return;
    }

    // Add all selected members
    addMemberSelectedIds.value.forEach((memberId) => {
        const user = potentialMembers.value.find((u) => u.id === memberId);
        if (user && !members.value.find((m) => m.id === memberId) && !admins.value.find((a) => a.id === memberId)) {
            members.value.push(createChatMember(memberId, 'Member'));
        }
    });

    // Reset and close
    addMemberSelectedIds.value = [];
    addMemberSearch.value = '';
    showAddMemberDialog.value = false;
};

// Remove member function
const openRemoveConfirm = (member: { id: number; name: string }) => {
    memberToRemove.value = member;
    showRemoveConfirmDialog.value = true;
};

const confirmRemoveMember = () => {
    if (memberToRemove.value) {
        // Remove from members or admins based on where they are
        members.value = members.value.filter(
            (m) => m.id !== memberToRemove.value!.id,
        );
        admins.value = admins.value.filter(
            (a) => a.id !== memberToRemove.value!.id,
        );
        memberToRemove.value = null;
        showRemoveConfirmDialog.value = false;
    }
};

// Set user as admin
const setAsAdmin = (user: { id: number }) => {
    // Remove from members if they're there
    members.value = members.value.filter((m) => m.id !== user.id);
    
    // Add to admins if not already there
    if (!admins.value.find((a) => a.id === user.id)) {
        admins.value.push(createChatMember(user.id, 'Admin'));
    }
};

// Remove admin status
const removeAdminStatus = (admin: { id: number }) => {
    // Remove from admins
    admins.value = admins.value.filter((a) => a.id !== admin.id);
    
    // Add to members if not already there
    if (!members.value.find((m) => m.id === admin.id)) {
        members.value.push(createChatMember(admin.id, 'Member'));
    }
};

// Chat-specific data structure
const chatData = {
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

// Current chat members, pinned messages, attachments, and messages (dynamic based on selected chat)
const admins = ref(chatData[1].admins);
const members = ref(chatData[1].members);
const pinnedMessages = ref(chatData[1].pinnedMessages);
const attachments = ref(chatData[1].attachments);
const messages = ref(chatData[1].messages || []);

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
        <div class="m-1 flex flex-1 min-h-0 rounded-b-sm border overflow-hidden">
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
                                            {{ chat.memberCount }}
                                        </span>
                                    </div>

                                    <div class="flex items-center justify-between">
                                        <p
                                            class="flex-1 truncate text-sm text-muted-foreground"
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
                                            <Pin class="mr-1 size-4" />
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
                                            {{ chat.memberCount }}
                                        </span>
                                    </div>

                                    <div class="flex items-center justify-between">
                                        <p
                                            class="flex-1 truncate text-sm text-muted-foreground"
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
                                        Change Name
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
                                        Change Name
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
                            <p class="text-sm text-foreground truncate mt-0.5" v-html="formatMessageText(latestPinnedMessage.text)"></p>
                        </div>
                        <ChevronRight class="size-5 text-muted-foreground shrink-0" />
                    </div>
                </div>

                <!-- Chat Area -->
                <div class="flex flex-1 flex-col min-h-0 min-w-0 overflow-hidden">
                    <!-- Main Messages Area -->
                    <div class="flex-1 min-h-0 min-w-0 overflow-hidden">
                        <ScrollArea :class="latestPinnedMessage ? 'h-full w-full max-h-[calc(100vh-360px)] p-2 sm:p-6' : 'h-full w-full max-h-[calc(100vh-320px)] p-2 sm:p-6'">
                            <div class="space-y-2 sm:space-y-4 min-w-0">
                                <!-- Messages -->
                                <div
                                    v-for="message in messages"
                                    :key="message.id"
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
                                            <div class="flex flex-col items-end gap-1.5 sm:gap-2 w-full max-w-[280px] sm:max-w-[540px] min-w-0">
                                                <div class="flex items-center gap-1.5 sm:gap-2 text-[9px] sm:text-xs text-muted-foreground">
                                                    <Badge
                                                        v-if="message.isPinned"
                                                        variant="secondary"
                                                        class="text-[8px] sm:text-[10px] px-1 py-0"
                                                    >
                                                        Pinned
                                                    </Badge>
                                                    <span>{{ message.timestamp }}</span>
                                                    <span class="text-[10px] sm:text-sm font-semibold text-foreground">{{ message.user }}</span>
                                                </div>
                                                <div class="flex items-center gap-1.5 sm:gap-2 w-full">
                                                    <DropdownMenu>
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
                                                            <DropdownMenuItem @click="togglePinMessage(message)">
                                                                <Pin class="mr-2 size-4" />
                                                                {{ message.isPinned ? 'Unpin' : 'Pin' }}
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem>
                                                                <Pencil class="mr-2 size-4" />
                                                                Edit
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem class="text-destructive focus:text-destructive">
                                                                <Trash2 class="mr-2 size-4" />
                                                                Delete
                                                            </DropdownMenuItem>
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                    <div class="w-full rounded-xl border border-primary/30 dark:border-primary/40 bg-primary/15 dark:bg-primary/20 px-2.5 py-2 sm:px-4 sm:py-3 shadow-sm flex flex-col gap-2 sm:gap-3 text-foreground min-w-0">
                                                    <div class="text-[11px] sm:text-sm break-words" v-html="formatMessageText(message.text)"></div>
                                                    <div
                                                        v-if="message.hasAttachment"
                                                        class="flex flex-wrap gap-2"
                                                    >
                                                        <div
                                                        v-for="attachment in (Array.isArray((message as any).attachments) ? (message as any).attachments : [{ name: message.attachmentName }])"
                                                            :key="attachment.name ?? attachment"
                                                            class="flex items-center gap-1.5 sm:gap-2 rounded-lg border border-primary/30 dark:border-primary/40 bg-primary/25 dark:bg-primary/30 px-2 py-1.5 sm:px-3 sm:py-2 w-full sm:w-auto max-w-full text-foreground hover:bg-primary/30 dark:hover:bg-primary/35 transition cursor-pointer"
                                                        >
                                                            <FileText class="size-3 sm:size-4 text-foreground/70" />
                                                            <span class="text-[10px] sm:text-xs font-medium truncate max-w-[200px] text-foreground">{{ attachment.name ?? 'Attachment' }}</span>
                                                        </div>
                                                    </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <Avatar class="size-7 sm:size-10 shrink-0 self-start">
                                                <AvatarImage
                                                    :src="message.avatar || ''"
                                                    :alt="message.user"
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
                                                />
                                                <AvatarFallback>
                                                    {{ getInitials(message.user || 'U') }}
                                                </AvatarFallback>
                                            </Avatar>
                                            <div class="flex flex-col gap-1.5 sm:gap-2 w-full max-w-[280px] sm:max-w-[540px] min-w-0">
                                                <div class="flex items-center gap-1.5 sm:gap-2 text-[9px] sm:text-xs text-muted-foreground">
                                                    <span class="text-[10px] sm:text-sm font-semibold text-foreground">{{ message.user || 'User' }}</span>
                                                    <span>{{ message.timestamp }}</span>
                                                    <Badge
                                                        v-if="message.isPinned"
                                                        variant="secondary"
                                                        class="text-[8px] sm:text-[10px] px-1 py-0"
                                                    >
                                                        Pinned
                                                    </Badge>
                                                </div>
                                                <div class="flex items-center gap-1.5 sm:gap-2 w-full">
                                                    <div class="rounded-xl border border-border bg-muted/50 dark:bg-muted/30 px-2.5 py-2 sm:px-4 sm:py-3 shadow-sm flex flex-col gap-2 sm:gap-3 w-full min-w-0">
                                                    <div class="text-[11px] sm:text-sm text-foreground break-words" v-html="formatMessageText(message.text)"></div>
                                                    <div
                                                        v-if="message.hasAttachment"
                                                        class="flex flex-wrap gap-2"
                                                    >
                                                        <div
                                                        v-for="attachment in (Array.isArray((message as any).attachments) ? (message as any).attachments : [{ name: message.attachmentName }])"
                                                            :key="attachment.name ?? attachment"
                                                            class="flex items-center gap-1.5 sm:gap-2 rounded-lg border border-border bg-muted/60 dark:bg-muted/40 px-2 py-1.5 sm:px-3 sm:py-2 w-full sm:w-auto max-w-full text-foreground hover:bg-muted/70 dark:hover:bg-muted/50 transition cursor-pointer"
                                                        >
                                                            <FileText class="size-3 sm:size-4 text-foreground/70" />
                                                            <span class="text-[10px] sm:text-xs font-medium truncate max-w-[200px] text-foreground">{{ attachment.name ?? 'Attachment' }}</span>
                                                        </div>
                                                    </div>
                                                    </div>
                                                    <DropdownMenu>
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
                                                            <DropdownMenuItem @click="togglePinMessage(message)">
                                                                <Pin class="mr-2 size-4" />
                                                                {{ message.isPinned ? 'Unpin' : 'Pin' }}
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem>
                                                                <Pencil class="mr-2 size-4" />
                                                                Edit
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem class="text-destructive focus:text-destructive">
                                                                <Trash2 class="mr-2 size-4" />
                                                                Delete
                                                            </DropdownMenuItem>
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

                    <!-- Fixed Input Area -->
                    <div class="border-t bg-background p-2 sm:p-4 min-w-0 overflow-hidden">
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
                                    placeholder="Type a message..."
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

                                    <!-- Right side: Attach + Send -->
                                    <div class="flex items-center gap-1 sm:gap-2 shrink-0">
                                        <input
                                            ref="fileInputRef"
                                            type="file"
                                            multiple
                                            accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx"
                                            class="hidden"
                                            @change="handleFileSelect"
                                        />
                                        <TooltipProvider>
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
                                            orientation="vertical"
                                            class="!h-4"
                                        />
                                        <InputGroupButton
                                            size="sm"
                                            variant="ghost"
                                            class="h-10 w-10 rounded-sm"
                                            :disabled="isSendDisabled"
                                            @click="sendMessage"
                                        >
                                            <SendHorizontal class="size-6" />
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
                                        <DropdownMenu>
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
                                        <DropdownMenu>
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

                    <div class="flex justify-end border-t pt-4">
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
            <DialogContent class="max-w-md max-h-[90vh] overflow-hidden">
                <DialogHeader>
                    <DialogTitle>Pinned Messages</DialogTitle>
                </DialogHeader>
                <ScrollArea class="flex-1 min-h-0 max-h-[calc(90vh-120px)]">
                    <div class="space-y-3">
                        <div
                            v-for="pinned in pinnedMessages"
                            :key="pinned.id"
                            class="rounded-md border p-3"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium">{{ pinned.user }}</p>
                                    <p class="mt-1 text-sm text-muted-foreground" v-html="formatMessageText(pinned.text)"></p>
                                    
                                    <!-- Attachments -->
                                    <div
                                        v-if="hasPinnedAttachments(pinned)"
                                        class="mt-2 flex flex-col gap-2"
                                    >
                                        <div
                                            v-for="(attachment, idx) in getPinnedAttachments(pinned)"
                                            :key="idx"
                                            class="flex items-center gap-2 rounded-lg border border-border bg-muted/50 px-3 py-2 text-sm cursor-pointer hover:bg-muted/70 transition-colors w-full"
                                        >
                                            <FileText class="size-4 text-muted-foreground shrink-0" />
                                            <span class="truncate flex-1 min-w-0">{{ typeof attachment === 'string' ? attachment : (attachment.name || '') }}</span>
                                        </div>
                                    </div>
                                    
                                    <p class="mt-2 text-xs text-muted-foreground">
                                        {{ pinned.time }}
                                    </p>
                                </div>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="h-6 w-6 shrink-0"
                                >
                                    <Pin class="size-4" />
                                </Button>
                            </div>
                        </div>
                        <div
                            v-if="pinnedMessages.length === 0"
                            class="py-8 text-center text-sm text-muted-foreground"
                        >
                            No pinned messages
                        </div>
                    </div>
                </ScrollArea>
            </DialogContent>
        </Dialog>

        <!-- Search Dialog -->
        <Dialog :open="showSearchDialog" @update:open="showSearchDialog = $event">
            <DialogContent class="max-w-md max-h-[90vh] overflow-hidden">
                <DialogHeader>
                    <DialogTitle>Search Messages</DialogTitle>
                </DialogHeader>
                <div class="space-y-4">
                    <div class="relative">
                        <span
                            class="absolute inset-y-0 left-0 flex items-center pl-2"
                        >
                            <Search class="size-4 text-muted-foreground" />
                        </span>
                        <Input
                            type="text"
                            placeholder="Search in conversation..."
                            class="w-full pl-8"
                        />
                    </div>
                    <div class="py-8 text-center text-sm text-muted-foreground">
                        No results found
                    </div>
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
                    >
                        Cancel
                    </AlertDialogCancel>
                    <AlertDialogAction
                        class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        :disabled="leaveCountdown > 0"
                        @click="confirmLeaveChat"
                    >
                        <LogOut class="mr-2 size-4" />
                        {{ leaveCountdown > 0 ? `Leave (${leaveCountdown}s)` : 'Leave Chat' }}
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
                                    <AvatarImage :src="member.avatar" :alt="member.name" />
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
