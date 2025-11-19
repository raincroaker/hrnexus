<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import api from '@/lib/axios';
import { toast } from 'vue-sonner';

// Props
interface Department {
    id: number;
    code: string;
    name: string;
}

interface DepartmentEvent {
    id: number;
    title: string;
    description?: string;
    location?: string;
    start_date: string;
    end_date?: string;
    start_time?: string;
    end_time?: string;
    is_all_day: boolean;
    visibility?: 'everyone' | 'department';
    category?: {
        id: number;
        name: string;
        color: string;
    };
    creator: {
        id: number;
        name: string;
    };
    department?: {
        id: number;
        code: string;
        name: string;
    } | null;
}

// Inertia props from backend
const props = defineProps<{
    currentUser?: {
        id: number;
        name: string;
        email: string;
        role: 'admin' | 'department_manager' | 'employee';
    };
    userDepartment?: Department | null;
    events?: EventBackend[];
    departments?: Department[];
    categories?: {
        id: number;
        name: string;
        color: string;
    }[];
}>();

import { ChevronUp, ChevronDown, Search, X, Pencil, Trash2, UserCheck, UserPlus } from "lucide-vue-next"
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

// Type definitions - Backend format (matches API)
interface EventBackend {
    id: number;
    title: string;
    description?: string | null;
    location?: string | null;
    is_all_day: boolean;
    start_date: string; // YYYY-MM-DD
    end_date?: string | null; // YYYY-MM-DD
    start_time?: string | null; // HH:MM:SS
    end_time?: string | null; // HH:MM:SS
    visibility: 'everyone' | 'department';
    category?: {
        id: number;
        name: string;
        color: string;
    } | null;
    creator: {
        id: number;
        name: string;
    };
    department?: {
        id: number;
        code: string;
        name: string;
    } | null;
    attendees?: {
        id: number;
        name: string;
        email: string;
        department_code?: string | null;
        position?: string | null;
    }[];
    current_user_is_attending?: boolean;
}

// Frontend display format (for calendar rendering)
interface Event {
    id: number;
    title: string;
    date: Date; // For calendar grid display
    time?: string;
    startTime?: string;
    endTime?: string;
    allDay?: boolean;
    startDate?: Date;
    endDate?: Date;
    type?: string | null;
    color?: string | null; // Tailwind class
    colorHex?: string; // Hex color for inline styles
    description?: string;
    category?: string;
    // Additional backend fields
    location?: string;
    visibility?: 'everyone' | 'department';
    creator?: {
        id: number;
        name: string;
    };
    department?: {
        id: number;
        code: string;
        name: string;
    } | null;
    attendees?: {
        id: number;
        name: string;
        email: string;
    }[];
}

type EventFilterType = 'today' | 'upcoming' | 'this-week' | 'this-month' | 'this-year' | 'all';

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

// Convert hex color to closest Tailwind class (moved here to be available for transformEvent)
const hexToTailwindClass = (hex: string): string | null => {
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
        '#f59e0b': 'bg-amber-500',
        '#84cc16': 'bg-lime-500',
        '#10b981': 'bg-emerald-500',
        '#14b8a6': 'bg-teal-500',
        '#6b7280': 'bg-background-500',
        '#374151': 'bg-background'
    }
    
    // Return mapped class or null to indicate fallback
    return colorMap[normalizedHex] ?? null
}

// Transform backend event to frontend format
const transformEvent = (backendEvent: EventBackend): Event => {
    const startDate = new Date(backendEvent.start_date);
    const endDate = backendEvent.end_date ? new Date(backendEvent.end_date) : undefined;
    
    // Get color from category or default
    // Use hexToTailwindClass to convert hex to Tailwind class
    const eventColorHex = backendEvent.category?.color || '#6b7280'; // gray-500 default
    const eventColorClass = hexToTailwindClass(eventColorHex);
    
    return {
        id: backendEvent.id,
        title: backendEvent.title,
        date: startDate, // For calendar grid display
        startTime: backendEvent.start_time ? backendEvent.start_time.substring(0, 5) : undefined, // Convert HH:MM:SS to HH:MM
        endTime: backendEvent.end_time ? backendEvent.end_time.substring(0, 5) : undefined,
        allDay: backendEvent.is_all_day,
        startDate: backendEvent.is_all_day ? startDate : undefined,
        endDate: backendEvent.is_all_day && endDate ? endDate : undefined,
        type: backendEvent.category?.name || null,
        color: eventColorClass,
        colorHex: eventColorHex, // Store hex for inline styles as fallback
        description: backendEvent.description || undefined,
        category: backendEvent.category?.name,
        location: backendEvent.location || undefined,
        visibility: backendEvent.visibility,
        creator: backendEvent.creator,
        department: backendEvent.department || undefined,
        attendees: backendEvent.attendees || [],
    };
};

// Transform backend events to frontend format
const allEvents = computed<Event[]>(() => {
    if (!props.events || props.events.length === 0) {
        return [];
    }
    return props.events.map(transformEvent);
});


// Use props from backend
const userDepartment = computed(() => props.userDepartment || null);
const departments = computed(() => props.departments || []);

// Department events are now part of the main events array (filtered by visibility='department')
const departmentEvents = computed<DepartmentEvent[]>(() => {
    if (!props.events) {
        return [];
    }
    
    return props.events
        .filter(event => event.visibility === 'department')
        .map(event => ({
            id: event.id,
            title: event.title,
            description: event.description || undefined,
            location: event.location || undefined,
            start_date: event.start_date,
            end_date: event.end_date || undefined,
            start_time: event.start_time || undefined,
            end_time: event.end_time || undefined,
            is_all_day: event.is_all_day,
            visibility: event.visibility,
            category: event.category || undefined,
            creator: event.creator,
            department: event.department || undefined,
        }));
});


// Calendar state
const showNewEventModal = ref<boolean>(false);
const showDatePicker = ref<boolean>(false);
const currentDate = ref<Date>(new Date());
const eventFilter = ref<EventFilterType>('today'); // Default to 'today'

// Department events filters
const deptEventSearch = ref<string>('');
const deptEventTimeFilter = ref<string>('today'); // Default to 'today'
const deptEventCategoryFilter = ref<string>('all');
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

// Filtered department events
const filteredDepartmentEvents = computed(() => {
    if (!departmentEvents.value || departmentEvents.value.length === 0) return [];
    if (!userDepartment.value) return []; // No department assigned, return empty
    
    const now = new Date();
    const searchTerm = deptEventSearch.value.toLowerCase().trim();
    
    return departmentEvents.value.filter((event) => {
        // Department filter - only show events from user's department
        if (event.department && event.department.id !== userDepartment.value?.id) {
            return false;
        }
        
        // Time filter
        const eventDate = new Date(event.start_date);
        let matchesTimeFilter = false;
        
        switch (deptEventTimeFilter.value) {
            case 'today':
                matchesTimeFilter = (
                    eventDate.getDate() === now.getDate() &&
                    eventDate.getMonth() === now.getMonth() &&
                    eventDate.getFullYear() === now.getFullYear()
                );
                break;
            case 'this-week':
                const weekStart = new Date(now);
                weekStart.setDate(now.getDate() - now.getDay());
                const weekEnd = new Date(weekStart);
                weekEnd.setDate(weekStart.getDate() + 6);
                matchesTimeFilter = eventDate >= weekStart && eventDate <= weekEnd;
                break;
            case 'this-month':
                matchesTimeFilter = (
                    eventDate.getMonth() === now.getMonth() &&
                    eventDate.getFullYear() === now.getFullYear()
                );
                break;
            case 'upcoming':
                matchesTimeFilter = eventDate >= now;
                break;
            case 'all':
            default:
                matchesTimeFilter = true;
        }
        
        if (!matchesTimeFilter) return false;
        
        // Category filter
        if (deptEventCategoryFilter.value !== 'all') {
            if (!event.category || event.category.name !== deptEventCategoryFilter.value) {
                return false;
            }
        }
        
        // Search filter
        if (searchTerm) {
            const titleMatch = event.title.toLowerCase().includes(searchTerm);
            const descriptionMatch = event.description?.toLowerCase().includes(searchTerm) || false;
            
            if (!titleMatch && !descriptionMatch) {
                return false;
            }
        }
        
        return true;
    });
});

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
                case 'upcoming':
                    matchesTimeFilter = event.date >= now;
                    break;
                case 'this-week':
                    const weekStart = new Date(now);
                    weekStart.setDate(now.getDate() - now.getDay());
                    weekStart.setHours(0, 0, 0, 0);
                    const weekEnd = new Date(weekStart);
                    weekEnd.setDate(weekStart.getDate() + 6);
                    weekEnd.setHours(23, 59, 59, 999);
                    matchesTimeFilter = event.date >= weekStart && event.date <= weekEnd;
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
                case 'all':
                    matchesTimeFilter = true;
                    break;
                default:
                    matchesTimeFilter = true;
            }

            if (!matchesTimeFilter) return false;

            // Category filter
            if (selectedCategoryFilter.value !== 'all') {
                if (event.category !== selectedCategoryFilter.value) {
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
        case 'upcoming':
            return 'Upcoming';
        case 'this-week':
            return 'This Week';
        case 'this-month':
            return `${currentMonthFull.value} ${currentYear.value}`;
        case 'this-year':
            return `${currentYear.value}`;
        case 'all':
            return 'All Events';
        default:
            return 'Today';
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
// Track window width for responsive +N indicator
const isMobile = ref(false);

// Update on mount and resize
let updateIsMobile: (() => void) | null = null;

onMounted(() => {
    updateIsMobile = () => {
        isMobile.value = window.innerWidth < 640; // sm breakpoint
    };
    updateIsMobile();
    window.addEventListener('resize', updateIsMobile);
});

onUnmounted(() => {
    if (updateIsMobile) {
        window.removeEventListener('resize', updateIsMobile);
    }
});

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
    // Set default visibility based on role
    if (props.currentUser?.role === 'department_manager') {
        newEventFormData.value.visibility = 'department'
        newEventFormData.value.department_id = userDepartment.value?.id || null
    } else {
        newEventFormData.value.visibility = 'everyone'
        newEventFormData.value.department_id = null
    }
    showNewEventModal.value = true;
};

const closeModal = (): void => {
    showNewEventModal.value = false;
};

// Removed addEvent and handleEventSubmit - now using API directly in handleNewEventSubmit

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

// Computed property to determine if user can create "everyone" events
const canCreateEveryoneEvents = computed(() => {
    return props.currentUser?.role === 'admin'
})

// Computed property to check if user is department manager
const isDepartmentManager = computed(() => {
    return props.currentUser?.role === 'department_manager'
})

// Computed property to check if user can edit/delete the selected event
const canEditDeleteEvent = computed(() => {
    if (!selectedEvent.value || !props.currentUser) return false
    
    // Admins can edit/delete any event
    if (props.currentUser.role === 'admin') {
        return true
    }
    
    // Department managers can only edit/delete:
    // 1. Events they created, OR
    // 2. Department events from their own department
    // They cannot edit/delete "everyone" events
    if (isDepartmentManager.value) {
        // Cannot edit/delete "everyone" events
        if (selectedEvent.value.visibility === 'everyone') {
            return false
        }
        
        // Check if they created the event
        if (selectedEvent.value.creator?.id === props.currentUser.id) {
            return true
        }
        
        // Check if it's a department event from their department
        if (selectedEvent.value.visibility === 'department' && 
            selectedEvent.value.department?.id === userDepartment.value?.id) {
            return true
        }
        
        return false
    }
    
    // Employees cannot edit/delete events
    return false
})

// Computed property to check if user can manage categories
const canManageCategories = computed(() => {
    // Only admins can manage categories
    return props.currentUser?.role === 'admin'
})

// Computed property to check if user can create events
const canCreateEvents = computed(() => {
    // Only admins and department managers can create events
    return props.currentUser?.role === 'admin' || props.currentUser?.role === 'department_manager'
})

// New Event form data - matching CalendarEvent model structure
// Model fields: user_id (set on backend), category_id (nullable), department_id (nullable, required if visibility='department'),
// visibility (enum: 'everyone'|'department'), title (required), description (nullable), location (nullable),
// is_all_day (boolean), start_date (required), end_date (nullable, typically for all-day events),
// start_time (nullable, null for all-day), end_time (nullable, null for all-day)
const newEventFormData = ref({
    title: '', // required - maps to 'title' in CalendarEvent
    description: '', // nullable - maps to 'description' in CalendarEvent
    location: '', // nullable - maps to 'location' in CalendarEvent
    category_id: null, // nullable - maps to 'category_id' in CalendarEvent (foreign key to event_categories)
    start_date: '', // required - maps to 'start_date' in CalendarEvent (date format: YYYY-MM-DD)
    end_date: '', // nullable - maps to 'end_date' in CalendarEvent (for all-day events, null for timed events)
    start_time: '', // nullable - maps to 'start_time' in CalendarEvent (time format: HH:MM, null for all-day events)
    end_time: '', // nullable - maps to 'end_time' in CalendarEvent (time format: HH:MM, null for all-day events)
    is_all_day: false, // maps to 'is_all_day' in CalendarEvent (boolean)
    visibility: 'everyone', // maps to 'visibility' in CalendarEvent (enum: 'everyone' or 'department')
    department_id: null as number | null // nullable - maps to 'department_id' in CalendarEvent (required if visibility='department')
})

const availableCategories = computed(() => {
    if (!props.categories || props.categories.length === 0) {
        return [];
    }
    
    return props.categories.map(cat => {
        const colorClass = hexToTailwindClass(cat.color);
        const borderClass = colorClass ? colorClass.replace('bg-', 'border-') : null;
        
        return {
            id: cat.id,
            value: cat.id,
            label: cat.name,
            colorClass: colorClass ?? null,
            borderClass,
            colorHex: cat.color,
        };
    });
})

// Auto-update end date when start date changes (if end date is empty or before start date)
watch(() => newEventFormData.value.start_date, (startDate) => {
    if (startDate && newEventFormData.value.is_all_day) {
        const start = new Date(startDate)
        const currentEnd = newEventFormData.value.end_date ? new Date(newEventFormData.value.end_date) : null
        
        // If end date is empty or before start date, set it to start date
        if (!currentEnd || currentEnd < start) {
            newEventFormData.value.end_date = startDate
        }
    }
})

// Clear end_date when switching from all-day to timed events
// Clear start_time and end_time when switching from timed to all-day events
watch(() => newEventFormData.value.is_all_day, (isAllDay) => {
    if (isAllDay) {
        // Switching to all-day: clear times, set end_date to start_date if empty
        newEventFormData.value.start_time = ''
        newEventFormData.value.end_time = ''
        if (newEventFormData.value.start_date && !newEventFormData.value.end_date) {
            newEventFormData.value.end_date = newEventFormData.value.start_date
        }
    } else {
        // Switching to timed: clear end_date (for timed events, end_date should be null)
        newEventFormData.value.end_date = ''
    }
})

// Watch visibility changes - auto-set department for department managers
watch(() => newEventFormData.value.visibility, (visibility) => {
    if (visibility === 'department' && !canCreateEveryoneEvents.value) {
        // Department managers: auto-set their department
        newEventFormData.value.department_id = userDepartment.value?.id || null
    } else if (visibility === 'everyone') {
        // Clear department when switching to "everyone"
        newEventFormData.value.department_id = null
    }
})

// Computed properties for min dates and times
const minDateForNewEvent = computed(() => {
    if (newEventFormData.value.is_all_day && newEventFormData.value.start_date) {
        return newEventFormData.value.start_date
    }
    if (!newEventFormData.value.is_all_day && newEventFormData.value.start_date) {
        return newEventFormData.value.start_date
    }
    return '' // Allow all dates when no start date is selected
})

const minTimeForEndTime = computed(() => {
    if (!newEventFormData.value.start_time) return ''
    // Return the next 30-minute slot after start time
    const [hours, minutes] = newEventFormData.value.start_time.split(':').map(Number)
    const startMinutes = hours * 60 + minutes
    const nextSlot = startMinutes + 30 // Next 30-minute slot
    const nextHours = Math.floor(nextSlot / 60)
    const nextMins = nextSlot % 60
    return `${String(nextHours).padStart(2, '0')}:${String(nextMins).padStart(2, '0')}`
})

// Calculate duration in days for all-day events (new event form)
const allDayDuration = computed(() => {
    if (!newEventFormData.value.is_all_day || !newEventFormData.value.start_date || !newEventFormData.value.end_date) {
        return null
    }
    const start = new Date(newEventFormData.value.start_date)
    const end = new Date(newEventFormData.value.end_date)
    const diffTime = Math.abs(end.getTime() - start.getTime())
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1 // +1 to include both start and end days
    
    if (diffDays === 1) {
        return '1 Day'
    }
    return `${diffDays} Days`
})

// Get selected category object for display
const getSelectedCategoryObject = () => {
    // Find category by matching the ID stored in category_id
    return availableCategories.value.find(cat => cat.id === newEventFormData.value.category_id)
}

// Handle new event submit - format data to match CalendarEvent model
const handleNewEventSubmit = async () => {
    // Validation: If visibility is 'department', department_id is required
    if (newEventFormData.value.visibility === 'department' && !newEventFormData.value.department_id) {
        toast.error('Please select a department when visibility is set to "Specific Department"')
        return
    }
    
    // Validation: For non-all-day events, start_time and end_time are required
    if (!newEventFormData.value.is_all_day) {
        if (!newEventFormData.value.start_time || !newEventFormData.value.end_time) {
            toast.error('Please select both start time and end time for timed events')
            return
        }
    }
    
    // Prepare data matching CalendarEvent model structure
    // Note: user_id will be set on the backend from the authenticated user
    const eventPayload = {
        title: newEventFormData.value.title.trim(),
        description: newEventFormData.value.description?.trim() || null,
        location: newEventFormData.value.location?.trim() || null,
        category_id: newEventFormData.value.category_id || null,
        department_id: newEventFormData.value.visibility === 'department' ? newEventFormData.value.department_id : null,
        visibility: newEventFormData.value.visibility, // 'everyone' or 'department'
        is_all_day: newEventFormData.value.is_all_day,
        start_date: newEventFormData.value.start_date,
        // For all-day events: end_date can be set (nullable), start_time and end_time must be null
        // For timed events: end_date should be null (or same as start_date), start_time and end_time are required
        end_date: newEventFormData.value.is_all_day ? (newEventFormData.value.end_date || null) : null,
        start_time: newEventFormData.value.is_all_day ? null : newEventFormData.value.start_time,
        end_time: newEventFormData.value.is_all_day ? null : newEventFormData.value.end_time,
    }
    
    isSaving.value = true
    
    try {
        await api.post('/calendar-events', eventPayload)
        toast.success('Event created successfully')
        closeModal()
        
        // Reset form after submission
        newEventFormData.value = {
            title: '',
            description: '',
            location: '',
            category_id: null,
            start_date: '',
            end_date: '',
            start_time: '',
            end_time: '',
            is_all_day: false,
            visibility: 'everyone',
            department_id: null
        }
        
        // Refresh the page to get updated events
        router.reload({ only: ['events'] })
    } catch (error: any) {
        console.error('Error creating event:', error)
        if (error.response?.data?.message) {
            toast.error(error.response.data.message)
        } else if (error.response?.data?.errors) {
            const firstError = Object.values(error.response.data.errors)[0] as string[]
            toast.error(firstError[0] || 'Failed to create event')
        } else {
            toast.error('Failed to create event. Please try again.')
        }
    } finally {
        isSaving.value = false
    }
}

// Event details modal state
const showEventDetails = ref(false)
const selectedEvent = ref<Event | null>(null)

// Day events modal state
const showDayEventsModal = ref(false)
const selectedDayEvents = ref<Event[]>([])
const selectedDate = ref<Date | null>(null)

// Event details data - will be populated from selected event
const attendees = ref<{ id: number; name: string; email: string; department_code?: string | null; position?: string | null }[]>([])

const eventDetailsInfo = ref({
    location: '',
    organizer: '',
})

const isAttending = ref(false)
const isTogglingAttendance = ref(false)
const showDeleteConfirm = ref(false)
const deleteCountdown = ref(5)
const deleteCountdownInterval = ref<number | null>(null)

// Loading states
const isSaving = ref(false)
const isUpdating = ref(false)
const isDeleting = ref(false)

// Edit event state
const isEditing = ref(false)
const editEventFormData = ref({
    title: '',
    description: '',
    location: '',
    category_id: null as number | null,
    visibility: 'everyone' as 'everyone' | 'department',
    department_id: null as number | null,
    start_date: '',
    end_date: '',
    start_time: '',
    end_time: '',
    is_all_day: false,
})

// Computed properties for edit form min dates and times
const minDateForEditEvent = computed(() => {
    if (editEventFormData.value.is_all_day && editEventFormData.value.start_date) {
        return editEventFormData.value.start_date
    }
    if (!editEventFormData.value.is_all_day && editEventFormData.value.start_date) {
        return editEventFormData.value.start_date
    }
    return new Date().toISOString().split('T')[0]
})

const minTimeForEditEndTime = computed(() => {
    if (!editEventFormData.value.start_time) return ''
    // Return the next 30-minute slot after start time
    const [hours, minutes] = editEventFormData.value.start_time.split(':').map(Number)
    const startMinutes = hours * 60 + minutes
    const nextSlot = startMinutes + 30 // Next 30-minute slot
    const nextHours = Math.floor(nextSlot / 60)
    const nextMins = nextSlot % 60
    return `${String(nextHours).padStart(2, '0')}:${String(nextMins).padStart(2, '0')}`
})

// Calculate duration in days for all-day events (edit form)
const allDayDurationEdit = computed(() => {
    if (!editEventFormData.value.is_all_day || !editEventFormData.value.start_date || !editEventFormData.value.end_date) {
        return null
    }
    const start = new Date(editEventFormData.value.start_date)
    const end = new Date(editEventFormData.value.end_date)
    const diffTime = Math.abs(end.getTime() - start.getTime())
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1 // +1 to include both start and end days
    
    if (diffDays === 1) {
        return '1 Day'
    }
    return `${diffDays} Days`
})

const toggleAttendance = async () => {
    if (!selectedEvent.value || isTogglingAttendance.value || !props.currentUser) return
    
    const eventId = selectedEvent.value.id
    isTogglingAttendance.value = true
    
    try {
        const response = await api.post(`/calendar-events/${eventId}/attend`)
        
        // Update local state
        isAttending.value = response.data.is_attending
        
        // Immediately update the attendees list
        if (response.data.is_attending) {
            // User is now attending - add them to the list if not already present
            const userAlreadyInList = attendees.value.some(attendee => attendee.id === props.currentUser?.id)
            if (!userAlreadyInList) {
                // Get employee info for the current user from backend event data
                const backendEvent = props.events?.find(e => e.id === eventId)
                const currentUserAttendee = backendEvent?.attendees?.find(a => a.id === props.currentUser?.id)
                
                if (currentUserAttendee) {
                    // Use the attendee data from backend if available (has position info)
                    attendees.value.push(currentUserAttendee)
                } else {
                    // Fallback: We need to get position from somewhere
                    // Try to find the current user in any event's attendees to get their position
                    let userPosition: string | null = null
                    for (const event of props.events || []) {
                        const userInEvent = event.attendees?.find(a => a.id === props.currentUser?.id)
                        if (userInEvent?.position) {
                            userPosition = userInEvent.position
                            break
                        }
                    }
                    
                    // Add basic user info (position will be updated when events reload)
                    attendees.value.push({
                        id: props.currentUser.id,
                        name: props.currentUser.name,
                        email: props.currentUser.email,
                        department_code: props.userDepartment?.code || null,
                        position: userPosition, // Try to get from other events, otherwise null
                    })
                }
            }
        } else {
            // User is no longer attending - remove them from the list
            attendees.value = attendees.value.filter(attendee => attendee.id !== props.currentUser?.id)
        }
        
        // Show toast notification
        toast.success(response.data.message)
        
        // Refresh the page to get updated attendees list (will sync with backend and update position)
        router.reload({ only: ['events'] })
    } catch (error: any) {
        console.error('Error toggling attendance:', error)
        if (error.response?.data?.message) {
            toast.error(error.response.data.message)
        } else {
            toast.error('Failed to update attendance. Please try again.')
        }
    } finally {
        isTogglingAttendance.value = false
    }
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
            // Don't auto-delete, just stop the countdown
        }
    }, 1000)
}

const confirmDelete = async () => {
    if (!selectedEvent.value) return
    
    isDeleting.value = true
    
    try {
        await api.delete(`/calendar-events/${selectedEvent.value.id}`)
        toast.success('Event deleted successfully')
        showDeleteConfirm.value = false
        closeEventDetails()
        
        // Refresh the page to get updated events
        router.reload({ only: ['events'] })
    } catch (error: any) {
        console.error('Error deleting event:', error)
        if (error.response?.data?.message) {
            toast.error(error.response.data.message)
        } else {
            toast.error('Failed to delete event. Please try again.')
        }
    } finally {
        isDeleting.value = false
        deleteCountdown.value = 5
        if (deleteCountdownInterval.value) {
            clearInterval(deleteCountdownInterval.value)
            deleteCountdownInterval.value = null
        }
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

const formatCategoryName = (name?: string | null): string => {
    return name || 'No category';
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
const showEditCategoryModal = ref(false)
const newCategoryName = ref<string>('')
const newCategoryColor = ref<string>('#6b7280')
const editingCategory = ref<{ id: number; name: string; color: string } | null>(null)
const editCategoryName = ref<string>('')
const editCategoryColor = ref<string>('#6b7280')

// Category loading states
const isSavingCategory = ref(false)
const isUpdatingCategory = ref(false)
const isDeletingCategory = ref(false)

// Category delete confirmation
const showDeleteCategoryConfirm = ref(false)
const categoryDeleteCountdown = ref(3)
const categoryDeleteCountdownInterval = ref<number | null>(null)
const categoryToDelete = ref<number | null>(null)
// Local categories for management (initialized from props, can be modified locally)
// Note: In the future, category management will be done via API
const localCategories = computed(() => {
    if (!props.categories) {
        return [];
    }
    return props.categories.map(cat => ({
        id: cat.id,
        name: cat.name,
        color: cat.color, // Store hex color for inline styles
        colorClass: hexToTailwindClass(cat.color), // Keep Tailwind class as fallback
    }));
})

// Event details functions
const openEventDetails = (event: Event) => {
    selectedEvent.value = event
    
    // Check if current user is attending by checking attendees list
    const backendEvent = props.events?.find(e => e.id === event.id)
    isAttending.value = backendEvent?.current_user_is_attending || false
    
    // Populate attendees from backend event data (more complete than frontend event data)
    if (backendEvent?.attendees) {
        attendees.value = [...backendEvent.attendees] // Create a new array to ensure reactivity
    } else {
        attendees.value = event.attendees || []
    }
    
    // Populate event details info
    eventDetailsInfo.value.location = event.location || 'Not specified'
    eventDetailsInfo.value.organizer = event.creator?.name || 'Not specified'
    showEventDetails.value = true
}

// Watch for changes in props.events to update attendees when events reload
watch(() => props.events, (newEvents) => {
    // If event details modal is open and we have a selected event, update attendees
    if (showEventDetails.value && selectedEvent.value && newEvents) {
        const backendEvent = newEvents.find(e => e.id === selectedEvent.value?.id)
        if (backendEvent?.attendees) {
            attendees.value = [...backendEvent.attendees] // Update with fresh data including positions
            isAttending.value = backendEvent.current_user_is_attending || false
        }
    }
}, { deep: true })

// Open department event details by converting to Event format
const openDepartmentEventDetails = (deptEvent: DepartmentEvent) => {
    let categoryColorClass: string | null = null
    if (deptEvent.category?.color) {
        categoryColorClass = hexToTailwindClass(deptEvent.category.color)
    }
    
    // Convert date strings to Date objects
    const startDate = new Date(deptEvent.start_date)
    const endDate = deptEvent.end_date ? new Date(deptEvent.end_date) : undefined
    
    // Create Event object from DepartmentEvent
    const event: Event = {
        id: deptEvent.id,
        title: deptEvent.title,
        date: startDate,
        startTime: deptEvent.start_time,
        endTime: deptEvent.end_time,
        allDay: deptEvent.is_all_day,
        startDate: deptEvent.is_all_day ? startDate : undefined,
        endDate: deptEvent.is_all_day && endDate ? endDate : undefined,
        type: deptEvent.category?.name || null,
        color: categoryColorClass,
        colorHex: deptEvent.category?.color || undefined,
        description: deptEvent.description,
        category: deptEvent.category?.name,
        visibility: 'department',
        department: deptEvent.department || undefined,
        creator: deptEvent.creator,
    }
    
    // Find the backend event to get attendees and attendance status
    const deptBackendEvent = props.events?.find(e => e.id === deptEvent.id)
    event.attendees = deptBackendEvent?.attendees || []
    
    // Update event details info with department event data
    eventDetailsInfo.value.location = deptEvent.location || 'Not specified'
    eventDetailsInfo.value.organizer = deptEvent.creator.name
    
    // Check if current user is attending
    isAttending.value = deptBackendEvent?.current_user_is_attending || false
    
    // Populate attendees from backend event data (more complete than frontend event data)
    if (deptBackendEvent?.attendees) {
        attendees.value = deptBackendEvent.attendees
    } else {
        attendees.value = event.attendees || []
    }
    
    // Open the event details modal
    selectedEvent.value = event
    showEventDetails.value = true
}

// Day events modal functions
const openDayEvents = (date: Date, events: Event[]) => {
    selectedDate.value = date
    selectedDayEvents.value = events
    showDayEventsModal.value = true
}

const closeDayEventsModal = () => {
    showDayEventsModal.value = false
    selectedDayEvents.value = []
    selectedDate.value = null
}

// Category management functions
const toggleCategoryDropdown = () => {
    showCategoryDropdown.value = !showCategoryDropdown.value
}

const editCategory = (category: Category) => {
    editingCategory.value = category
    editCategoryName.value = category.name
    editCategoryColor.value = category.color
    showEditCategoryModal.value = true
}

const closeEditCategoryModal = () => {
    showEditCategoryModal.value = false
    editingCategory.value = null
    editCategoryName.value = ''
    editCategoryColor.value = '#6b7280'
}

const handleDeleteCategoryClick = (categoryId: number) => {
    categoryToDelete.value = categoryId
    showDeleteCategoryConfirm.value = true
    categoryDeleteCountdown.value = 3
    
    if (categoryDeleteCountdownInterval.value) {
        clearInterval(categoryDeleteCountdownInterval.value)
    }
    
    categoryDeleteCountdownInterval.value = window.setInterval(() => {
        categoryDeleteCountdown.value--
        if (categoryDeleteCountdown.value <= 0) {
            if (categoryDeleteCountdownInterval.value) {
                clearInterval(categoryDeleteCountdownInterval.value)
                categoryDeleteCountdownInterval.value = null
            }
        }
    }, 1000)
}

const confirmDeleteCategory = async () => {
    if (!categoryToDelete.value) return
    
    isDeletingCategory.value = true
    
    try {
        await api.delete(`/event-categories/${categoryToDelete.value}`)
        toast.success('Category deleted successfully')
        showDeleteCategoryConfirm.value = false
        
        // Refresh the page to get updated categories and reflect colors in events
        router.reload({ only: ['categories', 'events'] })
    } catch (error: any) {
        console.error('Error deleting category:', error)
        if (error.response?.data?.message) {
            toast.error(error.response.data.message)
        } else {
            toast.error('Failed to delete category. Please try again.')
        }
    } finally {
        isDeletingCategory.value = false
        categoryToDelete.value = null
        categoryDeleteCountdown.value = 3
        if (categoryDeleteCountdownInterval.value) {
            clearInterval(categoryDeleteCountdownInterval.value)
            categoryDeleteCountdownInterval.value = null
        }
    }
}

const cancelDeleteCategory = () => {
    showDeleteCategoryConfirm.value = false
    categoryToDelete.value = null
    categoryDeleteCountdown.value = 3
    if (categoryDeleteCountdownInterval.value) {
        clearInterval(categoryDeleteCountdownInterval.value)
        categoryDeleteCountdownInterval.value = null
    }
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

const saveNewCategory = async () => {
    if (!newCategoryName.value.trim()) {
        toast.error('Category name is required')
        return
    }
    
    // Validate hex color format
    if (!/^#[0-9A-Fa-f]{6}$/.test(newCategoryColor.value)) {
        toast.error('Please enter a valid hex color code (e.g., #FF5733)')
        return
    }
    
    isSavingCategory.value = true
    
    try {
        await api.post('/event-categories', {
            name: newCategoryName.value.trim(),
            color: newCategoryColor.value,
        })
        toast.success('Category created successfully')
        closeAddCategoryModal()
        
        // Refresh the page to get updated categories and events so colors stay in sync
        router.reload({ only: ['categories', 'events'] })
    } catch (error: any) {
        console.error('Error creating category:', error)
        if (error.response?.data?.message) {
            toast.error(error.response.data.message)
        } else if (error.response?.data?.errors) {
            const firstError = Object.values(error.response.data.errors)[0] as string[]
            toast.error(firstError[0] || 'Failed to create category')
        } else {
            toast.error('Failed to create category. Please try again.')
        }
    } finally {
        isSavingCategory.value = false
    }
}

const saveEditCategory = async () => {
    if (!editingCategory.value) return
    
    if (!editCategoryName.value.trim()) {
        toast.error('Category name is required')
        return
    }
    
    // Validate hex color format
    if (!/^#[0-9A-Fa-f]{6}$/.test(editCategoryColor.value)) {
        toast.error('Please enter a valid hex color code (e.g., #FF5733)')
        return
    }
    
    isUpdatingCategory.value = true
    
    try {
        await api.put(`/event-categories/${editingCategory.value.id}`, {
            name: editCategoryName.value.trim(),
            color: editCategoryColor.value,
        })
        toast.success('Category updated successfully')
        closeEditCategoryModal()
        
        // Refresh the page to get updated categories
        router.reload({ only: ['categories'] })
    } catch (error: any) {
        console.error('Error updating category:', error)
        if (error.response?.data?.message) {
            toast.error(error.response.data.message)
        } else if (error.response?.data?.errors) {
            const firstError = Object.values(error.response.data.errors)[0] as string[]
            toast.error(firstError[0] || 'Failed to update category')
        } else {
            toast.error('Failed to update category. Please try again.')
        }
    } finally {
        isUpdatingCategory.value = false
    }
}

// hexToTailwindClass is defined earlier in the file (before transformEvent)

const closeEventDetails = () => {
    console.log('closeEventDetails called in Calendar.vue') // Debug log
    showEventDetails.value = false
    selectedEvent.value = null
    isEditing.value = false
    // Force re-render by changing the key
    setTimeout(() => {
        console.log('showEventDetails after close:', showEventDetails.value)
    }, 100)
}

// Edit event functions
const openEdit = () => {
    if (!selectedEvent.value) return
    
    const event = selectedEvent.value
    
    // Find the backend event to get the actual category_id and other backend fields
    const backendEvent = props.events?.find(e => e.id === event.id)
    
    // For department managers, always set visibility to 'department' and department_id to their department
    let visibility = event.visibility || 'everyone'
    let departmentId = event.department?.id || null
    
    if (isDepartmentManager.value) {
        visibility = 'department'
        departmentId = userDepartment.value?.id || null
    }
    
    // Populate edit form with event data (matching backend structure)
    editEventFormData.value = {
        title: event.title,
        description: event.description || '',
        location: event.location || eventDetailsInfo.value.location || '',
        category_id: backendEvent?.category?.id || null,
        visibility: visibility,
        department_id: departmentId,
        start_date: event.allDay 
            ? (event.startDate ? new Date(event.startDate).toISOString().split('T')[0] : new Date(event.date).toISOString().split('T')[0])
            : new Date(event.date).toISOString().split('T')[0],
        end_date: event.allDay && event.endDate ? new Date(event.endDate).toISOString().split('T')[0] : '',
        start_time: event.startTime || '',
        end_time: event.endTime || '',
        is_all_day: event.allDay || false,
    }
    
    isEditing.value = true
}

const closeEdit = () => {
    isEditing.value = false
}

const handleEditSubmit = async () => {
    if (!selectedEvent.value) return
    
    // Validation: If visibility is 'department', department_id is required
    if (editEventFormData.value.visibility === 'department' && !editEventFormData.value.department_id) {
        toast.error('Please select a department when visibility is set to "Specific Department"')
        return
    }
    
    // Validation: For non-all-day events, start_time and end_time are required
    if (!editEventFormData.value.is_all_day) {
        if (!editEventFormData.value.start_time || !editEventFormData.value.end_time) {
            toast.error('Please select both start time and end time for timed events')
            return
        }
    }
    
    // Prepare data matching CalendarEvent model structure
    const eventPayload = {
        title: editEventFormData.value.title.trim(),
        description: editEventFormData.value.description?.trim() || null,
        location: editEventFormData.value.location?.trim() || null,
        category_id: editEventFormData.value.category_id || null,
        department_id: editEventFormData.value.visibility === 'department' ? editEventFormData.value.department_id : null,
        visibility: editEventFormData.value.visibility,
        is_all_day: editEventFormData.value.is_all_day,
        start_date: editEventFormData.value.start_date,
        end_date: editEventFormData.value.is_all_day ? (editEventFormData.value.end_date || null) : null,
        start_time: editEventFormData.value.is_all_day ? null : editEventFormData.value.start_time,
        end_time: editEventFormData.value.is_all_day ? null : editEventFormData.value.end_time,
    }
    
    isUpdating.value = true
    
    try {
        await api.put(`/calendar-events/${selectedEvent.value.id}`, eventPayload)
        toast.success('Event updated successfully')
        closeEdit()
        closeEventDetails()
        
        // Refresh the page to get updated events
        router.reload({ only: ['events'] })
    } catch (error: any) {
        console.error('Error updating event:', error)
        if (error.response?.data?.message) {
            toast.error(error.response.data.message)
        } else if (error.response?.data?.errors) {
            const firstError = Object.values(error.response.data.errors)[0] as string[]
            toast.error(firstError[0] || 'Failed to update event')
        } else {
            toast.error('Failed to update event. Please try again.')
        }
    } finally {
        isUpdating.value = false
    }
}

// Auto-update end date when start date changes for edit form (if end date is empty or before start date)
watch(() => editEventFormData.value.start_date, (startDate) => {
    if (startDate && editEventFormData.value.is_all_day) {
        const start = new Date(startDate)
        const currentEnd = editEventFormData.value.end_date ? new Date(editEventFormData.value.end_date) : null
        
        // If end date is empty or before start date, set it to start date
        if (!currentEnd || currentEnd < start) {
            editEventFormData.value.end_date = startDate
        }
    }
})

// Clear end_date when switching from all-day to timed events
// Clear start_time and end_time when switching from timed to all-day events
watch(() => editEventFormData.value.is_all_day, (isAllDay) => {
    if (isAllDay) {
        // Switching to all-day: clear times, set end_date to start_date if empty
        editEventFormData.value.start_time = ''
        editEventFormData.value.end_time = ''
        if (editEventFormData.value.start_date && !editEventFormData.value.end_date) {
            editEventFormData.value.end_date = editEventFormData.value.start_date
        }
    } else {
        // Switching to timed: clear end_date (for timed events, end_date should be null)
        editEventFormData.value.end_date = ''
    }
})

// Get selected category object for edit form display
const getSelectedEditCategoryObject = () => {
    return availableCategories.value.find(cat => cat.id === editEventFormData.value.category_id)
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

const handleEditColorInput = (e: globalThis.Event) => {
    const target = (e.target as HTMLInputElement) || (e.currentTarget as HTMLInputElement)
    if (target) {
        editCategoryColor.value = target.value
    }
}
</script>

<template>
    <Head title="Calendar" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-2 sm:m-4 flex flex-col gap-2 sm:gap-4 md:grid md:grid-cols-12">
            <!-- Events - bottom on mobile, left side on desktop -->
            <div class="order-2 md:order-1 md:col-span-3 flex flex-col gap-2 sm:gap-4 md:h-full md:max-h-full">
                <!-- Events List -->
                <div class="flex-1 rounded-lg shadow bg-card flex flex-col min-h-[240px] sm:min-h-[256px] md:min-h-0 md:max-h-[70vh] overflow-hidden">
                    <!-- Events Header with Filter -->
                    <div class="p-2 sm:p-3 md:p-4 space-y-3 shrink-0">
                        <div class="flex items-center justify-between">
                            <h3 class="text-foreground text-sm sm:text-base md:text-lg font-semibold">Events</h3>
                            <Badge variant="secondary" class="text-[10px] px-1.5 py-0 h-4">
                                {{ getFilteredEvents.length }}
                            </Badge>
                        </div>
                        
                        <!-- Search Input -->
                        <div class="relative">
                            <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 text-muted-foreground w-4 h-4" />
                            <Input
                                v-model="eventSearch"
                                placeholder="Search events..."
                                class="w-full bg-muted border-border text-foreground text-xs sm:text-sm pl-9 pr-8 focus:outline-none focus:ring-0 focus-visible:outline-none"
                            />
                            <Button
                                v-if="eventSearch"
                                variant="ghost"
                                size="sm"
                                @click="eventSearch = ''"
                                class="absolute right-1 top-1/2 transform -translate-y-1/2 h-6 w-6 p-0 text-muted-foreground hover:text-foreground"
                            >
                                <X class="w-3 h-3" />
                            </Button>
                        </div>

                        <!-- Filters Row -->
                        <div class="flex gap-2">
                            <!-- Time Period Filter -->
                        <Select v-model="eventFilter">
                                <SelectTrigger class="flex-1 bg-muted border-border text-foreground text-xs sm:text-sm focus:outline-none focus:ring-0 focus-visible:outline-none">  
                                <SelectValue :placeholder="eventFilterLabel" />
                            </SelectTrigger>
                            <SelectContent class="bg-muted border-border">
                                <SelectGroup>
                                    <SelectLabel class="text-muted-foreground">Time Period</SelectLabel>
                                    <SelectItem value="today" class="text-foreground hover:bg-accent">
                                        Today
                                    </SelectItem>
                                    <SelectItem value="upcoming" class="text-foreground hover:bg-accent">
                                        Upcoming
                                    </SelectItem>
                                    <SelectItem value="this-week" class="text-foreground hover:bg-accent">
                                        This Week
                                    </SelectItem>
                                    <SelectItem value="this-month" class="text-foreground hover:bg-accent">
                                        This Month
                                    </SelectItem>
                                    <SelectItem value="this-year" class="text-foreground hover:bg-accent">
                                        This Year
                                    </SelectItem>
                                    <SelectItem value="all" class="text-foreground hover:bg-accent">   
                                        All Events 
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>

                            <!-- Category Filter -->
                            <Select v-model="selectedCategoryFilter">
                                <SelectTrigger class="flex-1 bg-muted border-border text-foreground text-xs sm:text-sm focus:outline-none focus:ring-0 focus-visible:outline-none">  
                                    <SelectValue placeholder="Category" />
                                </SelectTrigger>
                                <SelectContent class="bg-muted border-border">
                                    <SelectGroup>
                                    <SelectLabel class="text-muted-foreground">Category</SelectLabel>
                                    <SelectItem value="all" class="text-foreground hover:bg-accent">
                                        All Categories
                                    </SelectItem>
                                    <SelectItem 
                                        v-for="category in props.categories" 
                                        :key="category.id"
                                        :value="category.name" 
                                        class="text-foreground hover:bg-accent"
                                        >
                                            <div class="flex items-center gap-2">
                                                <div class="w-2 h-2 rounded-full" :style="{ backgroundColor: category.color }"></div>
                                                {{ category.name }}
                                            </div>
                                        </SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    
                    <!-- Events List -->
                    <ScrollArea class="flex-1 p-3 min-h-0 max-h-full overflow-auto">
                        <div v-if="getFilteredEvents.length === 0" class="text-muted-foreground text-sm text-center py-8">
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
                                class="relative p-3 rounded-lg bg-muted hover:bg-muted/80 transition-colors duration-200 cursor-pointer"
                                @click="openEventDetails(event)"
                            >
                                <div class="flex items-start justify-between">
                                    <div class="flex-1 min-w-0">
                                        <h4 class="text-foreground font-medium text-sm mb-1 flex items-center gap-1.5">
                                            <!-- "All" Badge for Everyone Events -->
                                            <Badge 
                                                v-if="event.visibility === 'everyone'"
                                                variant="secondary"
                                                class="text-[9px] px-1 py-0 h-4 font-semibold bg-green-600/30 text-green-300 border-green-500/50 shrink-0"
                                            >
                                                All
                                            </Badge>
                                            <!-- Department Code Badge for Departmental Events -->
                                            <Badge 
                                                v-if="event.visibility === 'department' && event.department?.code"
                                                variant="secondary"
                                                class="text-[9px] px-1 py-0 h-4 font-semibold bg-blue-600/30 text-blue-300 border-blue-500/50 shrink-0"
                                            >
                                                {{ event.department.code }}
                                            </Badge>
                                            <span class="truncate">{{ event.title }}</span>
                                        </h4>
                                        <p class="text-muted-foreground text-xs mb-1">{{ formatEventDate(event.date) }} • {{ formatEventTime(event) }}</p>
                                        <p class="text-muted-foreground/70 text-[11px] uppercase tracking-wide font-semibold">
                                            {{ event.category || 'No category' }}
                                        </p>
                                        <p v-if="event.description" class="text-muted-foreground/70 text-xs mt-1 line-clamp-2 leading-relaxed">{{ event.description }}</p>
                                        <p v-else class="text-muted-foreground/70 text-xs mt-1 italic">No additional information available.</p>
                                    </div>
                                    <div 
                                        class="w-3 h-3 rounded-full flex-shrink-0 ml-3"
                                        :class="event.color ?? undefined"
                                        :style="(!event.color && event.colorHex) ? { backgroundColor: event.colorHex } : undefined"
                                    ></div>
                                </div>
                            </div>
                        </div>
                    </ScrollArea>
                </div>

                <!-- Events by Department -->
                <div class="flex-1 rounded-lg shadow bg-card flex flex-col min-h-[240px] sm:min-h-[256px] md:min-h-0 md:max-h-[70vh] overflow-hidden">
                    <!-- Department Header -->
                    <div class="p-2 sm:p-3 md:p-4 space-y-3 shrink-0">
                        <div class="flex items-center justify-between">
                            <h3 class="text-foreground text-sm sm:text-base md:text-lg font-semibold">
                                {{ userDepartment ? `${userDepartment.name} Events` : 'Department Events' }}
                            </h3>
                            <Badge variant="secondary" class="text-[10px] px-1.5 py-0 h-4">
                                {{ filteredDepartmentEvents.length }}
                            </Badge>
                        </div>
                        
                        <!-- Search Input -->
                        <div class="relative">
                            <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 text-muted-foreground w-4 h-4" />
                            <Input
                                v-model="deptEventSearch"
                                placeholder="Search events..."
                                class="w-full bg-muted border-border text-foreground text-xs sm:text-sm pl-9 pr-8 focus:outline-none focus:ring-0 focus-visible:outline-none"
                            />
                            <Button
                                v-if="deptEventSearch"
                                variant="ghost"
                                size="sm"
                                @click="deptEventSearch = ''"
                                class="absolute right-1 top-1/2 transform -translate-y-1/2 h-6 w-6 p-0 text-muted-foreground hover:text-foreground"
                            >
                                <X class="w-3 h-3" />
                            </Button>
                        </div>

                        <!-- Filters Row -->
                        <div class="flex gap-2">
                            <!-- Time Period Filter -->
                            <Select v-model="deptEventTimeFilter">
                                <SelectTrigger class="flex-1 bg-muted border-border text-foreground text-xs sm:text-sm focus:outline-none focus:ring-0 focus-visible:outline-none">  
                                    <SelectValue placeholder="Today" />
                                </SelectTrigger>
                                <SelectContent class="bg-muted border-border">
                                    <SelectGroup>
                                        <SelectLabel class="text-muted-foreground">Time Period</SelectLabel>
                                        <SelectItem value="today" class="text-foreground hover:bg-accent">
                                            Today
                                        </SelectItem>
                                        <SelectItem value="upcoming" class="text-foreground hover:bg-accent">
                                            Upcoming
                                        </SelectItem>
                                        <SelectItem value="this-week" class="text-foreground hover:bg-accent">
                                            This Week
                                        </SelectItem>
                                        <SelectItem value="this-month" class="text-foreground hover:bg-accent">
                                            This Month
                                        </SelectItem>
                                        <SelectItem value="this-year" class="text-foreground hover:bg-accent">
                                            This Year
                                        </SelectItem>
                                        <SelectItem value="all" class="text-foreground hover:bg-accent">
                                            All Time
                                        </SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>

                            <!-- Category Filter -->
                            <Select v-model="deptEventCategoryFilter">
                                <SelectTrigger class="flex-1 bg-muted border-border text-foreground text-xs sm:text-sm focus:outline-none focus:ring-0 focus-visible:outline-none">  
                                    <SelectValue placeholder="All Categories" />
                                </SelectTrigger>
                                <SelectContent class="bg-muted border-border">
                                    <SelectGroup>
                                        <SelectLabel class="text-muted-foreground">Category</SelectLabel>
                                        <SelectItem value="all" class="text-foreground hover:bg-accent">
                                            <div class="flex items-center gap-2">
                                                <span>All Categories</span>
                                            </div>
                                        </SelectItem>
                                        <SelectItem 
                                            v-for="category in props.categories" 
                                            :key="category.id"
                                            :value="category.name" 
                                            class="text-foreground hover:bg-accent"
                                        >
                                            <div class="flex items-center gap-2">
                                                <div class="w-2 h-2 rounded-full" :style="{ backgroundColor: category.color }"></div>
                                                <span>{{ category.name }}</span>
                                            </div>
                                        </SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    
                    <!-- Department Events List -->
                    <ScrollArea class="flex-1 p-3 min-h-0 max-h-full overflow-auto">
                        <div v-if="!userDepartment" class="text-muted-foreground text-sm text-center py-8">
                            <p>No department assigned to your account.</p>
                        </div>
                        <div v-else-if="filteredDepartmentEvents.length === 0" class="text-muted-foreground text-sm text-center py-8">
                            <p>{{ deptEventSearch || deptEventTimeFilter !== 'all' || deptEventCategoryFilter !== 'all' ? 'No events match your filters.' : `No events scheduled for ${userDepartment.name}.` }}</p>
                        </div>
                        <div v-else class="space-y-3">
                            <div 
                                v-for="event in filteredDepartmentEvents" 
                                :key="event.id"
                                class="relative p-3 rounded-lg bg-muted hover:bg-muted/80 transition-colors duration-200 cursor-pointer"
                                @click="openDepartmentEventDetails(event)"
                            >
                                <div class="flex items-start justify-between">
                                    <div class="flex-1 min-w-0">
                                        <h4 class="text-foreground font-medium text-sm mb-1 flex items-center gap-1.5">
                                            <!-- "All" Badge for Everyone Events -->
                                            <Badge 
                                                v-if="event.visibility === 'everyone'"
                                                variant="secondary"
                                                class="text-[9px] px-1 py-0 h-4 font-semibold bg-green-600/30 text-green-300 border-green-500/50 shrink-0 dark:bg-green-600/30 dark:text-green-300"
                                            >
                                                All
                                            </Badge>
                                            <!-- Department Code Badge for Departmental Events -->
                                            <Badge 
                                                v-if="event.visibility === 'department' && event.department?.code"
                                                variant="secondary"
                                                class="text-[9px] px-1 py-0 h-4 font-semibold bg-blue-600/30 text-blue-300 border-blue-500/50 shrink-0 dark:bg-blue-600/30 dark:text-blue-300"
                                            >
                                                {{ event.department.code }}
                                            </Badge>
                                            <span class="truncate">{{ event.title }}</span>
                                        </h4>
                                        <p class="text-muted-foreground text-xs mb-1">
                                            {{ new Date(event.start_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) }}
                                            <span v-if="event.is_all_day">
                                                • All Day
                                            </span>
                                            <span v-else-if="event.start_time && event.end_time">
                                                • {{ formatTime(event.start_time) }} - {{ formatTime(event.end_time) }}
                                            </span>
                                            <span v-else-if="event.start_time">
                                                • {{ formatTime(event.start_time) }}
                                            </span>
                                        </p>
                                        <p v-if="event.description" class="text-muted-foreground/70 text-xs mt-1 line-clamp-2 leading-relaxed">{{ event.description }}</p>
                                        <p v-else class="text-muted-foreground/70 text-xs mt-1 italic">No additional information available.</p>
                                    </div>
                                    <div 
                                        v-if="event.category"
                                        class="w-3 h-3 rounded-full flex-shrink-0 ml-3"
                                        :style="{ backgroundColor: event.category.color }"
                                    ></div>
                                </div>
                            </div>
                        </div>
                    </ScrollArea>
                </div>
            </div>

            <!-- Calendar - top on mobile, right side on desktop -->
            <div class="order-1 md:order-2 md:col-span-9 bg-card rounded-lg shadow flex flex-col md:h-full">
                <!-- Calendar Controls Header -->
                <div class="flex items-center justify-between p-2 sm:p-4 bg-card border-b border-border">
                    <div class="flex items-center gap-2 sm:gap-4">
                        <div class="flex flex-col items-center text-foreground cursor-pointer hover:bg-accent px-2 sm:px-3 py-1 sm:py-2 rounded transition-colors" @click="moveToday">
                            <span class="text-xs text-muted-foreground uppercase tracking-wide">{{ currentMonthShort }}</span>
                            <span class="text-lg sm:text-2xl font-bold">{{ currentDay }}</span>
                        </div>
                        
                        <!-- Month/Year Display -->
                        <div class="flex flex-col relative">
                            <h1 class="text-base sm:text-xl font-bold text-foreground cursor-pointer hover:text-muted-foreground transition-colors" @click="toggleDatePicker">
                                {{ currentMonthFull }} {{ currentYear }}
                            </h1>
                            <p class="text-xs text-muted-foreground hidden sm:block">{{ monthDateRange }}</p>
                            
                            <!-- Clean Date Picker Dropdown -->
                            <div v-if="showDatePicker" class="absolute top-full left-0 mt-2 z-50 bg-popover border border-border rounded-lg shadow-xl p-4 min-w-[280px]">
                                <!-- Year Navigation -->
                                <div class="flex items-center justify-between mb-4">
                                    <Button 
                                        @click="previousYear"
                                        variant="ghost"
                                        size="sm"
                                        class="p-2 text-muted-foreground hover:text-foreground hover:bg-accent"
                                    >
                                        <ChevronDown class="w-4 h-4" />
                                    </Button>
                                    <h3 class="text-foreground font-semibold text-lg">{{ pickerYear }}</h3>
                                    <Button 
                                        @click="nextYear"
                                        variant="ghost"
                                        size="sm"
                                        class="p-2 text-muted-foreground hover:text-foreground hover:bg-accent"
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
                                        class="p-3 text-sm text-muted-foreground hover:text-foreground hover:bg-accent text-center"
                                        :class="{
                                            'bg-blue-600 text-foreground hover:bg-blue-600': currentDate.getMonth() === index && currentDate.getFullYear() === pickerYear
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
                                        class="px-3 py-1 text-sm text-muted-foreground hover:text-foreground hover:bg-accent"
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
                                class="text-muted-foreground hover:text-foreground text-xs sm:text-sm font-medium transition-colors duration-200 flex items-center gap-1"
                            >
                                Category
                                <ChevronDown class="w-3 h-3" :class="{ 'rotate-180': showCategoryDropdown }" />
                            </button>
                            
                            <!-- Category Dropdown -->
                            <div v-if="showCategoryDropdown" class="absolute top-full right-0 mt-2 z-50 bg-popover border border-border rounded-lg shadow-xl w-[280px] max-w-[90vw]">   
                                <div class="p-4">
                                    <h3 class="text-foreground font-medium mb-3 flex items-center gap-2">
                                        Categories ({{ localCategories.length }})
                                    </h3>
                                    <ScrollArea class="h-80">
                                        <div class="space-y-1 pr-4">
                                            <div v-for="category in localCategories" :key="category.id"
                                                 class="flex items-center justify-between p-3 bg-muted rounded hover:bg-muted/80 transition-colors">
                                                <div class="flex items-center gap-3 min-w-0 flex-1">
                                                    <div class="w-4 h-4 rounded-full flex-shrink-0" :style="{ backgroundColor: category.color }"></div>
                                                    <span class="text-foreground text-sm font-medium truncate">{{ category.name }}</span>
                                                </div>
                                                <div v-if="canManageCategories" class="flex items-center gap-2">
                                                    <Button 
                                                        @click="editCategory(category)"
                                                        variant="ghost"
                                                        size="sm"
                                                        class="p-1 text-muted-foreground hover:text-blue-400 hover:bg-blue-500/10"
                                                    >
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                        </svg>
                                                    </Button>
                                                    <Button 
                                                        @click="handleDeleteCategoryClick(category.id)"
                                                        variant="ghost"
                                                        size="sm"
                                                        class="p-1 text-muted-foreground hover:text-red-400 hover:bg-red-500/10"
                                                        :disabled="isDeletingCategory"
                                                    >
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                    </Button>
                                                </div>
                                            </div>
                                        </div>
                                    </ScrollArea>
                                    <div class="flex justify-between items-center mt-3 pt-3 border-t border-border">
                                        <Button 
                                            v-if="canManageCategories"
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
                                        <div v-else></div>
                                        <Button 
                                            @click="showCategoryDropdown = false"
                                            variant="ghost"
                                            size="sm"
                                            class="text-muted-foreground hover:text-foreground hover:bg-accent"
                                        >
                                            Close
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <Button
                            v-if="canCreateEvents"
                            @click="openModal" 
                            class="bg-blue-600 hover:bg-blue-500 text-foreground border-0 shadow-lg hover:shadow-xl transition-all duration-200 text-xs sm:text-sm px-2 sm:px-4 py-1 sm:py-2 dark:bg-blue-600 dark:hover:bg-blue-500"
                            variant="default"
                            size="sm"
                        >
                            <span class="hidden sm:inline">+ New Event</span>
                            <span class="sm:hidden">+</span>
                        </Button>
                    </div>
                    
                    <!-- New Event Modal -->
                    <Dialog :open="showNewEventModal" @update:open="(open) => !open && closeModal()">
                        <DialogContent class="sm:max-w-2xl bg-card text-foreground border-border">
                            <DialogHeader>
                                <DialogTitle>New Event</DialogTitle>
                            </DialogHeader>

                            <form @submit.prevent="handleNewEventSubmit" class="space-y-4">
                                <div class="space-y-2">
                                    <Label for="title" class="text-foreground">Event Title:</Label>
                                    <Input
                                        id="title"
                                        v-model="newEventFormData.title"
                                        class="bg-background border-border text-foreground focus:border-blue-500"
                                        required
                                    />
                                </div>
                                <!-- Description -->
                                <div class="space-y-2">
                                    <Label for="description" class="text-foreground">Description: <span class="text-muted-foreground text-xs">(optional)</span></Label>
                                    <textarea
                                        id="description"
                                        v-model="newEventFormData.description"
                                        rows="3"
                                        class="bg-background border-border flex min-h-[80px] w-full rounded-md border px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 disabled:cursor-not-allowed disabled:opacity-50 resize-vertical"
                                        placeholder="Enter event description (optional)..."
                                    ></textarea>
                                </div>
                                <!-- Location -->
                                <div class="space-y-2">
                                    <Label for="location" class="text-foreground">Location: <span class="text-muted-foreground text-xs">(optional)</span></Label>
                                    <Input
                                        id="location"
                                        v-model="newEventFormData.location"
                                        class="bg-background border-border text-foreground focus:border-blue-500"
                                        placeholder="Enter location (optional)..."
                                    />
                                </div>
                                
                                <div class="space-y-2">
                                    <Label class="text-foreground">Category:</Label>
                                    <Select v-model="newEventFormData.category_id">
                                        <SelectTrigger
                                            class="w-full bg-background text-foreground border-border transition-all duration-200"
                                            :class="[
                                                newEventFormData.category_id && getSelectedCategoryObject()?.borderClass ? `border-2 ${getSelectedCategoryObject()?.borderClass} shadow-lg` : '',
                                                newEventFormData.category_id && !getSelectedCategoryObject()?.borderClass ? 'border-2 shadow-lg' : ''
                                            ]"
                                            :style="(!getSelectedCategoryObject()?.borderClass && getSelectedCategoryObject()?.colorHex)
                                                ? { borderColor: getSelectedCategoryObject()?.colorHex }
                                                : undefined"
                                        >
                                            <SelectValue placeholder="Select a Category" />
                                        </SelectTrigger>
                                        <SelectContent class="!bg-popover text-foreground border-border max-h-[300px]">
                                            <SelectGroup>
                                                <SelectLabel>Categories</SelectLabel>
                                                <ScrollArea class="max-h-[250px]">
                                                    <div class="pr-4">
                                                        <SelectItem
                                                            v-for="category in availableCategories"
                                                            :key="category.id"
                                                            :value="category.id"
                                                        >
                                                            <div class="flex items-center gap-2">
                                                                <div
                                                                    class="w-3 h-3 rounded-full"
                                                                    :class="category.colorClass"
                                                                    :style="!category.colorClass ? { backgroundColor: category.colorHex } : undefined"
                                                                ></div>
                                                                {{ category.label }}
                                                            </div>
                                                        </SelectItem>
                                                    </div>
                                                </ScrollArea>
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <!-- Event Type Selection -->
                                <div class="space-y-2">
                                    <Label class="text-foreground">Event For:</Label>
                                    <Select v-model="newEventFormData.visibility">
                                        <SelectTrigger class="w-full bg-background text-foreground border-border">
                                            <SelectValue placeholder="Select event type" />
                                        </SelectTrigger>
                                        <SelectContent class="!bg-popover text-foreground border-border">
                                            <SelectGroup>
                                                <SelectItem v-if="canCreateEveryoneEvents" value="everyone">
                                                    <div class="flex items-center gap-2">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                                                            <circle cx="9" cy="7" r="4"/>
                                                            <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                                                            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                                        </svg>
                                                        <span>Everyone</span>
                                                    </div>
                                                </SelectItem>
                                                <SelectItem value="department">
                                                    <div class="flex items-center gap-2">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                                                            <polyline points="9 22 9 12 15 12 15 22"/>
                                                        </svg>
                                                        <span>Specific Department</span>
                                                    </div>
                                                </SelectItem>
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <!-- Department Selection (shown only when visibility is 'department') -->
                                <div v-if="newEventFormData.visibility === 'department'" class="space-y-2">
                                    <Label class="text-foreground">Select Department: <span class="text-red-400 text-xs">*</span></Label>
                                    <Select v-model="newEventFormData.department_id" required :disabled="!canCreateEveryoneEvents">
                                        <SelectTrigger class="w-full bg-background text-foreground border-border">
                                            <SelectValue placeholder="Choose a department" />
                                        </SelectTrigger>
                                        <SelectContent class="!bg-popover text-foreground border-border">
                                            <SelectGroup>
                                                <SelectLabel>Departments</SelectLabel>
                                                <SelectItem
                                                    v-for="dept in departments"
                                                    :key="dept.id"
                                                    :value="dept.id"
                                                >
                                                    <div class="flex items-center gap-2">
                                                        <div class="w-2 h-2 rounded-full bg-blue-500"></div>
                                                        <span>{{ dept.name }}</span>
                                                        <span class="text-muted-foreground text-xs">({{ dept.code }})</span>
                                                    </div>
                                                </SelectItem>
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                    <p v-if="!canCreateEveryoneEvents" class="text-xs text-muted-foreground">
                                        You can only create events for your department ({{ userDepartment?.name }})
                                    </p>
                                </div>
                                
                                <!-- Date and Time Row -->
                                <div class="space-y-3 sm:space-y-0 sm:flex sm:items-center sm:gap-3">
                                    <!-- Date picker - always visible -->
                                    <div class="w-full sm:w-auto">
                                        <Input
                                            v-if="!newEventFormData.is_all_day"
                                            type="date"
                                            id="start_date"
                                            v-model="newEventFormData.start_date"
                                            class="bg-background border-border text-foreground text-sm min-w-[140px] focus:border-blue-500"
                                            required
                                        />
                                        <Input
                                            v-else
                                            type="date"
                                            id="start_date_all_day"
                                            v-model="newEventFormData.start_date"
                                            class="bg-background border-border text-foreground text-sm min-w-[140px] focus:border-blue-500"
                                            required
                                        />
                                    </div>
                                    
                                    <!-- Time selectors OR Date range for all-day -->
                                    <template v-if="!newEventFormData.is_all_day">
                                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
                                            <div class="flex-1 sm:flex-none">
                                                <Select v-model="newEventFormData.start_time" required>
                                                    <SelectTrigger class="!bg-background border-border text-foreground text-sm w-full sm:min-w-[120px]">
                                                        <SelectValue placeholder="Start time" />
                                                    </SelectTrigger>
                                                    <SelectContent class="!bg-popover text-foreground border-border">
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
                                            
                                            <span class="text-muted-foreground text-sm self-center px-2 sm:px-1">to</span>
                                            
                                            <div class="flex-1 sm:flex-none">
                                                <Select v-model="newEventFormData.end_time" required>
                                                    <SelectTrigger class="!bg-background border-border text-foreground text-sm min-w-[120px]">
                                                        <SelectValue placeholder="End time" />
                                                    </SelectTrigger>
                                                    <SelectContent class="!bg-popover text-foreground border-border">
                                                        <SelectItem 
                                                            v-for="time in ['09:30', '10:00', '10:30', '11:00', '11:30', '12:00', '12:30', '13:00', '13:30', '14:00', '14:30', '15:00', '15:30', '16:00', '16:30', '17:00', '17:30']"
                                                            :key="time"
                                                            :value="time"
                                                            :disabled="!!(minTimeForEndTime && time < minTimeForEndTime)"
                                                        >
                                                            {{ formatTime(time) }}
                                                        </SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- Date range for all-day events (start date 'to' end date) -->
                                    <template v-else>
                                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
                                            <span class="text-muted-foreground text-sm self-center px-2 sm:px-1">to</span>
                                            
                                            <div class="flex-1 sm:flex-none">
                                                <Input
                                                    type="date"
                                                    id="end_date"
                                                    v-model="newEventFormData.end_date"
                                                    :min="minDateForNewEvent"
                                                    class="bg-background border-border text-foreground text-sm min-w-[140px] focus:border-blue-500"
                                                />
                                            </div>
                                            
                                            <div v-if="allDayDuration" class="flex items-center text-sm text-muted-foreground px-2">
                                                Duration: <span class="text-foreground font-medium ml-1">{{ allDayDuration }}</span>
                                            </div>
                                        </div>
                                    </template>
                                    
                                    <!-- All Day Toggle -->
                                    <div class="flex items-center justify-between sm:justify-end w-full sm:w-auto mt-2 sm:mt-0">
                                        <div class="flex items-center gap-2">
                                            <Label for="is_all_day" class="text-sm text-foreground">All day</Label>
                                            <Switch
                                                id="is_all_day"
                                                v-model="newEventFormData.is_all_day"
                                                class="data-[state=checked]:bg-blue-500 data-[state=unchecked]:bg-background-600 relative inline-flex h-6 w-11 items-center rounded-full transition-colors duration-200 focus:outline-none"
                                            >
                                                <span
                                                    class="pointer-events-none inline-block h-4 w-4 rounded-full bg-white shadow transform transition-transform duration-200"
                                                    :class="newEventFormData.is_all_day ? 'translate-x-6' : 'translate-x-1'"
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
                                        class="bg-transparent text-muted-foreground border-border hover:bg-accent"
                                    >
                                        Cancel
                                    </Button>
                                    <Button
                                        type="submit"
                                        class="bg-blue-600 hover:bg-blue-700"
                                        :disabled="isSaving"
                                    >
                                        {{ isSaving ? 'Creating...' : 'Submit' }}
                                    </Button>
                                </div>
                            </form>
                        </DialogContent>
                    </Dialog>
                    
                    <!-- Event Details Modal -->
                    <Dialog :open="showEventDetails && !!selectedEvent" @update:open="(open) => { if (!open) closeEventDetails() }">
                        <DialogContent class="sm:max-w-4xl bg-card text-foreground border-border max-h-[95vh]">
                            <ScrollArea class="max-h-[90vh] pr-2">
                                <DialogHeader>
                                    <DialogTitle class="flex items-center gap-2.5 text-lg sm:text-xl transition-all duration-200">
                                        <div
                                            class="w-4 h-4 sm:w-5 sm:h-5 rounded-full flex-shrink-0 transition-all duration-200"
                                            :class="selectedEvent?.color ?? undefined"
                                            :style="(!selectedEvent?.color && selectedEvent?.colorHex) ? { backgroundColor: selectedEvent?.colorHex } : undefined"
                                        ></div>
                                        <span class="truncate font-semibold">{{ isEditing ? 'Edit Event' : selectedEvent?.title }}</span>
                                    </DialogTitle>
                                </DialogHeader>

                                <!-- Edit Form -->
                                <form v-if="isEditing" @submit.prevent="handleEditSubmit" class="space-y-4 p-3 sm:p-4">
                                    <div class="space-y-2">
                                        <Label for="edit-title" class="text-foreground">Event Title:</Label>
                                        <Input
                                            id="edit-title"
                                            v-model="editEventFormData.title"
                                            class="bg-background border-border text-foreground focus:border-blue-500"
                                            required
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="edit-description" class="text-foreground">Description:</Label>
                                        <textarea
                                            id="edit-description"
                                            v-model="editEventFormData.description"
                                            rows="3"
                                            class="bg-background border-border flex min-h-[80px] w-full rounded-md border px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 disabled:cursor-not-allowed disabled:opacity-50 resize-vertical"
                                            placeholder="Enter event description..."
                                            required
                                        ></textarea>
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="edit-location" class="text-foreground">Location:</Label>
                                        <Input
                                            id="edit-location"
                                            v-model="editEventFormData.location"
                                            class="bg-background border-border text-foreground focus:border-blue-500"
                                            required
                                        />
                                    </div>
                                    
                                    <div class="space-y-2">
                                        <Label class="text-foreground">Category:</Label>
                                    <Select v-model="editEventFormData.category_id">
                                        <SelectTrigger
                                            class="w-full bg-background text-foreground border-border transition-all duration-200"
                                            :class="[
                                                editEventFormData.category_id && getSelectedEditCategoryObject()?.borderClass ? `border-2 ${getSelectedEditCategoryObject()?.borderClass} shadow-lg` : '',
                                                editEventFormData.category_id && !getSelectedEditCategoryObject()?.borderClass ? 'border-2 shadow-lg' : ''
                                            ]"
                                            :style="(!getSelectedEditCategoryObject()?.borderClass && getSelectedEditCategoryObject()?.colorHex)
                                                ? { borderColor: getSelectedEditCategoryObject()?.colorHex }
                                                : undefined"
                                        >
                                                <SelectValue placeholder="Select a Category" />
                                            </SelectTrigger>
                                            <SelectContent class="!bg-popover text-foreground border-border">
                                                <SelectGroup>
                                                    <SelectLabel>Categories</SelectLabel>
                                                    <SelectItem
                                                        v-for="category in availableCategories"
                                                        :key="category.id"
                                                        :value="category.id"
                                                    >
                                                        <div class="flex items-center gap-2">
                                                        <div
                                                            class="w-3 h-3 rounded-full"
                                                            :class="category.colorClass"
                                                            :style="!category.colorClass ? { backgroundColor: category.colorHex } : undefined"
                                                        ></div>
                                                            {{ category.label }}
                                                        </div>
                                                    </SelectItem>
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <!-- Event Type Selection -->
                                    <div class="space-y-2">
                                        <Label class="text-foreground">Event For:</Label>
                                        <Select v-model="editEventFormData.visibility" :disabled="isDepartmentManager">
                                            <SelectTrigger class="w-full bg-background text-foreground border-border" :class="{ 'opacity-50 cursor-not-allowed': isDepartmentManager }">
                                                <SelectValue placeholder="Select event type" />
                                            </SelectTrigger>
                                            <SelectContent class="!bg-popover text-foreground border-border">
                                                <SelectGroup>
                                                    <SelectItem v-if="canCreateEveryoneEvents" value="everyone">
                                                        <div class="flex items-center gap-2">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                                                                <circle cx="9" cy="7" r="4"/>
                                                                <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                                                                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                                            </svg>
                                                            <span>Everyone</span>
                                                        </div>
                                                    </SelectItem>
                                                    <SelectItem value="department">
                                                        <div class="flex items-center gap-2">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                                                                <polyline points="9 22 9 12 15 12 15 22"/>
                                                            </svg>
                                                            <span>Specific Department</span>
                                                        </div>
                                                    </SelectItem>
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                        <p v-if="isDepartmentManager" class="text-xs text-muted-foreground">
                                            Visibility is fixed to "Specific Department" for department managers
                                        </p>
                                    </div>

                                    <!-- Department Selection (shown only when visibility is 'department') -->
                                    <div v-if="editEventFormData.visibility === 'department'" class="space-y-2">
                                        <Label class="text-foreground">Select Department: <span class="text-red-400 text-xs">*</span></Label>
                                        <Select v-model="editEventFormData.department_id" required :disabled="isDepartmentManager">
                                            <SelectTrigger class="w-full bg-background text-foreground border-border" :class="{ 'opacity-50 cursor-not-allowed': isDepartmentManager }">
                                                <SelectValue placeholder="Choose a department" />
                                            </SelectTrigger>
                                            <SelectContent class="!bg-popover text-foreground border-border">
                                                <SelectGroup>
                                                    <SelectLabel>Departments</SelectLabel>
                                                    <SelectItem
                                                        v-for="dept in departments"
                                                        :key="dept.id"
                                                        :value="dept.id"
                                                    >
                                                        <div class="flex items-center gap-2">
                                                            <div class="w-2 h-2 rounded-full bg-blue-500"></div>
                                                            <span>{{ dept.name }}</span>
                                                            <span class="text-muted-foreground text-xs">({{ dept.code }})</span>
                                                        </div>
                                                    </SelectItem>
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                        <p v-if="isDepartmentManager" class="text-xs text-muted-foreground">
                                            Department is fixed to your department ({{ userDepartment?.name }})
                                        </p>
                                    </div>

                                    <!-- Date and Time Row -->
                                    <div class="space-y-3 sm:space-y-0 sm:flex sm:items-center sm:gap-3">
                                        <div class="w-full sm:w-auto">
                                            <Input
                                                v-if="!editEventFormData.is_all_day"
                                                type="date"
                                                id="edit-date"
                                                v-model="editEventFormData.start_date"
                                                :min="new Date().toISOString().split('T')[0]"
                                                class="bg-background border-border text-foreground text-sm min-w-[140px] focus:border-blue-500"
                                                required
                                            />
                                            <Input
                                                v-else
                                                type="date"
                                                id="edit-startDate"
                                                v-model="editEventFormData.start_date"
                                                :min="new Date().toISOString().split('T')[0]"
                                                class="bg-background border-border text-foreground text-sm min-w-[140px] focus:border-blue-500"
                                                required
                                            />
                                        </div>
                                        
                                        <template v-if="!editEventFormData.is_all_day">
                                            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
                                                <div class="flex-1 sm:flex-none">
                                                    <Select v-model="editEventFormData.start_time" required>
                                                        <SelectTrigger class="!bg-background border-border text-foreground text-sm w-full sm:min-w-[120px]">
                                                            <SelectValue placeholder="Start time" />
                                                        </SelectTrigger>
                                                        <SelectContent class="!bg-popover text-foreground border-border">
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
                                                
                                                <span class="text-muted-foreground text-sm self-center px-2 sm:px-1">to</span>
                                                
                                                <div class="flex-1 sm:flex-none">
                                                    <Select v-model="editEventFormData.end_time" required>
                                                        <SelectTrigger class="!bg-background border-border text-foreground text-sm min-w-[120px]">
                                                            <SelectValue placeholder="End time" />
                                                        </SelectTrigger>
                                                        <SelectContent class="!bg-popover text-foreground border-border">
                                                            <SelectItem 
                                                                v-for="time in ['09:30', '10:00', '10:30', '11:00', '11:30', '12:00', '12:30', '13:00', '13:30', '14:00', '14:30', '15:00', '15:30', '16:00', '16:30', '17:00', '17:30']"
                                                                :key="time"
                                                                :value="time"
                                                                :disabled="!!(minTimeForEditEndTime && time < minTimeForEditEndTime)"
                                                            >
                                                                {{ formatTime(time) }}
                                                            </SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            </div>
                                        </template>

                                        <!-- Date range for all-day events (start date 'to' end date) -->
                                        <template v-else>
                                            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
                                                <span class="text-muted-foreground text-sm self-center px-2 sm:px-1">to</span>
                                                
                                                <div class="flex-1 sm:flex-none">
                                                    <Input
                                                        type="date"
                                                        id="edit-endDate"
                                                        v-model="editEventFormData.end_date"
                                                        :min="minDateForEditEvent"
                                                        class="bg-background border-border text-foreground text-sm min-w-[140px] focus:border-blue-500"
                                                        required
                                                    />
                                                </div>
                                                
                                                <div v-if="allDayDurationEdit" class="flex items-center text-sm text-muted-foreground px-2">
                                                    Duration: <span class="text-foreground font-medium ml-1">{{ allDayDurationEdit }}</span>
                                                </div>
                                            </div>
                                        </template>
                                        
                                        <div class="flex items-center justify-between sm:justify-end w-full sm:w-auto mt-2 sm:mt-0">
                                            <div class="flex items-center gap-2">
                                                <Label for="edit-allDay" class="text-sm text-foreground">All day</Label>
                                                <Switch
                                                    id="edit-allDay"
                                                    v-model="editEventFormData.is_all_day"
                                                    class="data-[state=checked]:bg-blue-500 data-[state=unchecked]:bg-background-600 relative inline-flex h-6 w-11 items-center rounded-full transition-colors duration-200 focus:outline-none"
                                                >
                                                    <span
                                                        class="pointer-events-none inline-block h-4 w-4 rounded-full bg-white shadow transform transition-transform duration-200"
                                                        :class="editEventFormData.is_all_day ? 'translate-x-6' : 'translate-x-1'"
                                                    />
                                                </Switch>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex gap-4 justify-end pt-4">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            @click="closeEdit"
                                            class="bg-transparent text-muted-foreground border-border hover:bg-accent"
                                            :disabled="isUpdating"
                                        >
                                            Cancel
                                        </Button>
                                        <Button
                                            type="submit"
                                            class="bg-blue-600 hover:bg-blue-700"
                                            :disabled="isUpdating"
                                        >
                                            {{ isUpdating ? 'Saving...' : 'Save Changes' }}
                                        </Button>
                                    </div>
                                </form>

                                <!-- Event Details View -->
                                <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6 p-4 sm:p-6 h-full">
                                    <!-- Left Column - Event Details -->
                                    <div class="lg:col-span-2 flex flex-col space-y-4 min-h-0">
                                        <!-- Basic Information -->
                                        <div class="bg-muted p-5 sm:p-6 rounded-lg border border-border/50">
                                            <h3 class="text-sm font-semibold text-foreground uppercase tracking-wide mb-5">
                                                Event Information
                                            </h3>
                                            <div class="space-y-4">
                                                <!-- Date & Time -->
                                                <div class="pb-4 border-b border-border/50">
                                                    <Label class="text-xs text-muted-foreground font-medium mb-2 block">Date & Time</Label>
                                                    <p class="text-base text-foreground font-semibold mb-1">{{ selectedEvent?.date?.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) }}</p>
                                                    <p class="text-sm text-foreground">
                                                        <template v-if="selectedEvent?.allDay && selectedEvent?.startDate && selectedEvent?.endDate">
                                                            All Day: {{ selectedEvent.startDate.toLocaleDateString() }} - {{ selectedEvent.endDate.toLocaleDateString() }}
                                                        </template>
                                                        <template v-else-if="selectedEvent?.allDay">
                                                            All Day Event
                                                        </template>
                                                        <template v-else-if="selectedEvent?.startTime && selectedEvent?.endTime">
                                                            {{ formatEventDetailsTime(selectedEvent.startTime) }} - {{ formatEventDetailsTime(selectedEvent.endTime) }}
                                                        </template>
                                                        <template v-else-if="selectedEvent?.time">
                                                            {{ selectedEvent.time }}
                                                        </template>
                                                    </p>
                                                </div>
                                                
                                                <!-- Category & Visibility Row -->
                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pb-4 border-b border-border/50">
                                                    <div>
                                                        <Label class="text-xs text-muted-foreground font-medium mb-2 block">Category</Label>
                                                        <Badge
                                                            variant="secondary"
                                                            class="text-sm border-0 px-3 py-1.5"
                                                            :class="selectedEvent?.color ?? undefined"
                                                            :style="(!selectedEvent?.color && selectedEvent?.colorHex) ? { backgroundColor: selectedEvent?.colorHex } : undefined"
                                                        >
                                                            {{ formatCategoryName(selectedEvent?.category) }}
                                                        </Badge>
                                                    </div>
                                                    <div>
                                                        <Label class="text-xs text-muted-foreground font-medium mb-2 block">Visibility</Label>
                                                        <Badge
                                                            v-if="selectedEvent?.visibility === 'everyone'"
                                                            variant="secondary"
                                                            class="text-sm border-0 px-3 py-1.5 bg-blue-500/20 text-blue-300"
                                                        >
                                                            For Everyone
                                                        </Badge>
                                                        <Badge
                                                            v-else-if="selectedEvent?.visibility === 'department' && selectedEvent?.department"
                                                            variant="secondary"
                                                            class="text-sm border-0 px-3 py-1.5 bg-purple-500/20 text-purple-300"
                                                        >
                                                            {{ selectedEvent.department.name }}
                                                        </Badge>
                                                        <Badge
                                                            v-else-if="selectedEvent?.visibility === 'department'"
                                                            variant="secondary"
                                                            class="text-sm border-0 px-3 py-1.5 bg-purple-500/20 text-purple-300"
                                                        >
                                                            Department Event
                                                        </Badge>
                                                    </div>
                                                </div>
                                                
                                                <!-- Location & Organizer Row -->
                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                    <div>
                                                        <Label class="text-xs text-muted-foreground font-medium mb-2 block">Location</Label>
                                                        <p class="text-sm text-foreground">{{ eventDetailsInfo.location || 'Not specified' }}</p>
                                                    </div>
                                                    <div>
                                                        <Label class="text-xs text-muted-foreground font-medium mb-2 block">Organizer</Label>
                                                        <p class="text-sm text-foreground">{{ eventDetailsInfo.organizer || 'Not specified' }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Description -->
                                        <div class="bg-muted p-5 sm:p-6 rounded-lg border border-border/50 transition-all duration-200 flex flex-col flex-1 min-h-0">
                                            <h3 class="text-sm font-semibold text-foreground uppercase tracking-wide mb-4">
                                                Description
                                            </h3>
                                            <ScrollArea class="flex-1">
                                                <p class="text-sm text-foreground leading-relaxed transition-all duration-200 pr-4">
                                                    {{ selectedEvent?.description || 'No description provided.' }}
                                                </p>
                                            </ScrollArea>
                                        </div>
                                    </div>

                                    <!-- Right Column - Going -->
                                    <div class="flex flex-col">
                                        <div class="bg-muted p-4 sm:p-5 rounded-lg border border-border/50 flex flex-col">
                                            <h3 class="text-sm font-semibold text-foreground uppercase tracking-wide mb-4">
                                                Going ({{ attendees.length }})
                                            </h3>
                                            <ScrollArea class="max-h-96">
                                                <div class="pr-4">
                                                    <template v-if="attendees.length === 0">
                                                        <div class="text-center py-8">
                                                            <p class="text-sm text-muted-foreground">No attendees yet</p>
                                                        </div>
                                                    </template>
                                                    <template v-else>
                                                        <div
                                                            v-for="attendee in attendees"
                                                            :key="attendee.id"
                                                            class="flex items-center gap-3 p-3 rounded-lg hover:bg-muted/50 transition-all duration-200"
                                                        >
                                                            <Avatar class="w-10 h-10 shrink-0">
                                                                <AvatarFallback
                                                                    class="bg-gradient-to-br from-blue-500 to-purple-600 text-foreground text-sm font-semibold"
                                                                >
                                                                    {{ attendee.name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2) }}
                                                                </AvatarFallback>
                                                            </Avatar>
                                                            <div class="min-w-0 flex-1">
                                                                <p class="text-sm text-foreground font-medium truncate">{{ attendee.name }}</p>
                                                                <p class="text-xs text-muted-foreground truncate">
                                                                    <template v-if="attendee.department_code && attendee.position">
                                                                        {{ attendee.department_code }} | {{ attendee.position }}
                                                                    </template>
                                                                    <template v-else-if="attendee.department_code">
                                                                        {{ attendee.department_code }}
                                                                    </template>
                                                                    <template v-else-if="attendee.position">
                                                                        {{ attendee.position }}
                                                                    </template>
                                                                    <template v-else>
                                                                        {{ attendee.email }}
                                                                    </template>
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </template>
                                                </div>
                                            </ScrollArea>
                                        </div>
                                    </div>
                                </div>

                                <!-- Action Buttons (only show when not editing) -->
                                <div v-if="!isEditing" class="flex flex-col-reverse sm:flex-row gap-3 justify-end mt-4 pt-4 border-t border-border/50">
                                    <Button
                                        @click="closeEventDetails"
                                        variant="ghost"
                                        size="sm"
                                        class="text-muted-foreground hover:text-foreground hover:bg-muted/50 transition-all duration-200"
                                    >
                                        Cancel
                                    </Button>
                                    
                                    <div class="flex gap-2">
                                        <Button
                                            v-if="canEditDeleteEvent"
                                            @click="handleDeleteClick"
                                            variant="outline"
                                            size="sm"
                                            class="border-red-600/50 text-red-400 hover:bg-red-600/10 hover:border-red-600 hover:text-red-300 transition-all duration-200 group"
                                        >
                                            <Trash2 class="w-4 h-4 group-hover:scale-110 transition-transform" />
                                            Delete
                                        </Button>
                                        
                                        <Button
                                            v-if="canEditDeleteEvent"
                                            @click="openEdit"
                                            variant="outline"
                                            size="sm"
                                            class="border-blue-600/50 text-blue-400 hover:bg-blue-600/10 hover:border-blue-600 hover:text-blue-300 transition-all duration-200 group"
                                        >
                                            <Pencil class="w-4 h-4 group-hover:scale-110 transition-transform" />
                                            Edit
                                        </Button>
                                        
                                        <Button
                                            @click="toggleAttendance"
                                            :variant="isAttending ? 'default' : 'outline'"
                                            size="sm"
                                            :disabled="isTogglingAttendance"
                                            :class="[
                                                isAttending 
                                                    ? 'bg-green-600 hover:bg-green-700 text-foreground border-green-600 shadow-lg shadow-green-600/20' 
                                                    : 'border-green-600/50 text-green-400 hover:bg-green-600/10 hover:border-green-600 hover:text-green-300',
                                                'transition-all duration-200 group',
                                                isTogglingAttendance ? 'opacity-50 cursor-not-allowed' : ''
                                            ]"
                                        >
                                            <UserCheck 
                                                v-if="isAttending" 
                                                class="w-4 h-4 group-hover:scale-110 transition-transform" 
                                            />
                                            <UserPlus 
                                                v-else 
                                                class="w-4 h-4 group-hover:scale-110 transition-transform" 
                                            />
                                            <span>{{ isTogglingAttendance ? 'Updating...' : (isAttending ? 'Going' : "I'm Going") }}</span>
                                        </Button>
                                    </div>
                                </div>
                            </ScrollArea>
                        </DialogContent>
                    </Dialog>

                    <!-- Delete Confirmation Dialog -->
                    <Dialog :open="showDeleteConfirm" @update:open="(open) => { if (!open) cancelDelete() }">
                        <DialogContent class="sm:max-w-md bg-card text-foreground border-border">
                            <DialogHeader>
                                <DialogTitle>Delete Event</DialogTitle>
                            </DialogHeader>
                            <div class="py-3">
                                <p class="text-sm text-foreground">
                                    Are you sure you want to delete this event? This action cannot be undone.
                                </p>
                            </div>
                            <div class="flex flex-col-reverse sm:flex-row gap-2 justify-end">
                                <Button
                                    @click="cancelDelete"
                                    variant="outline"
                                    size="sm"
                                    class="bg-transparent text-foreground border-border hover:bg-muted hover:text-foreground transition-all duration-200"
                                >
                                    Cancel
                                </Button>
                                <Button
                                    @click="confirmDelete"
                                    variant="destructive"
                                    size="sm"
                                    :disabled="deleteCountdown > 0 || isDeleting"
                                    class="bg-red-600 hover:bg-red-700 text-foreground transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    {{ isDeleting ? 'Deleting...' : (deleteCountdown > 0 ? `Confirm (${deleteCountdown}s)` : 'Confirm Delete') }}
                                </Button>
                            </div>
                        </DialogContent>
                    </Dialog>
                    
                    <!-- Day Events Modal -->
                    <Dialog :open="showDayEventsModal" @update:open="(open) => { if (!open) closeDayEventsModal() }">
                        <DialogContent class="sm:max-w-2xl bg-card text-foreground border-border">
                            <DialogHeader>
                                <DialogTitle class="text-xl font-semibold">
                                    Events for {{ selectedDate?.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' }) }}
                                </DialogTitle>
                            </DialogHeader>
                            
                            <ScrollArea class="max-h-[60vh] pr-4">
                                <div class="space-y-3">
                                    <div 
                                        v-for="event in selectedDayEvents" 
                                        :key="event.id"
                                        class="p-4 rounded-lg border border-border hover:border-border hover:bg-muted/30 transition-all duration-200 cursor-pointer bg-muted"
                                        @click="openEventDetails(event); closeDayEventsModal()"
                                    >
                                        <div class="flex items-start gap-3">
                                            <div
                                                class="w-3 h-3 rounded-full mt-1 flex-shrink-0"
                                                :class="event.color ?? undefined"
                                                :style="(!event.color && event.colorHex) ? { backgroundColor: event.colorHex } : undefined"
                                            ></div>
                                            <div class="flex-1 min-w-0">
                                                <h4 class="font-semibold text-foreground text-base mb-1 flex items-center gap-1.5">
                                                    <!-- "All" Badge for Everyone Events -->
                                                    <Badge 
                                                        v-if="event.visibility === 'everyone'"
                                                        variant="secondary"
                                                        class="text-[9px] px-1 py-0 h-4 font-semibold bg-green-600/30 text-green-300 border-green-500/50 shrink-0"
                                                    >
                                                        All
                                                    </Badge>
                                                    <!-- Department Code Badge for Departmental Events -->
                                                    <Badge 
                                                        v-if="event.visibility === 'department' && event.department?.code"
                                                        variant="secondary"
                                                        class="text-[9px] px-1 py-0 h-4 font-semibold bg-blue-600/30 text-blue-300 border-blue-500/50 shrink-0"
                                                    >
                                                        {{ event.department.code }}
                                                    </Badge>
                                                    <span class="truncate">{{ event.title }}</span>
                                                </h4>
                                                <p class="text-sm text-muted-foreground mb-2">
                                                    {{ formatEventTime(event) }}
                                                </p>
                                                <p v-if="event.description" class="text-sm text-foreground line-clamp-2">
                                                    {{ event.description }}
                                                </p>
                                                <div class="mt-2">
                                                    <span class="text-xs text-muted-foreground">
                                                        {{ formatCategoryName(event.category) }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </ScrollArea>
                            
                            <div class="flex justify-end pt-4 border-t border-border">
                                <Button
                                    @click="closeDayEventsModal"
                                    variant="outline"
                                    class="bg-transparent text-foreground border-border hover:bg-accent"
                                >
                                    Close
                                </Button>
                            </div>
                        </DialogContent>
                    </Dialog>
                    
                    <!-- Add Category Modal -->
                    <Dialog :open="showAddCategoryModal" @update:open="(open) => { if (!open) closeAddCategoryModal() }">
                        <DialogContent class="sm:max-w-md bg-card text-foreground border-border">
                            <DialogHeader>
                                <DialogTitle>Add New Category</DialogTitle>
                            </DialogHeader>

                            <div class="space-y-4">
                                <!-- Category Name -->
                                <div class="space-y-2">
                                    <Label for="categoryName" class="text-foreground">Category Name:</Label>
                                    <Input 
                                        id="categoryName"
                                        v-model="newCategoryName"
                                        placeholder="Enter category name"
                                        class="bg-background border-border text-foreground focus:border-blue-500"
                                        @keyup.enter="saveNewCategory"
                                    />
                                </div>

                                <!-- Color Picker -->
                                <div class="space-y-2">
                                    <Label class="text-foreground">Category Color:</Label>
                                    <div class="flex items-center gap-3">
                                        <!-- Color preview -->
                                        <div 
                                            class="w-8 h-8 rounded-full border-2 border-border"
                                            :style="{ backgroundColor: newCategoryColor }"
                                        ></div>
                                        
                                        <!-- HTML5 Color Picker -->
                                        <input 
                                            type="color" 
                                            :value="newCategoryColor"
                                            @input="handleColorInput"
                                            @change="handleColorInput"
                                            class="w-12 h-8 rounded border border-border bg-background cursor-pointer"
                                        />
                                        
                                        <!-- Hex input -->
                                        <Input 
                                            v-model="newCategoryColor"
                                            placeholder="#000000"
                                            class="bg-background border-border text-foreground focus:border-blue-500 w-24 text-sm"
                                        />
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="flex gap-3 justify-end pt-4">
                                    <Button 
                                        type="button" 
                                        variant="outline"
                                        @click="closeAddCategoryModal"
                                        class="bg-transparent text-muted-foreground border-border hover:bg-accent"
                                    >
                                        Cancel
                                    </Button>
                                    <Button 
                                        type="button"
                                        @click="saveNewCategory"
                                        :disabled="!newCategoryName.trim() || isSavingCategory"
                                        class="bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        {{ isSavingCategory ? 'Creating...' : 'Add Category' }}
                                    </Button>
                                </div>
                            </div>
                        </DialogContent>
                    </Dialog>
                    
                    <!-- Edit Category Modal -->
                    <Dialog :open="showEditCategoryModal" @update:open="(open) => { if (!open) closeEditCategoryModal() }">
                        <DialogContent class="sm:max-w-md bg-card text-foreground border-border">
                            <DialogHeader>
                                <DialogTitle>Edit Category</DialogTitle>
                            </DialogHeader>

                            <div class="space-y-4">
                                <!-- Category Name -->
                                <div class="space-y-2">
                                    <Label for="editCategoryName" class="text-foreground">Category Name:</Label>
                                    <Input 
                                        id="editCategoryName"
                                        v-model="editCategoryName"
                                        placeholder="Enter category name"
                                        class="bg-background border-border text-foreground focus:border-blue-500"
                                        @keyup.enter="saveEditCategory"
                                    />
                                </div>

                                <!-- Color Picker -->
                                <div class="space-y-2">
                                    <Label class="text-foreground">Category Color:</Label>
                                    <div class="flex items-center gap-3">
                                        <!-- Color preview -->
                                        <div 
                                            class="w-8 h-8 rounded-full border-2 border-border"
                                            :style="{ backgroundColor: editCategoryColor }"
                                        ></div>
                                        
                                        <!-- HTML5 Color Picker -->
                                        <input 
                                            type="color" 
                                            :value="editCategoryColor"
                                            @input="handleEditColorInput"
                                            @change="handleEditColorInput"
                                            class="w-12 h-8 rounded border border-border bg-background cursor-pointer"
                                        />
                                        
                                        <!-- Hex input -->
                                        <Input 
                                            v-model="editCategoryColor"
                                            placeholder="#000000"
                                            class="bg-background border-border text-foreground focus:border-blue-500 w-24 text-sm"
                                        />
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="flex gap-3 justify-end pt-4">
                                    <Button 
                                        type="button" 
                                        variant="outline"
                                        @click="closeEditCategoryModal"
                                        class="bg-transparent text-muted-foreground border-border hover:bg-accent"
                                        :disabled="isUpdatingCategory"
                                    >
                                        Cancel
                                    </Button>
                                    <Button 
                                        type="button"
                                        @click="saveEditCategory"
                                        :disabled="!editCategoryName.trim() || isUpdatingCategory"
                                        class="bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        {{ isUpdatingCategory ? 'Updating...' : 'Update Category' }}
                                    </Button>
                                </div>
                            </div>
                        </DialogContent>
                    </Dialog>
                    
                    <!-- Delete Category Confirmation Dialog -->
                    <Dialog :open="showDeleteCategoryConfirm" @update:open="(open) => { if (!open) cancelDeleteCategory() }">
                        <DialogContent class="sm:max-w-md bg-card text-foreground border-border">
                            <DialogHeader>
                                <DialogTitle>Delete Category</DialogTitle>
                            </DialogHeader>
                            <div class="py-3">
                                <p class="text-sm text-foreground">
                                    Are you sure you want to delete this category? This action cannot be undone. Categories that are being used by events cannot be deleted.
                                </p>
                            </div>
                            <div class="flex flex-col-reverse sm:flex-row gap-2 justify-end">
                                <Button
                                    @click="cancelDeleteCategory"
                                    variant="outline"
                                    size="sm"
                                    class="bg-transparent text-foreground border-border hover:bg-muted hover:text-foreground transition-all duration-200"
                                >
                                    Cancel
                                </Button>
                                <Button
                                    @click="confirmDeleteCategory"
                                    variant="destructive"
                                    size="sm"
                                    :disabled="categoryDeleteCountdown > 0 || isDeletingCategory"
                                    class="bg-red-600 hover:bg-red-700 text-foreground transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    {{ isDeletingCategory ? 'Deleting...' : (categoryDeleteCountdown > 0 ? `Confirm (${categoryDeleteCountdown}s)` : 'Confirm Delete') }}
                                </Button>
                            </div>
                        </DialogContent>
                    </Dialog>
                </div>

                <!-- Calendar Grid -->
                <div class="flex flex-col">
                    <!-- Day Headers -->
                    <div class="grid grid-cols-7 shrink-0 border-b border-border bg-muted">
                        <div class="text-center text-xs font-medium text-muted-foreground p-1 sm:p-2 md:p-3">Sun</div>
                        <div class="text-center text-xs font-medium text-muted-foreground p-1 sm:p-2 md:p-3 border-l border-border">Mon</div>
                        <div class="text-center text-xs font-medium text-muted-foreground p-1 sm:p-2 md:p-3 border-l border-border">Tue</div>
                        <div class="text-center text-xs font-medium text-muted-foreground p-1 sm:p-2 md:p-3 border-l border-border">Wed</div>
                        <div class="text-center text-xs font-medium text-muted-foreground p-1 sm:p-2 md:p-3 border-l border-border">Thu</div>
                        <div class="text-center text-xs font-medium text-muted-foreground p-1 sm:p-2 md:p-3 border-l border-border">Fri</div>
                        <div class="text-center text-xs font-medium text-muted-foreground p-1 sm:p-2 md:p-3 border-l border-border">Sat</div>
                    </div>

                    <!-- Calendar Days -->
                    <div class="grid grid-cols-7">
                    <template v-for="(dayData, index) in calendarDays" :key="`${dayData.date.getTime()}-${index}`">
                        <div 
                            class="aspect-square p-1 sm:p-2 md:p-3 text-sm border-b border-r border-border relative hover:bg-muted/50 transition-all duration-200 cursor-pointer group flex flex-col"
                            :class="[
                                dayData.isCurrentMonth ? 'text-foreground' : 'text-muted-foreground/70',
                                dayData.date.toDateString() === today.toDateString() ? 'bg-blue-600/20 border-blue-500/30' : ''
                            ]"
                            @click="dayData.events.length === 1 ? openEventDetails(dayData.events[0]) : dayData.events.length >= 2 ? openDayEvents(dayData.date, dayData.events) : null"
                        >
                            <div class="flex justify-between items-start shrink-0">
                                <span 
                                    class="font-semibold text-xs sm:text-sm md:text-base transition-colors duration-200"
                                    :class="dayData.date.toDateString() === today.toDateString() ? 'text-blue-300' : ''"
                                >
                                    {{ dayData.day }}
                                </span>
                                <!-- +N indicator at top right, aligned with date -->
                                <Badge 
                                    v-if="dayData.events.length > (isMobile ? 3 : 2)" 
                                    variant="secondary"
                                    class="text-[10px] px-1.5 py-0 h-4 text-muted-foreground"
                                >
                                    +{{ dayData.events.length - (isMobile ? 3 : 2) }}
                                </Badge>
                            </div>
                            
                            <!-- Event indicators - more compact on mobile -->
                            <div v-if="dayData.events.length > 0" class="mt-0.5 sm:mt-1 md:mt-2 space-y-1 sm:space-y-2 flex-1 min-h-0 overflow-hidden">
                                <!-- Mobile: Show only dots -->
                                <div class="sm:hidden flex gap-1 flex-wrap">
                                    <div 
                                        v-for="event in dayData.events.slice(0, 3)" 
                                        :key="event.id"
                                        class="w-1.5 h-1.5 rounded-full"
                                        :style="event.colorHex ? { backgroundColor: event.colorHex } : { backgroundColor: '#6b7280' }"
                                        :title="`${event.title} - ${formatEventTime(event)}`"
                                        @click.stop="openEventDetails(event)"
                                    ></div>
                                </div>
                                
                                <!-- Tablet and Desktop: Show event cards -->
                                <div class="hidden sm:block space-y-2">
                                    <div 
                                        v-for="event in dayData.events.slice(0, 2)" 
                                        :key="event.id"
                                        class="text-xs px-2 py-1.5 rounded-md truncate transition-all duration-200 cursor-pointer font-medium text-foreground shadow-sm hover:shadow-md border border-black/20 hover:brightness-110 flex items-center gap-1.5"
                                        :style="event.colorHex ? { backgroundColor: event.colorHex } : { backgroundColor: '#6b7280' }"
                                        :title="`${event.title} - ${formatEventTime(event)}`"
                                        @click.stop="openEventDetails(event)"
                                    >
                                        <!-- "All" Badge for Everyone Events -->
                                        <Badge 
                                            v-if="event.visibility === 'everyone'"
                                            variant="secondary"
                                            class="text-[9px] px-1 py-0 h-4 font-semibold bg-green-600/30 text-green-300 border-green-500/50 shrink-0"
                                        >
                                            All
                                        </Badge>
                                        <!-- Department Code Badge for Departmental Events -->
                                        <Badge 
                                            v-if="event.visibility === 'department' && event.department?.code"
                                            variant="secondary"
                                            class="text-[9px] px-1 py-0 h-4 font-semibold bg-white/20 text-foreground border-white/30 shrink-0"
                                        >
                                            {{ event.department.code }}
                                        </Badge>
                                        <span class="truncate">{{ event.title }}</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- "..." indicator at bottom when there are more events - fixed position -->
                            <div v-if="dayData.events.length > (isMobile ? 3 : 2)" class="absolute bottom-1 left-0 right-0 text-center text-muted-foreground text-base pointer-events-none">
                                ...
                            </div>
                        </div>
                    </template>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>