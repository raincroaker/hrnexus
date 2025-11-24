<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { Calendar as CalendarIcon, Clock, Users, ClipboardList, Cake, MessageSquare, Video } from 'lucide-vue-next'
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
import ChartDataLabels from 'chartjs-plugin-datalabels'

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend, ArcElement, ChartDataLabels)

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

// Props from backend
const props = defineProps<{
  currentUser?: {
    id: number;
    name: string;
    first_name: string;
    last_name: string;
    employee_code: string;
    department: string;
    department_id: number | null;
    position: string;
    position_id: number | null;
    role: 'employee' | 'department_manager' | 'admin';
    email: string;
    contact_number: string | null;
    birth_date: string | null;
    avatar: string | null;
  };
  last7Attendance?: Array<{
    date: string;
    timeIn: string | null;
    timeOut: string | null;
    hoursWorked: number | null;
    status: string;
  }>;
  todayAttendance?: {
    timeIn: string | null;
    timeOut: string | null;
    date: string;
  };
  events?: Array<{
    id: number;
    title: string;
    date: string;
    time: string;
    type: string;
    isToday: boolean;
    location?: string;
  }>;
  birthdays?: Array<{
    id: number;
    name: string;
    department: string;
    date: string;
    age: number;
  }>;
  unreadMessages?: Array<{
    id: number;
    sender: string;
    message: string;
    time: string;
  }>;
  employeeAttendance?: Array<{
    id: number;
    name: string;
    department: string;
    timeIn: string;
    timeOut: string;
    status: string;
  }>;
  stats?: {
    totalPresents: number;
    workingDays: string;
    totalLate: number;
    lateRate: string;
    totalIncomplete: number;
    incompleteRate: string;
    hoursWorked: string;
    averageDaily: string;
  };
}>()

// Current user (from props or fallback)
const currentUser = computed(() => props.currentUser || { 
  id: 0,
  name: 'User', 
  first_name: 'User',
  last_name: '',
  employee_code: '',
  department: '',
  department_id: null,
  position: '',
  position_id: null,
  role: 'employee' as const,
  email: '',
  contact_number: null,
  birth_date: null,
  avatar: null
})

// Normalize role (handle case sensitivity)
const normalizedRole = computed(() => {
  if (!currentUser.value) return 'employee'
  return currentUser.value.role.toLowerCase()
})

// Check if user is admin
const isAdmin = computed(() => normalizedRole.value === 'admin')

// Last 7 attendance records (from props)
const last7Attendance = computed(() => props.last7Attendance || [])

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
})

onUnmounted(() => {
  if (timeInterval) {
    clearInterval(timeInterval)
  }
})

// Today's attendance (from props)
const todayAttendance = computed(() => props.todayAttendance || {
  timeIn: null,
  timeOut: null,
  date: new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
})

// Events list (from props)
const events = computed(() => props.events || [])

const getEventTypeColor = (type: string) => {
  switch (type) {
    case 'meeting': return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
    case 'deadline': return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
    case 'holiday': return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
    case 'training': return 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400'
    default: return 'bg-gray-100 text-gray-700 dark:bg-gray-900/30 dark:text-gray-400'
  }
}

// Events - separate today and upcoming

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

// Today events
const todayEvents = computed(() => {
  return events.value
    .filter(event => event.isToday)
    .map(event => {
      const colors = getScheduleEventColors(event.type)
      return {
        ...event,
        startTime: event.time,
        endTime: '',
        location: event.location || '',
        department: 'General',
        attendees: [],
        ...colors
      }
    })
})

// Upcoming events
const upcomingEvents = computed(() => {
  return events.value
    .filter(event => !event.isToday)
    .map(event => {
      const colors = getScheduleEventColors(event.type)
      return {
        ...event,
        startTime: event.time,
        endTime: '',
        location: event.location || '',
        department: 'General',
        attendees: [],
        ...colors
      }
    })
})

// Birthdays list (from props)
const birthdays = computed(() => props.birthdays || [])

// Unread messages (from props)
const unreadMessages = computed(() => props.unreadMessages || [])

// Pagination for employee attendance
const currentPage = ref(1)
const itemsPerPage = 5

// Employee attendance today (from props, admin only)
const employeeAttendance = computed(() => props.employeeAttendance || [])

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

// Stats data - User's personal attendance (from props)
const stats = computed(() => props.stats || {
  totalPresents: 0,
  workingDays: '0 Days',
  totalLate: 0,
  lateRate: '0%',
  totalIncomplete: 0,
  incompleteRate: '0%',
  hoursWorked: '0h',
  averageDaily: '0h/day'
})

// Helper function to calculate hours worked
const calculateHours = (timeIn: string | null, timeOut: string | null): number | null => {
  if (!timeIn || !timeOut) {
    return null;
  }

  const parseTime = (time: string): number => {
    const normalized = time.trim();
    if (/am|pm/i.test(normalized)) {
      const [timePart, period] = normalized.split(' ');
      const [hours, minutes] = timePart.split(':').map(Number);
      let adjustedHours = hours % 12;
      if (period?.toUpperCase() === 'PM') {
        adjustedHours += 12;
      }
      return adjustedHours + (minutes || 0) / 60;
    }

    const [hourString, minuteString = '0'] = normalized.split(':');
    const hours = Number(hourString);
    const minutes = Number(minuteString);
    return hours + minutes / 60;
  };
  
  const inHours = parseTime(timeIn);
  const outHours = parseTime(timeOut);
  const total = Math.max(outHours - inHours, 0);
  return parseFloat(total.toFixed(2));
};

// Chart data for attendance overview (last 7 days)
const attendanceChartData = computed(() => {
  const data = last7Attendance.value.map(record => {
    const hours = record.hoursWorked ?? calculateHours(record.timeIn, record.timeOut);
    const status = record.status.toLowerCase();
    // Show incomplete records as 0.5h so they're visible but clearly incomplete
    if (status === 'incomplete' && (hours === null || hours === 0)) {
      return 0.5;
    }
    return hours ?? 0;
  });

  return {
    labels: last7Attendance.value.map(record => {
      // Format date to show day name and date (e.g., "Mon, Nov 15")
      const date = new Date(record.date);
      return date.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
    }),
    datasets: [{
      label: 'Hours Worked',
      data,
      backgroundColor: last7Attendance.value.map(record => {
        const status = record.status.toLowerCase();
        if (status === 'present') return 'rgba(34, 197, 94, 0.8)';
        if (status === 'late') return 'rgba(249, 115, 22, 0.8)';
        return 'rgba(239, 68, 68, 0.8)';
      }),
      borderColor: last7Attendance.value.map(record => {
        const status = record.status.toLowerCase();
        if (status === 'present') return '#16a34a';
        if (status === 'late') return '#ea580c';
        return '#dc2626';
      }),
      borderWidth: 2,
      borderRadius: 8,
      borderSkipped: false,
      hoverBackgroundColor: last7Attendance.value.map(record => {
        const status = record.status.toLowerCase();
        if (status === 'present') return 'rgba(34, 197, 94, 1)';
        if (status === 'late') return 'rgba(249, 115, 22, 1)';
        return 'rgba(239, 68, 68, 1)';
      }),
      hoverBorderWidth: 3,
    }]
  };
})

const attendanceChartOptions = ref({
  responsive: true,
  maintainAspectRatio: false,
  interaction: {
    intersect: false,
    mode: 'index' as const
  },
  animation: {
    duration: 1000,
    easing: 'easeInOutQuart' as const
  },
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
      cornerRadius: 12,
      padding: 16,
      displayColors: false,
      titleFont: {
        size: 14,
        weight: 'bold' as const
      },
      bodyFont: {
        size: 13
      },
      callbacks: {
        title: (context: any) => {
          return `Date: ${context[0].label}`;
        },
        label: (context: any) => {
          const index = context.dataIndex;
          const record = last7Attendance.value[index];
          const hours = record.hoursWorked ?? calculateHours(record.timeIn, record.timeOut);
          const status = record.status.toLowerCase();
          const isIncomplete = status === 'incomplete' && (hours === null || hours === 0);
          
          if (isIncomplete) {
            return [
              `Status: Incomplete`,
              `Hours Worked: Missing time data`,
              `Time In: ${record.timeIn || 'Not recorded'}`,
              `Time Out: ${record.timeOut || 'Not recorded'}`,
              `⚠️ This bar represents an incomplete record`
            ];
          }
          
          return [
            `Hours Worked: ${hours ?? 0}h`,
            `Time In: ${record.timeIn || 'Not recorded'}`,
            `Time Out: ${record.timeOut || 'Not recorded'}`,
            `Status: ${record.status.charAt(0).toUpperCase() + record.status.slice(1)}`
          ];
        }
      }
    }
  },
  scales: {
    y: {
      beginAtZero: true,
      max: 10,
      ticks: {
        stepSize: 2,
        color: '#9ca3af',
        font: {
          size: 12,
          weight: 500 as any
        },
        callback: function(value: any) {
          return value + 'h';
        }
      },
      grid: {
        display: true,
        color: 'rgba(55, 65, 81, 0.1)',
        drawBorder: false
      },
      title: {
        display: true,
        text: 'Hours Worked',
        color: '#6b7280',
        font: {
          size: 13,
          weight: 600 as any
        },
        padding: {
          bottom: 10
        }
      }
    },
    x: {
      ticks: {
        color: '#9ca3af',
        font: {
          size: 12,
          weight: 500 as any
        },
        maxRotation: 0
      },
      grid: {
        display: false
      },
      title: {
        display: true,
        text: 'Date',
        color: '#6b7280',
        font: {
          size: 13,
          weight: 600 as any
        },
        padding: {
          top: 10
        }
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
        weight: 'bold' as const,
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
      textAlign: 'center' as const,
      anchor: 'center' as const,
      align: 'center' as const
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
                                    Hi {{ currentUser.first_name || currentUser.name }}!
                                </h1>
                                <Badge 
                                    :class="currentUser.role === 'admin' ? 'bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300' : 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300'"
                                    class="text-xs"
                                >
                                    {{ currentUser.role === 'admin' ? 'Admin' : currentUser.role === 'department_manager' ? 'Dept Manager' : 'Employee' }}
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
                        <CardTitle class="text-base font-semibold flex items-center gap-2">
                            <Users class="h-4 w-4 text-blue-600 dark:text-blue-400" />
                            Attendance Overview
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="h-[300px] pb-4">
                        <Bar :data="attendanceChartData" :options="attendanceChartOptions" />
                        <div class="flex items-center justify-center gap-6 mt-4">
                            <div class="flex items-center gap-2">
                                <div class="w-4 h-4 rounded" style="background-color: rgba(34, 197, 94, 0.85); border: 2px solid #16a34a;"></div>
                                <span class="text-sm text-muted-foreground">Present</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-4 h-4 rounded" style="background-color: rgba(249, 115, 22, 0.85); border: 2px solid #ea580c;"></div>
                                <span class="text-sm text-muted-foreground">Late</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-4 h-4 rounded" style="background-color: rgba(239, 68, 68, 0.85); border: 2px solid #dc2626;"></div>
                                <span class="text-sm text-muted-foreground">Incomplete</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Today Events Card (1 column, row 1) -->
                <Card class="bg-background border border-sidebar-border/70 dark:border-sidebar-border shadow-sm">
                    <CardHeader class="pb-2">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1.5">
                                <CalendarIcon class="h-4 w-4 text-blue-600 dark:text-blue-400" />
                                <CardTitle class="text-sm font-semibold">Today Events</CardTitle>
                            </div>
                            <Link href="/calendar">
                                <Button variant="ghost" size="sm" class="h-6 text-xs text-primary hover:text-primary/80 px-2">
                                    See All →
                                </Button>
                            </Link>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <ScrollArea class="h-[240px] pr-3">
                            <template v-if="todayEvents.length === 0">
                                <div class="flex flex-col items-center justify-center h-[200px] text-center px-4">
                                    <div class="w-12 h-12 rounded-full bg-muted/50 flex items-center justify-center mb-3">
                                        <CalendarIcon class="h-6 w-6 text-muted-foreground/40" />
                                    </div>
                                    <p class="text-sm font-medium text-foreground mb-0.5">No events today</p>
                                    <p class="text-xs text-muted-foreground">Your schedule is clear</p>
                                </div>
                            </template>
                            <template v-else>
                                <div class="space-y-2">
                                    <div
                                        v-for="event in todayEvents"
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

                <!-- Upcoming Events Card (1 column, row 2) -->
                <Card class="bg-background border border-sidebar-border/70 dark:border-sidebar-border shadow-sm">
                    <CardHeader class="pb-2">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1.5">
                                <Clock class="h-4 w-4 text-purple-600 dark:text-purple-400" />
                                <CardTitle class="text-sm font-semibold">Upcoming Events</CardTitle>
                            </div>
                            <Link href="/calendar">
                                <Button variant="ghost" size="sm" class="h-6 text-xs text-primary hover:text-primary/80 px-2">
                                    See All →
                                </Button>
                            </Link>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <ScrollArea class="h-[240px] pr-3">
                            <template v-if="upcomingEvents.length === 0">
                                <div class="flex flex-col items-center justify-center h-[200px] text-center px-4">
                                    <div class="w-12 h-12 rounded-full bg-muted/50 flex items-center justify-center mb-3">
                                        <Clock class="h-6 w-6 text-muted-foreground/40" />
                                    </div>
                                    <p class="text-sm font-medium text-foreground mb-0.5">No upcoming events</p>
                                    <p class="text-xs text-muted-foreground">Your schedule is clear</p>
                                </div>
                            </template>
                            <template v-else>
                                <div class="space-y-2">
                                    <div
                                        v-for="event in upcomingEvents"
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

                <!-- Birthdays Card (1 column, row 3) -->
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
                            <Link href="/chat">
                                <Button variant="ghost" size="sm" class="h-6 text-xs text-primary hover:text-primary/80 px-2">
                                    See All →
                                </Button>
                            </Link>
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
                                    
                                    <div class="flex-1 min-w-0 pl-0.5">
                                        <div class="mb-1">
                                            <h4 class="font-semibold text-sm text-foreground group-hover:text-green-700 dark:group-hover:text-green-400 transition-colors line-clamp-1">
                                                {{ message.sender }}
                                            </h4>
                                        </div>
                                        <p class="text-xs font-medium text-foreground line-clamp-1 mb-1">{{ message.message }}</p>
                                        <span class="text-xs text-muted-foreground">{{ message.time }}</span>
                                    </div>
                                </a>
                            </div>
                        </ScrollArea>
                    </CardContent>
                </Card>
            </div>

            <!-- Team Attendance Section (Admin Only) -->
            <div v-if="isAdmin" class="grid grid-cols-1 lg:grid-cols-3 gap-4">
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

                <!-- Attendance Status Chart (Admin Only) -->
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


