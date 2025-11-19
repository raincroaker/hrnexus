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
import { Calendar, Clock, Users, Database, Eye, Check, Search, CalendarDays, Trash2, Filter, MoreVertical, RefreshCw, Settings, Plus, Download } from 'lucide-vue-next';
import { Calendar as CalendarPicker } from '@/components/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { ScrollArea, ScrollBar } from '@/components/ui/scroll-area';
import { ref, watch, computed } from 'vue';
import { Bar, Line } from 'vue-chartjs';
import { Chart as ChartJS, CategoryScale, LinearScale, BarElement, LineElement, PointElement, Title, Tooltip, Legend } from 'chart.js';
import api from '@/lib/axios';
import { toast } from 'vue-sonner';
import {
  Pagination,
  PaginationContent,
  PaginationEllipsis,
  PaginationItem,
  PaginationNext,
  PaginationPrevious,
} from "@/components/ui/pagination"

ChartJS.register(CategoryScale, LinearScale, BarElement, LineElement, PointElement, Title, Tooltip, Legend);

const DEFAULT_REQUIRED_TIME_IN = '08:00';
const DEFAULT_REQUIRED_TIME_OUT = '22:00';

const normalizeBoolean = (value: unknown): boolean => {
    if (typeof value === 'boolean') {
        return value;
    }

    if (value === null || value === undefined) {
        return false;
    }

    if (typeof value === 'number') {
        return value === 1;
    }

    if (typeof value === 'string') {
        const normalized = value.trim().toLowerCase();
        if (['true', '1', 'on', 'yes'].includes(normalized)) {
            return true;
        }
        if (['false', '0', 'off', 'no'].includes(normalized)) {
            return false;
        }
    }

    return Boolean(value);
};

type PossibleDateValue = string | Date | { toDate?: (timeZone?: string) => Date } | { year?: number; month?: number; day?: number } | null | undefined;

const hasToDate = (value: unknown): value is { toDate: (timeZone?: string) => Date } => {
    return Boolean(value) && typeof (value as { toDate?: (timeZone?: string) => Date }).toDate === 'function';
};

const hasYearMonthDay = (value: unknown): value is { year: number; month: number; day: number } => {
    if (! value || typeof value !== 'object') {
        return false;
    }

    const candidate = value as { year?: number; month?: number; day?: number };

    return (
        typeof candidate.year === 'number'
        && typeof candidate.month === 'number'
        && typeof candidate.day === 'number'
    );
};

const resolveTimezone = (): string => {
    try {
        return Intl.DateTimeFormat().resolvedOptions().timeZone ?? 'UTC';
    } catch {
        return 'UTC';
    }
};

const coerceDate = (value: PossibleDateValue): Date | null => {
    console.log('[coerceDate] Input:', value, 'Type:', typeof value, 'Is Date:', value instanceof Date);
    
    if (! value) {
        console.log('[coerceDate] Value is falsy, returning null');
        return null;
    }

    if (value instanceof Date) {
        const result = Number.isNaN(value.getTime()) ? null : value;
        console.log('[coerceDate] Date instance, result:', result);
        return result;
    }

    if (typeof value === 'string') {
        const parsed = new Date(value);
        const result = Number.isNaN(parsed.getTime()) ? null : parsed;
        console.log('[coerceDate] String input, parsed:', parsed, 'result:', result);
        return result;
    }

    if (typeof value === 'object') {
        if (hasToDate(value)) {
            try {
                const result = value.toDate(resolveTimezone());
                console.log('[coerceDate] Object with toDate(), result:', result);
                return result;
            } catch {
                const result = value.toDate('UTC');
                console.log('[coerceDate] Object with toDate() (fallback to UTC), result:', result);
                return result;
            }
        }

        if (hasYearMonthDay(value)) {
            const result = new Date(value.year, value.month - 1, value.day);
            console.log('[coerceDate] Object with year/month/day:', { year: value.year, month: value.month, day: value.day }, 'result:', result);
            return result;
        }
        
        console.log('[coerceDate] Object but no recognized format, returning null');
    }

    console.log('[coerceDate] No match, returning null');
    return null;
};

const formatDateDisplay = (value?: PossibleDateValue): string => {
    const date = coerceDate(value);

    if (! date) {
        return '';
    }

    return date.toLocaleDateString();
};

const formatDateToYmd = (value: PossibleDateValue): string => {
    console.log('[formatDateToYmd] Input value:', value, 'Type:', typeof value, 'Constructor:', value?.constructor?.name);
    
    if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value)) {
        console.log('[formatDateToYmd] Already in Y-m-d format, returning:', value);
        return value;
    }

    const date = coerceDate(value);
    console.log('[formatDateToYmd] Coerced date:', date);

    if (! date) {
        console.log('[formatDateToYmd] Failed to coerce date, returning empty string');
        return '';
    }

    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const result = `${year}-${month}-${day}`;
    
    console.log('[formatDateToYmd] Formatted result:', result, 'from date:', date.toISOString());
    return result;
};

const extractDateParts = (value?: string | null): { year: string; month: string } | null => {
    if (! value) {
        return null;
    }

    const isoMatch = value.match(/^(\d{4})-(\d{2})/);
    if (isoMatch) {
        return {
            year: isoMatch[1],
            month: isoMatch[2],
        };
    }

    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) {
        return null;
    }

    return {
        year: String(parsed.getFullYear()),
        month: String(parsed.getMonth() + 1).padStart(2, '0'),
    };
};

interface AttendanceRecordBackend {
    id: number;
    employee_id: number;
    employee_code?: string | null;
    name?: string | null;
    department?: string | null;
    department_id?: number | null;
    position?: string | null;
    position_id?: number | null;
    date: string;
    time_in: string | null;
    time_out: string | null;
    total_hours: number | null;
    status: string;
    remarks?: string | null;
}

interface AttendanceRecord {
    id: number;
    employeeId: number;
    employeeCode: string;
    name: string;
    department: string;
    departmentId: number | null;
    position: string;
    positionId: number | null;
    date: string;
    timeIn: string | null;
    timeOut: string | null;
    totalHours: number | null;
    status: string;
    remarks: string;
}

interface EmployeeBackend {
    id: number;
    employee_code: string;
    name: string;
}

interface EmployeeOption {
    id: number;
    employee_code: string;
    label: string;
}

interface AttendanceSettingsPayload {
    id: number | null;
    required_time_in: string | null;
    required_time_out: string | null;
    break_duration_minutes: number;
    break_is_counted: boolean;
}

interface BiometricLogBackend {
    id: number;
    employee_code: string;
    date: string;
    time: string;
    scan_time?: string;
}

interface BiometricLogRecord {
    id: number;
    employeeCode: string;
    date: string;
    time: string;
    scanTime: string;
}

interface CurrentUserBackend {
    id: number;
    employee_code: string;
    name: string;
    first_name: string;
    last_name: string;
    department: string;
    department_id: number | null;
    position: string;
    position_id: number | null;
    role: 'employee' | 'department_manager' | 'admin';
    email: string;
    contact_number: string | null;
    birth_date: string | null;
    avatar: string | null;
}

const props = defineProps<{
    currentUser: CurrentUserBackend;
    attendanceSettings: AttendanceSettingsPayload | null;
    myAttendance: AttendanceRecordBackend[];
    canManageAll: boolean;
    teamAttendance?: AttendanceRecordBackend[];
    employees?: EmployeeBackend[];
    biometricLogs?: BiometricLogBackend[];
}>();

const canManageAll = computed(() => props.canManageAll);

const transformAttendanceRecord = (record: AttendanceRecordBackend): AttendanceRecord => ({
    id: record.id,
    employeeId: record.employee_id,
    employeeCode: record.employee_code ?? '',
    name: record.name ?? '',
    department: record.department ?? '',
    departmentId: record.department_id ?? null,
    position: record.position ?? '',
    positionId: record.position_id ?? null,
    date: record.date,
    timeIn: record.time_in,
    timeOut: record.time_out,
    totalHours: record.total_hours,
    status: record.status,
    remarks: record.remarks ?? '',
});

const transformEmployeeOption = (employee: EmployeeBackend): EmployeeOption => ({
    id: employee.id,
    employee_code: employee.employee_code,
    label: employee.name,
});

const transformBiometricLog = (log: BiometricLogBackend): BiometricLogRecord => ({
    id: log.id,
    employeeCode: log.employee_code,
    date: log.date,
    time: log.time,
    scanTime: log.scan_time ?? `${log.date} ${log.time}`,
});

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Attendance',
        href: '/attendance',
    },
];

const myAttendance = ref<AttendanceRecord[]>((props.myAttendance ?? []).map(transformAttendanceRecord));

// Function to calculate hours worked
const calculateHours = (timeIn: string, timeOut: string): number => {
    const parseTime = (time: string): number => {
        if (!time) {
            return 0;
        }

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

        const [hourString, minuteString = '0', secondString = '0'] = normalized.split(':');
        const hours = Number(hourString);
        const minutes = Number(minuteString);
        const seconds = Number(secondString);

        return hours + minutes / 60 + seconds / 3600;
    };
    
    const inHours = parseTime(timeIn);
    const outHours = parseTime(timeOut);
    const total = Math.max(outHours - inHours, 0);
    return parseFloat(total.toFixed(2));
};

const formatToTwentyFourHour = (hour: number, minute: number, period: string): string => {
    const sanitizedHour = period === 'PM' ? (hour % 12) + 12 : hour % 12;

    return `${String(sanitizedHour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`;
};

const formatDisplayTime = (time?: string | null): string => {
    if (!time) {
        return '';
    }

    const normalized = time.trim();
    if (/am|pm/i.test(normalized)) {
        return normalized.toUpperCase();
    }

    const [hourString, minuteString = '00'] = normalized.split(':');
    const hour = parseInt(hourString, 10);
    const minutes = minuteString.padStart(2, '0');
    if (Number.isNaN(hour)) {
        return normalized;
    }

    const period = hour >= 12 ? 'PM' : 'AM';
    const displayHour = hour % 12 === 0 ? 12 : hour % 12;

    return `${String(displayHour).padStart(2, '0')}:${minutes} ${period}`;
};

const getHoursWorked = (record: AttendanceRecord): number | null => {
    if (record.totalHours !== null && record.totalHours !== undefined) {
        return record.totalHours;
    }

    if (record.timeIn && record.timeOut) {
        return calculateHours(record.timeIn, record.timeOut);
    }

    return null;
};

const formatHoursWorked = (record: AttendanceRecord): string => {
    const hours = getHoursWorked(record);
    return hours !== null ? `${hours}h` : '-';
};

// Chart data for My Attendance
const myAttendanceChartData = computed(() => {
    const data = myAttendance.value.map(record => {
        const hours = getHoursWorked(record);
        const status = record.status.toLowerCase();
        // Show incomplete records as 0.5h so they're visible but clearly incomplete
        if (status === 'incomplete' && (hours === null || hours === 0)) {
            return 0.5;
        }
        return hours ?? 0;
    });

    return {
        labels: myAttendance.value.map(record => record.date),
        datasets: [{
            label: 'Hours Worked',
            data,
            backgroundColor: myAttendance.value.map(record => {
                const status = record.status.toLowerCase();
                if (status === 'present') return 'rgba(34, 197, 94, 0.8)';
                if (status === 'late') return 'rgba(249, 115, 22, 0.8)';
                return 'rgba(239, 68, 68, 0.8)';
            }),
            borderColor: myAttendance.value.map(record => {
                const status = record.status.toLowerCase();
                if (status === 'present') return '#16a34a';
                if (status === 'late') return '#ea580c';
                return '#dc2626';
            }),
            borderWidth: 2,
            borderRadius: 8,
            borderSkipped: false,
            hoverBackgroundColor: myAttendance.value.map(record => {
                const status = record.status.toLowerCase();
                if (status === 'present') return 'rgba(34, 197, 94, 1)';
                if (status === 'late') return 'rgba(249, 115, 22, 1)';
                return 'rgba(239, 68, 68, 1)';
            }),
            hoverBorderWidth: 3,
        }]
    };
});

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
                    const hoursWorked = getHoursWorked(record);
                    const status = record.status.toLowerCase();
                    const isIncomplete = status === 'incomplete' && (hoursWorked === null || hoursWorked === 0);
                    
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
                        `Hours Worked: ${hoursWorked ?? 0}h`,
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
        filtered = filtered.filter(record => normalizeStatus(record.status) === myAttendanceStatusFilter.value);
    }
    
    // Date range filter
    if (myAttendanceStartDate.value) {
        filtered = filtered.filter(record => record.date >= myAttendanceStartDate.value!);
    }
    if (myAttendanceEndDate.value) {
        filtered = filtered.filter(record => record.date <= myAttendanceEndDate.value!);
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
        filtered = filtered.filter(record => normalizeStatus(record.status) === teamAttendanceStatusFilter.value);
    }
    
    // Department filter
    if (teamAttendanceDepartmentFilter.value !== 'all') {
        filtered = filtered.filter(record => record.department === teamAttendanceDepartmentFilter.value);
    }
    
    // Date range filter
    if (teamAttendanceStartDate.value) {
        filtered = filtered.filter(record => record.date >= teamAttendanceStartDate.value!);
    }
    if (teamAttendanceEndDate.value) {
        filtered = filtered.filter(record => record.date <= teamAttendanceEndDate.value!);
    }
    
    return filtered;
});

const teamDepartments = computed(() => {
    const departments = teamAttendance.value
        .map(record => record.department)
        .filter((dept): dept is string => Boolean(dept));

    return [...new Set(departments)].sort();
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
    
    // Date range filter
    if (biometricStartDate.value) {
        filtered = filtered.filter(log => log.date >= biometricStartDate.value!);
    }
    if (biometricEndDate.value) {
        filtered = filtered.filter(log => log.date <= biometricEndDate.value!);
    }
    
    return filtered;
});

// Paginated biometric logs
const paginatedBiometricLogs = computed(() => {
    const start = (biometricCurrentPage.value - 1) * biometricItemsPerPage;
    const end = start + biometricItemsPerPage;
    return filteredBiometricLogs.value.slice(start, end);
});

const teamAttendance = ref<AttendanceRecord[]>((props.teamAttendance ?? []).map(transformAttendanceRecord));

// Function to generate dynamic remarks based on time data
const getAttendanceRemarks = (record: AttendanceRecord) => {
    if (record.remarks) {
        return record.remarks;
    }

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

const exportMonthOptions = [
    { value: '01', label: 'January' },
    { value: '02', label: 'February' },
    { value: '03', label: 'March' },
    { value: '04', label: 'April' },
    { value: '05', label: 'May' },
    { value: '06', label: 'June' },
    { value: '07', label: 'July' },
    { value: '08', label: 'August' },
    { value: '09', label: 'September' },
    { value: '10', label: 'October' },
    { value: '11', label: 'November' },
    { value: '12', label: 'December' },
] as const;

const getCurrentMonth = (): string => String(new Date().getMonth() + 1).padStart(2, '0');
const getCurrentYear = (): string => String(new Date().getFullYear());

const isExportDialogOpen = ref(false);
const exportMonth = ref<string>(getCurrentMonth());
const exportYear = ref<string>(getCurrentYear());

const myAttendanceYears = computed<string[]>(() => {
    const years = new Set<string>();

    myAttendance.value.forEach(record => {
        const parts = extractDateParts(record.date);
        if (parts) {
            years.add(parts.year);
        }
    });

    const currentYear = getCurrentYear();
    if (!years.size) {
        years.add(currentYear);
    } else if (!years.has(currentYear)) {
        years.add(currentYear);
    }

    return Array.from(years).sort((a, b) => Number(b) - Number(a));
});

const filterMyAttendanceForExport = (month: string, year: string): AttendanceRecord[] => {
    return myAttendance.value.filter(record => {
        const parts = extractDateParts(record.date);
        if (!parts) {
            return false;
        }

        return parts.month === month && parts.year === year;
    });
};

const escapeCsvValue = (value: string | number | null | undefined): string => {
    const normalized = value === null || value === undefined ? '' : String(value);

    if (/[",\n]/.test(normalized)) {
        return `"${normalized.replace(/"/g, '""')}"`;
    }

    return normalized;
};

const buildMyAttendanceCsv = (records: AttendanceRecord[]): string => {
    const headers = [
        'Employee Code',
        'Name',
        'Department',
        'Position',
        'Date',
        'Time In',
        'Time Out',
        'Total Hours',
        'Status',
        'Remarks',
    ];

    const rows = records.map(record => {
        const values = [
            record.employeeCode,
            record.name,
            record.department,
            record.position,
            record.date,
            formatDisplayTime(record.timeIn) || '',
            formatDisplayTime(record.timeOut) || '',
            formatHoursWorked(record),
            record.status,
            getAttendanceRemarks(record),
        ];

        return values.map(escapeCsvValue).join(',');
    });

    return [headers.map(escapeCsvValue).join(','), ...rows].join('\n');
};

const triggerCsvDownload = (csvContent: string, fileName: string): void => {
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');

    link.setAttribute('href', url);
    link.setAttribute('download', fileName);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
};

const openExportDialog = () => {
    const preferredYear = getCurrentYear();
    exportMonth.value = getCurrentMonth();
    exportYear.value = myAttendanceYears.value.includes(preferredYear)
        ? preferredYear
        : (myAttendanceYears.value[0] ?? preferredYear);
    isExportDialogOpen.value = true;
};

const closeExportDialog = () => {
    isExportDialogOpen.value = false;
};

const confirmMyAttendanceExport = () => {
    if (!exportMonth.value || !exportYear.value) {
        toast.error('Please select both a month and a year.');
        return;
    }

    const records = filterMyAttendanceForExport(exportMonth.value, exportYear.value);

    if (records.length === 0) {
        toast.error('No attendance records found for the selected month and year.');
        return;
    }

    const csvContent = buildMyAttendanceCsv(records);
    const fileName = `my_attendance_${exportYear.value}_${exportMonth.value}.csv`;
    triggerCsvDownload(csvContent, fileName);
    toast.success('Your attendance export is being downloaded.');
    closeExportDialog();
};

// All Attendance Chart - Daily status breakdown
const teamChartData = computed(() => {
    // Get unique dates from attendance data
    const dates = [...new Set(teamAttendance.value.map(emp => emp.date))].sort();
    
    const statusCounts = dates.map(date => {
        const dayEmployees = teamAttendance.value.filter(emp => emp.date === date);
        return {
            present: dayEmployees.filter(emp => normalizeStatus(emp.status) === 'present').length,
            late: dayEmployees.filter(emp => normalizeStatus(emp.status) === 'late').length,
            incomplete: dayEmployees.filter(emp => normalizeStatus(emp.status) === 'incomplete').length,
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
            display: false
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
const selectedRecord = ref<AttendanceRecord | null>(null);
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

const isSavingEdit = ref(false);

const openDetails = (record: AttendanceRecord) => {
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

const confirmDelete = async () => {
    if (recordToDelete.value === null) {
        return;
    }

    try {
        await api.delete(`/attendance/${recordToDelete.value}`);
        toast.success('Attendance record deleted successfully.');

        // Remove from local state
        const index = teamAttendance.value.findIndex(record => record.id === recordToDelete.value);
        if (index !== -1) {
            teamAttendance.value.splice(index, 1);
        }

        // Also remove from myAttendance if it exists there
        const myIndex = myAttendance.value.findIndex(record => record.id === recordToDelete.value);
        if (myIndex !== -1) {
            myAttendance.value.splice(myIndex, 1);
        }
    } catch {
        toast.error('Unable to delete attendance record.');
    } finally {
        closeDeleteDialog();
    }
};

const updateEditTimeIn = () => {
    if (editTimeInHour.value !== null && editTimeInMinute.value !== null) {
        editTimeIn.value = formatToTwentyFourHour(
            editTimeInHour.value,
            editTimeInMinute.value,
            editTimeInPeriod.value,
        );
    }
};

const updateEditTimeOut = () => {
    if (editTimeOutHour.value !== null && editTimeOutMinute.value !== null) {
        editTimeOut.value = formatToTwentyFourHour(
            editTimeOutHour.value,
            editTimeOutMinute.value,
            editTimeOutPeriod.value,
        );
    }
};

const initializeTimePicker = (
    time: string | null,
    hourRef: { value: number | null },
    minuteRef: { value: number | null },
    periodRef: { value: string },
): void => {
    if (!time) {
        hourRef.value = null;
        minuteRef.value = null;
        periodRef.value = 'AM';
        return;
    }

    const displayTime = formatDisplayTime(time);
    const match = displayTime.match(/(\d+):(\d+)\s*(AM|PM)/i);
    if (match) {
        hourRef.value = parseInt(match[1], 10);
        minuteRef.value = parseInt(match[2], 10);
        periodRef.value = match[3].toUpperCase();
    }
};

const startEdit = () => {
    isEditing.value = true;
    editTimeIn.value = selectedRecord.value?.timeIn || '';
    editTimeOut.value = selectedRecord.value?.timeOut || '';
    
    initializeTimePicker(editTimeIn.value, editTimeInHour, editTimeInMinute, editTimeInPeriod);
    initializeTimePicker(editTimeOut.value, editTimeOutHour, editTimeOutMinute, editTimeOutPeriod);
};

const saveEdit = async () => {
    if (isSavingEdit.value || !selectedRecord.value) {
        return;
    }

    // Convert times to 24-hour format (HH:mm) for API
    const timeIn24 = (editTimeIn.value && editTimeInHour.value !== null && editTimeInMinute.value !== null) 
        ? formatToTwentyFourHour(
            editTimeInHour.value,
            editTimeInMinute.value,
            editTimeInPeriod.value
        ) 
        : null;
    
    const timeOut24 = (editTimeOut.value && editTimeOutHour.value !== null && editTimeOutMinute.value !== null)
        ? formatToTwentyFourHour(
            editTimeOutHour.value,
            editTimeOutMinute.value,
            editTimeOutPeriod.value
        )
        : null;

    // Extract HH:mm from the 24-hour format (remove seconds if present)
    const timeInFormatted = timeIn24 ? timeIn24.substring(0, 5) : null;
    const timeOutFormatted = timeOut24 ? timeOut24.substring(0, 5) : null;

    const payload: any = {};
    if (timeInFormatted !== null) {
        payload.time_in = timeInFormatted;
    } else {
        payload.time_in = null;
    }
    if (timeOutFormatted !== null) {
        payload.time_out = timeOutFormatted;
    } else {
        payload.time_out = null;
    }

    try {
        isSavingEdit.value = true;
        const response = await api.put(`/attendance/${selectedRecord.value.id}`, payload);
        const data = response.data;
        toast.success(data.message ?? 'Attendance record updated successfully.');

        // Update local state with updated attendance record
        if (data.attendance) {
            const transformedAttendance = transformAttendanceRecord(data.attendance);
            
            // Update teamAttendance if it exists
            if (canManageAll.value) {
                const index = teamAttendance.value.findIndex(record => record.id === transformedAttendance.id);
                if (index !== -1) {
                    teamAttendance.value.splice(index, 1, transformedAttendance);
                }
            }

            // Update myAttendance if it's the current user's record
            const myIndex = myAttendance.value.findIndex(record => record.id === transformedAttendance.id);
            if (myIndex !== -1) {
                myAttendance.value.splice(myIndex, 1, transformedAttendance);
            }

            // Update selectedRecord to reflect changes
            selectedRecord.value = transformedAttendance;
        }

        isEditing.value = false;
    } catch (error: any) {
        if (error.response?.status === 422) {
            const messages = error.response.data.message;
            toast.error(messages || 'Validation error. Please check your input.');
        } else {
            toast.error('Unable to update attendance record.');
        }
    } finally {
        isSavingEdit.value = false;
    }
};

const cancelEdit = () => {
    isEditing.value = false;
};

// Employee details export dialog state
const isEmployeeExportDialogOpen = ref(false);
const employeeExportMonth = ref<string>(getCurrentMonth());
const employeeExportYear = ref<string>(getCurrentYear());

const getEmployeeAttendanceYears = (employeeCode: string): string[] => {
    const years = new Set<string>();
    const employeeRecords = teamAttendance.value.filter(record => 
        record.employeeCode === employeeCode
    );

    employeeRecords.forEach(record => {
        const parts = extractDateParts(record.date);
        if (parts) {
            years.add(parts.year);
        }
    });

    const currentYear = getCurrentYear();
    if (!years.size) {
        years.add(currentYear);
    } else if (!years.has(currentYear)) {
        years.add(currentYear);
    }

    return Array.from(years).sort((a, b) => Number(b) - Number(a));
};

const employeeExportYears = computed<string[]>(() => {
    const record = selectedRecord.value;
    if (!record) {
        return [getCurrentYear()];
    }

    return getEmployeeAttendanceYears(record.employeeCode);
});

const filterEmployeeAttendanceForExport = (employeeCode: string, month: string, year: string): AttendanceRecord[] => {
    return teamAttendance.value.filter(record => {
        if (record.employeeCode !== employeeCode) {
            return false;
        }

        const parts = extractDateParts(record.date);
        if (!parts) {
            return false;
        }

        return parts.month === month && parts.year === year;
    });
};

const buildEmployeeAttendanceCsv = (records: AttendanceRecord[]): string => {
    const headers = [
        'Employee Code',
        'Name',
        'Department',
        'Position',
        'Date',
        'Time In',
        'Time Out',
        'Total Hours',
        'Status',
        'Remarks',
    ];

    const rows = records.map(record => {
        const values = [
            record.employeeCode,
            record.name,
            record.department,
            record.position,
            record.date,
            formatDisplayTime(record.timeIn) || '',
            formatDisplayTime(record.timeOut) || '',
            formatHoursWorked(record),
            record.status,
            getAttendanceRemarks(record),
        ];

        return values.map(escapeCsvValue).join(',');
    });

    return [headers.map(escapeCsvValue).join(','), ...rows].join('\n');
};

const openEmployeeExportDialog = () => {
    const record = selectedRecord.value;
    if (!record) {
        return;
    }

    const preferredYear = getCurrentYear();
    employeeExportMonth.value = getCurrentMonth();
    const availableYears = getEmployeeAttendanceYears(record.employeeCode);
    employeeExportYear.value = availableYears.includes(preferredYear)
        ? preferredYear
        : (availableYears[0] ?? preferredYear);
    isEmployeeExportDialogOpen.value = true;
};

const closeEmployeeExportDialog = () => {
    isEmployeeExportDialogOpen.value = false;
};

const confirmEmployeeExport = () => {
    const record = selectedRecord.value;
    if (!record) {
        return;
    }

    if (!employeeExportMonth.value || !employeeExportYear.value) {
        toast.error('Please select both a month and a year.');
        return;
    }

    const records = filterEmployeeAttendanceForExport(
        record.employeeCode,
        employeeExportMonth.value,
        employeeExportYear.value
    );

    if (records.length === 0) {
        toast.error('No attendance records found for the selected month and year.');
        return;
    }

    const csvContent = buildEmployeeAttendanceCsv(records);
    const fileName = `attendance_${record.employeeCode}_${employeeExportYear.value}_${employeeExportMonth.value}.csv`;
    triggerCsvDownload(csvContent, fileName);
    toast.success('Attendance export is being downloaded.');
    closeEmployeeExportDialog();
};

const exportAttendanceDetails = () => {
    openEmployeeExportDialog();
};

// Add dialog state
const isAddOpen = ref(false);
const selectedEmployee = ref<EmployeeOption | null>(null);
const newAttendance = ref({
    employee_id: null as number | null,
    date: '',
    time_in: '',
    time_out: '',
    status: 'Present',
    remarks: '',
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
    if (timeInHour.value === null || timeInMinute.value === null) {
        return;
    }

    newAttendance.value.time_in = formatToTwentyFourHour(
        timeInHour.value,
        timeInMinute.value,
        timeInPeriod.value,
    );
};

const updateTimeOut = () => {
    if (timeOutHour.value === null || timeOutMinute.value === null) {
        return;
    }

    newAttendance.value.time_out = formatToTwentyFourHour(
        timeOutHour.value,
        timeOutMinute.value,
        timeOutPeriod.value,
    );
};

// Combobox state
// Watch for employee selection
watch(selectedEmployee, (newVal) => {
    if (newVal) {
        newAttendance.value.employee_id = newVal.id;
    } else {
        newAttendance.value.employee_id = null;
    }
});

// Employee list for dropdown
const employees = ref<EmployeeOption[]>((props.employees ?? []).map(transformEmployeeOption));

// Repopulate confirmation state
const showRepopulateDialog = ref(false);
const repopulateCountdown = ref(5);
const repopulatePassword = ref('');
const isSyncing = ref(false);
let countdownInterval: ReturnType<typeof setInterval> | null = null;

const isAttendanceSettingsOpen = ref(false);
const attendanceSettingsForm = ref({
    id: props.attendanceSettings?.id ?? null,
    required_time_in: props.attendanceSettings?.required_time_in ?? DEFAULT_REQUIRED_TIME_IN,
    required_time_out: props.attendanceSettings?.required_time_out ?? DEFAULT_REQUIRED_TIME_OUT,
    break_duration_minutes: props.attendanceSettings?.break_duration_minutes ?? 0,
    break_is_counted: normalizeBoolean(props.attendanceSettings?.break_is_counted ?? false),
    password: '',
});
const isSavingAttendanceSettings = ref(false);
const attendanceSettingsTimeInHour = ref<number | null>(null);
const attendanceSettingsTimeInMinute = ref<number | null>(null);
const attendanceSettingsTimeInPeriod = ref<string>('AM');
const attendanceSettingsTimeOutHour = ref<number | null>(null);
const attendanceSettingsTimeOutMinute = ref<number | null>(null);
const attendanceSettingsTimeOutPeriod = ref<string>('AM');

if (attendanceSettingsForm.value.required_time_in) {
    initializeTimePicker(
        attendanceSettingsForm.value.required_time_in,
        attendanceSettingsTimeInHour,
        attendanceSettingsTimeInMinute,
        attendanceSettingsTimeInPeriod
    );
}

if (attendanceSettingsForm.value.required_time_out) {
    initializeTimePicker(
        attendanceSettingsForm.value.required_time_out,
        attendanceSettingsTimeOutHour,
        attendanceSettingsTimeOutMinute,
        attendanceSettingsTimeOutPeriod
    );
}

watch(
    () => attendanceSettingsForm.value.break_is_counted,
    (value) => {
        const normalized = normalizeBoolean(value);
        if (value !== normalized) {
            attendanceSettingsForm.value.break_is_counted = normalized;
        }
    }
);

const openRepopulateDialog = () => {
    if (!canManageAll.value) {
        return;
    }

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
    repopulatePassword.value = '';
    if (countdownInterval) {
        clearInterval(countdownInterval);
        countdownInterval = null;
    }
};

const confirmRepopulate = async () => {
    if (isSyncing.value || !repopulatePassword.value) {
        if (!repopulatePassword.value) {
            toast.error('Password is required to sync attendance.');
        }
        return;
    }

    try {
        isSyncing.value = true;
        const response = await api.post('/attendance/sync', {
            password: repopulatePassword.value,
        });
        const data = response.data;
        
        const messages = [];
        if (data.created > 0) {
            messages.push(`${data.created} created`);
        }
        if (data.updated > 0) {
            messages.push(`${data.updated} updated`);
        }
        if (data.deleted > 0) {
            messages.push(`${data.deleted} deleted`);
        }
        
        const summary = messages.length > 0 
            ? `Sync completed! ${messages.join(', ')}.`
            : 'Sync completed! No changes needed.';
        
        toast.success(summary);

        // Refresh the page data by reloading
        // In a real app, you might want to refetch the data instead
        window.location.reload();
    } catch (error: any) {
        if (error.response?.status === 422) {
            const messages = error.response.data.message;
            toast.error(messages || 'Password is incorrect.');
        } else {
            toast.error('Unable to sync attendance data.');
        }
    } finally {
        isSyncing.value = false;
        repopulatePassword.value = '';
        closeRepopulateDialog();
    }
};

const openAttendanceSettingsDialog = () => {
    if (!canManageAll.value) {
        return;
    }
    attendanceSettingsForm.value.password = '';
    initializeTimePicker(
        attendanceSettingsForm.value.required_time_in,
        attendanceSettingsTimeInHour,
        attendanceSettingsTimeInMinute,
        attendanceSettingsTimeInPeriod
    );
    initializeTimePicker(
        attendanceSettingsForm.value.required_time_out,
        attendanceSettingsTimeOutHour,
        attendanceSettingsTimeOutMinute,
        attendanceSettingsTimeOutPeriod
    );
    isAttendanceSettingsOpen.value = true;
};

const closeAttendanceSettingsDialog = () => {
    isAttendanceSettingsOpen.value = false;
    attendanceSettingsForm.value.password = '';
};

const saveAttendanceSettings = async () => {
    if (isSavingAttendanceSettings.value) {
        return;
    }

    if (!attendanceSettingsForm.value.password) {
        toast.error('Password is required to update attendance settings.');
        return;
    }

    const payload = {
        required_time_in: attendanceSettingsForm.value.required_time_in,
        required_time_out: attendanceSettingsForm.value.required_time_out,
        break_duration_minutes: attendanceSettingsForm.value.break_duration_minutes,
        break_is_counted: normalizeBoolean(attendanceSettingsForm.value.break_is_counted),
        password: attendanceSettingsForm.value.password,
    };
    console.log('[Attendance] saving payload', payload);

    const hasExistingRecord = Boolean(attendanceSettingsForm.value.id);
    const url = hasExistingRecord
        ? `/attendance-settings/${attendanceSettingsForm.value.id}`
        : '/attendance-settings';

    try {
        isSavingAttendanceSettings.value = true;
        const response = hasExistingRecord
            ? await api.put(url, payload)
            : await api.post(url, payload);

        const data = response.data;
        attendanceSettingsForm.value = {
            id: data.id,
            required_time_in: data.required_time_in,
            required_time_out: data.required_time_out,
            break_duration_minutes: data.break_duration_minutes,
            break_is_counted: normalizeBoolean(data.break_is_counted),
            password: '',
        };

        initializeTimePicker(
            attendanceSettingsForm.value.required_time_in,
            attendanceSettingsTimeInHour,
            attendanceSettingsTimeInMinute,
            attendanceSettingsTimeInPeriod
        );

        initializeTimePicker(
            attendanceSettingsForm.value.required_time_out,
            attendanceSettingsTimeOutHour,
            attendanceSettingsTimeOutMinute,
            attendanceSettingsTimeOutPeriod
        );

        toast.success('Attendance settings updated.');
        closeAttendanceSettingsDialog();
    } catch (error: any) {
        if (error.response?.data?.message) {
            toast.error(error.response.data.message);
        } else if (error.response?.data?.errors) {
            const messages = Object.values(error.response.data.errors)
                .flat()
                .join('\n');
            toast.error(messages || 'Unable to update attendance settings.');
        } else {
            toast.error('Unable to update attendance settings.');
        }
    } finally {
        isSavingAttendanceSettings.value = false;
    }
};

const updateAttendanceSettingsTimeIn = (): void => {
    if (attendanceSettingsTimeInHour.value === null || attendanceSettingsTimeInMinute.value === null) {
        return;
    }

    attendanceSettingsForm.value.required_time_in = formatToTwentyFourHour(
        attendanceSettingsTimeInHour.value,
        attendanceSettingsTimeInMinute.value,
        attendanceSettingsTimeInPeriod.value,
    );
};

const updateAttendanceSettingsTimeOut = (): void => {
    if (attendanceSettingsTimeOutHour.value === null || attendanceSettingsTimeOutMinute.value === null) {
        return;
    }

    attendanceSettingsForm.value.required_time_out = formatToTwentyFourHour(
        attendanceSettingsTimeOutHour.value,
        attendanceSettingsTimeOutMinute.value,
        attendanceSettingsTimeOutPeriod.value,
    );
};

const openAddDialog = () => {
    if (!canManageAll.value) {
        return;
    }

    isAddOpen.value = true;
};

const closeAddDialog = () => {
    isAddOpen.value = false;
    newAttendance.value = {
        employee_id: null,
        date: '',
        time_in: '',
        time_out: '',
        status: 'Present',
        remarks: '',
    };
    selectedEmployee.value = null;
    timeInHour.value = null;
    timeInMinute.value = null;
    timeInPeriod.value = 'AM';
    timeOutHour.value = null;
    timeOutMinute.value = null;
    timeOutPeriod.value = 'AM';
};

const isSavingAttendance = ref(false);

const addAttendance = async () => {
    if (isSavingAttendance.value) {
        return;
    }

    // Validate required fields
    if (!selectedEmployee.value) {
        toast.error('Please select an employee.');
        return;
    }

    if (!newAttendance.value.date) {
        toast.error('Please select a date.');
        return;
    }

    if (!newAttendance.value.time_in && !newAttendance.value.time_out) {
        toast.error('Please provide at least Time In or Time Out.');
        return;
    }

    const formattedDate = formatDateToYmd(newAttendance.value.date);
    if (!formattedDate) {
        toast.error('Invalid date format. Please select a valid date.');
        return;
    }

    const payload = {
        employee_id: selectedEmployee.value.id,
        date: formattedDate,
        time_in: newAttendance.value.time_in || null,
        time_out: newAttendance.value.time_out || null,
    };

    try {
        isSavingAttendance.value = true;
        const response = await api.post('/attendance', payload);
        const data = response.data;
        toast.success(data.message ?? 'Attendance record created successfully.');

        // Update local state with new attendance record
        if (data.attendance) {
            const transformedAttendance = transformAttendanceRecord(data.attendance);
            if (canManageAll.value) {
                const index = teamAttendance.value.findIndex(record => record.id === transformedAttendance.id);
                if (index !== -1) {
                    teamAttendance.value.splice(index, 1, transformedAttendance);
                } else {
                    teamAttendance.value.unshift(transformedAttendance);
                }
            } else {
                const index = myAttendance.value.findIndex(record => record.id === transformedAttendance.id);
                if (index !== -1) {
                    myAttendance.value.splice(index, 1, transformedAttendance);
                } else {
                    myAttendance.value.unshift(transformedAttendance);
                }
            }
        }

        closeAddDialog();
    } catch (error: any) {
        if (error.response?.status === 422) {
            const errors = error.response.data.errors ?? {};
            const firstError = Object.values(errors).flat()[0];
            toast.error(firstError ?? 'Validation error. Please check your input.');
        } else {
            toast.error(error.response?.data?.message ?? 'Unable to create attendance record.');
        }
    } finally {
        isSavingAttendance.value = false;
    }
};

const biometricLogs = ref<BiometricLogRecord[]>((props.biometricLogs ?? []).map(transformBiometricLog));

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

const confirmBiometricDelete = async () => {
    if (biometricLogToDelete.value === null) {
        return;
    }

    try {
        const response = await api.delete(`/biometric-logs/${biometricLogToDelete.value}`);
        const data = response.data;
        toast.success(data.message ?? 'Biometric log deleted successfully.');

        // Remove from local state
        const index = biometricLogs.value.findIndex(log => log.id === biometricLogToDelete.value);
        if (index !== -1) {
            biometricLogs.value.splice(index, 1);
        }

        // Update attendance if it was updated
        if (data.attendance && canManageAll.value) {
            const transformedAttendance = transformAttendanceRecord(data.attendance);
            
            // Update teamAttendance
            const teamIndex = teamAttendance.value.findIndex(record => record.id === transformedAttendance.id);
            if (teamIndex !== -1) {
                teamAttendance.value.splice(teamIndex, 1, transformedAttendance);
            } else {
                teamAttendance.value.unshift(transformedAttendance);
            }

            // Update myAttendance if it's the current user's record
            const myIndex = myAttendance.value.findIndex(record => record.id === transformedAttendance.id);
            if (myIndex !== -1) {
                myAttendance.value.splice(myIndex, 1, transformedAttendance);
            }
        }
    } catch {
        toast.error('Unable to delete biometric log.');
    } finally {
        closeBiometricDeleteDialog();
    }
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
interface NewBiometricForm {
    employee_code: string;
    scan_date: PossibleDateValue;
    scan_time: string;
}

const newBiometric = ref<NewBiometricForm>({
    employee_code: '',
    scan_date: null,
    scan_time: '',
});
const biometricFormErrors = ref<Record<string, string[]>>({});
const isSavingBiometric = ref(false);

// Add Attendance dialog state

// Search and filter state (applied filters)
const myAttendanceSearch = ref('');
const myAttendanceStatusFilter = ref('all');
const myAttendanceStartDate = ref<string | null>(null);
const myAttendanceEndDate = ref<string | null>(null);

const teamAttendanceSearch = ref('');
const teamAttendanceStatusFilter = ref('all');
const teamAttendanceDepartmentFilter = ref('all');
const teamAttendanceStartDate = ref<string | null>(null);
const teamAttendanceEndDate = ref<string | null>(null);

const biometricSearch = ref('');
const biometricStartDate = ref<string | null>(null);
const biometricEndDate = ref<string | null>(null);

// Filter dialog states
const isMyAttendanceFilterOpen = ref(false);
const isTeamAttendanceFilterOpen = ref(false);
const isBiometricFilterOpen = ref(false);

// Temporary filter states (for staging changes before apply)
const tempMyAttendanceStatusFilter = ref('all');
const tempMyAttendanceStartDate = ref<string | null>(null);
const tempMyAttendanceEndDate = ref<string | null>(null);

const tempTeamAttendanceStatusFilter = ref('all');
const tempTeamAttendanceDepartmentFilter = ref('all');
const tempTeamAttendanceStartDate = ref<string | null>(null);
const tempTeamAttendanceEndDate = ref<string | null>(null);

const tempBiometricStartDate = ref<string | null>(null);
const tempBiometricEndDate = ref<string | null>(null);

// Filter functions
const openMyAttendanceFilter = () => {
    tempMyAttendanceStatusFilter.value = myAttendanceStatusFilter.value;
    tempMyAttendanceStartDate.value = myAttendanceStartDate.value;
    tempMyAttendanceEndDate.value = myAttendanceEndDate.value;
    isMyAttendanceFilterOpen.value = true;
};

const applyMyAttendanceFilter = () => {
    myAttendanceStatusFilter.value = tempMyAttendanceStatusFilter.value;
    myAttendanceStartDate.value = tempMyAttendanceStartDate.value;
    myAttendanceEndDate.value = tempMyAttendanceEndDate.value;
    isMyAttendanceFilterOpen.value = false;
};

const clearMyAttendanceFilter = () => {
    tempMyAttendanceStatusFilter.value = 'all';
    tempMyAttendanceStartDate.value = null;
    tempMyAttendanceEndDate.value = null;
    myAttendanceStatusFilter.value = 'all';
    myAttendanceStartDate.value = null;
    myAttendanceEndDate.value = null;
};

const openTeamAttendanceFilter = () => {
    tempTeamAttendanceStatusFilter.value = teamAttendanceStatusFilter.value;
    tempTeamAttendanceDepartmentFilter.value = teamAttendanceDepartmentFilter.value;
    tempTeamAttendanceStartDate.value = teamAttendanceStartDate.value;
    tempTeamAttendanceEndDate.value = teamAttendanceEndDate.value;
    isTeamAttendanceFilterOpen.value = true;
};

const applyTeamAttendanceFilter = () => {
    teamAttendanceStatusFilter.value = tempTeamAttendanceStatusFilter.value;
    teamAttendanceDepartmentFilter.value = tempTeamAttendanceDepartmentFilter.value;
    teamAttendanceStartDate.value = tempTeamAttendanceStartDate.value;
    teamAttendanceEndDate.value = tempTeamAttendanceEndDate.value;
    isTeamAttendanceFilterOpen.value = false;
};

const clearTeamAttendanceFilter = () => {
    tempTeamAttendanceStatusFilter.value = 'all';
    tempTeamAttendanceDepartmentFilter.value = 'all';
    tempTeamAttendanceStartDate.value = null;
    tempTeamAttendanceEndDate.value = null;
    teamAttendanceStatusFilter.value = 'all';
    teamAttendanceDepartmentFilter.value = 'all';
    teamAttendanceStartDate.value = null;
    teamAttendanceEndDate.value = null;
};

const openBiometricFilter = () => {
    tempBiometricStartDate.value = biometricStartDate.value;
    tempBiometricEndDate.value = biometricEndDate.value;
    isBiometricFilterOpen.value = true;
};

const applyBiometricFilter = () => {
    biometricStartDate.value = tempBiometricStartDate.value;
    biometricEndDate.value = tempBiometricEndDate.value;
    isBiometricFilterOpen.value = false;
};

const clearBiometricFilter = () => {
    tempBiometricStartDate.value = null;
    tempBiometricEndDate.value = null;
    biometricStartDate.value = null;
    biometricEndDate.value = null;
};

// Biometric time picker state (12-hour format with AM/PM)
const biometricTimeHour = ref<number | null>(null);
const biometricTimeMinute = ref<number | null>(null);
const biometricTimePeriod = ref<string>('AM');

const updateBiometricTime = () => {
    if (biometricTimeHour.value === null || biometricTimeMinute.value === null) {
        return;
    }

    newBiometric.value.scan_time = formatToTwentyFourHour(
        biometricTimeHour.value,
        biometricTimeMinute.value,
        biometricTimePeriod.value,
    );
};

const openAddBiometricDialog = () => {
    if (!canManageAll.value) {
        return;
    }

    biometricFormErrors.value = {};
    isAddBiometricOpen.value = true;
};

const closeAddBiometricDialog = () => {
    isAddBiometricOpen.value = false;
    newBiometric.value = {
        employee_code: '',
        scan_date: null,
        scan_time: '',
    };
    biometricTimeHour.value = null;
    biometricTimeMinute.value = null;
    biometricTimePeriod.value = 'AM';
    biometricFormErrors.value = {};
};

const addBiometric = async () => {
    if (isSavingBiometric.value) {
        return;
    }

    biometricFormErrors.value = {};

    // Validate before formatting
    const employeeCode = newBiometric.value.employee_code?.trim();
    const scanDate = newBiometric.value.scan_date;
    const scanTime = newBiometric.value.scan_time;

    console.log('=== Add Biometric Debug ===');
    console.log('Raw newBiometric.value:', newBiometric.value);
    console.log('Extracted values:', {
        employeeCode,
        scanDate,
        scanDateType: typeof scanDate,
        scanDateConstructor: scanDate?.constructor?.name,
        scanTime,
    });

    if (!employeeCode) {
        toast.error('Employee code is required.');
        return;
    }

    if (!scanDate) {
        toast.error('Date is required.');
        return;
    }

    if (!scanTime) {
        toast.error('Time is required.');
        return;
    }

    const formattedDate = formatDateToYmd(scanDate);
    console.log('Formatted date:', formattedDate);
    console.log('Date formatting result:', {
        input: scanDate,
        output: formattedDate,
    });

    if (!formattedDate) {
        toast.error('Invalid date format. Please select a valid date.');
        return;
    }

    const payload = {
        employee_code: employeeCode,
        scan_date: formattedDate,
        scan_time: scanTime,
    };

    console.log('Final payload being sent:', payload);
    console.log('Payload JSON:', JSON.stringify(payload));
    console.log('=== End Debug ===');

    try {
        isSavingBiometric.value = true;
        const response = await api.post('/biometric-logs', payload);
        const data = response.data;
        toast.success(data.message ?? 'Biometric log recorded.');

        if (data.biometric_log) {
            const transformedLog = transformBiometricLog(data.biometric_log);
            biometricLogs.value.unshift(transformedLog);
        }

        if (data.attendance && canManageAll.value) {
            const updatedAttendance = transformAttendanceRecord(data.attendance);
            const index = teamAttendance.value.findIndex(record => record.id === updatedAttendance.id);
            if (index !== -1) {
                teamAttendance.value.splice(index, 1, updatedAttendance);
            } else {
                teamAttendance.value.unshift(updatedAttendance);
            }
        }

        closeAddBiometricDialog();
    } catch (error: any) {
        if (error.response?.status === 422) {
            biometricFormErrors.value = error.response.data.errors ?? {};
            toast.error(error.response.data.message ?? 'Unable to add biometric log.');
        } else {
            toast.error('Unable to add biometric log.');
        }
    } finally {
        isSavingBiometric.value = false;
    }
};



const normalizeStatus = (status?: string | null): string => (status ?? '').toLowerCase();

const getStatusVariant = (status: string) => {
    switch (normalizeStatus(status)) {
        case 'present': return 'default';  // Will style with green
        case 'late': return 'secondary';   // Will style with orange/yellow
        case 'incomplete': return 'destructive';  // Will style with red
        default: return 'outline';
    }
};

const getStatusClass = (status: string) => {
    switch (normalizeStatus(status)) {
        case 'present': return 'bg-green-500 hover:bg-green-600 text-white';
        case 'late': return 'bg-orange-500 hover:bg-orange-600 text-white';
        case 'incomplete': return 'bg-red-500 hover:bg-red-600 text-white';
        default: return '';
    }
};

// Computed properties for active filter counts
const myAttendanceActiveFilters = computed(() => {
    let count = 0;
    if (myAttendanceStatusFilter.value !== 'all') count++;
    if (myAttendanceStartDate.value) count++;
    if (myAttendanceEndDate.value) count++;
    return count;
});

const teamAttendanceActiveFilters = computed(() => {
    let count = 0;
    if (teamAttendanceStatusFilter.value !== 'all') count++;
    if (teamAttendanceDepartmentFilter.value !== 'all') count++;
    if (teamAttendanceStartDate.value) count++;
    if (teamAttendanceEndDate.value) count++;
    return count;
});

const biometricActiveFilters = computed(() => {
    let count = 0;
    if (biometricStartDate.value) count++;
    if (biometricEndDate.value) count++;
    return count;
});

// Auto-adjust end dates if they're before start dates
watch(() => tempMyAttendanceStartDate.value, (newStartDate) => {
    if (newStartDate && tempMyAttendanceEndDate.value && tempMyAttendanceEndDate.value < newStartDate) {
        tempMyAttendanceEndDate.value = newStartDate;
    }
});

watch(() => tempTeamAttendanceStartDate.value, (newStartDate) => {
    if (newStartDate && tempTeamAttendanceEndDate.value && tempTeamAttendanceEndDate.value < newStartDate) {
        tempTeamAttendanceEndDate.value = newStartDate;
    }
});

watch(() => tempBiometricStartDate.value, (newStartDate) => {
    if (newStartDate && tempBiometricEndDate.value && tempBiometricEndDate.value < newStartDate) {
        tempBiometricEndDate.value = newStartDate;
    }
});
</script>

<template>
    <Head title="Attendance" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="relative min-h-[100vh] p-6 flex-1 border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border">
            <Tabs default-value="my-attendance" class="w-full">
                <TabsList :class="['grid w-full mb-6', canManageAll ? 'grid-cols-3' : 'grid-cols-1']">
                    <TabsTrigger value="my-attendance">
                        <div class="flex items-center justify-center gap-2">
                            <Calendar :size="16" class="shrink-0" />
                            <span class="hidden sm:inline">My Attendance</span>
                        </div>
                    </TabsTrigger>
                    <TabsTrigger v-if="canManageAll" value="all">
                        <div class="flex items-center justify-center gap-2">
                            <Users :size="16" class="shrink-0" />
                            <span class="hidden sm:inline">All Attendance</span>
                        </div>
                    </TabsTrigger>
                    <TabsTrigger v-if="canManageAll" value="biometrics">
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
                            <div class="flex flex-col items-center gap-3 mt-4">
                                <div class="flex items-center justify-center gap-6">
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
                                <p class="text-xs text-muted-foreground italic">Note: Incomplete records appear as small bars (0.5h) to indicate missing time data</p>
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
                                <Button variant="outline" @click="openExportDialog">
                                    <Download class=" h-4 w-4" />
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
                                            <Badge v-if="myAttendanceActiveFilters > 0" variant="secondary" class="ml-1">
                                                {{ myAttendanceActiveFilters }}
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
                                            <div class="space-y-3">
                                                <Label class="text-sm font-medium">Date Range</Label>
                                                <div class="space-y-2">
                                                    <div>
                                                        <Label class="text-xs text-muted-foreground">Start Date</Label>
                                                        <Popover>
                                                            <PopoverTrigger as-child>
                                                                <Button
                                                                    variant="outline"
                                                                    :class="['w-full justify-start text-left font-normal', !tempMyAttendanceStartDate && 'text-muted-foreground']"
                                                                >
                                                                    <CalendarDays class=" h-4 w-4" />
                                                                    {{ tempMyAttendanceStartDate ? new Date(tempMyAttendanceStartDate).toLocaleDateString() : 'Select start date' }}
                                                                </Button>
                                                            </PopoverTrigger>
                                                            <PopoverContent class="w-auto p-0">
                                                                <CalendarPicker v-model="tempMyAttendanceStartDate as any" />
                                                            </PopoverContent>
                                                        </Popover>
                                                    </div>
                                                    <div>
                                                        <Label class="text-xs text-muted-foreground">End Date</Label>
                                                        <Popover>
                                                            <PopoverTrigger as-child>
                                                                <Button
                                                                    variant="outline"
                                                                    :class="['w-full justify-start text-left font-normal', !tempMyAttendanceEndDate && 'text-muted-foreground']"
                                                                >
                                                                    <CalendarDays class=" h-4 w-4" />
                                                                    {{ tempMyAttendanceEndDate ? new Date(tempMyAttendanceEndDate).toLocaleDateString() : 'Select end date' }}
                                                                </Button>
                                                            </PopoverTrigger>
                                                            <PopoverContent class="w-auto p-0">
                                                                <CalendarPicker v-model="tempMyAttendanceEndDate as any" />
                                                            </PopoverContent>
                                                        </Popover>
                                                    </div>
                                                </div>
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
                                            <td class="p-4 align-middle">{{ formatDisplayTime(record.timeIn) || '-' }}</td>
                                            <td class="p-4 align-middle">{{ formatDisplayTime(record.timeOut) || '-' }}</td>
                                            <td class="p-4 align-middle">{{ formatHoursWorked(record) }}</td>
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
                                        'border-green-200 dark:border-green-800': normalizeStatus(record.status) === 'present',
                                        'border-orange-200 dark:border-orange-800': normalizeStatus(record.status) === 'late',
                                        'border-red-200 dark:border-red-800': normalizeStatus(record.status) === 'incomplete'
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
                                            <span class="ml-1 font-medium">{{ formatDisplayTime(record.timeIn) || '-' }}</span>
                                        </div>
                                        <div>
                                            <span class="text-muted-foreground">Out:</span>
                                            <span class="ml-1 font-medium">{{ formatDisplayTime(record.timeOut) || '-' }}</span>
                                        </div>
                                        <div>
                                            <span class="text-muted-foreground">Hours:</span>
                                            <span class="ml-1 font-medium">{{ formatHoursWorked(record) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- All Attendance Tab -->        
                <TabsContent value="all" v-if="canManageAll">
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

                    <!-- All Attendance Records Card -->
                    <Card>
                        <CardHeader>
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <div class="flex-1">
                                    <CardTitle>All Attendance</CardTitle>
                                    <CardDescription>View all employees' attendance records</CardDescription>
                                </div>
                                <div class="flex flex-col gap-2 w-full sm:w-auto sm:flex-row sm:flex-wrap sm:justify-end">
                                    <Button
                                        class="w-full sm:w-auto font-semibold bg-green-600 hover:bg-green-700 text-white"
                                        @click="openRepopulateDialog"
                                    >
                                        <RefreshCw class=" h-4 w-4" />
                                        Sync
                                    </Button>
                                    <Button
                                        variant="secondary"
                                        class="w-full sm:w-auto"
                                        @click="openAttendanceSettingsDialog"
                                    >
                                        <Settings class=" h-4 w-4" />
                                        Attendance Settings
                                    </Button>
                                    <Button
                                        class="w-full sm:w-auto"
                                        @click="openAddDialog"
                                    >
                                        <Plus class=" h-4 w-4" />
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
                                            <Badge v-if="teamAttendanceActiveFilters > 0" variant="secondary" class="ml-1">
                                                {{ teamAttendanceActiveFilters }}
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
                                                            <SelectItem
                                                                v-for="dept in teamDepartments"
                                                                :key="dept"
                                                                :value="dept"
                                                            >
                                                                {{ dept }}
                                                            </SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                                <div class="space-y-3">
                                                    <Label class="text-sm font-medium">Date Range</Label>
                                                    <div class="space-y-2">
                                                        <div>
                                                            <Label class="text-xs text-muted-foreground">Start Date</Label>
                                                            <Popover>
                                                                <PopoverTrigger as-child>
                                                                    <Button
                                                                        variant="outline"
                                                                        :class="['w-full justify-start text-left font-normal', !tempTeamAttendanceStartDate && 'text-muted-foreground']"
                                                                    >
                                                                        <CalendarDays class=" h-4 w-4" />
                                                                        {{ tempTeamAttendanceStartDate ? new Date(tempTeamAttendanceStartDate).toLocaleDateString() : 'Select start date' }}
                                                                    </Button>
                                                                </PopoverTrigger>
                                                                <PopoverContent class="w-auto p-0">
                                                                    <CalendarPicker v-model="tempTeamAttendanceStartDate as any" />
                                                                </PopoverContent>
                                                            </Popover>
                                                        </div>
                                                        <div>
                                                            <Label class="text-xs text-muted-foreground">End Date</Label>
                                                            <Popover>
                                                                <PopoverTrigger as-child>
                                                                    <Button
                                                                        variant="outline"
                                                                        :class="['w-full justify-start text-left font-normal', !tempTeamAttendanceEndDate && 'text-muted-foreground']"
                                                                    >
                                                                        <CalendarDays class=" h-4 w-4" />
                                                                        {{ tempTeamAttendanceEndDate ? new Date(tempTeamAttendanceEndDate).toLocaleDateString() : 'Select end date' }}
                                                                    </Button>
                                                                </PopoverTrigger>
                                                                <PopoverContent class="w-auto p-0">
                                                                    <CalendarPicker v-model="tempTeamAttendanceEndDate as any" />
                                                                </PopoverContent>
                                                            </Popover>
                                                        </div>
                                                    </div>
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
                                            <td class="p-4 align-middle">{{ formatDisplayTime(record.timeIn) || '-' }}</td>
                                            <td class="p-4 align-middle">{{ formatDisplayTime(record.timeOut) || '-' }}</td>
                                            <td class="p-4 align-middle">{{ formatHoursWorked(record) }}</td>
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
                                                            <Eye :size="16" class="" />
                                                            View Details
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem @click="deleteAttendanceRecord(record.id)" class="text-destructive focus:text-destructive">
                                                            <Trash2 :size="16" class="" />
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
                                        'border-green-200 dark:border-green-800': normalizeStatus(record.status) === 'present',
                                        'border-orange-200 dark:border-orange-800': normalizeStatus(record.status) === 'late',
                                        'border-red-200 dark:border-red-800': normalizeStatus(record.status) === 'incomplete'
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
                                                    <Eye :size="14" class="" />
                                                    View Details
                                                </DropdownMenuItem>
                                                <DropdownMenuItem @click="deleteAttendanceRecord(record.id)" class="text-destructive focus:text-destructive">
                                                    <Trash2 :size="14" class="" />
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
                                                    <span class="ml-1 font-medium">{{ formatDisplayTime(record.timeIn) || '-' }}</span>
                                            </div>
                                            <div>
                                                <span class="text-muted-foreground">Out:</span>
                                                    <span class="ml-1 font-medium">{{ formatDisplayTime(record.timeOut) || '-' }}</span>
                                            </div>
                                            <div>
                                                <span class="text-muted-foreground">Hours:</span>
                                                    <span class="ml-1 font-medium">{{ formatHoursWorked(record) }}</span>
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
                <TabsContent value="biometrics" v-if="canManageAll">
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
                                        <Plus class=" h-4 w-4" />
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
                                            <Badge v-if="biometricActiveFilters > 0" variant="secondary" class="ml-1">
                                                {{ biometricActiveFilters }}
                                            </Badge>
                                        </Button>
                                    </PopoverTrigger>
                                    <PopoverContent class="w-80" align="end">
                                        <div class="space-y-4">
                                            <div>
                                                <h4 class="font-medium mb-2">Filter Options</h4>
                                            </div>
                                            <div class="space-y-3">
                                                <Label class="text-sm font-medium">Date Range</Label>
                                                <div class="space-y-2">
                                                    <div>
                                                        <Label class="text-xs text-muted-foreground">Start Date</Label>
                                                        <Popover>
                                                            <PopoverTrigger as-child>
                                                                <Button
                                                                    variant="outline"
                                                                    :class="['w-full justify-start text-left font-normal', !tempBiometricStartDate && 'text-muted-foreground']"
                                                                >
                                                                    <CalendarDays class=" h-4 w-4" />
                                                                    {{ tempBiometricStartDate ? new Date(tempBiometricStartDate).toLocaleDateString() : 'Select start date' }}
                                                                </Button>
                                                            </PopoverTrigger>
                                                            <PopoverContent class="w-auto p-0">
                                                                <CalendarPicker v-model="tempBiometricStartDate as any" />
                                                            </PopoverContent>
                                                        </Popover>
                                                    </div>
                                                    <div>
                                                        <Label class="text-xs text-muted-foreground">End Date</Label>
                                                        <Popover>
                                                            <PopoverTrigger as-child>
                                                                <Button
                                                                    variant="outline"
                                                                    :class="['w-full justify-start text-left font-normal', !tempBiometricEndDate && 'text-muted-foreground']"
                                                                >
                                                                    <CalendarDays class=" h-4 w-4" />
                                                                    {{ tempBiometricEndDate ? new Date(tempBiometricEndDate).toLocaleDateString() : 'Select end date' }}
                                                                </Button>
                                                            </PopoverTrigger>
                                                            <PopoverContent class="w-auto p-0">
                                                                <CalendarPicker v-model="tempBiometricEndDate as any" />
                                                            </PopoverContent>
                                                        </Popover>
                                                    </div>
                                                </div>
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
                                                    {{ formatDisplayTime(log.time) || log.time }}
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
                                            <span>{{ formatDisplayTime(log.time) || log.time }}</span>
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
                                        <div v-if="!isEditing" class="text-sm font-medium text-green-600">{{ formatDisplayTime(selectedRecord.timeIn) || '-' }}</div>
                                        <Popover v-else>
                                            <PopoverTrigger as-child>
                                                <Button
                                                    variant="outline"
                                                    :class="['w-full justify-start text-left font-normal', !editTimeIn && 'text-muted-foreground']"
                                                >
                                                    <Clock class=" h-4 w-4 text-muted-foreground" />
                                                    {{ formatDisplayTime(editTimeIn) || 'Select time' }}
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
                                        <div v-if="!isEditing" class="text-sm font-medium text-red-600">{{ formatDisplayTime(selectedRecord.timeOut) || '-' }}</div>
                                        <Popover v-else>
                                            <PopoverTrigger as-child>
                                                <Button
                                                    variant="outline"
                                                    :class="['w-full justify-start text-left font-normal', !editTimeOut && 'text-muted-foreground']"
                                                >
                                                    <Clock class=" h-4 w-4 text-muted-foreground" />
                                                    {{ formatDisplayTime(editTimeOut) || 'Select time' }}
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
                                            {{ formatHoursWorked(selectedRecord) }}
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
                            <Database :size="16" class="" />
                            Export
                        </Button>
                        <Button variant="outline" @click="isEditing ? cancelEdit() : closeDetails()">
                            {{ isEditing ? 'Cancel Edit' : 'Close' }}
                        </Button>
                        <Button v-if="!isEditing" @click="startEdit">
                            Edit 
                        </Button>
                        <Button v-else @click="saveEdit" :disabled="isSavingEdit">
                            {{ isSavingEdit ? 'Saving...' : 'Save Changes' }}
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
                            <Label class="text-sm font-medium">Employee <span class="text-destructive">*</span></Label>
                            
                            <Combobox v-model="selectedEmployee" by="id" class="w-full">
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
                                            :key="emp.id"
                                            :value="emp"
                                        >
                                            <div class="flex flex-col">
                                                <span class="font-medium">{{ emp.label }}</span>
                                                <span class="text-xs text-muted-foreground font-mono">{{ emp.employee_code }}</span>
                                            </div>
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
                                        <CalendarDays class=" h-4 w-4" />
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
                                        :class="['w-full justify-start text-left font-normal', !newAttendance.time_in && 'text-muted-foreground']"
                                    >
                                        <Clock class=" h-4 w-4 text-muted-foreground" />
                                        {{ formatDisplayTime(newAttendance.time_in) || 'Select time' }}
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
                                        :class="['w-full justify-start text-left font-normal', !newAttendance.time_out && 'text-muted-foreground']"
                                    >
                                        <Clock class=" h-4 w-4 text-muted-foreground" />
                                        {{ formatDisplayTime(newAttendance.time_out) || 'Select time (Optional)' }}
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
                        <Button variant="outline" @click="closeAddDialog" :disabled="isSavingAttendance">Cancel</Button>
                        <Button @click="addAttendance" :disabled="isSavingAttendance">
                            {{ isSavingAttendance ? 'Saving...' : 'Add' }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Export Attendance Dialog -->
            <Dialog :open="isExportDialogOpen" @update:open="closeExportDialog">
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Export Attendance</DialogTitle>
                        <DialogDescription>Select the month and year you want to export.</DialogDescription>
                    </DialogHeader>

                    <div class="grid gap-4 py-4">
                        <div class="grid gap-2">
                            <Label class="text-sm font-medium">Month</Label>
                            <Select v-model="exportMonth">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select month" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="month in exportMonthOptions"
                                        :key="month.value"
                                        :value="month.value"
                                    >
                                        {{ month.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="grid gap-2">
                            <Label class="text-sm font-medium">Year</Label>
                            <Select v-model="exportYear">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select year" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="year in myAttendanceYears"
                                        :key="year"
                                        :value="year"
                                    >
                                        {{ year }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" @click="closeExportDialog">
                            Cancel
                        </Button>
                        <Button @click="confirmMyAttendanceExport">
                            <Download class=" h-4 w-4" />
                            Export
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Employee Export Dialog -->
            <Dialog :open="isEmployeeExportDialogOpen" @update:open="closeEmployeeExportDialog">
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Export Employee Attendance</DialogTitle>
                        <DialogDescription>Select the month and year you want to export for this employee.</DialogDescription>
                    </DialogHeader>

                    <div class="grid gap-4 py-4">
                        <div class="grid gap-2">
                            <Label class="text-sm font-medium">Month</Label>
                            <Select v-model="employeeExportMonth">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select month" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="month in exportMonthOptions"
                                        :key="month.value"
                                        :value="month.value"
                                    >
                                        {{ month.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="grid gap-2">
                            <Label class="text-sm font-medium">Year</Label>
                            <Select v-model="employeeExportYear">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select year" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="year in employeeExportYears"
                                        :key="year"
                                        :value="year"
                                    >
                                        {{ year }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" @click="closeEmployeeExportDialog">
                            Cancel
                        </Button>
                        <Button @click="confirmEmployeeExport">
                            <Download class=" h-4 w-4" />
                            Export
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Attendance Settings Dialog -->
            <Dialog :open="isAttendanceSettingsOpen" @update:open="closeAttendanceSettingsDialog">
                <DialogContent class="sm:max-w-[500px]">
                    <DialogHeader>
                        <DialogTitle>Attendance Settings</DialogTitle>
                        <DialogDescription>Configure required times and break settings for the team.</DialogDescription>
                    </DialogHeader>

                    <div class="grid gap-4 py-4">
                        <div class="grid gap-2">
                            <Label class="text-sm font-medium">Required Time In</Label>
                            <Popover>
                                <PopoverTrigger as-child>
                                    <Button
                                        variant="outline"
                                        :class="['w-full justify-start text-left font-normal', !attendanceSettingsForm.required_time_in && 'text-muted-foreground']"
                                    >
                                        <Clock class=" h-4 w-4 text-muted-foreground" />
                                        {{ formatDisplayTime(attendanceSettingsForm.required_time_in) || 'Select time' }}
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent class="w-auto p-0">
                                    <div class="flex flex-col sm:flex-row sm:h-[300px] divide-y sm:divide-y-0 sm:divide-x">
                                        <ScrollArea class="w-64 sm:w-auto">
                                            <div class="flex sm:flex-col p-2">
                                                <Button
                                                    v-for="hour in Array.from({ length: 12 }, (_, i) => 12 - i)"
                                                    :key="`attendance-in-hour-${hour}`"
                                                    size="icon"
                                                    :variant="attendanceSettingsTimeInHour === hour ? 'default' : 'ghost'"
                                                    class="sm:w-full shrink-0 aspect-square"
                                                    @click="attendanceSettingsTimeInHour = hour; updateAttendanceSettingsTimeIn()"
                                                >
                                                    {{ hour }}
                                                </Button>
                                            </div>
                                            <ScrollBar orientation="horizontal" class="sm:hidden" />
                                        </ScrollArea>
                                        <ScrollArea class="w-64 sm:w-auto">
                                            <div class="flex sm:flex-col p-2">
                                                <Button
                                                    v-for="minute in Array.from({ length: 60 }, (_, i) => i)"
                                                    :key="`attendance-in-minute-${minute}`"
                                                    size="icon"
                                                    :variant="attendanceSettingsTimeInMinute === minute ? 'default' : 'ghost'"
                                                    class="sm:w-full shrink-0 aspect-square"
                                                    @click="attendanceSettingsTimeInMinute = minute; updateAttendanceSettingsTimeIn()"
                                                >
                                                    {{ String(minute).padStart(2, '0') }}
                                                </Button>
                                            </div>
                                            <ScrollBar orientation="horizontal" class="sm:hidden" />
                                        </ScrollArea>
                                        <ScrollArea>
                                            <div class="flex sm:flex-col p-2">
                                                <Button
                                                    v-for="period in ['AM', 'PM']"
                                                    :key="`attendance-in-period-${period}`"
                                                    size="icon"
                                                    :variant="attendanceSettingsTimeInPeriod === period ? 'default' : 'ghost'"
                                                    class="sm:w-full shrink-0 aspect-square"
                                                    @click="attendanceSettingsTimeInPeriod = period; updateAttendanceSettingsTimeIn()"
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
                            <Label class="text-sm font-medium">Required Time Out</Label>
                            <Popover>
                                <PopoverTrigger as-child>
                                    <Button
                                        variant="outline"
                                        :class="['w-full justify-start text-left font-normal', !attendanceSettingsForm.required_time_out && 'text-muted-foreground']"
                                    >
                                        <Clock class=" h-4 w-4 text-muted-foreground" />
                                        {{ formatDisplayTime(attendanceSettingsForm.required_time_out) || 'Select time' }}
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent class="w-auto p-0">
                                    <div class="flex flex-col sm:flex-row sm:h-[300px] divide-y sm:divide-y-0 sm:divide-x">
                                        <ScrollArea class="w-64 sm:w-auto">
                                            <div class="flex sm:flex-col p-2">
                                                <Button
                                                    v-for="hour in Array.from({ length: 12 }, (_, i) => 12 - i)"
                                                    :key="`attendance-out-hour-${hour}`"
                                                    size="icon"
                                                    :variant="attendanceSettingsTimeOutHour === hour ? 'default' : 'ghost'"
                                                    class="sm:w-full shrink-0 aspect-square"
                                                    @click="attendanceSettingsTimeOutHour = hour; updateAttendanceSettingsTimeOut()"
                                                >
                                                    {{ hour }}
                                                </Button>
                                            </div>
                                            <ScrollBar orientation="horizontal" class="sm:hidden" />
                                        </ScrollArea>
                                        <ScrollArea class="w-64 sm:w-auto">
                                            <div class="flex sm:flex-col p-2">
                                                <Button
                                                    v-for="minute in Array.from({ length: 60 }, (_, i) => i)"
                                                    :key="`attendance-out-minute-${minute}`"
                                                    size="icon"
                                                    :variant="attendanceSettingsTimeOutMinute === minute ? 'default' : 'ghost'"
                                                    class="sm:w-full shrink-0 aspect-square"
                                                    @click="attendanceSettingsTimeOutMinute = minute; updateAttendanceSettingsTimeOut()"
                                                >
                                                    {{ String(minute).padStart(2, '0') }}
                                                </Button>
                                            </div>
                                            <ScrollBar orientation="horizontal" class="sm:hidden" />
                                        </ScrollArea>
                                        <ScrollArea>
                                            <div class="flex sm:flex-col p-2">
                                                <Button
                                                    v-for="period in ['AM', 'PM']"
                                                    :key="`attendance-out-period-${period}`"
                                                    size="icon"
                                                    :variant="attendanceSettingsTimeOutPeriod === period ? 'default' : 'ghost'"
                                                    class="sm:w-full shrink-0 aspect-square"
                                                    @click="attendanceSettingsTimeOutPeriod = period; updateAttendanceSettingsTimeOut()"
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
                            <Label class="text-sm font-medium">Break Duration (Minutes)</Label>
                            <Input
                                type="number"
                                min="0"
                                step="5"
                                v-model.number="attendanceSettingsForm.break_duration_minutes"
                                placeholder="Enter minutes"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label class="text-sm font-medium">Break is Counted</Label>
                            <Select
                                :model-value="attendanceSettingsForm.break_is_counted ? 'yes' : 'no'"
                                @update:model-value="(value) => attendanceSettingsForm.break_is_counted = normalizeBoolean(value === 'yes')"
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select option" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="yes">Yes</SelectItem>
                                    <SelectItem value="no">No</SelectItem>
                                </SelectContent>
                            </Select>
                            <p class="text-xs text-muted-foreground">
                                Choose “Yes” if break time counts toward total hours.
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <Label class="text-sm font-medium">Confirm Password <span class="text-destructive">*</span></Label>
                            <Input
                                type="password"
                                v-model="attendanceSettingsForm.password"
                                placeholder="Enter your password"
                                autocomplete="current-password"
                            />
                            <p class="text-xs text-muted-foreground">
                                Enter your password to confirm attendance setting changes.
                            </p>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" @click="closeAttendanceSettingsDialog">
                            Cancel
                        </Button>
                        <Button @click="saveAttendanceSettings" :disabled="isSavingAttendanceSettings">
                            {{ isSavingAttendanceSettings ? 'Saving...' : 'Save Settings' }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Sync Confirmation Dialog -->
            <Dialog :open="showRepopulateDialog" @update:open="closeRepopulateDialog">
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Confirm Sync</DialogTitle>
                        <DialogDescription>
                            <span v-if="repopulateCountdown > 0">
                                Please wait {{ repopulateCountdown }} second{{ repopulateCountdown !== 1 ? 's' : '' }} before confirming.
                            </span>
                            <span v-else-if="isSyncing">
                                Please don't close the browser while processing. This may take a few moments...
                            </span>
                            <span v-else>
                                Are you sure you want to sync the attendance data? This will process all biometric logs and update attendance records accordingly. Please enter your password to confirm.
                            </span>
                        </DialogDescription>
                    </DialogHeader>
                    
                    <div v-if="repopulateCountdown === 0 && !isSyncing" class="grid gap-4 py-4">
                        <div class="grid gap-2">
                            <Label for="sync-password">Password</Label>
                            <Input
                                id="sync-password"
                                v-model="repopulatePassword"
                                type="password"
                                placeholder="Enter your password"
                                @keyup.enter="confirmRepopulate"
                                :disabled="isSyncing"
                            />
                        </div>
                    </div>

                    <div v-if="isSyncing" class="py-4">
                        <div class="flex items-center justify-center gap-2">
                            <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-green-600"></div>
                            <span class="text-sm text-muted-foreground">Syncing attendance data...</span>
                        </div>
                    </div>
                    
                    <DialogFooter>
                        <Button 
                            variant="outline" 
                            @click="closeRepopulateDialog"
                            :disabled="isSyncing"
                        >
                            Cancel
                        </Button>
                        <Button 
                            @click="confirmRepopulate"
                            :disabled="repopulateCountdown > 0 || isSyncing || !repopulatePassword"
                            class="bg-green-600 hover:bg-green-700 text-white"
                        >
                            {{ repopulateCountdown > 0 ? `Wait (${repopulateCountdown}s)` : isSyncing ? 'Syncing...' : 'Confirm Sync' }}
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
                                v-model="newBiometric.employee_code"
                                placeholder="Enter employee code"
                                class="w-full"
                            />
                            <p v-if="biometricFormErrors.employee_code" class="text-xs text-destructive">
                                {{ biometricFormErrors.employee_code[0] }}
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <Label class="text-sm font-medium">Date <span class="text-destructive">*</span></Label>
                            <Popover>
                                <PopoverTrigger as-child>
                                    <Button
                                        variant="outline"
                                        :class="['w-full justify-start text-left font-normal', !newBiometric.scan_date && 'text-muted-foreground']"
                                    >
                                        <CalendarDays class=" h-4 w-4" />
                                        {{ formatDateDisplay(newBiometric.scan_date) || 'Pick a date' }}
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent class="w-auto p-0">
                                    <CalendarPicker v-model="newBiometric.scan_date as any" />
                                </PopoverContent>
                            </Popover>
                            <p v-if="biometricFormErrors.scan_date" class="text-xs text-destructive">
                                {{ biometricFormErrors.scan_date[0] }}
                            </p>
                        </div>
                        
                        <div class="grid gap-2">
                            <Label class="text-sm font-medium">Time <span class="text-destructive">*</span></Label>
                            <Popover>
                                <PopoverTrigger as-child>
                                    <Button
                                        variant="outline"
                                        :class="['w-full justify-start text-left font-normal', !newBiometric.scan_time && 'text-muted-foreground']"
                                    >
                                        <Clock class=" h-4 w-4 text-muted-foreground" />
                                        {{ formatDisplayTime(newBiometric.scan_time) || 'Select time' }}
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
                            <p v-if="biometricFormErrors.scan_time" class="text-xs text-destructive">
                                {{ biometricFormErrors.scan_time[0] }}
                            </p>
                        </div>
                    </div>

                    <DialogFooter>  
                        <Button variant="outline" @click="closeAddBiometricDialog">Cancel</Button>
                        <Button @click="addBiometric" :disabled="isSavingBiometric">
                            {{ isSavingBiometric ? 'Saving...' : 'Add' }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
