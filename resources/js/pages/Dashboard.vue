<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, type Ref } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Calendar as CalendarIcon, Clock, Users, ClipboardList, Cake, MessageSquare } from 'lucide-vue-next'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Calendar } from '@/components/ui/calendar'
import { ScrollArea } from '@/components/ui/scroll-area'
import { type DateValue } from '@internationalized/date'
import { today, getLocalTimeZone } from '@internationalized/date'
import { Bar } from 'vue-chartjs'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend
} from 'chart.js'

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend)

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

// Calendar - using @internationalized/date
const selectedDate = ref(today(getLocalTimeZone())) as Ref<DateValue>

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

const eventFilter = ref<'today' | 'upcoming'>('today')

const filteredEvents = computed(() => {
  if (eventFilter.value === 'today') {
    return events.value.filter(event => event.isToday)
  }
  return events.value.filter(event => !event.isToday)
})

const getEventTypeColor = (type: string) => {
  switch (type) {
    case 'meeting': return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
    case 'deadline': return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
    case 'holiday': return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
    case 'training': return 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400'
    default: return 'bg-gray-100 text-gray-700 dark:bg-gray-900/30 dark:text-gray-400'
  }
}

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

// Employee attendance today
const employeeAttendance = ref([
  { id: 1, name: 'Sarah Johnson', department: 'Marketing', timeIn: '08:45 AM', timeOut: '05:30 PM', status: 'present' },
  { id: 2, name: 'Michael Chen', department: 'Engineering', timeIn: '08:30 AM', timeOut: '05:15 PM', status: 'present' },
  { id: 3, name: 'Emma Davis', department: 'HR', timeIn: '09:00 AM', timeOut: '05:00 PM', status: 'present' },
  { id: 4, name: 'John Smith', department: 'Engineering', timeIn: '08:50 AM', timeOut: '05:10 PM', status: 'present' },
  { id: 5, name: 'Lisa Anderson', department: 'Sales', timeIn: '09:15 AM', timeOut: '05:45 PM', status: 'late' },
  { id: 6, name: 'David Wilson', department: 'Marketing', timeIn: '08:40 AM', timeOut: '05:25 PM', status: 'present' },
  { id: 7, name: 'Jennifer Lee', department: 'Finance', timeIn: '08:35 AM', timeOut: '-', status: 'incomplete' },
  { id: 8, name: 'Robert Brown', department: 'Engineering', timeIn: '08:55 AM', timeOut: '-', status: 'incomplete' }
])

const getStatusBadge = (status: string) => {
  switch (status) {
    case 'present': return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
    case 'late': return 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400'
    case 'incomplete': return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
    default: return 'bg-gray-100 text-gray-700 dark:bg-gray-900/30 dark:text-gray-400'
  }
}

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
          weight: '500'
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
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6 bg-gray-50/30 dark:bg-black/25">
        
            <!-- Unified Header & Stats Section -->
            <Card class="bg-background border border-sidebar-border/70 dark:border-sidebar-border shadow-sm">
                <CardContent class="p-6">
                    <!-- Header -->
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6 pb-6 border-b border-sidebar-border/50">
                        <!-- Left: User Greeting -->
                        <div>
                            <div class="flex items-center gap-3 mb-2">
                                <h1 class="text-3xl font-bold text-foreground">
                                    Hi {{ currentUser.name }}!
                                </h1>
                                <Badge 
                                    :class="currentUser.role === 'Admin' ? 'bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300' : 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300'"
                                >
                                    {{ currentUser.role }}
                                </Badge>
                            </div>
                            <p class="text-muted-foreground">Welcome back! Here's your overview for today.</p>
                        </div>

                        <!-- Right: Date, Time & Today's Attendance -->
                        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-6">
                            <!-- Date & Time -->
                            <div class="text-left sm:text-right">
                                <div class="flex items-center gap-2 text-foreground font-semibold text-base mb-1">
                                    <CalendarIcon class="h-4 w-4" />
                                    {{ formattedDate }}
                                </div>
                                <div class="flex items-center gap-2 text-muted-foreground text-sm">
                                    <Clock class="h-4 w-4" />
                                    {{ formattedTime }}
                                </div>
                            </div>

                            <!-- Divider -->
                            <div class="hidden sm:block h-16 w-px bg-border"></div>

                            <!-- Today's Attendance -->
                            <div class="flex gap-6">
                                <div>
                                    <p class="text-xs text-muted-foreground mb-1">Time In</p>
                                    <p class="text-lg font-bold text-foreground">{{ todayAttendance.timeIn || '-' }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-muted-foreground mb-1">Time Out</p>
                                    <p class="text-lg font-bold text-foreground">{{ todayAttendance.timeOut || '-' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Stats Cards -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 pt-6">
                        <!-- Monthly Presents Card -->
                        <div class="p-4 rounded-lg border border-sidebar-border/50 bg-card/30 hover:bg-card/50 transition-colors">
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex-1">
                                    <p class="text-muted-foreground text-xs font-medium mb-1.5">Monthly Presents</p>
                                    <h3 class="text-3xl font-bold text-foreground">{{ stats.totalPresents }}</h3>
                                </div>
                                <div class="bg-green-100 dark:bg-green-900/30 p-2.5 rounded-lg">
                                    <Users class="h-5 w-5 text-green-600 dark:text-green-400" />
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <Badge variant="outline" class="bg-green-50 text-green-600 border-green-200 dark:bg-green-950/20 dark:text-green-400 text-xs">
                                    {{ stats.workingDays }}
                                </Badge>
                                <span class="text-muted-foreground text-xs">Out of Working Days</span>
                            </div>
                        </div>

                        <!-- Monthly Late Card -->
                        <div class="p-4 rounded-lg border border-sidebar-border/50 bg-card/30 hover:bg-card/50 transition-colors">
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex-1">
                                    <p class="text-muted-foreground text-xs font-medium mb-1.5">Monthly Late</p>
                                    <h3 class="text-3xl font-bold text-foreground">{{ stats.totalLate }}</h3>
                                </div>
                                <div class="bg-orange-100 dark:bg-orange-900/30 p-2.5 rounded-lg">
                                    <Clock class="h-5 w-5 text-orange-600 dark:text-orange-400" />
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <Badge variant="outline" class="bg-orange-50 text-orange-600 border-orange-200 dark:bg-orange-950/20 dark:text-orange-400 text-xs">
                                    {{ stats.lateRate }}
                                </Badge>
                                <span class="text-muted-foreground text-xs">Of Total Days</span>
                            </div>
                        </div>

                        <!-- Monthly Incomplete Card -->
                        <div class="p-4 rounded-lg border border-sidebar-border/50 bg-card/30 hover:bg-card/50 transition-colors">
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex-1">
                                    <p class="text-muted-foreground text-xs font-medium mb-1.5">Monthly Incomplete</p>
                                    <h3 class="text-3xl font-bold text-foreground">{{ stats.totalIncomplete }}</h3>
                                </div>
                                <div class="bg-red-100 dark:bg-red-900/30 p-2.5 rounded-lg">
                                    <ClipboardList class="h-5 w-5 text-red-600 dark:text-red-400" />
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <Badge variant="outline" class="bg-red-50 text-red-600 border-red-200 dark:bg-red-950/20 dark:text-red-400 text-xs">
                                    {{ stats.incompleteRate }}
                                </Badge>
                                <span class="text-muted-foreground text-xs">Missing Records</span>
                            </div>
                        </div>

                        <!-- Monthly Hours Card -->
                        <div class="p-4 rounded-lg border border-sidebar-border/50 bg-card/30 hover:bg-card/50 transition-colors">
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex-1">
                                    <p class="text-muted-foreground text-xs font-medium mb-1.5">Monthly Hours</p>
                                    <h3 class="text-3xl font-bold text-foreground">{{ stats.hoursWorked }}</h3>
                                </div>
                                <div class="bg-blue-100 dark:bg-blue-900/30 p-2.5 rounded-lg">
                                    <Clock class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <Badge variant="outline" class="bg-blue-50 text-blue-600 border-blue-200 dark:bg-blue-950/20 dark:text-blue-400 text-xs">
                                    {{ stats.averageDaily }}
                                </Badge>
                                <span class="text-muted-foreground text-xs">Average Per Day</span>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Row 1: Attendance Chart & Calendar -->
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-5">
                <!-- Attendance Overview Chart (3 columns) -->
                <Card class="lg:col-span-3 bg-background border border-sidebar-border/70 dark:border-sidebar-border shadow-sm">
                    <CardHeader class="pb-4">
                        <CardTitle class="text-lg font-semibold flex items-center gap-2">
                            <Users class="h-5 w-5 text-primary" />
                            Attendance Overview
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="h-[380px] pb-6">
                        <Bar :data="attendanceChartData" :options="attendanceChartOptions" />
                    </CardContent>
                </Card>

                <!-- Calendar (1 column) -->
                <Card class="bg-background border border-sidebar-border/70 dark:border-sidebar-border shadow-sm flex flex-col">
                    <CardHeader class="pb-3">
                        <CardTitle class="text-base font-semibold flex items-center gap-2">
                            <CalendarIcon class="h-5 w-5 text-primary" />
                            Calendar
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="flex-1 flex items-center justify-center p-3 overflow-hidden">
                        <div style="transform: scale(1.1); transform-origin: center;">
                            <Calendar 
                                v-model="selectedDate" 
                                :weekday-format="'short'"
                                class="rounded-md border-0 text-center justify-center"
                            />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Row 2: Birthdays, Messages & Events -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                <!-- Birthdays Card (Left) -->
                <Card class="bg-background border border-sidebar-border/70 dark:border-sidebar-border shadow-sm">
                    <CardHeader class="pb-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <Cake class="h-5 w-5 text-amber-600 dark:text-amber-400" />
                                <CardTitle class="text-base font-semibold">Birthdays</CardTitle>
                            </div>
                            <Badge variant="secondary" class="text-xs font-semibold px-2.5 py-1">
                                {{ birthdays.length }}
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div v-if="birthdays.length > 0">
                            <ScrollArea class="h-[300px] pr-3">
                                <div class="space-y-2">
                                    <div
                                        v-for="birthday in birthdays"
                                        :key="birthday.id"
                                        class="group relative p-3 rounded-lg border border-amber-200/40 dark:border-amber-900/30 bg-gradient-to-br from-amber-50/40 to-orange-50/30 dark:from-amber-950/20 dark:to-orange-950/10 hover:from-amber-100/50 hover:to-orange-100/40 dark:hover:from-amber-900/30 dark:hover:to-orange-900/20 hover:border-amber-300/50 dark:hover:border-amber-800/40 transition-all duration-200 cursor-pointer"
                                    >
                                        <div class="absolute left-0 top-0 bottom-0 w-1 rounded-l-lg bg-gradient-to-b from-amber-500/60 to-orange-500/60 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                                        
                                        <div class="flex items-center gap-3 pl-0.5">
                                            <div class="flex-shrink-0 w-10 h-10 rounded-full bg-gradient-to-br from-amber-400/80 to-orange-500/80 flex items-center justify-center text-white font-semibold text-sm shadow-sm">
                                                {{ birthday.name.split(' ').map(n => n[0]).join('') }}
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <h4 class="font-semibold text-sm text-foreground mb-0.5 group-hover:text-amber-700 dark:group-hover:text-amber-400 transition-colors line-clamp-1">
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
                        <div v-else class="flex flex-col items-center justify-center h-[300px] text-center">
                            <Cake class="h-12 w-12 text-muted-foreground/30 mb-3" />
                            <p class="text-sm text-muted-foreground">No birthdays today</p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Messages Card (Center) -->
                <Card class="bg-background border border-sidebar-border/70 dark:border-sidebar-border shadow-sm">
                    <CardHeader class="pb-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <MessageSquare class="h-5 w-5 text-green-600 dark:text-green-400" />
                                <CardTitle class="text-base font-semibold">Messages</CardTitle>
                            </div>
                            <Badge class="text-xs font-semibold px-2.5 py-1 bg-green-600 hover:bg-green-700 dark:bg-green-500 dark:hover:bg-green-600">
                                {{ unreadMessages.reduce((sum, msg) => sum + msg.unread, 0) }}
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <ScrollArea class="h-[300px] pr-3">
                            <div class="space-y-2">
                                <a
                                    v-for="message in unreadMessages"
                                    :key="message.id"
                                    href="/chat"
                                    class="group relative block p-3 rounded-lg border border-sidebar-border/50 bg-card/50 hover:bg-accent/50 hover:border-sidebar-border transition-all duration-200"
                                >
                                    <div class="absolute left-0 top-0 bottom-0 w-1 rounded-l-lg bg-green-500/60 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                                    
                                    <div class="flex items-start gap-3 pl-0.5">
                                        <div class="flex-shrink-0 w-9 h-9 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center text-green-700 dark:text-green-400 font-semibold text-xs border border-green-200 dark:border-green-800">
                                            {{ message.avatar }}
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-start justify-between mb-1">
                                                <h4 class="font-semibold text-sm text-foreground group-hover:text-green-700 dark:group-hover:text-green-400 transition-colors line-clamp-1">
                                                    {{ message.sender }}
                                                </h4>
                                                <Badge 
                                                    v-if="message.unread > 0"
                                                    class="text-xs font-semibold px-1.5 py-0 bg-green-600 hover:bg-green-700 dark:bg-green-500 dark:hover:bg-green-600 ml-2"
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

                <!-- Events Card (Right) -->
                <Card class="bg-background border border-sidebar-border/70 dark:border-sidebar-border shadow-sm">
                    <CardHeader class="pb-4">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-2">
                                <ClipboardList class="h-5 w-5 text-primary" />
                                <CardTitle class="text-base font-semibold">Events</CardTitle>
                            </div>
                            <Badge variant="secondary" class="text-xs font-semibold px-2.5 py-1">
                                {{ filteredEvents.length }}
                            </Badge>
                        </div>
                        <div class="flex gap-2">
                            <Button
                                @click="eventFilter = 'today'"
                                :variant="eventFilter === 'today' ? 'default' : 'outline'"
                                size="sm"
                                class="flex-1 h-8 text-xs"
                            >
                                Today
                            </Button>
                            <Button
                                @click="eventFilter = 'upcoming'"
                                :variant="eventFilter === 'upcoming' ? 'default' : 'outline'"
                                size="sm"
                                class="flex-1 h-8 text-xs"
                            >
                                Upcoming
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <ScrollArea class="h-[300px] pr-3">
                            <div class="space-y-2.5">
                                <div
                                    v-for="event in filteredEvents"
                                    :key="event.id"
                                    class="group relative p-3 rounded-lg border border-sidebar-border/50 bg-card/50 hover:bg-accent/50 hover:border-sidebar-border transition-all duration-200 cursor-pointer"
                                >
                                    <div class="absolute left-0 top-0 bottom-0 w-1 rounded-l-lg bg-primary/60 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                                    
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="flex-1 min-w-0 pl-0.5">
                                            <h4 class="font-semibold text-sm text-foreground mb-1.5 group-hover:text-primary transition-colors line-clamp-1">
                                                {{ event.title }}
                                            </h4>
                                            <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                                <div class="flex items-center gap-1">
                                                    <CalendarIcon class="h-3 w-3 flex-shrink-0" />
                                                    <span>{{ event.date }}</span>
                                                </div>
                                                <span>•</span>
                                                <div class="flex items-center gap-1">
                                                    <Clock class="h-3 w-3 flex-shrink-0" />
                                                    <span>{{ event.time }}</span>
                                                </div>
                                            </div>
                                        </div>
                                        <Badge 
                                            :class="getEventTypeColor(event.type)"
                                            class="text-xs capitalize flex-shrink-0 font-medium     "
                                        >
                                            {{ event.type }}
                                        </Badge>
                                    </div>
                                </div>
                            </div>
                        </ScrollArea>
                    </CardContent>
                </Card>
            </div>

            <!-- Team Attendance Section -->
            <Card class="bg-background border border-sidebar-border/70 dark:border-sidebar-border shadow-sm">
                <CardHeader class="pb-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="text-lg font-semibold mb-1">
                                Employee Attendance
                            </CardTitle>
                            <p class="text-sm text-muted-foreground">Today's attendance overview</p>
                        </div>
                        <Badge variant="secondary" class="text-xs font-semibold px-3 py-1.5">   
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
                                        <th class="text-left p-4 text-sm font-semibold text-foreground">Employee</th>
                                        <th class="text-left p-4 text-sm font-semibold text-foreground">Department</th>
                                        <th class="text-center p-4 text-sm font-semibold text-foreground">Time In</th>
                                        <th class="text-center p-4 text-sm font-semibold text-foreground">Time Out</th>
                                        <th class="text-center p-4 text-sm font-semibold text-foreground">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="employee in employeeAttendance" :key="employee.id" class="border-b border-sidebar-border/50 last:border-0 hover:bg-muted/30 transition-colors">
                                        <td class="p-4 text-sm font-medium text-foreground">{{ employee.name }}</td>
                                        <td class="p-4 text-sm text-muted-foreground">{{ employee.department }}</td>
                                        <td class="p-4 text-sm text-center font-medium" :class="employee.timeIn === '-' ? 'text-muted-foreground' : 'text-foreground'">{{ employee.timeIn }}</td>
                                        <td class="p-4 text-sm text-center font-medium" :class="employee.timeOut === '-' ? 'text-muted-foreground' : 'text-foreground'">{{ employee.timeOut }}</td>
                                        <td class="p-4 text-center">
                                            <Badge :class="getStatusBadge(employee.status)" class="text-xs capitalize font-medium">{{ employee.status }}</Badge>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>


