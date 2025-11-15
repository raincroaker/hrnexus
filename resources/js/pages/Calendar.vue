<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

import { ChevronUp, ChevronDown, Search, X } from "lucide-vue-next"
import { Button } from "@/components/ui/button"
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select"
import { ScrollArea } from "@/components/ui/scroll-area"
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Badge } from '@/components/ui/badge'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'

// Type definitions
interface Event {
    id: number;
    title: string;
    date: Date;
    time?: string;
    startTime?: string;
    endTime?: string;
    allDay?: boolean;
    startDate?: Date;
    endDate?: Date;
    type: 'meeting' | 'deadline' | 'presentation' | 'review' | 'planning' | 'social';
    color: string;
    description?: string;
    category?: string;
}

type EventFilterType = 'today' | 'this-month' | 'this-year' | 'upcoming' | 'all';

interface EventData {
    title: string;
    date: string | Date;
    time?: string;
    startTime?: string;
    endTime?: string;
    allDay?: boolean;
    startDate?: string | Date;
    endDate?: string | Date;
    type: Event['type'];
    color: string;
    description?: string;
    category?: string;
}

interface CalendarDay {
    day: number;
    date: Date;
    isCurrentMonth: boolean;
    isPrevMonth: boolean;
    events: Event[];
}

interface Category {
    id: number;
    name: string;
    color: string;
}

// Events data
const allEvents = ref<Event[]>([
    {
        id: 1,
        title: 'Team Meeting',
        date: new Date(2025, 9, 28), // October 28, 2025
        startTime: '10:00',
        endTime: '11:00',
        description: 'Weekly team sync to discuss project progress, blockers, and upcoming tasks. All team members are required to attend.',
        type: 'meeting',
        color: 'bg-blue-500',
    },
    {
        id: 2,
        title: 'Project Deadline',
        date: new Date(2025, 9, 30), // October 30, 2025
        time: '5:00 PM',
        description: 'Final submission deadline for Q4 project deliverables. Ensure all documentation and code reviews are completed.',
        type: 'deadline',
        color: 'bg-red-500',
    },
    {
        id: 3,
        title: 'Client Presentation',
        date: new Date(2025, 10, 5), // November 5, 2025
        startTime: '14:00',
        endTime: '15:30',
        description: 'Present the new product features and roadmap to key stakeholders. Prepare demo environment and presentation slides.',
        type: 'presentation',
        color: 'bg-green-500',
    },
    {
        id: 4,
        title: 'Code Review',
        date: new Date(2025, 9, 25), // October 25, 2025
        startTime: '15:30',
        endTime: '16:30',
        description: 'Review pull requests for the authentication module. Focus on security best practices and code quality standards.',
        type: 'review',
        color: 'bg-purple-500',
    },
    {
        id: 5,
        title: 'Sprint Planning',
        date: new Date(2025, 10, 12), // November 12, 2025
        startTime: '09:00',
        endTime: '12:00',
        description: 'Plan the next sprint goals, assign tasks, and estimate story points. Review backlog priorities and dependencies.',
        type: 'planning',
        color: 'bg-yellow-500',
    },
    {
        id: 6,
        title: 'Holiday Party',
        date: new Date(2025, 11, 20), // December 20, 2025
        startTime: '18:00',
        endTime: '22:00',
        description: 'Annual company holiday celebration with dinner, drinks, and entertainment. Dress code: Smart casual. RSVP required.',
        type: 'social',
        color: 'bg-pink-500',
    },
    {
        id: 7,
        title: 'Weekly Standup',
        date: new Date(2025, 9, 29), // October 29, 2025
        startTime: '09:30',
        endTime: '10:00',
        description: 'Quick daily standup to share progress updates and identify any blockers. Keep updates concise and focused.',
        type: 'meeting',
        color: 'bg-blue-500',
    },
    {
        id: 8,
        title: 'Design Review',
        date: new Date(2025, 10, 8), // November 8, 2025
        startTime: '13:00',
        endTime: '15:00',
        description: 'Review new UI/UX designs for the dashboard redesign. Provide feedback on user experience and visual consistency.',
        type: 'review',
        color: 'bg-purple-500',
    },
    {
        id: 9,
        title: 'Company Retreat',
        date: new Date(2025, 10, 15), // November 15, 2025
        allDay: true,
        startDate: new Date(2025, 10, 15), // November 15, 2025
        endDate: new Date(2025, 10, 17), // November 17, 2025
        description: 'Annual company retreat at the mountain resort. Team building activities, workshops, and networking sessions. Transportation and accommodation provided.',
        type: 'social',
        color: 'bg-pink-500',
    },
]);

// Calendar state
const showNewEventModal = ref<boolean>(false);
const showDatePicker = ref<boolean>(false);
const currentDate = ref<Date>(new Date());
const eventFilter = ref<EventFilterType>('today');
const pickerYear = ref<number>(currentDate.value.getFullYear());

// Event search and category filter
const eventSearch = ref<string>('');
const selectedCategoryFilter = ref<string>('all');

// Computed values
const today = new Date();
const currentDay = computed(() => today.getDate());
const currentMonthShort = computed(() =>
    currentDate.value
        .toLocaleDateString('en-US', { month: 'short' })
        .toUpperCase(),
);
const currentMonthFull = computed(() =>
    currentDate.value.toLocaleDateString('en-US', { month: 'long' }),
);
const currentYear = computed(() => currentDate.value.getFullYear());

const monthDateRange = computed(() => {
    const firstDay = new Date(
        currentDate.value.getFullYear(),
        currentDate.value.getMonth(),
        1,
    );
    const lastDay = new Date(
        currentDate.value.getFullYear(),
        currentDate.value.getMonth() + 1,
        0,
    );
    return `${firstDay.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })} - ${lastDay.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}`;
});

// Event filter functions
const getFilteredEvents = computed(() => {
    const now = new Date();
    const currentMonth = currentDate.value.getMonth();
    const currentYear = currentDate.value.getFullYear();
    const searchTerm = eventSearch.value.toLowerCase().trim();

    return allEvents.value
        .filter((event: Event) => {
            // Time period filter
            let matchesTimeFilter = false;
            switch (eventFilter.value) {
                case 'today':
                    const today = new Date();
                    matchesTimeFilter = (
                        event.date.getDate() === today.getDate() &&
                        event.date.getMonth() === today.getMonth() &&
                        event.date.getFullYear() === today.getFullYear()
                    );
                    break;
                case 'this-month':
                    matchesTimeFilter = (
                        event.date.getMonth() === currentMonth &&
                        event.date.getFullYear() === currentYear
                    );
                    break;
                case 'this-year':
                    matchesTimeFilter = event.date.getFullYear() === currentYear;
                    break;
                case 'upcoming':
                    matchesTimeFilter = event.date >= now;
                    break;
                case 'all':
                    matchesTimeFilter = true;
                    break;
                default:
                    matchesTimeFilter = true;
            }

            if (!matchesTimeFilter) return false;

            // Category filter
            if (selectedCategoryFilter.value !== 'all') {
                const categoryMatch = availableCategories.value.find(
                    cat => cat.value === selectedCategoryFilter.value
                );
                if (categoryMatch && event.type !== categoryMatch.type) {
                    return false;
                }
            }

            // Search filter
            if (searchTerm) {
                const titleMatch = event.title.toLowerCase().includes(searchTerm);
                const descriptionMatch = event.description?.toLowerCase().includes(searchTerm) || false;
                const dateMatch = event.date.toLocaleDateString().toLowerCase().includes(searchTerm);
                const categoryMatch = event.category?.toLowerCase().includes(searchTerm) || false;
                
                if (!titleMatch && !descriptionMatch && !dateMatch && !categoryMatch) {
                    return false;
                }
            }

            return true;
        })
        .sort(
            (a: Event, b: Event) => a.date.getTime() - b.date.getTime(),
        );
});

const eventFilterLabel = computed(() => {
    switch (eventFilter.value) {
        case 'today':
            return 'Today';
        case 'this-month':
            return `${currentMonthFull.value} ${currentYear.value}`;
        case 'this-year':
            return `${currentYear.value}`;
        case 'upcoming':
            return 'Upcoming Events';
        case 'all':
            return 'All Events';
        default:
            return 'This Month';
    }
});

// Format date for display
const formatEventDate = (date: Date): string => {
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year:
            date.getFullYear() !== new Date().getFullYear()
                ? 'numeric'
                : undefined,
    });
};

// Get events for a specific date
const getEventsForDate = (date: Date): Event[] => {
    return allEvents.value.filter(
        (event: Event) => event.date.toDateString() === date.toDateString(),
    );
};

// Calendar days computation
const calendarDays = computed<CalendarDay[]>(() => {
    const year = currentDate.value.getFullYear();
    const month = currentDate.value.getMonth();

    // Get first day of month and how many days in month
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const daysInMonth = lastDay.getDate();
    const startingDayOfWeek = firstDay.getDay();

    // Get previous month's last days to fill the grid
    const prevMonth = new Date(year, month - 1, 0);
    const daysInPrevMonth = prevMonth.getDate();

    const days: CalendarDay[] = [];

    // Add previous month's trailing days
    for (let i = startingDayOfWeek - 1; i >= 0; i--) {
        const day = daysInPrevMonth - i;
        const date = new Date(year, month - 1, day);
        days.push({
            day,
            date,
            isCurrentMonth: false,
            isPrevMonth: true,
            events: getEventsForDate(date),
        });
    }

    // Add current month's days
    for (let day = 1; day <= daysInMonth; day++) {
        const date = new Date(year, month, day);
        days.push({
            day,
            date,
            isCurrentMonth: true,
            isPrevMonth: false,
            events: getEventsForDate(date),
        });
    }

    // Add next month's leading days to complete the grid (42 total cells = 6 weeks)
    const totalCells = 42;
    const remainingCells = totalCells - days.length;
    for (let day = 1; day <= remainingCells; day++) {
        const date = new Date(year, month + 1, day);
        days.push({
            day,
            date,
            isCurrentMonth: false,
            isPrevMonth: false,
            events: getEventsForDate(date),
        });
    }

    return days;
});

// Navigation functions
const previousMonth = (): void => {
    currentDate.value = new Date(
        currentDate.value.getFullYear(),
        currentDate.value.getMonth() - 1,
        1,
    );
};

const nextMonth = (): void => {
    currentDate.value = new Date(
        currentDate.value.getFullYear(),
        currentDate.value.getMonth() + 1,
        1,
    );
};

const moveToday = (): void => {
    currentDate.value = new Date();
};

// Modal functions
const openModal = (): void => {
    showNewEventModal.value = true;
};

const closeModal = (): void => {
    showNewEventModal.value = false;
};

// Add new event
const addEvent = (eventData: EventData): void => {
    const newEvent: Event = {
        id: Date.now(), // Simple ID generation
        ...eventData,
        date: new Date(eventData.date),
        startDate: eventData.startDate
            ? new Date(eventData.startDate)
            : undefined,
        endDate: eventData.endDate
            ? new Date(eventData.endDate)
            : undefined,
    };
    allEvents.value.push(newEvent);
};

const handleEventSubmit = (eventData: EventData): void => {
    addEvent(eventData);
    closeModal();
};

// Date picker functions
const toggleDatePicker = (): void => {
    showDatePicker.value = !showDatePicker.value;
};

const monthNames: string[] = [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
];

const selectMonth = (month: number, year: number): void => {
    currentDate.value = new Date(year, month, 1);
    showDatePicker.value = false;
};

const previousYear = (): void => {
    pickerYear.value--;
};

const nextYear = (): void => {
    pickerYear.value++;
};

const breadcrumbs = [
    { title: 'Calendar', href: '/calendar' },
];

// New Event form data
const newEventFormData = ref({
    name: '',
    description: '',
    location: '',
    category: '', 
    date: '',
    startDate: '',
    endDate: '',
    startTime: '',
    endTime: '',
    allDay: false,
    recurring: false,
    duration: 1 // Number of days for all-day events
})

// Predefined categories for new event form
const availableCategories = ref([
    { value: 'meeting', label: 'Meetings', color: 'bg-blue-500', borderColor: 'border-blue-500', type: 'meeting' },
    { value: 'review', label: 'Reviews & Feedback', color: 'bg-purple-500', borderColor: 'border-purple-500', type: 'review' },
    { value: 'presentation', label: 'Presentation & Demos', color: 'bg-green-500', borderColor: 'border-green-500', type: 'presentation' },
    { value: 'planning', label: 'Planning', color: 'bg-yellow-500', borderColor: 'border-yellow-500', type: 'planning' },
    { value: 'deadline', label: 'Deadlines', color: 'bg-red-500', borderColor: 'border-red-500', type: 'deadline' },
    { value: 'social', label: 'Other', color: 'bg-pink-500', borderColor: 'border-pink-500', type: 'social' }
])

// Auto-update end date based on start date and duration
watch([() => newEventFormData.value.startDate, () => newEventFormData.value.duration], ([startDate, duration]) => {
    if (startDate && duration && newEventFormData.value.allDay) {
        const start = new Date(startDate)
        const end = new Date(start)
        end.setDate(start.getDate() + (typeof duration === 'number' ? duration : parseInt(String(duration))) - 1)
        
        // Format as YYYY-MM-DD for the date input
        const endDateString = end.toISOString().split('T')[0]
        newEventFormData.value.endDate = endDateString
    }
})

// Get selected category object for display
const getSelectedCategoryObject = () => {
    return availableCategories.value.find(cat => cat.value === newEventFormData.value.category)
}

// Handle new event submit
const handleNewEventSubmit = () => {
    const selectedCategoryObj = availableCategories.value.find((cat: { value: string; type: string; color: string }) => cat.value === newEventFormData.value.category)
    
    const eventData: EventData = {
        title: newEventFormData.value.name,
        description: newEventFormData.value.description,
        date: newEventFormData.value.allDay ? newEventFormData.value.startDate : newEventFormData.value.date,
        startDate: newEventFormData.value.allDay ? newEventFormData.value.startDate : undefined,
        endDate: newEventFormData.value.allDay ? newEventFormData.value.endDate : undefined,
        startTime: newEventFormData.value.allDay ? undefined : newEventFormData.value.startTime,
        endTime: newEventFormData.value.allDay ? undefined : newEventFormData.value.endTime,
        allDay: newEventFormData.value.allDay,
        type: (selectedCategoryObj?.type as Event['type']) || 'social',
        color: selectedCategoryObj?.color || 'bg-gray-500',
        category: newEventFormData.value.category
    }
    
    handleEventSubmit(eventData)
    
    // Reset form after submission
    newEventFormData.value = {
        name: '',
        description: '',
        location: '',
        category: '',
        date: '',
        startDate: '',
        endDate: '',
        startTime: '',
        endTime: '',
        allDay: false,
        recurring: false,
        duration: 1
    }
}

// Event details modal state
const showEventDetails = ref(false)
const selectedEvent = ref<Event | null>(null)

// Event details data
const attendees = ref([
    { id: 1, name: 'John Doe', email: 'john@example.com', avatar: 'JD', role: 'Project Manager' },
    { id: 2, name: 'Jane Smith', email: 'jane@example.com', avatar: 'JS', role: 'Developer' },
    { id: 3, name: 'Mike Johnson', email: 'mike@example.com', avatar: 'MJ', role: 'Designer' },
    { id: 4, name: 'Sarah Wilson', email: 'sarah@example.com', avatar: 'SW', role: 'QA Engineer' },
    { id: 5, name: 'David Brown', email: 'david@example.com', avatar: 'DB', role: 'Team Lead' },
    { id: 6, name: 'Lisa Chen', email: 'lisa@example.com', avatar: 'LC', role: 'Business Analyst' },
    { id: 7, name: 'Tom Wilson', email: 'tom@example.com', avatar: 'TW', role: 'DevOps Engineer' }
])

const eventDetailsInfo = ref({
    location: 'Conference Room A',
    organizer: 'John Doe',
})

const isAttending = ref(false)
const showDeleteConfirm = ref(false)
const deleteCountdown = ref(5)
const deleteCountdownInterval = ref<number | null>(null)

const toggleAttendance = () => {
    isAttending.value = !isAttending.value
}

const handleDeleteClick = () => {
    showDeleteConfirm.value = true
    deleteCountdown.value = 5
    
    if (deleteCountdownInterval.value) {
        clearInterval(deleteCountdownInterval.value)
    }
    
    deleteCountdownInterval.value = window.setInterval(() => {
        deleteCountdown.value--
        if (deleteCountdown.value <= 0) {
            if (deleteCountdownInterval.value) {
                clearInterval(deleteCountdownInterval.value)
                deleteCountdownInterval.value = null
            }
            confirmDelete()
        }
    }, 1000)
}

const confirmDelete = () => {
    if (selectedEvent.value) {
        // Remove event from the list
        allEvents.value = allEvents.value.filter(event => event.id !== selectedEvent.value?.id)
        showDeleteConfirm.value = false
        closeEventDetails()
    }
    deleteCountdown.value = 5
    if (deleteCountdownInterval.value) {
        clearInterval(deleteCountdownInterval.value)
        deleteCountdownInterval.value = null
    }
}

const cancelDelete = () => {
    showDeleteConfirm.value = false
    deleteCountdown.value = 5
    if (deleteCountdownInterval.value) {
        clearInterval(deleteCountdownInterval.value)
        deleteCountdownInterval.value = null
    }
}

const getCategoryName = (color: string): string => {
    const categoryMap: Record<string, string> = {
        'bg-blue-500': 'Meetings & Standups',
        'bg-red-500': 'Deadlines & Urgent',
        'bg-green-500': 'Presentations & Demos',
        'bg-purple-500': 'Reviews & Feedback',
        'bg-yellow-500': 'Planning & Strategy'
    }
    return categoryMap[color] || 'Other'
}

const formatEventDetailsTime = (time: string): string => {
    if (!time) return ''
    
    // If it's already in 12-hour format (contains AM/PM), return as is
    if (time.includes('AM') || time.includes('PM')) {
        return time
    }
    
    // Handle 24-hour format (HH:MM)
    const [hours, minutes] = time.split(':')
    const hour = parseInt(hours)
    const ampm = hour >= 12 ? 'PM' : 'AM'
    const displayHour = hour === 0 ? 12 : hour > 12 ? hour - 12 : hour
    return `${displayHour}:${minutes} ${ampm}`
}

// Category management state
const showCategoryDropdown = ref(false)
const showAddCategoryModal = ref(false)
const newCategoryName = ref<string>('')
const newCategoryColor = ref<string>('#6b7280')
const categories = ref<Category[]>([
    { id: 1, name: 'Meetings & Standups', color: 'bg-blue-500' },
    { id: 2, name: 'Deadlines & Urgent', color: 'bg-red-500' },
    { id: 3, name: 'Presentations & Demos', color: 'bg-green-500' },
    { id: 4, name: 'Reviews & Feedback', color: 'bg-purple-500' },
    { id: 5, name: 'Planning & Strategy', color: 'bg-yellow-500' }
])

// Event details functions
const openEventDetails = (event: Event) => {
    selectedEvent.value = event
    showEventDetails.value = true
}

// Category management functions
const toggleCategoryDropdown = () => {
    showCategoryDropdown.value = !showCategoryDropdown.value
}

const editCategory = (category: Category) => {
    console.log('Edit category:', category)
    // Add edit functionality here
}

const deleteCategory = (categoryId: number) => {
    categories.value = categories.value.filter(cat => cat.id !== categoryId)
}

const addCategory = () => {
    showAddCategoryModal.value = true
    newCategoryName.value = ''
    newCategoryColor.value = '#6b7280'
}

const closeAddCategoryModal = () => {
    showAddCategoryModal.value = false
    newCategoryName.value = ''
}

const saveNewCategory = () => {
    if (newCategoryName.value.trim()) {
        // Convert hex color to Tailwind class
        const colorClass = hexToTailwindClass(newCategoryColor.value)
        
        const newCategory: Category = {
            id: Date.now(), // Simple ID generation
            name: newCategoryName.value.trim(),
            color: colorClass
        }
        
        categories.value.push(newCategory)
        closeAddCategoryModal()
    }
}

// Convert hex color to closest Tailwind class
const hexToTailwindClass = (hex: string): string => {
    const normalizedHex = hex.toLowerCase()
    const colorMap: Record<string, string> = {
        '#ef4444': 'bg-red-500',
        '#f97316': 'bg-orange-500', 
        '#eab308': 'bg-yellow-500',
        '#22c55e': 'bg-green-500',
        '#06b6d4': 'bg-cyan-500',
        '#3b82f6': 'bg-blue-500',
        '#6366f1': 'bg-indigo-500',
        '#8b5cf6': 'bg-violet-500',
        '#a855f7': 'bg-purple-500',
        '#ec4899': 'bg-pink-500',
        '#f43f5e': 'bg-rose-500',
        '#84cc16': 'bg-lime-500',
        '#10b981': 'bg-emerald-500',
        '#14b8a6': 'bg-teal-500',
        '#6b7280': 'bg-gray-500',
        '#374151': 'bg-gray-700'
    }
    
    // Find closest color or default to gray
    return colorMap[normalizedHex] || `bg-[${hex}]`
}

const closeEventDetails = () => {
    console.log('closeEventDetails called in Calendar.vue') // Debug log
    showEventDetails.value = false
    selectedEvent.value = null
    // Force re-render by changing the key
    setTimeout(() => {
        console.log('showEventDetails after close:', showEventDetails.value)
    }, 100)
}

// Format time 
const formatTime = (time?: string): string => {
    if (!time) return ''
    
    
    if (time.includes('AM') || time.includes('PM')) {
        return time
    }
    
    // Handle 24-hour format (HH:MM)
    const [hours, minutes] = time.split(':')
    const hour = parseInt(hours)
    const ampm = hour >= 12 ? 'PM' : 'AM'
    const displayHour = hour === 0 ? 12 : hour > 12 ? hour - 12 : hour
    return `${displayHour}:${minutes} ${ampm}`
}

// Format event time display
const formatEventTime = (event: Event): string => {
    if (event.allDay) {
        if (event.startDate && event.endDate) {
            return `All Day: ${event.startDate.toLocaleDateString()} - ${event.endDate.toLocaleDateString()}`
        }
        return 'All Day'
    }
    
    if (event.startTime && event.endTime) {
        return `${formatTime(event.startTime)} - ${formatTime(event.endTime)}`
    }
    
    return event.time || ''
}

const handleColorInput = (e: globalThis.Event) => {
    const target = (e.target as HTMLInputElement) || (e.currentTarget as HTMLInputElement)
    if (target) {
        newCategoryColor.value = target.value
    }
}
</script>

<template>
    <Head title="Calendar" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-2 sm:m-4 flex flex-col gap-2 sm:gap-4 md:grid md:grid-cols-12">
            <!-- Events - bottom on mobile, left side on desktop -->
            <div class="order-2 md:order-1 md:col-span-3 flex flex-col md:h-full md:max-h-full">
                <!-- Events -->
                <div class="flex-1 rounded-lg shadow bg-[#171717] flex flex-col min-h-[240px] sm:min-h-[256px] md:min-h-0 md:max-h-full overflow-hidden">
                    <!-- Events Header with Filter -->
                    <div class="p-2 sm:p-3 md:p-4 space-y-3 shrink-0">
                        <div class="flex items-center justify-between">
                            <h3 class="text-white text-sm sm:text-base md:text-lg font-semibold">Events</h3>
                            <span class="text-xs text-gray-400">{{ getFilteredEvents.length }} event{{ getFilteredEvents.length !== 1 ? 's' : '' }}</span>
                        </div>
                        
                        <!-- Search Input -->
                        <div class="relative">
                            <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
                            <Input
                                v-model="eventSearch"
                                placeholder="Search events..."
                                class="w-full bg-[#2a2a2a] border-gray-600 text-white text-xs sm:text-sm pl-9 pr-8 focus:border-blue-500"
                            />
                            <Button
                                v-if="eventSearch"
                                variant="ghost"
                                size="sm"
                                @click="eventSearch = ''"
                                class="absolute right-1 top-1/2 transform -translate-y-1/2 h-6 w-6 p-0 text-gray-400 hover:text-white"
                            >
                                <X class="w-3 h-3" />
                            </Button>
                        </div>

                        <!-- Filters Row -->
                        <div class="flex gap-2">
                            <!-- Time Period Filter -->
                        <Select v-model="eventFilter">
                                <SelectTrigger class="flex-1 bg-[#2a2a2a] border-gray-600 text-white text-xs sm:text-sm">  
                                <SelectValue :placeholder="eventFilterLabel" />
                            </SelectTrigger>
                            <SelectContent class="bg-[#2a2a2a] border-gray-600">
                                <SelectGroup>
                                    <SelectLabel class="text-gray-400">Time Period</SelectLabel>
                                    <SelectItem value="today" class="text-white hover:bg-gray-700">
                                        Today
                                    </SelectItem>
                                    <SelectItem value="this-month" class="text-white hover:bg-gray-700">
                                        This Month
                                    </SelectItem>
                                    <SelectItem value="this-year" class="text-white hover:bg-gray-700">
                                        This Year
                                    </SelectItem>
                                    <SelectItem value="upcoming" class="text-white hover:bg-gray-700">
                                        Upcoming
                                    </SelectItem>
                                    <SelectItem value="all" class="text-white hover:bg-gray-700">   
                                        All Events 
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>

                            <!-- Category Filter -->
                            <Select v-model="selectedCategoryFilter">
                                <SelectTrigger class="flex-1 bg-[#2a2a2a] border-gray-600 text-white text-xs sm:text-sm">  
                                    <SelectValue placeholder="Category" />
                                </SelectTrigger>
                                <SelectContent class="bg-[#2a2a2a] border-gray-600">
                                    <SelectGroup>
                                        <SelectLabel class="text-gray-400">Category</SelectLabel>
                                        <SelectItem value="all" class="text-white hover:bg-gray-700">
                                            All Categories
                                        </SelectItem>
                                        <SelectItem 
                                            v-for="category in availableCategories" 
                                            :key="category.value"
                                            :value="category.value" 
                                            class="text-white hover:bg-gray-700"
                                        >
                                            <div class="flex items-center gap-2">
                                                <div class="w-2 h-2 rounded-full" :class="category.color"></div>
                                                {{ category.label }}
                                            </div>
                                        </SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    
                    <!-- Events List -->
                    <ScrollArea class="flex-1 p-3 min-h-0 max-h-full overflow-auto">
                        <div v-if="getFilteredEvents.length === 0" class="text-gray-400 text-sm text-center py-8">
                            <p v-if="eventSearch || selectedCategoryFilter !== 'all'">
                                No events found matching your search criteria.
                            </p>
                            <p v-else>
                            No events scheduled for {{ eventFilterLabel.toLowerCase() }}
                            </p>
                        </div>
                        
                        <div v-else class="space-y-3">
                            <div 
                                v-for="event in getFilteredEvents" 
                                :key="event.id"
                                class="relative p-3 rounded-lg bg-[#2a2a2a] hover:bg-[#333333] transition-colors duration-200 cursor-pointer"
                                @click="openEventDetails(event)"
                            >
                                <div class="flex items-start justify-between">
                                    <div class="flex-1 min-w-0">
                                        <h4 class="text-white font-medium text-sm mb-1 truncate">{{ event.title }}</h4>
                                        <p class="text-gray-400 text-xs mb-1">{{ formatEventDate(event.date) }} • {{ formatEventTime(event) }}</p>
                                        <p v-if="event.description" class="text-gray-500 text-xs mt-1 line-clamp-2 leading-relaxed">{{ event.description }}</p>
                                        <p v-else class="text-gray-500 text-xs mt-1 italic">No additional information available.</p>
                                    </div>
                                    <div 
                                        class="w-3 h-3 rounded-full flex-shrink-0 ml-3"
                                        :class="event.color"
                                    ></div>
                                </div>
                            </div>
                        </div>
                    </ScrollArea>
                </div>
            </div>

            <!-- Calendar - top on mobile, right side on desktop -->
            <div class="order-1 md:order-2 md:col-span-9 bg-[#171717] rounded-lg shadow flex flex-col md:h-full">
                <!-- Calendar Controls Header -->
                <div class="flex items-center justify-between p-2 sm:p-4 bg-[#171717] border-b border-gray-600">
                    <div class="flex items-center gap-2 sm:gap-4">
                        <div class="flex flex-col items-center text-white cursor-pointer hover:bg-gray-700 px-2 sm:px-3 py-1 sm:py-2 rounded transition-colors" @click="moveToday">
                            <span class="text-xs text-gray-400 uppercase tracking-wide">{{ currentMonthShort }}</span>
                            <span class="text-lg sm:text-2xl font-bold">{{ currentDay }}</span>
                        </div>
                        
                        <!-- Month/Year Display -->
                        <div class="flex flex-col relative">
                            <h1 class="text-base sm:text-xl font-bold text-white cursor-pointer hover:text-gray-300 transition-colors" @click="toggleDatePicker">
                                {{ currentMonthFull }} {{ currentYear }}
                            </h1>
                            <p class="text-xs text-gray-400 hidden sm:block">{{ monthDateRange }}</p>
                            
                            <!-- Clean Date Picker Dropdown -->
                            <div v-if="showDatePicker" class="absolute top-full left-0 mt-2 z-50 bg-[#2a2a2a] border border-gray-600 rounded-lg shadow-xl p-4 min-w-[280px]">
                                <!-- Year Navigation -->
                                <div class="flex items-center justify-between mb-4">
                                    <Button 
                                        @click="previousYear"
                                        variant="ghost"
                                        size="sm"
                                        class="p-2 text-gray-400 hover:text-white hover:bg-gray-700"
                                    >
                                        <ChevronDown class="w-4 h-4" />
                                    </Button>
                                    <h3 class="text-white font-semibold text-lg">{{ pickerYear }}</h3>
                                    <Button 
                                        @click="nextYear"
                                        variant="ghost"
                                        size="sm"
                                        class="p-2 text-gray-400 hover:text-white hover:bg-gray-700"
                                    >
                                        <ChevronUp class="w-4 h-4" />
                                    </Button>
                                </div>
                                
                                <!-- Month Grid -->
                                <div class="grid grid-cols-3 gap-2 mb-4">
                                    <Button
                                        v-for="(month, index) in monthNames"
                                        :key="month"
                                        @click="selectMonth(index, pickerYear)"
                                        variant="ghost"
                                        size="sm"
                                        class="p-3 text-sm text-gray-300 hover:text-white hover:bg-gray-700 text-center"
                                        :class="{
                                            'bg-blue-600 text-white hover:bg-blue-600': currentDate.getMonth() === index && currentDate.getFullYear() === pickerYear
                                        }"
                                    >
                                        {{ month.slice(0, 3) }}
                                    </Button>
                                </div>
                                
                                <!-- Close Button -->
                                <div class="flex justify-end">
                                    <Button 
                                        @click="showDatePicker = false"
                                        variant="ghost"
                                        size="sm"
                                        class="px-3 py-1 text-sm text-gray-400 hover:text-white hover:bg-gray-700"
                                    >
                                        Close
                                    </Button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Month Navigation Buttons - Side by Side -->
                        <div class="flex items-center gap-1 sm:gap-2">
                            <Button variant="outline" size="sm" @click="previousMonth" class="p-1 sm:p-2">
                                <ChevronDown class="w-3 h-3 sm:w-4 sm:h-4" />
                            </Button>
                            <Button variant="outline" size="sm" @click="nextMonth" class="p-1 sm:p-2">
                                <ChevronUp class="w-3 h-3 sm:w-4 sm:h-4" />
                            </Button>
                        </div>
                    </div> 
                    
                    <div class="flex items-center gap-3 relative">
                        <div class="relative">
                            <button 
                                @click="toggleCategoryDropdown"
                                class="text-gray-400 hover:text-white text-xs sm:text-sm font-medium transition-colors duration-200 flex items-center gap-1"
                            >
                                Category
                                <ChevronDown class="w-3 h-3" :class="{ 'rotate-180': showCategoryDropdown }" />
                            </button>
                            
                            <!-- Category Dropdown -->
                            <div v-if="showCategoryDropdown" class="absolute top-full right-0 mt-2 z-50 bg-[#2a2a2a] border border-gray-600 rounded-lg shadow-xl w-[280px] max-w-[90vw]">   
                                <div class="p-4">
                                    <h3 class="text-white font-medium mb-3 flex items-center gap-2">
                                        Categories ({{ categories.length }})
                                    </h3>
                                    <ScrollArea class="max-h-80">
                                        <div class="space-y-2">
                                            <div v-for="category in categories" :key="category.id"
                                                 class="flex items-center justify-between p-3 bg-[#1a1a1a] rounded hover:bg-[#333333] transition-colors">
                                                <div class="flex items-center gap-3 min-w-0 flex-1">
                                                    <div class="w-4 h-4 rounded-full flex-shrink-0" :class="category.color"></div>
                                                    <span class="text-white text-sm font-medium truncate">{{ category.name }}</span>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <Button 
                                                        @click="editCategory(category)"
                                                        variant="ghost"
                                                        size="sm"
                                                        class="p-1 text-gray-400 hover:text-blue-400 hover:bg-blue-500/10"
                                                    >
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                        </svg>
                                                    </Button>
                                                    <Button 
                                                        @click="deleteCategory(category.id)"
                                                        variant="ghost"
                                                        size="sm"
                                                        class="p-1 text-gray-400 hover:text-red-400 hover:bg-red-500/10"
                                                    >
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                    </Button>
                                                </div>
                                            </div>
                                        </div>
                                    </ScrollArea>
                                    <div class="flex justify-between items-center mt-3 pt-3 border-t border-gray-600">
                                        <Button 
                                            @click="addCategory"
                                            variant="ghost"
                                            size="sm"
                                            class="text-blue-400 hover:text-blue-300 hover:bg-blue-500/10 flex items-center gap-1"
                                        >
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                            </svg>
                                            Add Category
                                        </Button>
                                        <Button 
                                            @click="showCategoryDropdown = false"
                                            variant="ghost"
                                            size="sm"
                                            class="text-gray-400 hover:text-white hover:bg-gray-700"
                                        >
                                            Close
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <Button
                            @click="openModal" 
                            class="bg-blue-600 hover:bg-blue-500 text-white border-0 shadow-lg hover:shadow-xl transition-all duration-200 transform hover:scale-105 text-xs sm:text-sm px-2 sm:px-4 py-1 sm:py-2"
                            variant="default"
                            size="sm"
                        >
                            <span class="hidden sm:inline">+ New Event</span>
                            <span class="sm:hidden">+</span>
                        </Button>
                    </div>
                    
                    <!-- New Event Modal -->
                    <Dialog :open="showNewEventModal" @update:open="(open) => !open && closeModal()">
                        <DialogContent class="sm:max-w-2xl bg-[#171717] text-white border-gray-600">
                            <DialogHeader>
                                <DialogTitle>New Event</DialogTitle>
                            </DialogHeader>

                            <form @submit.prevent="handleNewEventSubmit" class="space-y-4">
                                <div class="space-y-2">
                                    <Label for="name" class="text-gray-300">Event Title:</Label>
                                    <Input
                                        id="name"
                                        v-model="newEventFormData.name"
                                        class="bg-gray-700 border-gray-600 text-white focus:border-blue-500"
                                        required
                                    />
                                </div>
                                <!-- Description -->
                                <div class="space-y-2">
                                    <Label for="description" class="text-gray-300">Description:</Label>
                                    <textarea
                                        id="description"
                                        v-model="newEventFormData.description"
                                        rows="3"
                                        class="bg-gray border-gray-600 flex min-h-[80px] w-full rounded-md border px-3 py-2 text-sm text-white placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 disabled:cursor-not-allowed disabled:opacity-50 resize-vertical"
                                        placeholder="Enter event description..."
                                        required
                                    ></textarea>
                                </div>
                                <!-- Location -->
                                <div class="space-y-2">
                                    <Label for="location" class="text-gray-300">Location:</Label>
                                    <Input
                                        id="location"
                                        v-model="newEventFormData.location"
                                        class="bg-gray-700 border-gray-600 text-white focus:border-blue-500"
                                        required
                                    />
                                </div>
                                
                                <div class="space-y-2">
                                    <Label class="text-gray-300">Category:</Label>
                                    <Select v-model="newEventFormData.category">
                                        <SelectTrigger
                                            class="w-full bg-gray text-white border-gray-600 transition-all duration-200"
                                            :class="newEventFormData.category ? `border-2 ${getSelectedCategoryObject()?.borderColor} shadow-lg` : ''"
                                        >
                                            <SelectValue placeholder="Select a Category" />
                                        </SelectTrigger>
                                        <SelectContent class="!bg-gray text-white border-gray-600">
                                            <SelectGroup>
                                                <SelectLabel>Categories</SelectLabel>
                                                <SelectItem
                                                    v-for="category in availableCategories"
                                                    :key="category.value"
                                                    :value="category.value"
                                                >
                                                    <div class="flex items-center gap-2">
                                                        <div class="w-3 h-3 rounded-full" :class="category.color"></div>
                                                        {{ category.label }}
                                                    </div>
                                                </SelectItem>
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                </div>
                                
                                <!-- Date and Time Row -->
                                <div class="space-y-3 sm:space-y-0 sm:flex sm:items-center sm:gap-3">
                                    <!-- Date picker - always visible -->
                                    <div class="w-full sm:w-auto">
                                        <Input
                                            v-if="!newEventFormData.allDay"
                                            type="date"
                                            id="date"
                                            v-model="newEventFormData.date"
                                            class="bg-gray-700 border-gray-600 text-white text-sm min-w-[140px] focus:border-blue-500"
                                            required
                                        />
                                        <Input
                                            v-else
                                            type="date"
                                            id="startDate"
                                            v-model="newEventFormData.startDate"
                                            class="bg-gray-700 border-gray-600 text-white text-sm min-w-[140px] focus:border-blue-500"
                                            required
                                        />
                                    </div>
                                    
                                    <!-- Time selectors OR Duration selector -->
                                    <template v-if="!newEventFormData.allDay">
                                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
                                            <div class="flex-1 sm:flex-none">
                                                <Select v-model="newEventFormData.startTime" required>
                                                    <SelectTrigger class="!bg-gray border-gray-600 text-white text-sm w-full sm:min-w-[120px]">
                                                        <SelectValue placeholder="Start time" />
                                                    </SelectTrigger>
                                                    <SelectContent class="!bg-gray text-white border-gray-600">
                                                        <SelectItem value="09:00">9:00 AM</SelectItem>
                                                        <SelectItem value="09:30">9:30 AM</SelectItem>
                                                        <SelectItem value="10:00">10:00 AM</SelectItem>
                                                        <SelectItem value="10:30">10:30 AM</SelectItem>
                                                        <SelectItem value="11:00">11:00 AM</SelectItem>
                                                        <SelectItem value="11:30">11:30 AM</SelectItem>
                                                        <SelectItem value="12:00">12:00 PM</SelectItem>
                                                        <SelectItem value="12:30">12:30 PM</SelectItem>
                                                        <SelectItem value="13:00">1:00 PM</SelectItem>
                                                        <SelectItem value="13:30">1:30 PM</SelectItem>
                                                        <SelectItem value="14:00">2:00 PM</SelectItem>
                                                        <SelectItem value="14:30">2:30 PM</SelectItem>
                                                        <SelectItem value="15:00">3:00 PM</SelectItem>
                                                        <SelectItem value="15:30">3:30 PM</SelectItem>
                                                        <SelectItem value="16:00">4:00 PM</SelectItem>
                                                        <SelectItem value="16:30">4:30 PM</SelectItem>
                                                        <SelectItem value="17:00">5:00 PM</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            
                                            <span class="text-gray-400 text-sm self-center px-2 sm:px-1">to</span>
                                            
                                            <div class="flex-1 sm:flex-none">
                                                <Select v-model="newEventFormData.endTime" required>
                                                    <SelectTrigger class="!bg-gray border-gray-600 text-white text-sm min-w-[120px]">
                                                        <SelectValue placeholder="End time" />
                                                    </SelectTrigger>
                                                    <SelectContent class="!bg-gray text-white border-gray-600">
                                                        <SelectItem value="09:30">9:30 AM</SelectItem>
                                                        <SelectItem value="10:00">10:00 AM</SelectItem>
                                                        <SelectItem value="10:30">10:30 AM</SelectItem>
                                                        <SelectItem value="11:00">11:00 AM</SelectItem>
                                                        <SelectItem value="11:30">11:30 AM</SelectItem>
                                                        <SelectItem value="12:00">12:00 PM</SelectItem>
                                                        <SelectItem value="12:30">12:30 PM</SelectItem>
                                                        <SelectItem value="13:00">1:00 PM</SelectItem>
                                                        <SelectItem value="13:30">1:30 PM</SelectItem>
                                                        <SelectItem value="14:00">2:00 PM</SelectItem>
                                                        <SelectItem value="14:30">2:30 PM</SelectItem>
                                                        <SelectItem value="15:00">3:00 PM</SelectItem>
                                                        <SelectItem value="15:30">3:30 PM</SelectItem>
                                                        <SelectItem value="16:00">4:00 PM</SelectItem>
                                                        <SelectItem value="16:30">4:30 PM</SelectItem>
                                                        <SelectItem value="17:00">5:00 PM</SelectItem>
                                                        <SelectItem value="17:30">5:30 PM</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- Duration selector (shown when all day is enabled) -->
                                    <template v-else>
                                        <div class="flex items-center">
                                            <Input
                                                type="date"
                                                id="endDate"
                                                v-model="newEventFormData.endDate"
                                                class="bg-gray-700 border-gray-600 text-white text-sm min-w-[140px] focus:border-blue-500"
                                                required
                                            />
                                        </div>

                                        <div class="flex items-center">
                                            <Select v-model="newEventFormData.duration">
                                                <SelectTrigger class="!bg-gray border-gray-600 text-white text-sm min-w-[120px] focus:border-blue-500 placeholder:text-gray-400 [&>span]:text-gray-400">
                                                    <SelectValue placeholder="Select Duration" class="text-gray-400 placeholder:text-gray-400" />
                                                </SelectTrigger>
                                                <SelectContent class="!bg-gray text-white border-gray-600">
                                                    <SelectItem value="1">1 day</SelectItem>
                                                    <SelectItem value="2">2 days</SelectItem>
                                                    <SelectItem value="3">3 days</SelectItem>
                                                    <SelectItem value="4">4 days</SelectItem>
                                                    <SelectItem value="5">5 days</SelectItem>
                                                    <SelectItem value="6">6 days</SelectItem>
                                                    <SelectItem value="7">1 week</SelectItem>
                                                    <SelectItem value="14">2 weeks</SelectItem>
                                                    <SelectItem value="30">1 month</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </template>
                                    
                                    <!-- All Day Toggle -->
                                    <div class="flex items-center justify-between sm:justify-end w-full sm:w-auto mt-2 sm:mt-0">
                                        <div class="flex items-center gap-2">
                                            <Label for="allDay" class="text-sm text-gray-300">All day</Label>
                                            <Switch
                                                id="allDay"
                                                v-model="newEventFormData.allDay"
                                                class="data-[state=checked]:bg-blue-500 data-[state=unchecked]:bg-gray-600 relative inline-flex h-6 w-11 items-center rounded-full transition-colors duration-200 focus:outline-none"
                                            >
                                                <span
                                                    class="pointer-events-none inline-block h-4 w-4 rounded-full bg-white shadow transform transition-transform duration-200"
                                                    :class="newEventFormData.allDay ? 'translate-x-6' : 'translate-x-1'"
                                                />
                                            </Switch>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex gap-4 justify-end pt-4">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        @click="closeModal"
                                        class="bg-transparent text-gray-400 border-gray-600 hover:bg-gray-700"
                                    >
                                        Cancel
                                    </Button>
                                    <Button
                                        type="submit"
                                        class="bg-blue-600 hover:bg-blue-700"
                                    >
                                        Submit
                                    </Button>
                                </div>
                            </form>
                        </DialogContent>
                    </Dialog>
                    
                    <!-- Event Details Modal -->
                    <Dialog :open="showEventDetails && !!selectedEvent" @update:open="(open) => { if (!open) closeEventDetails() }">
                        <DialogContent class="sm:max-w-4xl bg-[#171717] text-white border-gray-600 max-h-[95vh]">
                            <ScrollArea class="max-h-[90vh] pr-2">
                                <DialogHeader>
                                    <DialogTitle class="flex items-center gap-2.5 text-lg sm:text-xl transition-all duration-200">
                                        <div class="w-4 h-4 sm:w-5 sm:h-5 rounded-full flex-shrink-0 transition-all duration-200" :class="selectedEvent?.color"></div>
                                        <span class="truncate font-semibold">{{ selectedEvent?.title }}</span>
                                    </DialogTitle>
                                </DialogHeader>

                                <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 sm:gap-4 p-3 sm:p-4">
                                    <!-- Left Column - Event Details -->
                                    <div class="lg:col-span-2 space-y-3">
                                        <!-- Basic Information -->
                                        <div class="bg-[#2a2a2a] p-3 rounded-lg border border-gray-700/50">
                                            <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">
                                                Event Information
                                            </h3>
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                <div class="space-y-1">
                                                    <Label class="text-xs text-gray-400 font-medium">Date & Time</Label>
                                                    <p class="text-sm text-white font-medium">{{ selectedEvent?.date?.toLocaleDateString() }}</p>
                                                    <p class="text-xs text-gray-400">
                                                        <template v-if="selectedEvent?.allDay && selectedEvent?.startDate && selectedEvent?.endDate">
                                                            All Day: {{ selectedEvent.startDate.toLocaleDateString() }} - {{ selectedEvent.endDate.toLocaleDateString() }}
                                                        </template>
                                                        <template v-else-if="selectedEvent?.allDay">
                                                            All Day
                                                        </template>
                                                        <template v-else-if="selectedEvent?.startTime && selectedEvent?.endTime">
                                                            {{ formatEventDetailsTime(selectedEvent.startTime) }} - {{ formatEventDetailsTime(selectedEvent.endTime) }}
                                                        </template>
                                                        <template v-else>
                                                            {{ selectedEvent?.time }}
                                                        </template>
                                                    </p>
                                                </div>
                                                <div class="space-y-1">
                                                    <Label class="text-xs text-gray-400 font-medium">Category</Label>
                                                    <div class="flex items-center gap-2">
                                                        <Badge
                                                            variant="secondary"
                                                            class="text-xs text-white border-0 px-2 py-0.5"
                                                            :class="selectedEvent?.color"
                                                        >
                                                            {{ getCategoryName(selectedEvent?.color || '') }}
                                                        </Badge>
                                                    </div>
                                                </div>
                                                <div class="space-y-1">
                                                    <Label class="text-xs text-gray-400 font-medium">Location</Label>
                                                    <p class="text-sm text-white">{{ eventDetailsInfo.location }}</p>
                                                </div>
                                                <div class="space-y-1">
                                                    <Label class="text-xs text-gray-400 font-medium">Organizer</Label>
                                                    <p class="text-sm text-white">{{ eventDetailsInfo.organizer }}</p>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Description -->
                                        <div class="bg-[#2a2a2a] p-3 rounded-lg border border-gray-700/50 transition-all duration-200">
                                            <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">
                                                Description
                                            </h3>
                                            <p class="text-sm text-gray-300 leading-relaxed transition-all duration-200">
                                                {{ selectedEvent?.description || 'No description provided.' }}
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Right Column - Going -->
                                    <div class="flex flex-col">
                                        <div class="bg-[#2a2a2a] p-3 rounded-lg border border-gray-700/50 flex flex-col flex-1">
                                            <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">
                                                Going ({{ attendees.length }})
                                            </h3>
                                            <ScrollArea class="flex-1 max-h-64">
                                                <div class="space-y-1.5">
                                                    <div
                                                        v-for="attendee in attendees"
                                                        :key="attendee.id"
                                                        class="flex items-center gap-2 p-2 bg-[#1a1a1a] rounded-md hover:bg-[#222222] transition-all duration-200"
                                                    >
                                                        <Avatar class="w-9 h-9 shrink-0">
                                                            <AvatarFallback
                                                                class="bg-gradient-to-br from-blue-500 to-purple-600 text-white text-xs font-semibold"
                                                            >
                                                                {{ attendee.avatar }}
                                                            </AvatarFallback>
                                                        </Avatar>
                                                        <div class="min-w-0 flex-1">
                                                            <p class="text-sm text-white font-medium truncate">{{ attendee.name }}</p>
                                                            <p class="text-xs text-gray-400 truncate">{{ attendee.role }}</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </ScrollArea>
                                        </div>
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="flex flex-col-reverse sm:flex-row gap-2 justify-end mt-4 pt-3 border-t border-gray-700/50">
                                    <Button
                                        @click="closeEventDetails"
                                        variant="outline"
                                        size="sm"
                                        class="bg-transparent text-gray-300 border-gray-600 hover:bg-gray-800 hover:text-white transition-all duration-200"
                                    >
                                        Cancel
                                    </Button>
                                    <Button
                                        @click="handleDeleteClick"
                                        variant="destructive"
                                        size="sm"
                                        class="bg-red-600 hover:bg-red-700 text-white transition-all duration-200"
                                    >
                                        Delete
                                    </Button>
                                    <Button
                                        variant="default"
                                        size="sm"
                                        class="bg-blue-600 hover:bg-blue-700 text-white transition-all duration-200"
                                    >
                                        Edit
                                    </Button>
                                    <Button
                                        @click="toggleAttendance"
                                        variant="default"
                                        size="sm"
                                        :class="isAttending ? 'bg-green-600 hover:bg-green-700 text-white' : 'bg-green-600 hover:bg-green-700 text-white'"
                                        class="transition-all duration-200"
                                    >
                                        <span v-if="isAttending" class="transition-all duration-200">Going</span>
                                        <span v-else class="transition-all duration-200">I'm Going</span>
                                    </Button>
                                </div>
                            </ScrollArea>
                        </DialogContent>
                    </Dialog>

                    <!-- Delete Confirmation Dialog -->
                    <Dialog :open="showDeleteConfirm" @update:open="(open) => { if (!open) cancelDelete() }">
                        <DialogContent class="sm:max-w-md bg-[#171717] text-white border-gray-600">
                            <DialogHeader>
                                <DialogTitle>Delete Event</DialogTitle>
                            </DialogHeader>
                            <div class="py-3">
                                <p class="text-sm text-gray-300 mb-3">
                                    Are you sure you want to delete this event? This action cannot be undone.
                                </p>
                                <div class="flex items-center justify-center gap-2 mb-3">
                                    <div class="text-2xl font-bold text-red-500 transition-all duration-200">
                                        {{ deleteCountdown }}
                                    </div>
                                    <span class="text-sm text-gray-400">seconds remaining</span>
                                </div>
                            </div>
                            <div class="flex flex-col-reverse sm:flex-row gap-2 justify-end">
                                <Button
                                    @click="cancelDelete"
                                    variant="outline"
                                    size="sm"
                                    class="bg-transparent text-gray-300 border-gray-600 hover:bg-gray-800 hover:text-white transition-all duration-200"
                                >
                                    Cancel
                                </Button>
                                <Button
                                    @click="confirmDelete"
                                    variant="destructive"
                                    size="sm"
                                    :disabled="deleteCountdown > 0"
                                    class="bg-red-600 hover:bg-red-700 text-white transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    {{ deleteCountdown > 0 ? `Confirm (${deleteCountdown}s)` : 'Confirm Delete' }}
                                </Button>
                            </div>
                        </DialogContent>
                    </Dialog>
                    
                    <!-- Add Category Modal -->
                    <Dialog :open="showAddCategoryModal" @update:open="(open) => { if (!open) closeAddCategoryModal() }">
                        <DialogContent class="sm:max-w-md bg-[#171717] text-white border-gray-600">
                            <DialogHeader>
                                <DialogTitle>Add New Category</DialogTitle>
                            </DialogHeader>

                            <div class="space-y-4">
                                <!-- Category Name -->
                                <div class="space-y-2">
                                    <Label for="categoryName" class="text-gray-300">Category Name:</Label>
                                    <Input 
                                        id="categoryName"
                                        v-model="newCategoryName"
                                        placeholder="Enter category name"
                                        class="bg-gray-700 border-gray-600 text-white focus:border-blue-500"
                                        @keyup.enter="saveNewCategory"
                                    />
                                </div>

                                <!-- Color Picker -->
                                <div class="space-y-2">
                                    <Label class="text-gray-300">Category Color:</Label>
                                    <div class="flex items-center gap-3">
                                        <!-- Color preview -->
                                        <div 
                                            class="w-8 h-8 rounded-full border-2 border-gray-400"
                                            :style="{ backgroundColor: newCategoryColor }"
                                        ></div>
                                        
                                        <!-- HTML5 Color Picker -->
                                        <input 
                                            type="color" 
                                            :value="newCategoryColor"
                                            @input="handleColorInput"
                                            @change="handleColorInput"
                                            class="w-12 h-8 rounded border border-gray-600 bg-gray-700 cursor-pointer"
                                        />
                                        
                                        <!-- Hex input -->
                                        <Input 
                                            v-model="newCategoryColor"
                                            placeholder="#000000"
                                            class="bg-gray-700 border-gray-600 text-white focus:border-blue-500 w-24 text-sm"
                                        />
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="flex gap-3 justify-end pt-4">
                                    <Button 
                                        type="button" 
                                        variant="outline"
                                        @click="closeAddCategoryModal"
                                        class="bg-transparent text-gray-400 border-gray-600 hover:bg-gray-700"
                                    >
                                        Cancel
                                    </Button>
                                    <Button 
                                        type="button"
                                        @click="saveNewCategory"
                                        :disabled="!newCategoryName.trim()"
                                        class="bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        Add Category
                                    </Button>
                                </div>
                            </div>
                        </DialogContent>
                    </Dialog>
                </div>

                <!-- Calendar Grid -->
                <div class="flex flex-col">
                    <!-- Day Headers -->
                    <div class="grid grid-cols-7 shrink-0 border-b border-gray-600 bg-[#2a2a2a]">
                        <div class="text-center text-xs font-medium text-gray-400 p-1 sm:p-2 md:p-3">Sun</div>
                        <div class="text-center text-xs font-medium text-gray-400 p-1 sm:p-2 md:p-3 border-l border-gray-600">Mon</div>
                        <div class="text-center text-xs font-medium text-gray-400 p-1 sm:p-2 md:p-3 border-l border-gray-600">Tue</div>
                        <div class="text-center text-xs font-medium text-gray-400 p-1 sm:p-2 md:p-3 border-l border-gray-600">Wed</div>
                        <div class="text-center text-xs font-medium text-gray-400 p-1 sm:p-2 md:p-3 border-l border-gray-600">Thu</div>
                        <div class="text-center text-xs font-medium text-gray-400 p-1 sm:p-2 md:p-3 border-l border-gray-600">Fri</div>
                        <div class="text-center text-xs font-medium text-gray-400 p-1 sm:p-2 md:p-3 border-l border-gray-600">Sat</div>
                    </div>

                    <!-- Calendar Days -->
                    <div class="grid grid-cols-7">
                    <template v-for="(dayData, index) in calendarDays" :key="`${dayData.date.getTime()}-${index}`">
                        <div 
                            class="aspect-square p-1 sm:p-2 md:p-3 text-sm border-b border-r border-gray-600 relative hover:bg-gray-800/50 transition-all duration-200 cursor-pointer group flex flex-col"
                            :class="[
                                dayData.isCurrentMonth ? 'text-white' : 'text-gray-500',
                                dayData.date.toDateString() === today.toDateString() ? 'bg-blue-600/20 border-blue-500/30' : ''
                            ]"
                        >
                            <span 
                                class="font-semibold text-xs sm:text-sm md:text-base transition-colors duration-200 block shrink-0"
                                :class="dayData.date.toDateString() === today.toDateString() ? 'text-blue-300' : ''"
                            >
                                {{ dayData.day }}
                            </span>
                            
                            <!-- Event indicators - more compact on mobile -->
                            <div v-if="dayData.events.length > 0" class="mt-0.5 sm:mt-1 md:mt-2 space-y-0.5 sm:space-y-1 flex-1 min-h-0 overflow-hidden">
                                <!-- Mobile: Show only dots -->
                                <div class="sm:hidden flex gap-1 flex-wrap">
                                    <div 
                                        v-for="event in dayData.events.slice(0, 3)" 
                                        :key="event.id"
                                        class="w-1.5 h-1.5 rounded-full"
                                        :class="event.color"
                                        :title="`${event.title} - ${formatEventTime(event)}`"
                                        @click="openEventDetails(event)"
                                    ></div>
                                    <span v-if="dayData.events.length > 3" class="text-xs text-gray-400 ml-1">+{{ dayData.events.length - 3 }}</span>
                                </div>
                                
                                <!-- Tablet and Desktop: Show event cards -->
                                <div class="hidden sm:block">
                                    <div 
                                        v-for="event in dayData.events.slice(0, 2)" 
                                        :key="event.id"
                                        class="text-xs px-2 py-1.5 rounded-md truncate transition-all duration-200 cursor-pointer font-medium text-white shadow-sm hover:shadow-md border border-black/20"
                                        :class="[event.color, 'hover:brightness-110']"
                                        :title="`${event.title} - ${formatEventTime(event)}`"
                                        @click="openEventDetails(event)"
                                    >
                                        {{ event.title }}
                                    </div>
                                    <div v-if="dayData.events.length > 2" class="text-xs text-gray-400 font-medium px-1 mt-1">
                                        +{{ dayData.events.length - 2 }} more
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>