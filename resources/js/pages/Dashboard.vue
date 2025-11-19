<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Calendar as CalendarIcon, Clock, Users, ClipboardList, Cake, MessageSquare, ChevronLeft, ChevronRight, Video } from 'lucide-vue-next'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { ScrollArea } from '@/components/ui/scroll-area'
import {
  Pagination,
  PaginationContent,
  PaginationItem,
  PaginationNext,
  PaginationPrevious,
} from '@/components/ui/pagination'

import { Bar, Pie } from 'vue-chartjs'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend,
  ArcElement
} from 'chart.js'
// @ts-expect-error - chartjs-plugin-datalabels may not have type definitions
import ChartDataLabels from 'chartjs-plugin-datalabels'

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend, ArcElement, ChartDataLabels)

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

// Current user
const currentUser = ref({
  name: 'Jerick',
  role: 'Admin', // or 'Admin'
})

// View mode
const viewMode = ref<'monthly' | 'weekly'>('monthly')

// Date and time
const currentDateTime = ref(new Date())
let timeInterval: number | null = null

const formattedDate = computed(() => {
  return currentDateTime.value.toLocaleDateString('en-US', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
})

const formattedTime = computed(() => {
  return currentDateTime.value.toLocaleTimeString('en-US', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit'
  })
})

onMounted(() => {
  timeInterval = window.setInterval(() => {
    currentDateTime.value = new Date()
  }, 1000)
  
  // Add event indicators to calendar
  updateCalendarEventIndicators()
  
  // Re-apply indicators when calendar changes (month navigation)
  setTimeout(() => {
    const observer = new MutationObserver(updateCalendarEventIndicators)
    const calendarEl = document.querySelector('[data-radix-calendar]')
    if (calendarEl) {
      observer.observe(calendarEl, { childList: true, subtree: true })
    }
  }, 500)
})

onUnmounted(() => {
  if (timeInterval) {
    clearInterval(timeInterval)
  }
})

// Today's attendance
const todayAttendance = ref({
  timeIn: '08:45 AM',  // Set to empty string '' if not clocked in yet
  timeOut: '',         // Set to empty string '' if not clocked out yet
  date: new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
})

// Events list
const events = ref([
  { id: 1, title: 'Team Meeting', date: '2025-11-15', time: '10:00 AM', type: 'meeting', isToday: true },
  { id: 2, title: 'Project Deadline', date: '2025-11-18', time: '5:00 PM', type: 'deadline', isToday: false },
  { id: 3, title: 'Company Holiday', date: '2025-11-25', time: 'All Day', type: 'holiday', isToday: false },
  { id: 4, title: 'Performance Review', date: '2025-11-20', time: '2:00 PM', type: 'meeting', isToday: false },
  { id: 5, title: 'Training Session', date: '2025-11-22', time: '9:00 AM', type: 'training', isToday: false },
  { id: 6, title: 'Client Call', date: '2025-11-15', time: '2:00 PM', type: 'meeting', isToday: true },
  { id: 7, title: 'Code Review', date: '2025-11-15', time: '4:00 PM', type: 'training', isToday: true }
])

const getEventTypeColor = (type: string) => {
  switch (type) {
    case 'meeting': return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
    case 'deadline': return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
    case 'holiday': return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
    case 'training': return 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400'
    default: return 'bg-gray-100 text-gray-700 dark:bg-gray-900/30 dark:text-gray-400'
  }
}

// Schedule widget state
const scheduleSearch = ref('')
const scheduleTab = ref<'today' | 'upcoming'>('today')
const selectedScheduleDate = ref(new Date())

// Generate date range for horizontal scroller (7 days: 3 before, today, 3 after)
const dateRange = computed(() => {
  const dates = []
  const baseDate = new Date(selectedScheduleDate.value)
  
  for (let i = -3; i <= 3; i++) {
    const date = new Date(baseDate)
    date.setDate(baseDate.getDate() + i)
    dates.push(date)
  }
  
  return dates
})

// Check if date is today
const isToday = (date: Date) => {
  const today = new Date()
  return date.getDate() === today.getDate() &&
         date.getMonth() === today.getMonth() &&
         date.getFullYear() === today.getFullYear()
}

// Check if date is selected
const isSelectedDate = (date: Date) => {
  return date.getDate() === selectedScheduleDate.value.getDate() &&
         date.getMonth() === selectedScheduleDate.value.getMonth() &&
         date.getFullYear() === selectedScheduleDate.value.getFullYear()
}

// Format date for display
const formatScheduleDate = (date: Date, format: 'day' | 'date' | 'full') => {
  if (format === 'day') {
    return date.toLocaleDateString('en-US', { weekday: 'short' })
  } else if (format === 'date') {
    return date.getDate().toString()
  } else {
    return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' })
  }
}

// Navigate dates
const previousWeek = () => {
  const newDate = new Date(selectedScheduleDate.value)
  newDate.setDate(newDate.getDate() - 7)
  selectedScheduleDate.value = newDate
}

const nextWeek = () => {
  const newDate = new Date(selectedScheduleDate.value)
  newDate.setDate(newDate.getDate() + 7)
  selectedScheduleDate.value = newDate
}

// Helper function to get color and border for event type
const getScheduleEventColors = (type: string) => {
  switch (type) {
    case 'meeting':
      return { color: 'bg-blue-100 dark:bg-blue-900/30', borderColor: 'border-blue-300 dark:border-blue-700' }
    case 'deadline':
      return { color: 'bg-red-100 dark:bg-red-900/30', borderColor: 'border-red-300 dark:border-red-700' }
    case 'holiday':
      return { color: 'bg-amber-100 dark:bg-amber-900/30', borderColor: 'border-amber-300 dark:border-amber-700' }
    case 'training':
      return { color: 'bg-purple-100 dark:bg-purple-900/30', borderColor: 'border-purple-300 dark:border-purple-700' }
    default:
      return { color: 'bg-gray-100 dark:bg-gray-900/30', borderColor: 'border-gray-300 dark:border-gray-700' }
  }
}

// Filtered schedule events - using calendar events
const filteredScheduleEvents = computed(() => {
  return events.value
    .filter(event => {
      // Filter by tab (today or upcoming)
      if (scheduleTab.value === 'today') {
        return event.isToday
      } else {
        // Show only future events (not today)
        return !event.isToday
      }
    })
    .filter(event => {
      // Filter by search
      if (scheduleSearch.value) {
        const search = scheduleSearch.value.toLowerCase()
        return event.title.toLowerCase().includes(search) ||
               event.type.toLowerCase().includes(search)
      }
      return true
    })
    .map(event => {
      // Transform calendar event to schedule event format
      const colors = getScheduleEventColors(event.type)
      return {
        ...event,
        startTime: event.time,
        endTime: '',
        location: event.type === 'meeting' ? 'Conference Room' : '',
        department: 'General',
        attendees: [],
        ...colors
      }
    })
})

// Get events for a specific date
const getEventsForDate = (dateStr: string) => {
  return events.value.filter(event => event.date === dateStr)
}

// Add event indicators to calendar dates
const updateCalendarEventIndicators = () => {
  setTimeout(() => {
    const calendarButtons = document.querySelectorAll('[data-radix-calendar] button[data-radix-collection-item]')
    calendarButtons.forEach((button) => {
      const dateText = button.textContent?.trim()
      if (dateText && !isNaN(parseInt(dateText))) {
        const day = parseInt(dateText).toString().padStart(2, '0')
        const dateStr = `2025-11-${day}`
        
        const eventsForDate = getEventsForDate(dateStr)
        if (eventsForDate.length > 0) {
          button.setAttribute('data-has-events', 'true')
          button.setAttribute('data-event-count', eventsForDate.length.toString())
          button.setAttribute('title', eventsForDate.map(e => `${e.title} - ${e.time}`).join('\n'))
        }
      }
    })
  }, 300)
}

// Birthdays list
const birthdays = ref([
  { id: 1, name: 'Sarah Johnson', department: 'Marketing', date: 'Today', age: 28 },
  { id: 2, name: 'Michael Chen', department: 'Engineering', date: 'Nov 16', age: 32 },
  { id: 3, name: 'Emma Davis', department: 'HR', date: 'Nov 18', age: 26 }
])

// Unread messages
const unreadMessages = ref([
  { id: 1, sender: 'Sarah Johnson', avatar: 'SJ', message: 'Can you review the project proposal?', time: '5 min ago', unread: 2 },
  { id: 2, sender: 'Engineering Team', avatar: 'ET', message: 'Meeting rescheduled to 3 PM', time: '15 min ago', unread: 3 },
  { id: 3, sender: 'Michael Chen', avatar: 'MC', message: 'Thanks for the quick response!', time: '1 hour ago', unread: 1 },
  { id: 4, sender: 'HR Department', avatar: 'HR', message: 'Please submit your timesheet', time: '2 hours ago', unread: 1 }
])

// Pagination for employee attendance
const currentPage = ref(1)
const itemsPerPage = 5

// Employee attendance today
const employeeAttendance = ref([
  { id: 1, name: 'Sarah Johnson', department: 'Marketing', timeIn: '08:45 AM', timeOut: '05:30 PM', status: 'present' },
  { id: 2, name: 'Michael Chen', department: 'Engineering', timeIn: '08:30 AM', timeOut: '05:15 PM', status: 'present' },
  { id: 3, name: 'Emma Davis', department: 'HR', timeIn: '09:00 AM', timeOut: '05:00 PM', status: 'present' },
  { id: 4, name: 'John Smith', department: 'Engineering', timeIn: '08:50 AM', timeOut: '05:10 PM', status: 'present' },
  { id: 5, name: 'Lisa Anderson', department: 'Sales', timeIn: '09:15 AM', timeOut: '05:45 PM', status: 'late' },
  { id: 6, name: 'David Wilson', department: 'Marketing', timeIn: '08:40 AM', timeOut: '05:25 PM', status: 'present' },
  { id: 7, name: 'Jennifer Lee', department: 'Finance', timeIn: '08:35 AM', timeOut: '-', status: 'incomplete' },
  { id: 8, name: 'Robert Brown', department: 'Engineering', timeIn: '08:55 AM', timeOut: '-', status: 'incomplete' },
  { id: 9, name: 'Amanda White', department: 'Marketing', timeIn: '08:50 AM', timeOut: '05:20 PM', status: 'present' },
  { id: 10, name: 'James Miller', department: 'Sales', timeIn: '08:35 AM', timeOut: '05:35 PM', status: 'present' },
  { id: 11, name: 'Patricia Garcia', department: 'HR', timeIn: '09:20 AM', timeOut: '05:50 PM', status: 'late' },
  { id: 12, name: 'Daniel Martinez', department: 'Engineering', timeIn: '08:45 AM', timeOut: '05:15 PM', status: 'present' },
  { id: 13, name: 'Linda Rodriguez', department: 'Finance', timeIn: '08:40 AM', timeOut: '-', status: 'incomplete' },
  { id: 14, name: 'Christopher Lee', department: 'Sales', timeIn: '09:25 AM', timeOut: '05:55 PM', status: 'late' },
  { id: 15, name: 'Barbara Taylor', department: 'Marketing', timeIn: '08:30 AM', timeOut: '05:00 PM', status: 'present' },
  { id: 16, name: 'Matthew Thomas', department: 'Engineering', timeIn: '08:55 AM', timeOut: '-', status: 'incomplete' },
  { id: 17, name: 'Nancy Anderson', department: 'HR', timeIn: '08:45 AM', timeOut: '05:15 PM', status: 'present' },
  { id: 18, name: 'Joseph Jackson', department: 'Finance', timeIn: '09:10 AM', timeOut: '05:40 PM', status: 'late' },
  { id: 19, name: 'Susan White', department: 'Sales', timeIn: '08:50 AM', timeOut: '05:20 PM', status: 'present' },
  { id: 20, name: 'Charles Harris', department: 'Engineering', timeIn: '08:35 AM', timeOut: '-', status: 'incomplete' },
  { id: 21, name: 'Karen Moore', department: 'Marketing', timeIn: '08:40 AM', timeOut: '05:10 PM', status: 'present' },
  { id: 22, name: 'Steven Clark', department: 'Sales', timeIn: '09:30 AM', timeOut: '06:00 PM', status: 'late' },
  { id: 23, name: 'Betty Lewis', department: 'HR', timeIn: '08:55 AM', timeOut: '05:25 PM', status: 'present' },
  { id: 24, name: 'Edward Walker', department: 'Engineering', timeIn: '08:30 AM', timeOut: '-', status: 'incomplete' },
  { id: 25, name: 'Dorothy Hall', department: 'Finance', timeIn: '08:45 AM', timeOut: '05:15 PM', status: 'present' },
  { id: 26, name: 'Brian Allen', department: 'Marketing', timeIn: '09:05 AM', timeOut: '05:35 PM', status: 'late' },
  { id: 27, name: 'Helen Young', department: 'Sales', timeIn: '08:50 AM', timeOut: '05:20 PM', status: 'present' },
  { id: 28, name: 'Ronald King', department: 'Engineering', timeIn: '08:40 AM', timeOut: '-', status: 'incomplete' },
  { id: 29, name: 'Sandra Wright', department: 'HR', timeIn: '08:35 AM', timeOut: '05:05 PM', status: 'present' },
  { id: 30, name: 'Kevin Scott', department: 'Finance', timeIn: '09:20 AM', timeOut: '05:50 PM', status: 'late' }
])

const getStatusBadge = (status: string) => {
  switch (status) {
    case 'present': return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
    case 'late': return 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400'
    case 'incomplete': return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
    default: return 'bg-gray-100 text-gray-700 dark:bg-gray-900/30 dark:text-gray-400'
  }
}

// Paginated employee attendance
const totalPages = computed(() => Math.ceil(employeeAttendance.value.length / itemsPerPage))

const paginatedEmployees = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage
  const end = start + itemsPerPage
  return employeeAttendance.value.slice(start, end)
})

// Visible page numbers (show max 3)
const visiblePages = computed(() => {
  const pages = []
  const total = totalPages.value
  const current = currentPage.value
  
  if (total <= 3) {
    // Show all pages if 3 or less
    for (let i = 1; i <= total; i++) {
      pages.push(i)
    }
  } else {
    // Show current page and one on each side
    if (current === 1) {
      pages.push(1, 2, 3)
    } else if (current === total) {
      pages.push(total - 2, total - 1, total)
    } else {
      pages.push(current - 1, current, current + 1)
    }
  }
  
  return pages
})

// Stats data - User's personal attendance
const stats = ref({
  totalEmployees: 26,
  employeeHiring: 4,
  hiringPercentage: '+15%',
  totalPresents: 15,
  workingDays: '20 Days',
  presentTrend: '+5%',
  presentTrendUp: true,
  totalLate: 3,
  lateRate: '15%',
  lateTrend: '-2%',
  lateTrendUp: false,
  totalIncomplete: 2,
  incompleteRate: '10%',
  incompleteTrend: '+1',
  incompleteTrendUp: true,
  hoursWorked: '120h',
  averageDaily: '8h/day',
  hoursTrend: '+8h',
  hoursTrendUp: true
})

// Chart data for attendance overview (last 7 days)
const attendanceChartData = computed(() => {
  const isMonthly = viewMode.value === 'monthly'
  
  if (isMonthly) {
    // Monthly view - show weekly personal attendance (days per week)
    return {
      labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
      datasets: [
        {
          label: 'Present',
          data: [4, 5, 4, 3],
          backgroundColor: 'rgba(34, 197, 94, 0.8)',
          borderColor: '#16a34a',
          borderWidth: 0,
          borderRadius: 8,
          borderSkipped: false,
        },
        {
          label: 'Late',
          data: [1, 0, 1, 1],
          backgroundColor: 'rgba(249, 115, 22, 0.8)',
          borderColor: '#ea580c',
          borderWidth: 0,
          borderRadius: 8,
          borderSkipped: false,
        },
        {
          label: 'Incomplete',
          data: [0, 0, 0, 1],
          backgroundColor: 'rgba(239, 68, 68, 0.8)',
          borderColor: '#dc2626',
          borderWidth: 0,
          borderRadius: 8,
          borderSkipped: false,
        }
      ]
    }
  } else {
    // Weekly view - show daily data
    return {
      labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
      datasets: [
        {
          label: 'Present',
          data: [1, 1, 0, 1, 1, 0, 0],
          backgroundColor: 'rgba(34, 197, 94, 0.8)',
          borderColor: '#16a34a',
          borderWidth: 0,
          borderRadius: 8,
          borderSkipped: false,
        },
        {
          label: 'Late',
          data: [0, 0, 1, 0, 0, 0, 0],
          backgroundColor: 'rgba(249, 115, 22, 0.8)',
          borderColor: '#ea580c',
          borderWidth: 0,
          borderRadius: 8,
          borderSkipped: false,
        },
        {
          label: 'Incomplete',
          data: [0, 0, 0, 0, 0, 0, 0],
          backgroundColor: 'rgba(239, 68, 68, 0.8)',
          borderColor: '#dc2626',
          borderWidth: 0,
          borderRadius: 8,
          borderSkipped: false,
        }
      ]
    }
  }
})

const attendanceChartOptions = ref({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      display: false
    },
    tooltip: {
      backgroundColor: 'rgba(17, 24, 39, 0.95)',
      titleColor: '#f9fafb',
      bodyColor: '#f9fafb',
      borderColor: 'rgba(75, 85, 99, 0.3)',
      borderWidth: 1,
      cornerRadius: 8,
      padding: 12,
      displayColors: true,
      boxWidth: 8,
      boxHeight: 8,
      usePointStyle: true
    }
  },
  scales: {
    y: {
      beginAtZero: true,
      title: {
        display: true,
        text: 'Days',
        color: '#9ca3af',
        font: {
          size: 12,
          weight: 500
        }
      },
      ticks: {
        stepSize: 1,
        color: '#9ca3af',
        font: {
          size: 11
        }
      },
      grid: {
        color: 'rgba(55, 65, 81, 0.1)',
        drawBorder: false
      }
    },
    x: {
      ticks: {
        color: '#9ca3af',
        font: {
          size: 11
        }
      },
      grid: {
        display: false
      }
    }
  }
})

// Attendance status distribution chart data (Donut Chart)
const attendanceStatusChartData = computed(() => {
  // Count employees by status
  const statusCounts = employeeAttendance.value.reduce((acc, emp) => {
    acc[emp.status] = (acc[emp.status] || 0) + 1
    return acc
  }, {} as Record<string, number>)

  const present = statusCounts.present || 0
  const late = statusCounts.late || 0
  const incomplete = statusCounts.incomplete || 0

  return {
    labels: ['Present', 'Late', 'Incomplete'],
    datasets: [
      {
        label: 'Employees',
        data: [present, late, incomplete],
        backgroundColor: [
          'rgba(34, 197, 94, 0.9)',   // Green for Present
          'rgba(249, 115, 22, 0.9)',  // Orange for Late
          'rgba(239, 68, 68, 0.9)',   // Red for Incomplete
        ],
        borderColor: '#252422 ',
        borderWidth: 4,
        hoverOffset: 12,
        spacing: 2,
      }
    ]
  }
})

const attendanceStatusChartOptions = ref({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      display: false
    },
    tooltip: {
      backgroundColor: 'rgba(17, 24, 39, 0.95)',
      titleColor: '#f9fafb',
      bodyColor: '#f9fafb',
      borderColor: 'rgba(75, 85, 99, 0.3)',
      borderWidth: 1,
      cornerRadius: 8,
      padding: 12,
      displayColors: true,
      boxWidth: 12,
      boxHeight: 12,
      usePointStyle: true,
      callbacks: {
        label: function(context: any) {
          const label = context.label || ''
          const value = context.parsed || 0
          const total = context.dataset.data.reduce((a: number, b: number) => a + b, 0)
          const percentage = ((value / total) * 100).toFixed(1)
          return `${label}: ${value} (${percentage}%)`
        }
      }
    },
    datalabels: {
      backgroundColor: 'rgba(255, 255, 255, 0.95)',
      borderRadius: 8 ,
      color: '#374151',
      font: {
        weight: 'bold',
        size: 11,
      },
      padding: {
        top: 6,
        bottom: 6,
        left: 10,
        right: 10
      },
      formatter: (value: number, context: any) => {
        const total = context.dataset.data.reduce((a: number, b: number) => a + b, 0)
        const percentage = ((value / total) * 100).toFixed(1)
        return `${value}\n${percentage}%`
      },
      textAlign: 'center',
      anchor: 'center',
      align: 'center'
    }
  }
})
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4 bg-gray-50/30 dark:bg-black/25">
        
            <!-- Unified Header & Stats Section -->
            <Card class="bg-background border border-sidebar-border/70 dark:border-sidebar-border shadow-sm">
                <CardContent class="p-4">
                    <!-- Header -->
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 pb-4 border-b border-sidebar-border/50">
                        <!-- Left: User Greeting -->
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <h1 class="text-2xl font-bold text-foreground">
                                    Hi {{ currentUser.name }}!
                                </h1>
                                <Badge 
                                    :class="currentUser.role === 'Admin' ? 'bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300' : 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300'"
                                    class="text-xs"
                                >
                                    {{ currentUser.role }}
                                </Badge>
                            </div>
                            <p class="text-sm text-muted-foreground">Welcome back! Here's your overview for today.</p>
                        </div>

                        <!-- Right: Date, Time & Today's Attendance -->
                        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
                            <!-- Date & Time -->
                            <div class="text-left sm:text-right">
                                <div class="flex items-center gap-1.5 text-foreground font-semibold text-sm mb-0.5">
                                    <CalendarIcon class="h-3.5 w-3.5" />
                                    {{ formattedDate }}
                                </div>
                                <div class="flex items-center gap-1.5 text-muted-foreground text-xs">
                                    <Clock class="h-3.5 w-3.5" />
                                    {{ formattedTime }}
                                </div>
                            </div>

                            <!-- Divider -->
                            <div class="hidden sm:block h-12 w-px bg-border"></div>

                            <!-- Today's Attendance -->
                            <div class="flex gap-4">
                                <div>
                                    <p class="text-[10px] text-muted-foreground mb-0.5">Time In</p>
                                    <p class="text-base font-bold text-foreground">{{ todayAttendance.timeIn || '-' }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] text-muted-foreground mb-0.5">Time Out</p>
                                    <p class="text-base font-bold text-foreground">{{ todayAttendance.timeOut || '-' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Stats Cards -->
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 pt-4">
                        <!-- Monthly Presents Card -->
                        <div class="p-3 lg:p-3.5 rounded-lg border border-sidebar-border/50 bg-card/30 hover:bg-card/50 transition-colors">
                            <div class="flex items-start justify-between mb-2">
                                <div class="flex-1 min-w-0">
                                    <p class="text-muted-foreground text-xs font-medium mb-1">Monthly Presents</p>
                                    <h3 class="text-2xl lg:text-2xl font-bold text-foreground">{{ stats.totalPresents }}</h3>
                                </div>
                                <div class="bg-green-100 dark:bg-green-900/30 p-2 rounded-lg flex-shrink-0">
                                    <Users class="h-4 w-4 text-green-600 dark:text-green-400" />
                                </div>
                            </div>
                            <div class="space-y-0.5">
                                <Badge variant="outline" class="bg-green-50 text-green-600 border-green-200 dark:bg-green-950/20 dark:text-green-400 text-xs">
                                    {{ stats.workingDays }}
                                </Badge>
                                <p class="text-muted-foreground text-xs">Out of Working Days</p>
                            </div>
                        </div>

                        <!-- Monthly Late Card -->
                        <div class="p-3 lg:p-3.5 rounded-lg border border-sidebar-border/50 bg-card/30 hover:bg-card/50 transition-colors">
                            <div class="flex items-start justify-between mb-2">
                                <div class="flex-1 min-w-0">
                                    <p class="text-muted-foreground text-xs font-medium mb-1">Monthly Late</p>
                                    <h3 class="text-2xl lg:text-2xl font-bold text-foreground">{{ stats.totalLate }}</h3>
                                </div>
                                <div class="bg-orange-100 dark:bg-orange-900/30 p-2 rounded-lg flex-shrink-0">
                                    <Clock class="h-4 w-4 text-orange-600 dark:text-orange-400" />
                                </div>
                            </div>
                            <div class="space-y-0.5">
                                <Badge variant="outline" class="bg-orange-50 text-orange-600 border-orange-200 dark:bg-orange-950/20 dark:text-orange-400 text-xs">
                                    {{ stats.lateRate }}
                                </Badge>
                                <p class="text-muted-foreground text-xs">Of Total Days</p>
                            </div>
                        </div>

                        <!-- Monthly Incomplete Card -->
                        <div class="p-3 lg:p-3.5 rounded-lg border border-sidebar-border/50 bg-card/30 hover:bg-card/50 transition-colors">
                            <div class="flex items-start justify-between mb-2">
                                <div class="flex-1 min-w-0">
                                    <p class="text-muted-foreground text-xs font-medium mb-1">Monthly Incomplete</p>
                                    <h3 class="text-2xl lg:text-2xl font-bold text-foreground">{{ stats.totalIncomplete }}</h3>
                                </div>
                                <div class="bg-red-100 dark:bg-red-900/30 p-2 rounded-lg flex-shrink-0">
                                    <ClipboardList class="h-4 w-4 text-red-600 dark:text-red-400" />
                                </div>
                            </div>
                            <div class="space-y-0.5">
                                <Badge variant="outline" class="bg-red-50 text-red-600 border-red-200 dark:bg-red-950/20 dark:text-red-400 text-xs">
                                    {{ stats.incompleteRate }}
                                </Badge>
                                <p class="text-muted-foreground text-xs">Missing Records</p>
                            </div>
                        </div>

                        <!-- Monthly Hours Card -->
                        <div class="p-3 lg:p-3.5 rounded-lg border border-sidebar-border/50 bg-card/30 hover:bg-card/50 transition-colors">
                            <div class="flex items-start justify-between mb-2">
                                <div class="flex-1 min-w-0">
                                    <p class="text-muted-foreground text-xs font-medium mb-1">Monthly Hours</p>
                                    <h3 class="text-2xl lg:text-2xl font-bold text-foreground">{{ stats.hoursWorked }}</h3>
                                </div>
                                <div class="bg-blue-100 dark:bg-blue-900/30 p-2 rounded-lg flex-shrink-0">
                                    <Clock class="h-4 w-4 text-blue-600 dark:text-blue-400" />
                                </div>
                            </div>
                            <div class="space-y-0.5">
                                <Badge variant="outline" class="bg-blue-50 text-blue-600 border-blue-200 dark:bg-blue-950/20 dark:text-blue-400 text-xs">
                                    {{ stats.averageDaily }}
                                </Badge>
                                <p class="text-muted-foreground text-xs">Average Per Day</p>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Combined Grid: Attendance, Schedule, Birthdays, Messages -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 lg:auto-rows-fr">
                <!-- Attendance Overview Chart (2 columns, row 1) -->
                <Card class="lg:col-span-2 bg-background border border-sidebar-border/70 dark:border-sidebar-border shadow-sm">
                    <CardHeader class="pb-3">
                        <div class="flex items-center justify-between">
                            <CardTitle class="text-base font-semibold flex items-center gap-2">
                                <Users class="h-4 w-4 text-blue-600 dark:text-blue-400" />
                                Attendance Overview
                            </CardTitle>
                            <div class="flex gap-1 bg-muted/50 p-0.5 rounded-lg">
                                <Button 
                                    variant="ghost" 
                                    size="sm"
                                    @click="viewMode = 'weekly'"
                                    :class="viewMode === 'weekly' ? 'bg-background shadow-sm' : ''"
                                    class="h-6 text-xs px-2.5"
                                >
                                    Weekly
                                </Button>
                                <Button 
                                    variant="ghost" 
                                    size="sm"
                                    @click="viewMode = 'monthly'"
                                    :class="viewMode === 'monthly' ? 'bg-background shadow-sm' : ''"
                                    class="h-6 text-xs px-2.5"
                                >
                                    Monthly
                                </Button>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="h-[240px] pb-4">
                        <Bar :data="attendanceChartData" :options="attendanceChartOptions" />
                    </CardContent>
                </Card>

                <!-- Schedule Widget (1 column, spans 2 rows) -->
                <Card class="lg:row-span-2 bg-background border border-sidebar-border/70 dark:border-sidebar-border shadow-sm">
                    <CardHeader class="pb-2">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1.5">
                                <CalendarIcon class="h-4 w-4 text-purple-600 dark:text-purple-400" />
                                <CardTitle class="text-sm font-semibold">Schedule</CardTitle>
                            </div>
                            <Button variant="ghost" size="sm" class="h-6 text-xs text-primary hover:text-primary/80 px-2">
                                See All →
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-2.5">
                        <!-- Month/Year Display with Navigation -->
                        <div class="flex items-center justify-between px-1">
                            <Button 
                                variant="ghost" 
                                size="sm" 
                                @click="previousWeek"
                                class="h-6 w-6 p-0"
                            >
                                <ChevronLeft class="h-3.5 w-3.5" />
                            </Button>
                            <span class="text-xs font-semibold text-foreground">
                                {{ formatScheduleDate(selectedScheduleDate, 'full') }}
                            </span>
                            <Button 
                                variant="ghost" 
                                size="sm" 
                                @click="nextWeek"
                                class="h-6 w-6 p-0"
                            >
                                <ChevronRight class="h-3.5 w-3.5" />
                            </Button>
                        </div>

                        <!-- Horizontal Date Scroller -->
                        <div class="flex gap-2 overflow-x-auto pb-2 scrollbar-thin justify-center">
                            <button
                                v-for="date in dateRange"
                                :key="date.toISOString()"
                                @click="selectedScheduleDate = date"
                                class="flex flex-col items-center justify-center text-center min-w-[48px] h-[56px] rounded-lg border transition-all duration-200"
                                :class="[
                                    isSelectedDate(date) 
                                        ? 'bg-primary text-primary-foreground border-primary shadow-md scale-105' 
                                        : 'bg-card/50 border-sidebar-border/50 hover:border-primary/30 hover:bg-card hover:scale-102',
                                    isToday(date) && !isSelectedDate(date) ? 'border-primary/40 bg-primary/5' : ''
                                ]"
                            >
                                <span class="text-xs font-medium mb-0.5 w-full opacity-90">
                                    {{ formatScheduleDate(date, 'day') }}
                                </span>
                                <span class="text-lg font-bold w-full">
                                    {{ formatScheduleDate(date, 'date') }}
                                </span>
                            </button>
                        </div>

                        <!-- Tabs -->
                        <div class="flex border-b border-sidebar-border/50">
                            <button
                                @click="scheduleTab = 'today'"
                                class="flex-1 flex items-center justify-center gap-1 py-2 text-xs font-medium transition-all relative"
                                :class="scheduleTab === 'today' ? 'text-foreground' : 'text-muted-foreground hover:text-foreground'"
                            >
                                <CalendarIcon 
                                    class="h-3 w-3" 
                                    :class="scheduleTab === 'today' ? 'text-blue-500' : ''"
                                />
                                Today
                                <span 
                                    v-if="scheduleTab === 'today'"
                                    class="absolute bottom-0 left-0 right-0 h-0.5 bg-blue-500"
                                ></span>
                            </button>
                            <button
                                @click="scheduleTab = 'upcoming'"
                                class="flex-1 flex items-center justify-center gap-1 py-2 text-xs font-medium transition-all relative"
                                :class="scheduleTab === 'upcoming' ? 'text-foreground' : 'text-muted-foreground hover:text-foreground'"
                            >
                                <Clock 
                                    class="h-3 w-3" 
                                    :class="scheduleTab === 'upcoming' ? 'text-purple-500' : ''"
                                />
                                Upcoming
                                <span 
                                    v-if="scheduleTab === 'upcoming'"
                                    class="absolute bottom-0 left-0 right-0 h-0.5 bg-purple-500"
                                ></span>
                            </button>
                        </div>

                        <!-- Events List -->
                        <ScrollArea class="flex-1 min-h-0">
                            <template v-if="filteredScheduleEvents.length === 0">
                                <div class="flex flex-col items-center justify-center h-[200px] text-center px-4">
                                    <div class="w-12 h-12 rounded-full bg-muted/50 flex items-center justify-center mb-3">
                                        <CalendarIcon class="h-6 w-6 text-muted-foreground/40" />
                                    </div>
                                    <p class="text-sm font-medium text-foreground mb-0.5">No events {{ scheduleTab === 'today' ? 'today' : 'upcoming' }}</p>
                                    <p class="text-xs text-muted-foreground">Your schedule is clear</p>
                                </div>
                            </template>
                            <template v-else>
                                <div class="space-y-2 pr-3">
                                    <div
                                        v-for="event in filteredScheduleEvents"
                                        :key="event.id"
                                        class="rounded-lg border border-sidebar-border/50 bg-card/50 p-3 transition-all duration-200 hover:shadow-md hover:bg-accent/50 hover:border-sidebar-border animate-in fade-in slide-in-from-bottom-2"
                                    >
                                        <div class="flex items-start justify-between gap-2 mb-1.5">
                                            <h4 class="font-semibold text-sm text-foreground line-clamp-1 flex-1">
                                                {{ event.title }}
                                            </h4>
                                            <Badge :class="getEventTypeColor(event.type)" class="text-xs capitalize flex-shrink-0 px-2 py-0.5">
                                                {{ event.type }}
                                            </Badge>
                                        </div>
                                        <div class="flex items-center gap-2 text-xs text-muted-foreground mb-2">
                                            <div class="flex items-center gap-1">
                                                <CalendarIcon class="h-3 w-3 flex-shrink-0" />
                                                <span>{{ event.date }}</span>
                                            </div>
                                            <span>•</span>
                                            <div class="flex items-center gap-1">
                                                <Clock class="h-3 w-3 flex-shrink-0" />
                                                <span>{{ event.startTime }}</span>
                                            </div>
                                        </div>

                                        <!-- Location (always show, use placeholder if empty) -->
                                        <div class="flex items-center gap-1.5 text-xs text-muted-foreground min-h-[20px]">
                                            <Video class="h-3.5 w-3.5 flex-shrink-0" :class="event.location ? '' : 'opacity-0'" />
                                            <span :class="event.location ? '' : 'opacity-0'">{{ event.location || 'No location' }}</span>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </ScrollArea>
                    </CardContent>
                </Card>

                <!-- Birthdays Card (1 column, row 2) -->
                <Card class="bg-background border border-sidebar-border/70 dark:border-sidebar-border shadow-sm">
                    <CardHeader class="pb-2">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1.5">
                                <Cake class="h-4 w-4 text-amber-600 dark:text-amber-400" />
                                <CardTitle class="text-sm font-semibold">Birthdays</CardTitle>
                            </div>
                            <Badge variant="secondary" class="text-xs font-semibold px-2 py-0.5">
                                {{ birthdays.length }}
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div v-if="birthdays.length > 0">
                            <ScrollArea class="h-[240px] pr-3">
                                <div class="space-y-2">
                                    <div
                                        v-for="birthday in birthdays"
                                        :key="birthday.id"
                                        class="group relative p-3 rounded-lg border transition-all duration-200 cursor-pointer"
                                        :class="birthday.date === 'Today' 
                                            ? 'border-amber-200/40 dark:border-amber-900/30 bg-gradient-to-br from-amber-50/40 to-orange-50/30 dark:from-amber-950/20 dark:to-orange-950/10 hover:from-amber-100/50 hover:to-orange-100/40 dark:hover:from-amber-900/30 dark:hover:to-orange-900/20 hover:border-amber-300/50 dark:hover:border-amber-800/40' 
                                            : 'border-sidebar-border/50 bg-card/50 hover:bg-accent/50 hover:border-sidebar-border'"
                                    >
                                        <div 
                                            v-if="birthday.date === 'Today'"
                                            class="absolute left-0 top-0 bottom-0 w-1 rounded-l-lg bg-gradient-to-b from-amber-500/60 to-orange-500/60 opacity-0 group-hover:opacity-100 transition-opacity"
                                        ></div>
                                        
                                        <div class="flex items-center gap-3 pl-0.5">
                                            <div 
                                                class="flex-shrink-0 w-9 h-9 rounded-full flex items-center justify-center font-semibold text-sm shadow-sm"
                                                :class="birthday.date === 'Today' 
                                                    ? 'bg-gradient-to-br from-amber-400/80 to-orange-500/80 text-white' 
                                                    : 'bg-muted text-muted-foreground'"
                                            >
                                                {{ birthday.name.split(' ').map(n => n[0]).join('') }}
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <h4 
                                                    class="font-semibold text-sm text-foreground mb-0.5 transition-colors line-clamp-1"
                                                    :class="birthday.date === 'Today' ? 'group-hover:text-amber-700 dark:group-hover:text-amber-400' : ''"
                                                >
                                                    {{ birthday.name }}
                                                </h4>
                                                <div class="flex items-center gap-1.5 text-xs">
                                                    <Badge 
                                                        :variant="birthday.date === 'Today' ? 'default' : 'outline'" 
                                                        class="text-xs font-semibold px-1.5 py-0"
                                                        :class="birthday.date === 'Today' ? 'bg-amber-600 hover:bg-amber-700 dark:bg-amber-500 dark:hover:bg-amber-600' : 'border-amber-400 text-amber-700 dark:border-amber-600 dark:text-amber-400'"
                                                    >
                                                        {{ birthday.date }}
                                                    </Badge>
                                                    <span class="text-muted-foreground">• {{ birthday.age }} years</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </ScrollArea>
                        </div>
                        <div v-else class="flex flex-col items-center justify-center h-[240px] text-center">
                            <Cake class="h-10 w-10 text-muted-foreground/30 mb-2" />
                            <p class="text-xs text-muted-foreground">No birthdays today</p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Messages Card (Center) -->
                <Card class="bg-background border border-sidebar-border/70 dark:border-sidebar-border shadow-sm">
                    <CardHeader class="pb-2">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1.5">
                                <MessageSquare class="h-4 w-4 text-green-600 dark:text-green-400" />
                                <CardTitle class="text-sm font-semibold">Messages</CardTitle>
                            </div>
                            <Badge class="text-xs font-semibold px-2 py-0.5 bg-green-600 hover:bg-green-700 dark:bg-green-500 dark:hover:bg-green-600">
                                {{ unreadMessages.reduce((sum, msg) => sum + msg.unread, 0) }}
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <ScrollArea class="h-[240px] pr-3">
                            <div class="space-y-2">
                                <a
                                    v-for="message in unreadMessages"
                                    :key="message.id"
                                    href="/chat"
                                    class="group relative block p-3 rounded-lg border border-sidebar-border/50 bg-card/50 hover:bg-accent/50 hover:border-sidebar-border transition-all duration-200"
                                >
                                    <div class="absolute left-0 top-0 bottom-0 w-1 rounded-l-lg bg-green-500/60 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                                    
                                    <div class="flex items-start gap-3 pl-0.5">
                                        <div class="flex-shrink-0 w-9 h-9 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center text-green-700 dark:text-green-400 font-semibold text-sm border border-green-200 dark:border-green-800 group-hover:scale-110 transition-transform">
                                            {{ message.avatar }}
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-start justify-between mb-1">
                                                <h4 class="font-semibold text-sm text-foreground group-hover:text-green-700 dark:group-hover:text-green-400 transition-colors line-clamp-1">
                                                    {{ message.sender }}
                                                </h4>
                                                <Badge 
                                                    v-if="message.unread > 0"
                                                    class="text-xs font-semibold px-1.5 py-0 bg-green-600 hover:bg-green-700 dark:bg-green-500 dark:hover:bg-green-600 ml-2 animate-pulse"
                                                >
                                                    {{ message.unread }}
                                                </Badge>
                                            </div>
                                            <p class="text-xs text-muted-foreground line-clamp-1 mb-1">{{ message.message }}</p>
                                            <span class="text-xs text-muted-foreground">{{ message.time }}</span>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </ScrollArea>
                    </CardContent>
                </Card>
            </div>

            <!-- Team Attendance Section -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <!-- Employee Attendance Table (2 columns) -->
                <Card class="lg:col-span-2 bg-background border border-sidebar-border/70 dark:border-sidebar-border shadow-sm">
                    <CardHeader class="pb-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <CardTitle class="text-base font-semibold mb-0.5">
                                    Employee Attendance
                                </CardTitle>
                                <p class="text-xs text-muted-foreground">Today's attendance overview</p>
                            </div>
                            <Badge variant="secondary" class="text-xs font-semibold px-2.5 py-1">   
                                {{ employeeAttendance.length }} employees
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="rounded-lg border border-sidebar-border/50 overflow-hidden">
                            <div class="overflow-x-auto">
                                <table class="w-full">
                                    <thead class="bg-muted/50">
                                        <tr class="border-b border-sidebar-border/50">
                                            <th class="text-left p-3 text-xs font-semibold text-foreground">Employee</th>
                                            <th class="text-left p-3 text-xs font-semibold text-foreground">Department</th>
                                            <th class="text-center p-3 text-xs font-semibold text-foreground">Time In</th>
                                            <th class="text-center p-3 text-xs font-semibold text-foreground">Time Out</th>
                                            <th class="text-center p-3 text-xs font-semibold text-foreground">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="employee in paginatedEmployees" :key="employee.id" class="border-b border-sidebar-border/50 last:border-0 hover:bg-muted/30 transition-colors">
                                            <td class="p-3 text-xs font-medium text-foreground">{{ employee.name }}</td>
                                            <td class="p-3 text-xs text-muted-foreground">{{ employee.department }}</td>
                                            <td class="p-3 text-xs text-center font-medium" :class="employee.timeIn === '-' ? 'text-muted-foreground' : 'text-foreground'">{{ employee.timeIn }}</td>
                                            <td class="p-3 text-xs text-center font-medium" :class="employee.timeOut === '-' ? 'text-muted-foreground' : 'text-foreground'">{{ employee.timeOut }}</td>
                                            <td class="p-3 text-center">
                                                <Badge :class="getStatusBadge(employee.status)" class="text-[10px] capitalize font-medium px-1.5 py-0">{{ employee.status }}</Badge>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Pagination -->
                        <div class="mt-4 flex items-center justify-between">
                            <p class="text-xs text-muted-foreground">
                                Showing {{ ((currentPage - 1) * itemsPerPage) + 1 }} to {{ Math.min(currentPage * itemsPerPage, employeeAttendance.length) }} of {{ employeeAttendance.length }} employees
                            </p>
                             <div class="flex justify-end">
                            <Pagination :total="totalPages" :items-per-page="itemsPerPage" :sibling-count="1" show-edges :default-page="1" v-model:page="currentPage">
                                <PaginationContent>
                                  
                                    <PaginationPrevious />
                                    
                                    <PaginationItem
                                        v-for="page in visiblePages"
                                        :key="page"
                                        :value="page"
                                        as-child
                                    >
                                        <Button
                                            @click="currentPage = page"
                                            :variant="currentPage === page ? 'default' : 'outline'"
                                            size="sm"
                                            class="h-9 w-9 p-0"
                                        >
                                            {{ page }}
                                        </Button>
                                    </PaginationItem>
                                    
                                    <PaginationNext />
                                   
                                </PaginationContent>
                            </Pagination>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Attendance Status Chart -->
                <Card class="bg-background border border-sidebar-border/70 dark:border-sidebar-border shadow-sm">
                    <CardHeader class="pb-3">
                        <CardTitle class="text-base font-semibold flex items-center gap-2">
                            <ClipboardList class="h-4 w-4 text-blue-600 dark:text-blue-400" />
                            Attendance Status
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="pb-4">
                        <!-- Pie Chart -->
                        <div class="flex items-center justify-center h-[280px] mb-4">
                            <Pie :data="attendanceStatusChartData" :options="attendanceStatusChartOptions" />
                        </div>
                        
                        <!-- Legend Below Chart - Centered -->
                        <div class="flex items-center justify-center gap-6">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 rounded-full bg-green-500"></div>
                                <span class="text-xs font-medium text-foreground">Present</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 rounded-full bg-orange-500"></div>
                                <span class="text-xs font-medium text-foreground">Late</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 rounded-full bg-red-500"></div>
                                <span class="text-xs font-medium text-foreground">Incomplete</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>


