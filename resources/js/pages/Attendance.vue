<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Combobox, ComboboxAnchor, ComboboxEmpty, ComboboxGroup, ComboboxInput, ComboboxItem, ComboboxItemIndicator, ComboboxList } from '@/components/ui/combobox';
import { Calendar, Clock, Users, Database, Eye, Check, Search, CalendarDays, Trash2, Filter, MoreVertical } from 'lucide-vue-next';
import { Calendar as CalendarPicker } from '@/components/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { ScrollArea, ScrollBar } from '@/components/ui/scroll-area';
import { ref, watch, computed } from 'vue';
import { Bar, Line } from 'vue-chartjs';
import { Chart as ChartJS, CategoryScale, LinearScale, BarElement, LineElement, PointElement, Title, Tooltip, Legend } from 'chart.js';
import {
  Pagination,
  PaginationContent,
  PaginationEllipsis,
  PaginationItem,
  PaginationNext,
  PaginationPrevious,
} from "@/components/ui/pagination"

ChartJS.register(CategoryScale, LinearScale, BarElement, LineElement, PointElement, Title, Tooltip, Legend);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Attendance',
        href: '/attendance',
    },
];

// Dummy data for My Attendance (John Doe only - Last 7 days)
const myAttendance = ref([
    { id: 1, employeeCode: 'EMP9999', name: 'Jerick Cruza', date: '2025-11-08', timeIn: '08:45 AM', timeOut: '05:30 PM', status: 'present' },
    { id: 2, employeeCode: 'EMP9999', name: 'Jerick Cruza', date: '2025-11-07', timeIn: '08:30 AM', timeOut: '05:15 PM', status: 'present' },
    { id: 3, employeeCode: 'EMP9999', name: 'Jerick Cruza', date: '2025-11-06', timeIn: '09:10 AM', timeOut: '05:45 PM', status: 'late' },
    { id: 4, employeeCode: 'EMP9999', name: 'Jerick Cruza', date: '2025-11-05', timeIn: '08:40 AM', timeOut: '05:20 PM', status: 'present' },
    { id: 5, employeeCode: 'EMP9999', name: 'Jerick Cruza', date: '2025-11-04', timeIn: '08:35 AM', timeOut: '05:25 PM', status: 'present' },
    { id: 6, employeeCode: 'EMP9999', name: 'Jerick Cruza', date: '2025-11-01', timeIn: '08:50 AM', timeOut: '05:30 PM', status: 'present' },
    { id: 7, employeeCode: 'EMP9999', name: 'Jerick Cruza', date: '2025-10-31', timeIn: '09:05 AM', timeOut: '05:40 PM', status: 'late' },
]);

const exportMyAttendance = () => {
    // Convert data to CSV format
    const headers = ['Employee Code', 'Name', 'Date', 'Time In', 'Time Out', 'Status'];
    const csvContent = [
        headers.join(','),
        ...myAttendance.value.map(record => `${record.employeeCode},${record.name},${record.date},${record.timeIn},${record.timeOut},${record.status}`)
    ].join('\n');
    
    // Create blob and download
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', `my_attendance_${new Date().toISOString().split('T')[0]}.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};

// Function to calculate hours worked
const calculateHours = (timeIn: string, timeOut: string): number => {
    const parseTime = (time: string) => {
        const [timePart, period] = time.split(' ');
        const [hours, minutes] = timePart.split(':').map(Number);
        let adjustedHours = hours;
        if (period === 'PM' && hours !== 12) adjustedHours += 12;
        if (period === 'AM' && hours === 12) adjustedHours = 0;
        return adjustedHours + minutes / 60;
    };
    
    const inHours = parseTime(timeIn);
    const outHours = parseTime(timeOut);
    return parseFloat((outHours - inHours).toFixed(1));
};

// Chart data for My Attendance
const myAttendanceChartData = computed(() => ({
    labels: myAttendance.value.map(record => record.date),
    datasets: [{
        label: 'Hours Worked',
        data: myAttendance.value.map(record => calculateHours(record.timeIn, record.timeOut)),
        backgroundColor: myAttendance.value.map(record => {
            if (record.status === 'present') return 'rgba(34, 197, 94, 0.8)';
            if (record.status === 'late') return 'rgba(249, 115, 22, 0.8)';
            return 'rgba(239, 68, 68, 0.8)';
        }),
        borderColor: myAttendance.value.map(record => {
            if (record.status === 'present') return '#16a34a';
            if (record.status === 'late') return '#ea580c';
            return '#dc2626';
        }),
        borderWidth: 2,
        borderRadius: 8,
        borderSkipped: false,
        hoverBackgroundColor: myAttendance.value.map(record => {
            if (record.status === 'present') return 'rgba(34, 197, 94, 1)';
            if (record.status === 'late') return 'rgba(249, 115, 22, 1)';
            return 'rgba(239, 68, 68, 1)';
        }),
        hoverBorderWidth: 3,
    }]
}));

const myAttendanceChartOptions = ref({
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
                    const record = myAttendance.value[index];
                    return [
                        `Hours Worked: ${context.parsed.y}h`,
                        `Time In: ${record.timeIn}`,
                        `Time Out: ${record.timeOut}`,
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
});

// Pagination state for my attendance
const myAttendanceCurrentPage = ref(1);
const myAttendanceItemsPerPage = 10;

// Pagination state for team attendance
const teamAttendanceCurrentPage = ref(1);
const teamAttendanceItemsPerPage = 10;

// Pagination state for biometric logs
const biometricCurrentPage = ref(1);
const biometricItemsPerPage = 10;

// Filtered computed properties
const filteredMyAttendance = computed(() => {
    let filtered = myAttendance.value;
    
    // Search filter - only by date since it's personal attendance
    if (myAttendanceSearch.value) {
        const search = myAttendanceSearch.value.toLowerCase();
        filtered = filtered.filter(record => 
            record.date.includes(search)
        );
    }
    
    // Status filter
    if (myAttendanceStatusFilter.value !== 'all') {
        filtered = filtered.filter(record => record.status === myAttendanceStatusFilter.value);
    }
    
    return filtered;
});

// Paginated my attendance
const paginatedMyAttendance = computed(() => {
    const start = (myAttendanceCurrentPage.value - 1) * myAttendanceItemsPerPage;
    const end = start + myAttendanceItemsPerPage;
    return filteredMyAttendance.value.slice(start, end);
});

const filteredTeamAttendance = computed(() => {
    let filtered = teamAttendance.value;
    
    // Search filter
    if (teamAttendanceSearch.value) {
        const search = teamAttendanceSearch.value.toLowerCase();
        filtered = filtered.filter(record => 
            record.name.toLowerCase().includes(search) ||
            record.department.toLowerCase().includes(search) ||
            record.employeeCode.toLowerCase().includes(search) ||
            record.date.includes(search)
        );
    }
    
    // Status filter
    if (teamAttendanceStatusFilter.value !== 'all') {
        filtered = filtered.filter(record => record.status === teamAttendanceStatusFilter.value);
    }
    
    // Department filter
    if (teamAttendanceDepartmentFilter.value !== 'all') {
        filtered = filtered.filter(record => record.department === teamAttendanceDepartmentFilter.value);
    }
    
    return filtered;
});

// Paginated team attendance
const paginatedTeamAttendance = computed(() => {
    const start = (teamAttendanceCurrentPage.value - 1) * teamAttendanceItemsPerPage;
    const end = start + teamAttendanceItemsPerPage;
    return filteredTeamAttendance.value.slice(start, end);
});

const filteredBiometricLogs = computed(() => {
    let filtered = biometricLogs.value;
    
    // Search filter
    if (biometricSearch.value) {
        const search = biometricSearch.value.toLowerCase();
        filtered = filtered.filter(log => 
            log.employeeCode.toLowerCase().includes(search) ||
            log.date.includes(search) ||
            log.time.includes(search)
        );
    }
    
    // Date filter
    if (biometricDateFilter.value !== 'all') {
        const today = new Date().toISOString().split('T')[0];
        const yesterday = new Date(Date.now() - 86400000).toISOString().split('T')[0];
        
        if (biometricDateFilter.value === 'today') {
            filtered = filtered.filter(log => log.date === today);
        } else if (biometricDateFilter.value === 'yesterday') {
            filtered = filtered.filter(log => log.date === yesterday);
        } else if (biometricDateFilter.value === 'this-week') {
            const weekAgo = new Date(Date.now() - 7 * 86400000).toISOString().split('T')[0];
            filtered = filtered.filter(log => log.date >= weekAgo);
        }
    }
    
    return filtered;
});

// Paginated biometric logs
const paginatedBiometricLogs = computed(() => {
    const start = (biometricCurrentPage.value - 1) * biometricItemsPerPage;
    const end = start + biometricItemsPerPage;
    return filteredBiometricLogs.value.slice(start, end);
});

// Dummy data for All Attendance - 15 employees × 5 days = 75 records
const teamAttendance = ref([
    // November 8, 2025 - 15 employees
    { id: 1, employeeCode: 'EMP001', department: 'Engineering', position: 'Senior Developer', name: 'John Doe', date: '2025-11-08', timeIn: '08:30 AM', timeOut: '05:00 PM', status: 'present' },
    { id: 2, employeeCode: 'EMP002', department: 'Marketing', position: 'Marketing Manager', name: 'Jane Smith', date: '2025-11-08', timeIn: '09:15 AM', timeOut: '05:30 PM', status: 'late' },
    { id: 3, employeeCode: 'EMP003', department: 'Engineering', position: 'Frontend Developer', name: 'Mike Johnson', date: '2025-11-08', timeIn: '', timeOut: '05:15 PM', status: 'incomplete' },
    { id: 4, employeeCode: 'EMP004', department: 'HR', position: 'HR Specialist', name: 'Sarah Williams', date: '2025-11-08', timeIn: '08:35 AM', timeOut: '05:10 PM', status: 'present' },
    { id: 5, employeeCode: 'EMP005', department: 'Sales', position: 'Sales Executive', name: 'Tom Brown', date: '2025-11-08', timeIn: '09:15 AM', timeOut: '05:30 PM', status: 'late' },
    { id: 6, employeeCode: 'EMP006', department: 'Finance', position: 'Accountant', name: 'Emily Davis', date: '2025-11-08', timeIn: '08:40 AM', timeOut: '05:05 PM', status: 'present' },
    { id: 7, employeeCode: 'EMP007', department: 'Engineering', position: 'Backend Developer', name: 'David Wilson', date: '2025-11-08', timeIn: '08:55 AM', timeOut: '05:20 PM', status: 'late' },
    { id: 8, employeeCode: 'EMP008', department: 'IT', position: 'System Admin', name: 'Alex Chen', date: '2025-11-08', timeIn: '09:00 AM', timeOut: '', status: 'incomplete' },
    { id: 9, employeeCode: 'EMP009', department: 'Design', position: 'UI Designer', name: 'Lisa Park', date: '2025-11-08', timeIn: '08:25 AM', timeOut: '05:15 PM', status: 'present' },
    { id: 10, employeeCode: 'EMP010', department: 'QA', position: 'QA Tester', name: 'Mark Rodriguez', date: '2025-11-08', timeIn: '09:00 AM', timeOut: '', status: 'incomplete' },
    { id: 11, employeeCode: 'EMP011', department: 'Operations', position: 'Operations Manager', name: 'Rachel Kim', date: '2025-11-08', timeIn: '09:20 AM', timeOut: '05:45 PM', status: 'late' },
    { id: 12, employeeCode: 'EMP012', department: 'Engineering', position: 'DevOps Engineer', name: 'Chris Anderson', date: '2025-11-08', timeIn: '08:20 AM', timeOut: '05:10 PM', status: 'present' },
    { id: 13, employeeCode: 'EMP013', department: 'Marketing', position: 'Content Writer', name: 'Amanda Lee', date: '2025-11-08', timeIn: '09:10 AM', timeOut: '05:25 PM', status: 'late' },
    { id: 14, employeeCode: 'EMP014', department: 'Sales', position: 'Sales Representative', name: 'Robert Taylor', date: '2025-11-08', timeIn: '08:45 AM', timeOut: '', status: 'incomplete' },
    { id: 15, employeeCode: 'EMP015', department: 'Finance', position: 'Financial Analyst', name: 'Jessica Martinez', date: '2025-11-08', timeIn: '08:30 AM', timeOut: '05:00 PM', status: 'present' },
    
    // November 7, 2025 - 15 employees
    { id: 16, employeeCode: 'EMP001', department: 'Engineering', position: 'Senior Developer', name: 'John Doe', date: '2025-11-07', timeIn: '08:25 AM', timeOut: '05:05 PM', status: 'present' },
    { id: 17, employeeCode: 'EMP002', department: 'Marketing', position: 'Marketing Manager', name: 'Jane Smith', date: '2025-11-07', timeIn: '08:50 AM', timeOut: '05:20 PM', status: 'present' },
    { id: 18, employeeCode: 'EMP003', department: 'Engineering', position: 'Frontend Developer', name: 'Mike Johnson', date: '2025-11-07', timeIn: '09:10 AM', timeOut: '05:30 PM', status: 'late' },
    { id: 19, employeeCode: 'EMP004', department: 'HR', position: 'HR Specialist', name: 'Sarah Williams', date: '2025-11-07', timeIn: '08:30 AM', timeOut: '05:00 PM', status: 'present' },
    { id: 20, employeeCode: 'EMP005', department: 'Sales', position: 'Sales Executive', name: 'Tom Brown', date: '2025-11-07', timeIn: '08:45 AM', timeOut: '', status: 'incomplete' },
    { id: 21, employeeCode: 'EMP006', department: 'Finance', position: 'Accountant', name: 'Emily Davis', date: '2025-11-07', timeIn: '08:35 AM', timeOut: '05:10 PM', status: 'present' },
    { id: 22, employeeCode: 'EMP007', department: 'Engineering', position: 'Backend Developer', name: 'David Wilson', date: '2025-11-07', timeIn: '09:05 AM', timeOut: '05:25 PM', status: 'late' },
    { id: 23, employeeCode: 'EMP008', department: 'IT', position: 'System Admin', name: 'Alex Chen', date: '2025-11-07', timeIn: '08:20 AM', timeOut: '05:15 PM', status: 'present' },
    { id: 24, employeeCode: 'EMP009', department: 'Design', position: 'UI Designer', name: 'Lisa Park', date: '2025-11-07', timeIn: '08:40 AM', timeOut: '05:20 PM', status: 'present' },
    { id: 25, employeeCode: 'EMP010', department: 'QA', position: 'QA Tester', name: 'Mark Rodriguez', date: '2025-11-07', timeIn: '09:15 AM', timeOut: '05:35 PM', status: 'late' },
    { id: 26, employeeCode: 'EMP011', department: 'Operations', position: 'Operations Manager', name: 'Rachel Kim', date: '2025-11-07', timeIn: '08:30 AM', timeOut: '', status: 'incomplete' },
    { id: 27, employeeCode: 'EMP012', department: 'Engineering', position: 'DevOps Engineer', name: 'Chris Anderson', date: '2025-11-07', timeIn: '08:15 AM', timeOut: '05:00 PM', status: 'present' },
    { id: 28, employeeCode: 'EMP013', department: 'Marketing', position: 'Content Writer', name: 'Amanda Lee', date: '2025-11-07', timeIn: '08:55 AM', timeOut: '05:30 PM', status: 'present' },
    { id: 29, employeeCode: 'EMP014', department: 'Sales', position: 'Sales Representative', name: 'Robert Taylor', date: '2025-11-07', timeIn: '09:20 AM', timeOut: '05:40 PM', status: 'late' },
    { id: 30, employeeCode: 'EMP015', department: 'Finance', position: 'Financial Analyst', name: 'Jessica Martinez', date: '2025-11-07', timeIn: '08:25 AM', timeOut: '05:10 PM', status: 'present' },
    
    // November 6, 2025 - 15 employees
    { id: 31, employeeCode: 'EMP001', department: 'Engineering', position: 'Senior Developer', name: 'John Doe', date: '2025-11-06', timeIn: '08:35 AM', timeOut: '05:10 PM', status: 'present' },
    { id: 32, employeeCode: 'EMP002', department: 'Marketing', position: 'Marketing Manager', name: 'Jane Smith', date: '2025-11-06', timeIn: '09:20 AM', timeOut: '05:45 PM', status: 'late' },
    { id: 33, employeeCode: 'EMP003', department: 'Engineering', position: 'Frontend Developer', name: 'Mike Johnson', date: '2025-11-06', timeIn: '08:40 AM', timeOut: '', status: 'incomplete' },
    { id: 34, employeeCode: 'EMP004', department: 'HR', position: 'HR Specialist', name: 'Sarah Williams', date: '2025-11-06', timeIn: '08:20 AM', timeOut: '05:00 PM', status: 'present' },
    { id: 35, employeeCode: 'EMP005', department: 'Sales', position: 'Sales Executive', name: 'Tom Brown', date: '2025-11-06', timeIn: '08:55 AM', timeOut: '05:25 PM', status: 'present' },
    { id: 36, employeeCode: 'EMP006', department: 'Finance', position: 'Accountant', name: 'Emily Davis', date: '2025-11-06', timeIn: '09:10 AM', timeOut: '05:30 PM', status: 'late' },
    { id: 37, employeeCode: 'EMP007', department: 'Engineering', position: 'Backend Developer', name: 'David Wilson', date: '2025-11-06', timeIn: '08:30 AM', timeOut: '05:15 PM', status: 'present' },
    { id: 38, employeeCode: 'EMP008', department: 'IT', position: 'System Admin', name: 'Alex Chen', date: '2025-11-06', timeIn: '08:45 AM', timeOut: '05:20 PM', status: 'present' },
    { id: 39, employeeCode: 'EMP009', department: 'Design', position: 'UI Designer', name: 'Lisa Park', date: '2025-11-06', timeIn: '09:15 AM', timeOut: '', status: 'incomplete' },
    { id: 40, employeeCode: 'EMP010', department: 'QA', position: 'QA Tester', name: 'Mark Rodriguez', date: '2025-11-06', timeIn: '08:25 AM', timeOut: '05:05 PM', status: 'present' },
    { id: 41, employeeCode: 'EMP011', department: 'Operations', position: 'Operations Manager', name: 'Rachel Kim', date: '2025-11-06', timeIn: '08:50 AM', timeOut: '05:30 PM', status: 'present' },
    { id: 42, employeeCode: 'EMP012', department: 'Engineering', position: 'DevOps Engineer', name: 'Chris Anderson', date: '2025-11-06', timeIn: '09:05 AM', timeOut: '05:35 PM', status: 'late' },
    { id: 43, employeeCode: 'EMP013', department: 'Marketing', position: 'Content Writer', name: 'Amanda Lee', date: '2025-11-06', timeIn: '08:35 AM', timeOut: '05:10 PM', status: 'present' },
    { id: 44, employeeCode: 'EMP014', department: 'Sales', position: 'Sales Representative', name: 'Robert Taylor', date: '2025-11-06', timeIn: '08:40 AM', timeOut: '', status: 'incomplete' },
    { id: 45, employeeCode: 'EMP015', department: 'Finance', position: 'Financial Analyst', name: 'Jessica Martinez', date: '2025-11-06', timeIn: '09:20 AM', timeOut: '05:40 PM', status: 'late' },
    
    // November 5, 2025 - 15 employees
    { id: 46, employeeCode: 'EMP001', department: 'Engineering', position: 'Senior Developer', name: 'John Doe', date: '2025-11-05', timeIn: '08:40 AM', timeOut: '05:15 PM', status: 'present' },
    { id: 47, employeeCode: 'EMP002', department: 'Marketing', position: 'Marketing Manager', name: 'Jane Smith', date: '2025-11-05', timeIn: '08:55 AM', timeOut: '05:30 PM', status: 'present' },
    { id: 48, employeeCode: 'EMP003', department: 'Engineering', position: 'Frontend Developer', name: 'Mike Johnson', date: '2025-11-05', timeIn: '09:10 AM', timeOut: '', status: 'incomplete' },
    { id: 49, employeeCode: 'EMP004', department: 'HR', position: 'HR Specialist', name: 'Sarah Williams', date: '2025-11-05', timeIn: '08:30 AM', timeOut: '05:05 PM', status: 'present' },
    { id: 50, employeeCode: 'EMP005', department: 'Sales', position: 'Sales Executive', name: 'Tom Brown', date: '2025-11-05', timeIn: '09:15 AM', timeOut: '05:35 PM', status: 'late' },
    { id: 51, employeeCode: 'EMP006', department: 'Finance', position: 'Accountant', name: 'Emily Davis', date: '2025-11-05', timeIn: '08:25 AM', timeOut: '05:10 PM', status: 'present' },
    { id: 52, employeeCode: 'EMP007', department: 'Engineering', position: 'Backend Developer', name: 'David Wilson', date: '2025-11-05', timeIn: '08:50 AM', timeOut: '05:20 PM', status: 'present' },
    { id: 53, employeeCode: 'EMP008', department: 'IT', position: 'System Admin', name: 'Alex Chen', date: '2025-11-05', timeIn: '09:05 AM', timeOut: '05:40 PM', status: 'late' },
    { id: 54, employeeCode: 'EMP009', department: 'Design', position: 'UI Designer', name: 'Lisa Park', date: '2025-11-05', timeIn: '08:35 AM', timeOut: '', status: 'incomplete' },
    { id: 55, employeeCode: 'EMP010', department: 'QA', position: 'QA Tester', name: 'Mark Rodriguez', date: '2025-11-05', timeIn: '08:45 AM', timeOut: '05:25 PM', status: 'present' },
    { id: 56, employeeCode: 'EMP011', department: 'Operations', position: 'Operations Manager', name: 'Rachel Kim', date: '2025-11-05', timeIn: '08:20 AM', timeOut: '05:00 PM', status: 'present' },
    { id: 57, employeeCode: 'EMP012', department: 'Engineering', position: 'DevOps Engineer', name: 'Chris Anderson', date: '2025-11-05', timeIn: '09:20 AM', timeOut: '05:45 PM', status: 'late' },
    { id: 58, employeeCode: 'EMP013', department: 'Marketing', position: 'Content Writer', name: 'Amanda Lee', date: '2025-11-05', timeIn: '08:30 AM', timeOut: '05:15 PM', status: 'present' },
    { id: 59, employeeCode: 'EMP014', department: 'Sales', position: 'Sales Representative', name: 'Robert Taylor', date: '2025-11-05', timeIn: '08:55 AM', timeOut: '', status: 'incomplete' },
    { id: 60, employeeCode: 'EMP015', department: 'Finance', position: 'Financial Analyst', name: 'Jessica Martinez', date: '2025-11-05', timeIn: '08:40 AM', timeOut: '05:20 PM', status: 'present' },
    
    // November 4, 2025 - 15 employees
    { id: 61, employeeCode: 'EMP001', department: 'Engineering', position: 'Senior Developer', name: 'John Doe', date: '2025-11-04', timeIn: '08:20 AM', timeOut: '05:00 PM', status: 'present' },
    { id: 62, employeeCode: 'EMP002', department: 'Marketing', position: 'Marketing Manager', name: 'Jane Smith', date: '2025-11-04', timeIn: '09:10 AM', timeOut: '05:25 PM', status: 'late' },
    { id: 63, employeeCode: 'EMP003', department: 'Engineering', position: 'Frontend Developer', name: 'Mike Johnson', date: '2025-11-04', timeIn: '08:45 AM', timeOut: '05:20 PM', status: 'present' },
    { id: 64, employeeCode: 'EMP004', department: 'HR', position: 'HR Specialist', name: 'Sarah Williams', date: '2025-11-04', timeIn: '08:30 AM', timeOut: '', status: 'incomplete' },
    { id: 65, employeeCode: 'EMP005', department: 'Sales', position: 'Sales Executive', name: 'Tom Brown', date: '2025-11-04', timeIn: '08:55 AM', timeOut: '05:30 PM', status: 'present' },
    { id: 66, employeeCode: 'EMP006', department: 'Finance', position: 'Accountant', name: 'Emily Davis', date: '2025-11-04', timeIn: '09:15 AM', timeOut: '05:35 PM', status: 'late' },
    { id: 67, employeeCode: 'EMP007', department: 'Engineering', position: 'Backend Developer', name: 'David Wilson', date: '2025-11-04', timeIn: '08:25 AM', timeOut: '05:10 PM', status: 'present' },
    { id: 68, employeeCode: 'EMP008', department: 'IT', position: 'System Admin', name: 'Alex Chen', date: '2025-11-04', timeIn: '08:40 AM', timeOut: '05:15 PM', status: 'present' },
    { id: 69, employeeCode: 'EMP009', department: 'Design', position: 'UI Designer', name: 'Lisa Park', date: '2025-11-04', timeIn: '09:05 AM', timeOut: '', status: 'incomplete' },
    { id: 70, employeeCode: 'EMP010', department: 'QA', position: 'QA Tester', name: 'Mark Rodriguez', date: '2025-11-04', timeIn: '08:35 AM', timeOut: '05:20 PM', status: 'present' },
    { id: 71, employeeCode: 'EMP011', department: 'Operations', position: 'Operations Manager', name: 'Rachel Kim', date: '2025-11-04', timeIn: '08:50 AM', timeOut: '05:25 PM', status: 'present' },
    { id: 72, employeeCode: 'EMP012', department: 'Engineering', position: 'DevOps Engineer', name: 'Chris Anderson', date: '2025-11-04', timeIn: '09:20 AM', timeOut: '05:40 PM', status: 'late' },
    { id: 73, employeeCode: 'EMP013', department: 'Marketing', position: 'Content Writer', name: 'Amanda Lee', date: '2025-11-04', timeIn: '08:30 AM', timeOut: '', status: 'incomplete' },
    { id: 74, employeeCode: 'EMP014', department: 'Sales', position: 'Sales Representative', name: 'Robert Taylor', date: '2025-11-04', timeIn: '08:45 AM', timeOut: '05:15 PM', status: 'present' },
    { id: 75, employeeCode: 'EMP015', department: 'Finance', position: 'Financial Analyst', name: 'Jessica Martinez', date: '2025-11-04', timeIn: '08:20 AM', timeOut: '05:05 PM', status: 'present' },
]);

// Function to generate dynamic remarks based on time data
const getAttendanceRemarks = (record: any) => {
    const hasTimeIn = record.timeIn && record.timeIn.trim() !== '';
    const hasTimeOut = record.timeOut && record.timeOut.trim() !== '';
    
    if (!hasTimeIn && !hasTimeOut) {
        return 'Missing Time In & Time Out';
    } else if (!hasTimeIn) {
        return 'Missing Time In';
    } else if (!hasTimeOut) {
        return 'Missing Time Out';
    } else {
        return 'Complete';
    }
};

// All Attendance Chart - Daily status breakdown
const teamChartData = computed(() => {
    // Get unique dates from attendance data
    const dates = [...new Set(teamAttendance.value.map(emp => emp.date))].sort();
    
    const statusCounts = dates.map(date => {
        const dayEmployees = teamAttendance.value.filter(emp => emp.date === date);
        return {
            present: dayEmployees.filter(emp => emp.status === 'present').length,
            late: dayEmployees.filter(emp => emp.status === 'late').length,
            incomplete: dayEmployees.filter(emp => emp.status === 'incomplete').length,
            total: dayEmployees.length
        };
    });

    return {
        labels: dates,
        datasets: [
            {
                label: 'Present',
                data: statusCounts.map(d => d.present),
                backgroundColor: 'rgba(34, 197, 94, 0.85)',
                borderColor: '#16a34a',
                borderWidth: 0,
                borderRadius: 6,
                borderSkipped: false,
                hoverBackgroundColor: 'rgba(34, 197, 94, 0.95)',
                hoverBorderWidth: 2,
            },
            {
                label: 'Late',
                data: statusCounts.map(d => d.late),
                backgroundColor: 'rgba(249, 115, 22, 0.85)',
                borderColor: '#ea580c',
                borderWidth: 0,
                borderRadius: 6,
                borderSkipped: false,
                hoverBackgroundColor: 'rgba(249, 115, 22, 0.95)',
                hoverBorderWidth: 2,
            },
            {
                label: 'Incomplete',
                data: statusCounts.map(d => d.incomplete),
                backgroundColor: 'rgba(239, 68, 68, 0.85)',
                borderColor: '#dc2626',
                borderWidth: 0,
                borderRadius: 6,
                borderSkipped: false,
                hoverBackgroundColor: 'rgba(239, 68, 68, 0.95)',
                hoverBorderWidth: 2,
            },
        ],
        totals: statusCounts.map(d => d.total)
    };
});

const teamChartOptions = ref({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: true,
            position: 'top' as const,
            labels: {
                color: '#9ca3af',
                padding: 15,
                font: {
                    size: 12
                }
            }
        },
        tooltip: {
            backgroundColor: '#1f2937',
            titleColor: '#f9fafb',
            bodyColor: '#f9fafb',
            borderColor: '#374151',
            borderWidth: 1,
            callbacks: {
                label: (context: any) => {
                    const label = context.dataset.label || '';
                    const value = context.parsed.y;
                    const dataIndex = context.dataIndex;
                    const total = teamChartData.value.totals[dataIndex];
                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                    return `${label}: ${value} (${percentage}%)`;
                },
                afterBody: (context: any) => {
                    const dataIndex = context[0].dataIndex;
                    const total = teamChartData.value.totals[dataIndex];
                    return `Total: ${total} employees`;
                }
            }
        }
    },
    scales: {
        x: {
            ticks: {
                color: '#9ca3af',
                font: {
                    size: 12,
                    weight: 500 as any
                }
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
        },
        y: {
            beginAtZero: true,
            ticks: {
                color: '#9ca3af',
                stepSize: 2,
                font: {
                    size: 12,
                    weight: 500 as any
                },
                callback: function(value: any) {
                    return value;
                }
            },
            grid: {
                color: 'rgba(55, 65, 81, 0.1)',
                drawBorder: false
            },
            title: {
                display: true,
                text: 'Number of Employees',
                color: '#6b7280',
                font: {
                    size: 13,
                    weight: 600 as any
                },
                padding: {
                    bottom: 10
                }
            }
        }
    }
});

// Dialog state
const isDetailsOpen = ref(false);
const selectedRecord = ref<any>(null);
const isEditing = ref(false);
const editTimeIn = ref('');
const editTimeOut = ref('');

// Edit time picker state (12-hour format with AM/PM)
const editTimeInHour = ref<number | null>(null);
const editTimeInMinute = ref<number | null>(null);
const editTimeInPeriod = ref<string>('AM');
const editTimeOutHour = ref<number | null>(null);
const editTimeOutMinute = ref<number | null>(null);
const editTimeOutPeriod = ref<string>('AM');

const openDetails = (record: any) => {
    selectedRecord.value = record;
    isDetailsOpen.value = true;
};

const closeDetails = () => {
    isDetailsOpen.value = false;
    selectedRecord.value = null;
    isEditing.value = false;
};

// Delete confirmation dialog state
const showDeleteDialog = ref(false);
const deleteCountdown = ref(5);
const recordToDelete = ref<number | null>(null);
let deleteCountdownInterval: ReturnType<typeof setInterval> | null = null;

// Biometric delete confirmation dialog state
const showBiometricDeleteDialog = ref(false);
const biometricDeleteCountdown = ref(5);
const biometricLogToDelete = ref<number | null>(null);
let biometricDeleteCountdownInterval: ReturnType<typeof setInterval> | null = null;

const deleteAttendanceRecord = (id: number) => {
    recordToDelete.value = id;
    showDeleteDialog.value = true;
    deleteCountdown.value = 5;
    
    // Start countdown
    deleteCountdownInterval = setInterval(() => {
        deleteCountdown.value--;
        if (deleteCountdown.value <= 0 && deleteCountdownInterval) {
            clearInterval(deleteCountdownInterval);
        }
    }, 1000);
};

const closeDeleteDialog = () => {
    showDeleteDialog.value = false;
    recordToDelete.value = null;
    if (deleteCountdownInterval) {
        clearInterval(deleteCountdownInterval);
        deleteCountdownInterval = null;
    }
};

const confirmDelete = () => {
    if (recordToDelete.value !== null) {
        const index = teamAttendance.value.findIndex(record => record.id === recordToDelete.value);
        if (index !== -1) {
            teamAttendance.value.splice(index, 1);
            console.log('Deleted attendance record:', recordToDelete.value);
        }
    }
    closeDeleteDialog();
};

const updateEditTimeIn = () => {
    if (editTimeInHour.value !== null && editTimeInMinute.value !== null) {
        const hour = String(editTimeInHour.value).padStart(2, '0');
        const minute = String(editTimeInMinute.value).padStart(2, '0');
        editTimeIn.value = `${hour}:${minute} ${editTimeInPeriod.value}`;
    }
};

const updateEditTimeOut = () => {
    if (editTimeOutHour.value !== null && editTimeOutMinute.value !== null) {
        const hour = String(editTimeOutHour.value).padStart(2, '0');
        const minute = String(editTimeOutMinute.value).padStart(2, '0');
        editTimeOut.value = `${hour}:${minute} ${editTimeOutPeriod.value}`;
    }
};

const startEdit = () => {
    isEditing.value = true;
    editTimeIn.value = selectedRecord.value?.timeIn || '';
    editTimeOut.value = selectedRecord.value?.timeOut || '';
    
    // Parse existing time in
    if (selectedRecord.value?.timeIn) {
        const timeInMatch = selectedRecord.value.timeIn.match(/(\d+):(\d+)\s*(AM|PM)/i);
        if (timeInMatch) {
            editTimeInHour.value = parseInt(timeInMatch[1]);
            editTimeInMinute.value = parseInt(timeInMatch[2]);
            editTimeInPeriod.value = timeInMatch[3].toUpperCase();
        }
    }
    
    // Parse existing time out
    if (selectedRecord.value?.timeOut) {
        const timeOutMatch = selectedRecord.value.timeOut.match(/(\d+):(\d+)\s*(AM|PM)/i);
        if (timeOutMatch) {
            editTimeOutHour.value = parseInt(timeOutMatch[1]);
            editTimeOutMinute.value = parseInt(timeOutMatch[2]);
            editTimeOutPeriod.value = timeOutMatch[3].toUpperCase();
        }
    }
};

const saveEdit = () => {
    // Update the record with new times
    if (selectedRecord.value) {
        selectedRecord.value.timeIn = editTimeIn.value;
        selectedRecord.value.timeOut = editTimeOut.value;
    }
    isEditing.value = false;
    console.log('Saved time changes:', { timeIn: editTimeIn.value, timeOut: editTimeOut.value });
};

const cancelEdit = () => {
    isEditing.value = false;
};

const exportAttendanceDetails = () => {
    if (!selectedRecord.value) return;
    
    // Get all attendance records for this employee
    const employeeRecords = teamAttendance.value.filter(record => 
        record.employeeCode === selectedRecord.value.employeeCode
    );
    
    if (employeeRecords.length === 0) return;
    
    // Convert all employee records to CSV format
    const headers = ['Employee Code', 'Name', 'Department', 'Position', 'Date', 'Time In', 'Time Out', 'Status', 'Hours Worked', 'Remarks'];
    const csvContent = [
        headers.join(','),
        ...employeeRecords.map(record => {
            const hoursWorked = (record.timeIn && record.timeOut) ? calculateHours(record.timeIn, record.timeOut) + 'h' : '-';
            const remarks = getAttendanceRemarks(record);
            return `${record.employeeCode},${record.name},${record.department},${record.position},${record.date},${record.timeIn || '-'},${record.timeOut || '-'},${record.status},${hoursWorked},"${remarks}"`;
        })
    ].join('\n');
    
    // Create blob and download
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', `attendance_${selectedRecord.value.employeeCode}_all_records.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};

// Add dialog state
const isAddOpen = ref(false);
const selectedEmployee = ref<any>(null);
const newAttendance = ref({
    employeeName: '',
    date: '',
    timeIn: '',
    timeOut: '',
});

// Time picker state (12-hour format with AM/PM)
const timeInHour = ref<number | null>(null);
const timeInMinute = ref<number | null>(null);
const timeInPeriod = ref<string>('AM');
const timeOutHour = ref<number | null>(null);
const timeOutMinute = ref<number | null>(null);
const timeOutPeriod = ref<string>('AM');

// Update time functions
const updateTimeIn = () => {
    if (timeInHour.value !== null && timeInMinute.value !== null) {
        const hour = String(timeInHour.value).padStart(2, '0');
        const minute = String(timeInMinute.value).padStart(2, '0');
        newAttendance.value.timeIn = `${hour}:${minute} ${timeInPeriod.value}`;
    }
};

const updateTimeOut = () => {
    if (timeOutHour.value !== null && timeOutMinute.value !== null) {
        const hour = String(timeOutHour.value).padStart(2, '0');
        const minute = String(timeOutMinute.value).padStart(2, '0');
        newAttendance.value.timeOut = `${hour}:${minute} ${timeOutPeriod.value}`;
    }
};

// Combobox state
const selectedEmployeeValue = ref('')

// Watch for employee selection
watch(selectedEmployee, (newVal) => {
    if (newVal) {
        newAttendance.value.employeeName = newVal.label;
        selectedEmployeeValue.value = newVal.value;
    }
});

// Employee list for dropdown
const employees = ref([
    { value: 'EMP001', label: 'John Doe' },
    { value: 'EMP002', label: 'Jane Smith' },
    { value: 'EMP003', label: 'Mike Johnson' },
    { value: 'EMP004', label: 'Sarah Williams' },
    { value: 'EMP005', label: 'Tom Brown' },
    { value: 'EMP006', label: 'Emily Davis' },
    { value: 'EMP007', label: 'David Wilson' },
]);

// Repopulate confirmation state
const showRepopulateDialog = ref(false);
const repopulateCountdown = ref(5);
let countdownInterval: ReturnType<typeof setInterval> | null = null;

const openRepopulateDialog = () => {
    showRepopulateDialog.value = true;
    repopulateCountdown.value = 5;
    
    // Start countdown
    countdownInterval = setInterval(() => {
        repopulateCountdown.value--;
        if (repopulateCountdown.value <= 0 && countdownInterval) {
            clearInterval(countdownInterval);
        }
    }, 1000);
};

const closeRepopulateDialog = () => {
    showRepopulateDialog.value = false;
    if (countdownInterval) {
        clearInterval(countdownInterval);
        countdownInterval = null;
    }
};

const confirmRepopulate = () => {
    // Your repopulate logic here
    console.log('Repopulating attendance data...');
    closeRepopulateDialog();
};

const openAddDialog = () => {
    isAddOpen.value = true;
};

const closeAddDialog = () => {
    isAddOpen.value = false;
    newAttendance.value = {
        employeeName: '',
        date: '',
        timeIn: '',
        timeOut: '',
    };
};

const addAttendance = () => {
    // Your backend will handle this
    console.log('Adding attendance:', newAttendance.value);
    closeAddDialog();
};

// Dummy data for Biometric Logs (throughout the day)
const biometricLogs = ref([
    { id: 1, employeeCode: 'EMP001', date: '2025-11-08', time: '08:15:23' },
    { id: 2, employeeCode: 'EMP002', date: '2025-11-08', time: '08:30:15' },
    { id: 3, employeeCode: 'EMP003', date: '2025-11-08', time: '08:35:42' },
    { id: 4, employeeCode: 'EMP004', date: '2025-11-08', time: '08:45:11' },
    { id: 5, employeeCode: 'EMP005', date: '2025-11-08', time: '08:50:55' },
    { id: 6, employeeCode: 'EMP006', date: '2025-11-08', time: '09:00:33' },
    { id: 7, employeeCode: 'EMP007', date: '2025-11-08', time: '09:10:18' },
    { id: 8, employeeCode: 'EMP008', date: '2025-11-08', time: '09:15:44' },
    { id: 9, employeeCode: 'EMP009', date: '2025-11-08', time: '12:00:12' },
    { id: 10, employeeCode: 'EMP010', date: '2025-11-08', time: '12:05:28' },
    { id: 11, employeeCode: 'EMP011', date: '2025-11-08', time: '12:30:45' },
    { id: 12, employeeCode: 'EMP012', date: '2025-11-08', time: '13:00:19' },
    { id: 13, employeeCode: 'EMP001', date: '2025-11-08', time: '17:00:23' },
    { id: 14, employeeCode: 'EMP002', date: '2025-11-08', time: '17:15:37' },
    { id: 15, employeeCode: 'EMP003', date: '2025-11-08', time: '17:20:51' },
    { id: 16, employeeCode: 'EMP004', date: '2025-11-08', time: '17:30:18' },
    { id: 17, employeeCode: 'EMP005', date: '2025-11-08', time: '17:35:42' },
    { id: 18, employeeCode: 'EMP006', date: '2025-11-08', time: '17:45:29' },
    { id: 19, employeeCode: 'EMP007', date: '2025-11-08', time: '18:00:15' },
]);

const deleteBiometricLog = (id: number) => {
    biometricLogToDelete.value = id;
    showBiometricDeleteDialog.value = true;
    biometricDeleteCountdown.value = 5;
    
    // Start countdown
    biometricDeleteCountdownInterval = setInterval(() => {
        biometricDeleteCountdown.value--;
        if (biometricDeleteCountdown.value <= 0 && biometricDeleteCountdownInterval) {
            clearInterval(biometricDeleteCountdownInterval);
        }
    }, 1000);
};

const closeBiometricDeleteDialog = () => {
    showBiometricDeleteDialog.value = false;
    biometricLogToDelete.value = null;
    if (biometricDeleteCountdownInterval) {
        clearInterval(biometricDeleteCountdownInterval);
        biometricDeleteCountdownInterval = null;
    }
    biometricDeleteCountdown.value = 5;
};

const confirmBiometricDelete = () => {
    if (biometricLogToDelete.value !== null) {
        const index = biometricLogs.value.findIndex(log => log.id === biometricLogToDelete.value);
        if (index !== -1) {
            biometricLogs.value.splice(index, 1);
            console.log('Deleted biometric log:', biometricLogToDelete.value);
        }
    }
    closeBiometricDeleteDialog();
};

const exportBiometricData = () => {
    // Convert data to CSV format
    const headers = ['Employee Code', 'Date', 'Time'];
    const csvContent = [
        headers.join(','),
        ...biometricLogs.value.map(log => `${log.employeeCode},${log.date},${log.time}`)
    ].join('\n');
    
    // Create blob and download
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', `biometric_logs_${new Date().toISOString().split('T')[0]}.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};

// Biometric Scan Activity Chart
const biometricChartData = computed(() => {
    // Time slots from 8 AM to 6 PM
    const timeSlots = [
        '08:00', '09:00', '10:00', '11:00', '12:00', 
        '13:00', '14:00', '15:00', '16:00', '17:00', '18:00'
    ];
    
    // Count scans per hour
    const scanCounts = timeSlots.map(slot => {
        const hour = parseInt(slot.split(':')[0]);
        return biometricLogs.value.filter(log => {
            const logHour = parseInt(log.time.split(':')[0]);
            return logHour === hour;
        }).length;
    });
    
    return {
        labels: timeSlots,
        datasets: [{
            label: 'Scan Activity',
            data: scanCounts,
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            borderWidth: 3,
            tension: 0.4,
            pointRadius: 6,
            pointHoverRadius: 8,
            pointBackgroundColor: '#3b82f6',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointHoverBackgroundColor: '#60a5fa',
            pointHoverBorderColor: '#fff',
            fill: true
        }]
    };
});

const biometricChartOptions = ref({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: false
        },
        tooltip: {
            backgroundColor: '#1f2937',
            titleColor: '#f9fafb',
            bodyColor: '#f9fafb',
            borderColor: '#374151',
            borderWidth: 1,
            padding: 12,
            displayColors: false,
            callbacks: {
                title: (context: any) => {
                    return `Time: ${context[0].label}`;
                },
                label: (context: any) => {
                    return `Scans: ${context.parsed.y}`;
                }
            }
        }
    },
    scales: {
        y: {
            beginAtZero: true,
            ticks: {
                color: '#9ca3af',
                stepSize: 1
            },
            grid: {
                color: 'rgba(55, 65, 81, 0.3)',
                drawBorder: false
            },
            title: {
                display: true,
                text: 'Number of Scans',
                color: '#9ca3af'
            }
        },
        x: {
            ticks: {
                color: '#9ca3af'
            },
            grid: {
                color: 'rgba(55, 65, 81, 0.3)',
                drawBorder: false
            },
            title: {
                display: true,
                text: 'Time of Day',
                color: '#9ca3af'
            }
        }
    }
});

// Add Biometric dialog state
const isAddBiometricOpen = ref(false);
const biometricEmployeeCode = ref('');
const newBiometric = ref({
    employeeCode: '',
    date: '',
    time: '',
});

// Add Attendance dialog state

// Search and filter state (applied filters)
const myAttendanceSearch = ref('');
const myAttendanceStatusFilter = ref('all');
const teamAttendanceSearch = ref('');
const teamAttendanceStatusFilter = ref('all');
const teamAttendanceDepartmentFilter = ref('all');
const biometricSearch = ref('');
const biometricDateFilter = ref('all');

// Filter dialog states
const isMyAttendanceFilterOpen = ref(false);
const isTeamAttendanceFilterOpen = ref(false);
const isBiometricFilterOpen = ref(false);

// Temporary filter states (for staging changes before apply)
const tempMyAttendanceStatusFilter = ref('all');
const tempTeamAttendanceStatusFilter = ref('all');
const tempTeamAttendanceDepartmentFilter = ref('all');
const tempBiometricDateFilter = ref('all');

// Filter functions
const openMyAttendanceFilter = () => {
    tempMyAttendanceStatusFilter.value = myAttendanceStatusFilter.value;
    isMyAttendanceFilterOpen.value = true;
};

const applyMyAttendanceFilter = () => {
    myAttendanceStatusFilter.value = tempMyAttendanceStatusFilter.value;
    isMyAttendanceFilterOpen.value = false;
};

const clearMyAttendanceFilter = () => {
    tempMyAttendanceStatusFilter.value = 'all';
    myAttendanceStatusFilter.value = 'all';
};

const openTeamAttendanceFilter = () => {
    tempTeamAttendanceStatusFilter.value = teamAttendanceStatusFilter.value;
    tempTeamAttendanceDepartmentFilter.value = teamAttendanceDepartmentFilter.value;
    isTeamAttendanceFilterOpen.value = true;
};

const applyTeamAttendanceFilter = () => {
    teamAttendanceStatusFilter.value = tempTeamAttendanceStatusFilter.value;
    teamAttendanceDepartmentFilter.value = tempTeamAttendanceDepartmentFilter.value;
    isTeamAttendanceFilterOpen.value = false;
};

const clearTeamAttendanceFilter = () => {
    tempTeamAttendanceStatusFilter.value = 'all';
    tempTeamAttendanceDepartmentFilter.value = 'all';
    teamAttendanceStatusFilter.value = 'all';
    teamAttendanceDepartmentFilter.value = 'all';
};

const openBiometricFilter = () => {
    tempBiometricDateFilter.value = biometricDateFilter.value;
    isBiometricFilterOpen.value = true;
};

const applyBiometricFilter = () => {
    biometricDateFilter.value = tempBiometricDateFilter.value;
    isBiometricFilterOpen.value = false;
};

const clearBiometricFilter = () => {
    tempBiometricDateFilter.value = 'all';
    biometricDateFilter.value = 'all';
};

// Biometric time picker state (12-hour format with AM/PM)
const biometricTimeHour = ref<number | null>(null);
const biometricTimeMinute = ref<number | null>(null);
const biometricTimePeriod = ref<string>('AM');

const updateBiometricTime = () => {
    if (biometricTimeHour.value !== null && biometricTimeMinute.value !== null) {
        const hour = String(biometricTimeHour.value).padStart(2, '0');
        const minute = String(biometricTimeMinute.value).padStart(2, '0');
        newBiometric.value.time = `${hour}:${minute} ${biometricTimePeriod.value}`;
    }
};

const openAddBiometricDialog = () => {
    isAddBiometricOpen.value = true;
};

const closeAddBiometricDialog = () => {
    isAddBiometricOpen.value = false;
    newBiometric.value = {
        employeeCode: '',
        date: '',
        time: '',
    };
    biometricEmployeeCode.value = '';
    biometricTimeHour.value = null;
    biometricTimeMinute.value = null;
    biometricTimePeriod.value = 'AM';
};

const addBiometric = () => {
    // Add new biometric log
    const newId = Math.max(...biometricLogs.value.map(log => log.id)) + 1;
    biometricLogs.value.push({
        id: newId,
        employeeCode: biometricEmployeeCode.value,
        date: newBiometric.value.date,
        time: newBiometric.value.time,
    });
    console.log('Adding biometric log:', newBiometric.value);
    closeAddBiometricDialog();
};



const getStatusVariant = (status: string) => {
    switch (status) {
        case 'present': return 'default';  // Will style with green
        case 'late': return 'secondary';   // Will style with orange/yellow
        case 'incomplete': return 'destructive';  // Will style with red
        default: return 'outline';
    }
};

const getStatusClass = (status: string) => {
    switch (status) {
        case 'present': return 'bg-green-500 hover:bg-green-600 text-white';
        case 'late': return 'bg-orange-500 hover:bg-orange-600 text-white';
        case 'incomplete': return 'bg-red-500 hover:bg-red-600 text-white';
        default: return '';
    }
};
</script>

<template>
    <Head title="Attendance" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="relative min-h-[100vh] p-6 flex-1 border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border">
            <Tabs default-value="my-attendance" class="w-full">
                <TabsList class="grid w-full grid-cols-3 mb-6">
                    <TabsTrigger value="my-attendance">
                        <div class="flex items-center justify-center gap-2">
                            <Calendar :size="16" class="shrink-0" />
                            <span class="hidden sm:inline">My Attendance</span>
                        </div>
                    </TabsTrigger>
                    <TabsTrigger value="all">
                        <div class="flex items-center justify-center gap-2">
                            <Users :size="16" class="shrink-0" />
                            <span class="hidden sm:inline">All Attendance</span>
                        </div>
                    </TabsTrigger>
                    <TabsTrigger value="biometrics">
                        <div class="flex items-center justify-center gap-2">
                            <Database :size="16" class="shrink-0" />
                            <span class="hidden sm:inline">Raw Biometrics</span>
                        </div>
                    </TabsTrigger>
                </TabsList>

                <!-- My Attendance Tab -->
                <TabsContent value="my-attendance">
                    <!-- Chart Card -->
                    <Card class="mb-6">
                        <CardHeader>
                            <CardTitle>Hours Worked Overview</CardTitle>
                            <CardDescription>Daily hours worked for the past week</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div style="height: 300px;">
                                <Bar :data="myAttendanceChartData" :options="myAttendanceChartOptions" />
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Attendance Records Card -->
                    <Card>
                        <CardHeader>
                            <div class="flex items-center justify-between">
                                <div>
                                    <CardTitle>My Attendance Records</CardTitle>
                                    <CardDescription>View your personal attendance history</CardDescription>
                                </div>
                                <Button variant="outline" @click="exportMyAttendance">
                                    Export Data
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <!-- Search and Filter Controls -->
                            <div class="flex gap-4 mb-6">
                                <div class="flex-1 relative">
                                    <Search :size="16" class="absolute left-3 top-1/2 transform -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        v-model="myAttendanceSearch"
                                        placeholder="Search by date..."
                                        class="w-full pl-10"
                                    />
                                </div>
                                <Popover v-model:open="isMyAttendanceFilterOpen">
                                    <PopoverTrigger as-child>
                                        <Button variant="outline" class="gap-2" @click="openMyAttendanceFilter">        
                                            <Filter :size="16" />
                                            Filter
                                            <Badge v-if="myAttendanceStatusFilter !== 'all'" variant="secondary" class="ml-1">
                                                1
                                            </Badge>
                                        </Button>
                                    </PopoverTrigger>
                                    <PopoverContent class="w-80" align="end">
                                        <div class="space-y-4">
                                            <div>
                                                <h4 class="font-medium mb-2">Filter Options</h4>
                                            </div>
                                            <div class="space-y-2">
                                                <Label class="text-sm font-medium">Status</Label>
                                                <Select v-model="tempMyAttendanceStatusFilter">
                                                    <SelectTrigger>
                                                        <SelectValue placeholder="Filter by status" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="all">All Status</SelectItem>
                                                        <SelectItem value="present">Present</SelectItem>
                                                        <SelectItem value="late">Late</SelectItem>
                                                        <SelectItem value="incomplete">Incomplete</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            <div class="flex justify-between pt-2">
                                                <Button variant="outline" size="sm" @click="clearMyAttendanceFilter">
                                                    Clear Filters
                                                </Button>
                                                <Button size="sm" @click="applyMyAttendanceFilter">
                                                    Apply
                                                </Button>
                                            </div>
                                        </div>
                                    </PopoverContent>
                                </Popover>
                            </div>

                            <!-- Desktop Table View -->
                            <div class="hidden md:block relative w-full overflow-auto">
                                <table class="w-full caption-bottom text-sm">
                                    <thead class="[&_tr]:border-b">
                                        <tr class="border-b transition-colors hover:bg-muted/50">
                                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Employee Code</th>
                                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Name</th>
                                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Date</th>
                                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Time In</th>
                                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Time Out</th>
                                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Hours Worked</th>
                                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Status</th>
                                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody class="[&_tr:last-child]:border-0">
                                        <template v-for="record in paginatedMyAttendance" :key="record.id">
                                            <tr class="border-b transition-colors hover:bg-muted/50">
                                                <td class="p-4 align-middle font-mono text-xs">{{ record.employeeCode }}</td>
                                                <td class="p-4 align-middle font-medium">{{ record.name }}</td>
                                                <td class="p-4 align-middle">{{ record.date }}</td>
                                                <td class="p-4 align-middle">{{ record.timeIn || '-' }}</td>
                                                <td class="p-4 align-middle">{{ record.timeOut || '-' }}</td>
                                                <td class="p-4 align-middle">{{ (record.timeIn && record.timeOut) ? calculateHours(record.timeIn, record.timeOut) + 'h' : '-' }}</td>
                                                <td class="p-4 align-middle">
                                                    <Badge :variant="getStatusVariant(record.status)" :class="['capitalize', getStatusClass(record.status)]">
                                                        {{ record.status }}
                                                    </Badge>
                                                </td>
                                                <td class="p-4 align-middle text-sm text-muted-foreground">{{ getAttendanceRemarks(record) }}</td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <div v-if="filteredMyAttendance.length > myAttendanceItemsPerPage" class="mt-6">
                                <Pagination v-model:page="myAttendanceCurrentPage" :items-per-page="myAttendanceItemsPerPage" :total="filteredMyAttendance.length" class="justify-end">
                                    <PaginationContent v-slot="{ items }">
                                        <PaginationPrevious />
                                        
                                        <template v-for="(item, index) in items" :key="index">
                                            <PaginationItem
                                                v-if="item.type === 'page'"
                                                :value="item.value"
                                                :is-active="item.value === myAttendanceCurrentPage"
                                            >
                                                {{ item.value }}
                                            </PaginationItem>
                                            <PaginationEllipsis v-else-if="item.type === 'ellipsis'" :index="index" />
                                        </template>
                                        
                                        <PaginationNext />
                                    </PaginationContent>
                                </Pagination>
                            </div>

                            <!-- Mobile Card View -->
                            <div class="md:hidden space-y-3">
                                <div 
                                    v-for="record in paginatedMyAttendance" 
                                    :key="record.id" 
                                    class="p-4 rounded-lg border transition-colors"
                                    :class="{
                                        'border-green-200 dark:border-green-800': record.status === 'present',
                                        'border-orange-200 dark:border-orange-800': record.status === 'late',
                                        'border-red-200 dark:border-red-800': record.status === 'incomplete'
                                    }"
                                >
                                    <div class="flex justify-between items-start mb-2">
                                        <div>
                                            <h3 class="font-semibold text-sm">{{ record.name }}</h3>
                                            <p class="text-xs font-mono text-muted-foreground">{{ record.employeeCode }}</p>
                                        </div>
                                        <span class="text-xs text-muted-foreground">{{ record.date }}</span>
                                    </div>
                                    <div class="grid grid-cols-3 gap-2 text-sm">
                                        <div>
                                            <span class="text-muted-foreground">In:</span>
                                            <span class="ml-1 font-medium">{{ record.timeIn || '-' }}</span>
                                        </div>
                                        <div>
                                            <span class="text-muted-foreground">Out:</span>
                                            <span class="ml-1 font-medium">{{ record.timeOut || '-' }}</span>
                                        </div>
                                        <div>
                                            <span class="text-muted-foreground">Hours:</span>
                                            <span class="ml-1 font-medium">{{ (record.timeIn && record.timeOut) ? calculateHours(record.timeIn, record.timeOut) + 'h' : '-' }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- All Attendance Tab -->        
                <TabsContent value="all">
                    <!-- Department Chart Card -->
                    <Card class="mb-6">
                        <CardHeader>
                            <CardTitle>Daily Attendance Overview</CardTitle>
                            <CardDescription>Daily attendance status breakdown</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div style="height: 300px;">
                                <Bar :data="teamChartData" :options="teamChartOptions" />
                            </div>
                        </CardContent>
                    </Card>

                    <!-- All Attendance Records Card -->
                    <Card>
                        <CardHeader>
                            <div class="flex items-center justify-between">
                                <div>
                                    <CardTitle>All Attendance</CardTitle>
                                    <CardDescription>View all employees' attendance records</CardDescription>
                                </div>
                                <div class="flex gap-2">
                                    <Button variant="outline" @click="openRepopulateDialog">
                                        Repopulate
                                    </Button>
                                    <Button variant="outline" @click="openAddDialog">
                                        Add
                                    </Button>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <!-- Search and Filter Controls -->
                            <div class="flex gap-4 mb-6">
                                <div class="flex-1 relative">
                                    <Search :size="16" class="absolute left-3 top-1/2 transform -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        v-model="teamAttendanceSearch"
                                        placeholder="Search by name, department, or employee code..."
                                        class="w-full pl-10"
                                    />
                                </div>
                                <Popover v-model:open="isTeamAttendanceFilterOpen">     
                                    <PopoverTrigger as-child>
                                        <Button variant="outline" class="gap-2" @click="openTeamAttendanceFilter">  
                                            <Filter :size="16" />
                                            Filter
                                            <Badge v-if="teamAttendanceStatusFilter !== 'all' || teamAttendanceDepartmentFilter !== 'all'" variant="secondary" class="ml-1">
                                                {{ (teamAttendanceStatusFilter !== 'all' ? 1 : 0) + (teamAttendanceDepartmentFilter !== 'all' ? 1 : 0) }}
                                            </Badge>
                                        </Button>
                                    </PopoverTrigger>
                                    <PopoverContent class="w-80" align="end">
                                        <div class="space-y-4">
                                            <div>
                                                <h4 class="font-medium mb-2">Filter Options</h4>
                                            </div>
                                            <div class="space-y-4">
                                                <div class="space-y-2">
                                                    <Label class="text-sm font-medium">Status</Label>
                                                    <Select v-model="tempTeamAttendanceStatusFilter">
                                                        <SelectTrigger>
                                                            <SelectValue placeholder="Filter by status" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="all">All Status</SelectItem>
                                                            <SelectItem value="present">Present</SelectItem>
                                                            <SelectItem value="late">Late</SelectItem>
                                                            <SelectItem value="incomplete">Incomplete</SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                                <div class="space-y-2">
                                                    <Label class="text-sm font-medium">Department</Label>
                                                    <Select v-model="tempTeamAttendanceDepartmentFilter">
                                                        <SelectTrigger>
                                                            <SelectValue placeholder="Filter by department" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="all">All Departments</SelectItem>
                                                            <SelectItem value="Engineering">Engineering</SelectItem>
                                                            <SelectItem value="Marketing">Marketing</SelectItem>
                                                            <SelectItem value="HR">HR</SelectItem>
                                                            <SelectItem value="Sales">Sales</SelectItem>
                                                            <SelectItem value="Finance">Finance</SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            </div>
                                            <div class="flex justify-between pt-2">
                                                <Button variant="outline" size="sm" @click="clearTeamAttendanceFilter">
                                                    Clear Filters
                                                </Button>
                                                <Button size="sm" @click="applyTeamAttendanceFilter">
                                                    Apply
                                                </Button>
                                            </div>
                                        </div>
                                    </PopoverContent>
                                </Popover>
                            </div>

                            <!-- Desktop Table View -->
                            <div class="hidden md:block relative w-full overflow-auto">
                                <table class="w-full caption-bottom text-sm">
                                    <thead class="[&_tr]:border-b">
                                        <tr class="border-b transition-colors hover:bg-muted/50">
                                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Employee Code</th>
                                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Name</th>
                                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Department</th>
                                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Date</th>
                                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Time In</th>
                                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Time Out</th>
                                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Hours Worked</th>
                                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Status</th>
                                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Remarks</th>
                                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="[&_tr:last-child]:border-0">
                                            <tr v-for="record in paginatedTeamAttendance" :key="record.id" class="border-b transition-colors hover:bg-muted/50">
                                            <td class="p-4 align-middle font-mono text-xs">{{ record.employeeCode }}</td>
                                            <td class="p-4 align-middle font-medium">{{ record.name }}</td>
                                            <td class="p-4 align-middle">{{ record.department }}</td>
                                            <td class="p-4 align-middle">{{ record.date }}</td>
                                            <td class="p-4 align-middle">{{ record.timeIn || '-' }}</td>
                                            <td class="p-4 align-middle">{{ record.timeOut || '-' }}</td>
                                            <td class="p-4 align-middle">{{ (record.timeIn && record.timeOut) ? calculateHours(record.timeIn, record.timeOut) + 'h' : '-' }}</td>
                                            <td class="p-4 align-middle">
                                                <Badge :variant="getStatusVariant(record.status)" :class="['capitalize', getStatusClass(record.status)]">
                                                    {{ record.status }}
                                                </Badge>
                                            </td>
                                            <td class="p-4 align-middle text-sm text-muted-foreground">{{ getAttendanceRemarks(record) }}</td>
                                            <td class="p-4 align-middle">
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger as-child>
                                                        <Button variant="ghost" size="sm" class="h-8 w-8 p-0">
                                                            <MoreVertical :size="16" />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <DropdownMenuItem @click="openDetails(record)">
                                                            <Eye :size="16" class="mr-2" />
                                                            View Details
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem @click="deleteAttendanceRecord(record.id)" class="text-destructive focus:text-destructive">
                                                            <Trash2 :size="16" class="mr-2" />
                                                            Delete
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Mobile Card View -->
                            <div class="md:hidden space-y-3">
                                <div 
                                    v-for="record in paginatedTeamAttendance" 
                                    :key="record.id" 
                                    class="p-4 rounded-lg border transition-colors"
                                    :class="{
                                        'border-green-200 dark:border-green-800': record.status === 'present',
                                        'border-orange-200 dark:border-orange-800': record.status === 'late',
                                        'border-red-200 dark:border-red-800': record.status === 'incomplete'
                                    }"
                                >
                                    <div class="flex justify-between items-start mb-2">
                                        <div>
                                            <h3 class="font-semibold text-sm">{{ record.name }}</h3>
                                            <p class="text-xs font-mono text-muted-foreground">{{ record.employeeCode }}</p>
                                            <p class="text-xs text-muted-foreground">{{ record.department }}</p>
                                        </div>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger as-child>
                                                <Button variant="ghost" size="sm" class="h-6 w-6 p-0">
                                                    <MoreVertical :size="14" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem @click="openDetails(record)">
                                                    <Eye :size="14" class="mr-2" />
                                                    View Details
                                                </DropdownMenuItem>
                                                <DropdownMenuItem @click="deleteAttendanceRecord(record.id)" class="text-destructive focus:text-destructive">
                                                    <Trash2 :size="14" class="mr-2" />
                                                    Delete
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                    <div class="space-y-1 text-xs">
                                        <div class="text-muted-foreground">{{ record.date }}</div>
                                        <div class="grid grid-cols-3 gap-2">
                                            <div>
                                                <span class="text-muted-foreground">In:</span>
                                                <span class="ml-1 font-medium">{{ record.timeIn || '-' }}</span>
                                            </div>
                                            <div>
                                                <span class="text-muted-foreground">Out:</span>
                                                <span class="ml-1 font-medium">{{ record.timeOut || '-' }}</span>
                                            </div>
                                            <div>
                                                <span class="text-muted-foreground">Hours:</span>
                                                <span class="ml-1 font-medium">{{ (record.timeIn && record.timeOut) ? calculateHours(record.timeIn, record.timeOut) + 'h' : '-' }}</span>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <Badge :variant="getStatusVariant(record.status)" :class="['capitalize', 'text-xs', getStatusClass(record.status)]">
                                                {{ record.status }}
                                            </Badge>
                                        </div>
                                        <div class="text-muted-foreground italic text-xs">
                                            "{{ getAttendanceRemarks(record) }}"
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Pagination -->
                            <div v-if="filteredTeamAttendance.length > teamAttendanceItemsPerPage" class="mt-6">
                                <Pagination v-model:page="teamAttendanceCurrentPage" :items-per-page="teamAttendanceItemsPerPage" :total="filteredTeamAttendance.length" class="justify-end">
                                    <PaginationContent v-slot="{ items }">
                                        <PaginationPrevious />
                                        
                                        <template v-for="(item, index) in items" :key="index">
                                            <PaginationItem
                                                v-if="item.type === 'page'"
                                                :value="item.value"
                                                :is-active="item.value === teamAttendanceCurrentPage"
                                            >
                                                {{ item.value }}
                                            </PaginationItem>
                                            <PaginationEllipsis v-else-if="item.type === 'ellipsis'" :index="index" />
                                        </template>
                                        
                                        <PaginationNext />
                                    </PaginationContent>
                                </Pagination>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- Raw Biometrics Tab -->
                <TabsContent value="biometrics">
                    <!-- Scan Activity Chart Card -->
                    <Card class="mb-6">
                        <CardHeader>
                            <CardTitle>Scan Activity Throughout the Day</CardTitle>
                            <CardDescription>Peak check-in and check-out times</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div style="height: 300px;">
                                <Line :data="biometricChartData" :options="biometricChartOptions" />
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Biometric Logs Card -->
                    <Card>
                        <CardHeader>
                            <div class="flex items-center justify-between">
                                <div>
                                    <CardTitle>Raw Biometric Logs</CardTitle>
                                    <CardDescription>View raw biometric scan data</CardDescription>
                                </div>
                                <div class="flex gap-2">
                                    <Button variant="outline" @click="openAddBiometricDialog">
                                        Add
                                    </Button>
                                    <Button variant="outline" @click="exportBiometricData">
                                        Export Data
                                    </Button>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <!-- Search and Filter Controls -->
                            <div class="flex gap-4 mb-6">
                                <div class="flex-1 relative">
                                    <Search :size="16" class="absolute left-3 top-1/2 transform -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        v-model="biometricSearch"
                                        placeholder="Search by employee code, date, or time..."
                                        class="w-full pl-10"
                                    />
                                </div>
                                <Popover v-model:open="isBiometricFilterOpen">
                                    <PopoverTrigger as-child>
                                        <Button variant="outline" class="gap-2" @click="openBiometricFilter">
                                            <Filter :size="16" />
                                            Filter
                                            <Badge v-if="biometricDateFilter !== 'all'" variant="secondary" class="ml-1">
                                                1
                                            </Badge>
                                        </Button>
                                    </PopoverTrigger>
                                    <PopoverContent class="w-80" align="end">
                                        <div class="space-y-4">
                                            <div>
                                                <h4 class="font-medium mb-2">Filter Options</h4>
                                            </div>
                                            <div class="space-y-2">
                                                <Label class="text-sm font-medium">Date Range</Label>
                                                <Select v-model="tempBiometricDateFilter">
                                                    <SelectTrigger>
                                                        <SelectValue placeholder="Filter by date" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="all">All Dates</SelectItem>
                                                        <SelectItem value="today">Today</SelectItem>
                                                        <SelectItem value="yesterday">Yesterday</SelectItem>
                                                        <SelectItem value="this-week">This Week</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            <div class="flex justify-between pt-2">
                                                <Button variant="outline" size="sm" @click="clearBiometricFilter">
                                                    Clear Filters
                                                </Button>
                                                <Button size="sm" @click="applyBiometricFilter">
                                                    Apply
                                                </Button>
                                            </div>
                                        </div>
                                    </PopoverContent>
                                </Popover>
                            </div>

                            <!-- Desktop Table View -->
                            <div class="hidden md:block relative w-full overflow-auto">
                                <table class="w-full caption-bottom text-sm">
                                    <thead class="[&_tr]:border-b">
                                        <tr class="border-b transition-colors hover:bg-muted/50">
                                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Employee Code</th>
                                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Date</th>
                                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Time</th>
                                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="[&_tr:last-child]:border-0">
                                        <tr v-for="log in paginatedBiometricLogs" :key="log.id" class="border-b transition-colors hover:bg-muted/50">
                                            <td class="p-4 align-middle font-medium">{{ log.employeeCode }}</td>
                                            <td class="p-4 align-middle">
                                                <div class="flex items-center gap-2">
                                                    <CalendarDays :size="14" class="text-muted-foreground" />
                                                    {{ log.date }}
                                                </div>
                                            </td>
                                            <td class="p-4 align-middle">
                                                <div class="flex items-center gap-2">
                                                    <Clock :size="14" class="text-muted-foreground" />
                                                    {{ log.time }}
                                                </div>
                                            </td>
                                            <td class="p-4 align-middle">
                                                <Button 
                                                    variant="ghost" 
                                                    size="sm" 
                                                    class="h-8 gap-2 text-destructive hover:text-destructive hover:bg-destructive/10"
                                                    @click="deleteBiometricLog(log.id)"
                                                >
                                                    <Trash2 :size="16" />
                                                    Delete
                                                </Button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <div v-if="filteredBiometricLogs.length > biometricItemsPerPage" class="mt-6">
                                <Pagination v-model:page="biometricCurrentPage" :items-per-page="biometricItemsPerPage" :total="filteredBiometricLogs.length" class="justify-end">
                                    <PaginationContent v-slot="{ items }">
                                        <PaginationPrevious />
                                        
                                        <template v-for="(item, index) in items" :key="index">
                                            <PaginationItem
                                                v-if="item.type === 'page'"
                                                :value="item.value"
                                                :is-active="item.value === biometricCurrentPage"
                                            >
                                                {{ item.value }}
                                            </PaginationItem>
                                            <PaginationEllipsis v-else-if="item.type === 'ellipsis'" :index="index" />
                                        </template>
                                        
                                        <PaginationNext />
                                    </PaginationContent>
                                </Pagination>
                            </div>

                            <!-- Mobile Card View -->
                            <div class="md:hidden space-y-3">
                                <div 
                                    v-for="log in paginatedBiometricLogs" 
                                    :key="log.id" 
                                    class="p-4 rounded-lg border transition-colors"
                                >
                                    <div class="flex justify-between items-start mb-3">
                                        <div class="font-semibold text-sm">{{ log.employeeCode }}</div>
                                        <Button 
                                            variant="ghost" 
                                            size="sm" 
                                            class="h-6 w-6 p-0 text-muted-foreground hover:text-destructive hover:bg-destructive/10"
                                            @click="deleteBiometricLog(log.id)"
                                        >
                                            <Trash2 :size="12" />
                                        </Button>
                                    </div>
                                    <div class="grid grid-cols-2 gap-3 text-sm">
                                        <div class="flex items-center gap-2">
                                            <CalendarDays :size="14" class="text-muted-foreground" />
                                            <span>{{ log.date }}</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <Clock :size="14" class="text-muted-foreground" />
                                            <span>{{ log.time }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>

            <!-- Details Dialog -->
            <Dialog :open="isDetailsOpen" @update:open="closeDetails">
                <DialogContent class="sm:max-w-[500px]">
                    <DialogHeader>
                        <DialogTitle>Attendance Details</DialogTitle>
                        <DialogDescription></DialogDescription>
                    </DialogHeader>
                    
                    <div v-if="selectedRecord" class="space-y-6 py-4">
                        <!-- Employee Information Section -->
                        <div class="space-y-4">
                            <div class="flex items-center gap-2 pb-2 border-b border-border">
                                <Users class="h-4 w-4 text-muted-foreground" />
                                <h4 class="text-sm font-semibold text-foreground">Employee Information</h4>
                            </div>
                            
                            <div class="grid gap-3">
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="space-y-1">
                                        <Label class="text-sm font-medium text-muted-foreground">Name</Label>
                                        <div class="text-sm font-medium">{{ selectedRecord.name }}</div>
                                    </div>
                                    <div class="space-y-1">
                                        <Label class="text-sm font-medium text-muted-foreground">Employee Code</Label>
                                        <div class="text-xs font-mono bg-muted px-2 py-1 rounded w-fit">{{ selectedRecord.employeeCode }}</div>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="space-y-1">
                                        <Label class="text-sm font-medium text-muted-foreground">Department</Label>
                                        <div class="text-sm">{{ selectedRecord.department }}</div>
                                    </div>
                                    <div class="space-y-1">
                                        <Label class="text-sm font-medium text-muted-foreground">Position</Label>
                                        <div class="text-sm">{{ selectedRecord.position }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Attendance Details Section -->
                        <div class="space-y-4">
                            <div class="flex items-center gap-2 pb-2 border-b border-border">
                                <CalendarDays class="h-4 w-4 text-muted-foreground" />
                                <h4 class="text-sm font-semibold text-foreground">Attendance Details</h4>
                            </div>
                            
                            <div class="grid gap-3">
                                <div class="space-y-1">
                                    <Label class="text-sm font-medium text-muted-foreground">Date</Label>
                                    <div class="text-sm font-medium">{{ selectedRecord.date }}</div>
                                </div>
                                
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="space-y-2">
                                        <Label class="text-xs font-medium text-muted-foreground">Time In</Label>
                                        <div v-if="!isEditing" class="text-sm font-medium text-green-600">{{ selectedRecord.timeIn || '-' }}</div>
                                        <Popover v-else>
                                            <PopoverTrigger as-child>
                                                <Button
                                                    variant="outline"
                                                    :class="['w-full justify-start text-left font-normal', !editTimeIn && 'text-muted-foreground']"
                                                >
                                                    <Clock class="mr-2 h-4 w-4 text-muted-foreground" />
                                                    {{ editTimeIn || 'Select time' }}
                                                </Button>
                                            </PopoverTrigger>
                                            <PopoverContent class="w-auto p-0">
                                                <div class="flex flex-col sm:flex-row sm:h-[300px] divide-y sm:divide-y-0 sm:divide-x">
                                                    <!-- Hours picker (12-hour) -->
                                                    <ScrollArea class="w-64 sm:w-auto">
                                                        <div class="flex sm:flex-col p-2">
                                                            <Button
                                                                v-for="hour in Array.from({ length: 12 }, (_, i) => 12 - i)"
                                                                :key="hour"
                                                                size="icon"
                                                                :variant="editTimeInHour === hour ? 'default' : 'ghost'"
                                                                class="sm:w-full shrink-0 aspect-square"
                                                                @click="editTimeInHour = hour; updateEditTimeIn()"
                                                            >
                                                                {{ hour }}
                                                            </Button>
                                                        </div>
                                                        <ScrollBar orientation="horizontal" class="sm:hidden" />
                                                    </ScrollArea>
                                                    
                                                    <!-- Minutes picker -->
                                                    <ScrollArea class="w-64 sm:w-auto">
                                                        <div class="flex sm:flex-col p-2">
                                                            <Button
                                                                v-for="minute in Array.from({ length: 60 }, (_, i) => i)"
                                                                :key="minute"
                                                                size="icon"
                                                                :variant="editTimeInMinute === minute ? 'default' : 'ghost'"
                                                                class="sm:w-full shrink-0 aspect-square"
                                                                @click="editTimeInMinute = minute; updateEditTimeIn()"
                                                            >
                                                                {{ String(minute).padStart(2, '0') }}
                                                            </Button>
                                                        </div>
                                                        <ScrollBar orientation="horizontal" class="sm:hidden" />
                                                    </ScrollArea>
                                                    
                                                    <!-- AM / PM picker -->
                                                    <ScrollArea>
                                                        <div class="flex sm:flex-col p-2">
                                                            <Button
                                                                v-for="period in ['AM', 'PM']"
                                                                :key="period"
                                                                size="icon"
                                                                :variant="editTimeInPeriod === period ? 'default' : 'ghost'"
                                                                class="sm:w-full shrink-0 aspect-square"
                                                                @click="editTimeInPeriod = period; updateEditTimeIn()"
                                                            >
                                                                {{ period }}
                                                            </Button>
                                                        </div>
                                                    </ScrollArea>
                                                </div>
                                            </PopoverContent>
                                        </Popover>
                                    </div>
                                    <div class="space-y-2">
                                        <Label class="text-xs font-medium text-muted-foreground">Time Out</Label>
                                        <div v-if="!isEditing" class="text-sm font-medium text-red-600">{{ selectedRecord.timeOut || '-' }}</div>
                                        <Popover v-else>
                                            <PopoverTrigger as-child>
                                                <Button
                                                    variant="outline"
                                                    :class="['w-full justify-start text-left font-normal', !editTimeOut && 'text-muted-foreground']"
                                                >
                                                    <Clock class="mr-2 h-4 w-4 text-muted-foreground" />
                                                    {{ editTimeOut || 'Select time' }}
                                                </Button>
                                            </PopoverTrigger>
                                            <PopoverContent class="w-auto p-0">
                                                <div class="flex flex-col sm:flex-row sm:h-[300px] divide-y sm:divide-y-0 sm:divide-x">
                                                    <!-- Hours picker (12-hour) -->
                                                    <ScrollArea class="w-64 sm:w-auto">
                                                        <div class="flex sm:flex-col p-2">
                                                            <Button
                                                                v-for="hour in Array.from({ length: 12 }, (_, i) => 12 - i)"
                                                                :key="hour"
                                                                size="icon"
                                                                :variant="editTimeOutHour === hour ? 'default' : 'ghost'"
                                                                class="sm:w-full shrink-0 aspect-square"
                                                                @click="editTimeOutHour = hour; updateEditTimeOut()"
                                                            >
                                                                {{ hour }}
                                                            </Button>
                                                        </div>
                                                        <ScrollBar orientation="horizontal" class="sm:hidden" />
                                                    </ScrollArea>
                                                    
                                                    <!-- Minutes picker -->
                                                    <ScrollArea class="w-64 sm:w-auto">
                                                        <div class="flex sm:flex-col p-2">
                                                            <Button
                                                                v-for="minute in Array.from({ length: 60 }, (_, i) => i)"
                                                                :key="minute"
                                                                size="icon"
                                                                :variant="editTimeOutMinute === minute ? 'default' : 'ghost'"
                                                                class="sm:w-full shrink-0 aspect-square"
                                                                @click="editTimeOutMinute = minute; updateEditTimeOut()"
                                                            >
                                                                {{ String(minute).padStart(2, '0') }}
                                                            </Button>
                                                        </div>
                                                        <ScrollBar orientation="horizontal" class="sm:hidden" />
                                                    </ScrollArea>
                                                    
                                                    <!-- AM / PM picker -->
                                                    <ScrollArea>
                                                        <div class="flex sm:flex-col p-2">
                                                            <Button
                                                                v-for="period in ['AM', 'PM']"
                                                                :key="period"
                                                                size="icon"
                                                                :variant="editTimeOutPeriod === period ? 'default' : 'ghost'"
                                                                class="sm:w-full shrink-0 aspect-square"
                                                                @click="editTimeOutPeriod = period; updateEditTimeOut()"
                                                            >
                                                                {{ period }}
                                                            </Button>
                                                        </div>
                                                    </ScrollArea>
                                                </div>
                                            </PopoverContent>
                                        </Popover>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Summary Section -->
                        <div class="space-y-4">
                            <div class="flex items-center gap-2 pb-2 border-b border-border">
                                <Clock class="h-4 w-4 text-muted-foreground" />
                                <h4 class="text-sm font-semibold text-foreground">Summary</h4>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div class="space-y-3">
                                    <div class="space-y-1">
                                        <Label class="text-sm font-medium text-muted-foreground">Status</Label>
                                        <div>
                                            <Badge :variant="getStatusVariant(selectedRecord.status)" :class="['capitalize', getStatusClass(selectedRecord.status)]">
                                                {{ selectedRecord.status }}
                                            </Badge>
                                        </div>
                                    </div>
                                    
                                    <div class="space-y-1">
                                        <Label class="text-sm font-medium text-muted-foreground">Hours Worked</Label>
                                        <div class="text-sm font-semibold text-blue-600">
                                            {{ (selectedRecord.timeIn && selectedRecord.timeOut) ? calculateHours(selectedRecord.timeIn, selectedRecord.timeOut) + 'h' : '-' }}
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="space-y-1">
                                    <Label class="text-sm font-medium text-muted-foreground">Remarks</Label>
                                    <div class="text-sm">
                                        {{ getAttendanceRemarks(selectedRecord) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <DialogFooter>
                        <Button variant="outline" @click="exportAttendanceDetails" v-if="!isEditing">
                            <Database :size="16" class="mr-2" />
                            Export
                        </Button>
                        <Button variant="outline" @click="isEditing ? cancelEdit() : closeDetails()">
                            {{ isEditing ? 'Cancel Edit' : 'Close' }}
                        </Button>
                        <Button v-if="!isEditing" @click="startEdit">
                            Edit 
                        </Button>
                        <Button v-else @click="saveEdit">
                            Save Changes
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Add Attendance Dialog -->
            <Dialog :open="isAddOpen" @update:open="closeAddDialog">
                <DialogContent class="sm:max-w-[500px]">
                    <DialogHeader>
                        <DialogTitle>Add Attendance</DialogTitle>
                        <DialogDescription>Add a new attendance record</DialogDescription>
                    </DialogHeader>
                    
                    <div class="grid gap-4 py-4">
                        <!-- Employee Name Combobox -->
                     
                        <div class="grid gap-2">
                            <Label class="text-sm font-medium">Employee Name <span class="text-destructive">*</span></Label>
                            
                            <Combobox v-model="selectedEmployee" by="label" class="w-full">
                                <ComboboxAnchor class="w-full">
                                    <div class="relative w-full items-center">
                                        <ComboboxInput
                                            class="pl-9 w-full"
                                            :display-value="(val: any) => val?.label ?? ''"
                                            placeholder="Select employee"
                                        />
                                        <span class="absolute start-0 inset-y-0 flex items-center justify-center px-3">
                                            <Search class="size-4 text-muted-foreground" />
                                        </span>
                                    </div>
                                </ComboboxAnchor>

                                <ComboboxList class="w-[445px]">
                                    <ComboboxEmpty>No employee found.</ComboboxEmpty>

                                    <ComboboxGroup>
                                        <ComboboxItem
                                            v-for="emp in employees"
                                            :key="emp.value"
                                            :value="emp"
                                        >
                                            {{ emp.label }}
                                            <ComboboxItemIndicator>
                                                <Check class="ml-auto h-4 w-4" />
                                            </ComboboxItemIndicator>
                                        </ComboboxItem>
                                    </ComboboxGroup>
                                </ComboboxList> 
                            </Combobox>
                        </div>


                        <div class="grid gap-2">
                            <Label class="text-sm font-medium">Date <span class="text-destructive">*</span></Label>
                            <Popover>
                                <PopoverTrigger as-child>
                                    <Button
                                        variant="outline"
                                        :class="['w-full justify-start text-left font-normal', !newAttendance.date && 'text-muted-foreground']"
                                    >
                                        <CalendarDays class="mr-2 h-4 w-4" />
                                        {{ newAttendance.date ? new Date(newAttendance.date).toLocaleDateString() : 'Pick a date' }}
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent class="w-auto p-0">
                                    <CalendarPicker v-model="newAttendance.date as any" />
                                </PopoverContent>
                            </Popover>
                        </div>
                        
                        <div class="grid gap-2">
                            <Label class="text-sm font-medium">Time In <span class="text-destructive">*</span></Label>
                            <Popover>
                                <PopoverTrigger as-child>
                                    <Button
                                        variant="outline"
                                        :class="['w-full justify-start text-left font-normal', !newAttendance.timeIn && 'text-muted-foreground']"
                                    >
                                        <Clock class="mr-2 h-4 w-4 text-muted-foreground" />
                                        {{ newAttendance.timeIn || 'Select time' }}
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent class="w-auto p-0">
                                    <div class="flex flex-col sm:flex-row sm:h-[300px] divide-y sm:divide-y-0 sm:divide-x">
                                        <!-- Hours picker (12-hour) -->
                                        <ScrollArea class="w-64 sm:w-auto">
                                            <div class="flex sm:flex-col p-2">
                                                <Button
                                                    v-for="hour in Array.from({ length: 12 }, (_, i) => 12 - i)"
                                                    :key="hour"
                                                    size="icon"
                                                    :variant="timeInHour === hour ? 'default' : 'ghost'"
                                                    class="sm:w-full shrink-0 aspect-square"
                                                    @click="timeInHour = hour; updateTimeIn()"
                                                >
                                                    {{ hour }}
                                                </Button>
                                            </div>
                                            <ScrollBar orientation="horizontal" class="sm:hidden" />
                                        </ScrollArea>
                                        
                                        <!-- Minutes picker -->
                                        <ScrollArea class="w-64 sm:w-auto">
                                            <div class="flex sm:flex-col p-2">
                                                <Button
                                                    v-for="minute in Array.from({ length: 60 }, (_, i) => i)"
                                                    :key="minute"
                                                    size="icon"
                                                    :variant="timeInMinute === minute ? 'default' : 'ghost'"
                                                    class="sm:w-full shrink-0 aspect-square"
                                                    @click="timeInMinute = minute; updateTimeIn()"
                                                >
                                                    {{ String(minute).padStart(2, '0') }}
                                                </Button>
                                            </div>
                                            <ScrollBar orientation="horizontal" class="sm:hidden" />
                                        </ScrollArea>
                                        
                                        <!-- AM / PM picker -->
                                        <ScrollArea>
                                            <div class="flex sm:flex-col p-2">
                                                <Button
                                                    v-for="period in ['AM', 'PM']"
                                                    :key="period"
                                                    size="icon"
                                                    :variant="timeInPeriod === period ? 'default' : 'ghost'"
                                                    class="sm:w-full shrink-0 aspect-square"
                                                    @click="timeInPeriod = period; updateTimeIn()"
                                                >
                                                    {{ period }}
                                                </Button>
                                            </div>
                                        </ScrollArea>
                                    </div>
                                </PopoverContent>
                            </Popover>
                        </div>
                        
                        <div class="grid gap-2">
                            <Label class="text-sm font-medium">Time Out</Label>
                            <Popover>
                                <PopoverTrigger as-child>
                                    <Button
                                        variant="outline"
                                        :class="['w-full justify-start text-left font-normal', !newAttendance.timeOut && 'text-muted-foreground']"
                                    >
                                        <Clock class="mr-2 h-4 w-4 text-muted-foreground" />
                                        {{ newAttendance.timeOut || 'Select time (Optional)' }}
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent class="w-auto p-0">
                                    <div class="flex flex-col sm:flex-row sm:h-[300px] divide-y sm:divide-y-0 sm:divide-x">
                                        <!-- Hours picker (12-hour) -->
                                        <ScrollArea class="w-64 sm:w-auto">
                                            <div class="flex sm:flex-col p-2">
                                                <Button
                                                    v-for="hour in Array.from({ length: 12 }, (_, i) => 12 - i)"
                                                    :key="hour"
                                                    size="icon"
                                                    :variant="timeOutHour === hour ? 'default' : 'ghost'"
                                                    class="sm:w-full shrink-0 aspect-square"
                                                    @click="timeOutHour = hour; updateTimeOut()"
                                                >
                                                    {{ hour }}
                                                </Button>
                                            </div>
                                            <ScrollBar orientation="horizontal" class="sm:hidden" />
                                        </ScrollArea>
                                        
                                        <!-- Minutes picker -->
                                        <ScrollArea class="w-64 sm:w-auto">
                                            <div class="flex sm:flex-col p-2">
                                                <Button
                                                    v-for="minute in Array.from({ length: 60 }, (_, i) => i)"
                                                    :key="minute"
                                                    size="icon"
                                                    :variant="timeOutMinute === minute ? 'default' : 'ghost'"
                                                    class="sm:w-full shrink-0 aspect-square"
                                                    @click="timeOutMinute = minute; updateTimeOut()"
                                                >
                                                    {{ String(minute).padStart(2, '0') }}
                                                </Button>
                                            </div>
                                            <ScrollBar orientation="horizontal" class="sm:hidden" />
                                        </ScrollArea>
                                        
                                        <!-- AM / PM picker -->
                                        <ScrollArea>
                                            <div class="flex sm:flex-col p-2">
                                                <Button
                                                    v-for="period in ['AM', 'PM']"
                                                    :key="period"
                                                    size="icon"
                                                    :variant="timeOutPeriod === period ? 'default' : 'ghost'"
                                                    class="sm:w-full shrink-0 aspect-square"
                                                    @click="timeOutPeriod = period; updateTimeOut()"
                                                >
                                                    {{ period }}
                                                </Button>
                                            </div>
                                        </ScrollArea>
                                    </div>
                                </PopoverContent>
                            </Popover>
                        </div>
                    </div>

                    <DialogFooter>  
                        <Button variant="outline" @click="closeAddDialog">Cancel</Button>
                        <Button @click="addAttendance">Add</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Repopulate Confirmation Dialog -->
            <Dialog :open="showRepopulateDialog" @update:open="closeRepopulateDialog">
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Confirm Repopulate</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to repopulate the attendance data? This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    
                    <DialogFooter>
                        <Button variant="outline" @click="closeRepopulateDialog">
                            Cancel
                        </Button>
                        <Button 
                            @click="confirmRepopulate"
                            :disabled="repopulateCountdown > 0"
                            variant="destructive"
                        >
                            {{ repopulateCountdown > 0 ? `Wait (${repopulateCountdown}s)` : 'Confirm Repopulate' }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Delete Confirmation Dialog -->
            <Dialog :open="showDeleteDialog" @update:open="closeDeleteDialog">
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Delete Attendance Record</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete this attendance record? This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    
                    <DialogFooter>
                        <Button variant="outline" @click="closeDeleteDialog">
                            Cancel
                        </Button>
                        <Button 
                            @click="confirmDelete"
                            :disabled="deleteCountdown > 0"
                            variant="destructive"
                        >
                            {{ deleteCountdown > 0 ? `Wait (${deleteCountdown}s)` : 'Delete' }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Biometric Delete Confirmation Dialog -->
            <Dialog :open="showBiometricDeleteDialog" @update:open="closeBiometricDeleteDialog">
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Delete Biometric Log</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete this biometric log? This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    
                    <DialogFooter>
                        <Button variant="outline" @click="closeBiometricDeleteDialog">
                            Cancel
                        </Button>
                        <Button 
                            @click="confirmBiometricDelete"
                            :disabled="biometricDeleteCountdown > 0"
                            variant="destructive"
                        >
                            {{ biometricDeleteCountdown > 0 ? `Wait (${biometricDeleteCountdown}s)` : 'Delete' }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Add Biometric Dialog -->
            <Dialog :open="isAddBiometricOpen" @update:open="closeAddBiometricDialog">
                <DialogContent class="sm:max-w-[500px]">
                    <DialogHeader>
                        <DialogTitle>Add Biometric Log</DialogTitle>
                        <DialogDescription>Add a new biometric scan record</DialogDescription>
                    </DialogHeader>
                    
                    <div class="grid gap-4 py-4">
                        <!-- Employee Code Combobox -->
                        <div class="grid gap-2">
                            <Label class="text-sm font-medium">Employee Code <span class="text-destructive">*</span></Label>
                            
                            <Input
                                v-model="biometricEmployeeCode"
                                placeholder="Enter employee code"
                                class="w-full"
                            />

                        </div>

                        <div class="grid gap-2">
                            <Label class="text-sm font-medium">Date <span class="text-destructive">*</span></Label>
                            <Popover>
                                <PopoverTrigger as-child>
                                    <Button
                                        variant="outline"
                                        :class="['w-full justify-start text-left font-normal', !newBiometric.date && 'text-muted-foreground']"
                                    >
                                        <CalendarDays class="mr-2 h-4 w-4" />
                                        {{ newBiometric.date ? new Date(newBiometric.date).toLocaleDateString() : 'Pick a date' }}
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent class="w-auto p-0">
                                    <CalendarPicker v-model="newBiometric.date as any" />
                                </PopoverContent>
                            </Popover>
                        </div>
                        
                        <div class="grid gap-2">
                            <Label class="text-sm font-medium">Time <span class="text-destructive">*</span></Label>
                            <Popover>
                                <PopoverTrigger as-child>
                                    <Button
                                        variant="outline"
                                        :class="['w-full justify-start text-left font-normal', !newBiometric.time && 'text-muted-foreground']"
                                    >
                                        <Clock class="mr-2 h-4 w-4 text-muted-foreground" />
                                        {{ newBiometric.time || 'Select time' }}
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent class="w-auto p-0">
                                    <div class="flex flex-col sm:flex-row sm:h-[300px] divide-y sm:divide-y-0 sm:divide-x">
                                        <!-- Hours picker (12-hour) -->
                                        <ScrollArea class="w-64 sm:w-auto">
                                            <div class="flex sm:flex-col p-2">
                                                <Button
                                                    v-for="hour in Array.from({ length: 12 }, (_, i) => 12 - i)"
                                                    :key="hour"
                                                    size="icon"
                                                    :variant="biometricTimeHour === hour ? 'default' : 'ghost'"
                                                    class="sm:w-full shrink-0 aspect-square"
                                                    @click="biometricTimeHour = hour; updateBiometricTime()"
                                                >
                                                    {{ hour }}
                                                </Button>
                                            </div>
                                            <ScrollBar orientation="horizontal" class="sm:hidden" />
                                        </ScrollArea>
                                        
                                        <!-- Minutes picker -->
                                        <ScrollArea class="w-64 sm:w-auto">
                                            <div class="flex sm:flex-col p-2">
                                                <Button
                                                    v-for="minute in Array.from({ length: 60 }, (_, i) => i)"
                                                    :key="minute"
                                                    size="icon"
                                                    :variant="biometricTimeMinute === minute ? 'default' : 'ghost'"
                                                    class="sm:w-full shrink-0 aspect-square"
                                                    @click="biometricTimeMinute = minute; updateBiometricTime()"
                                                >
                                                    {{ String(minute).padStart(2, '0') }}
                                                </Button>
                                            </div>
                                            <ScrollBar orientation="horizontal" class="sm:hidden" />
                                        </ScrollArea>
                                        
                                        <!-- AM / PM picker -->
                                        <ScrollArea>
                                            <div class="flex sm:flex-col p-2">
                                                <Button
                                                    v-for="period in ['AM', 'PM']"
                                                    :key="period"
                                                    size="icon"
                                                    :variant="biometricTimePeriod === period ? 'default' : 'ghost'"
                                                    class="sm:w-full shrink-0 aspect-square"
                                                    @click="biometricTimePeriod = period; updateBiometricTime()"
                                                >
                                                    {{ period }}
                                                </Button>
                                            </div>
                                        </ScrollArea>
                                    </div>
                                </PopoverContent>
                            </Popover>
                        </div>
                    </div>

                    <DialogFooter>  
                        <Button variant="outline" @click="closeAddBiometricDialog">Cancel</Button>
                        <Button @click="addBiometric">Add</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
