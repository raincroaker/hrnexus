<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import api from '@/lib/axios';
import { Head, router } from '@inertiajs/vue3';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Calendar, Users, AlertTriangle, Clock, UserX, Download, Edit, Trash2, Plus, Search } from 'lucide-vue-next';
import { ref, computed, watch, onMounted } from 'vue';
import { Bar, Pie } from 'vue-chartjs';
import { Chart as ChartJS, CategoryScale, LinearScale, BarElement, LineElement, PointElement, Title, Tooltip, Legend, ArcElement } from 'chart.js';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
  Pagination,
  PaginationContent,
  PaginationEllipsis,
  PaginationItem,
  PaginationNext,
  PaginationPrevious,
} from "@/components/ui/pagination";
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { toast } from 'vue-sonner';
ChartJS.register(CategoryScale, LinearScale, BarElement, LineElement, PointElement, Title, Tooltip, Legend, ArcElement);

// Local date helper (avoid UTC day shifts from toISOString())
function toYmdLocal(d: Date): string {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}
// Start of week (Monday) to match Laravel Carbon startOfWeek()
function getStartOfWeekYmd(now: Date): string {
    const d = new Date(now);
    const day = d.getDay();
    const diff = day === 0 ? 6 : day - 1;
    d.setDate(d.getDate() - diff);
    return toYmdLocal(d);
}

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
        role: string;
        email: string;
        contact_number: string | null;
        birth_date: string | null;
        avatar: string | null;
    };
    canManageAll?: boolean;
    myAttendance?: {
        data: Array<{
            id: number;
            date: string;
            morning_time_in: string | null;
            morning_time_out: string | null;
            afternoon_time_in: string | null;
            afternoon_time_out: string | null;
            overtime_time_in: string | null;
            overtime_time_out: string | null;
            status: string;
        }>;
        links: unknown;
        meta: { current_page: number; last_page: number; per_page: number; total: number; from: number | null; to: number | null };
    };
    dtrStartDate?: string;
    dtrEndDate?: string;
    myLateCount?: number;
    myAbsentCount?: number;
    /** Current user's total absences for the current year (for memorandum rules) */
    myYearlyAbsentCount?: number;
    /** Current user's longest consecutive absence streak (days) for the current year */
    myConsecutiveAbsences?: number;
    /** Dates (Y-m-d) when current user was late this month */
    myLateDates?: string[];
    /** Dates (Y-m-d) when current user was absent this month */
    myAbsentDates?: string[];
    /** Current user's leaves this month (dates + type/notes) */
    myLeaveDetails?: Array<{ id: number; date: string; leave_type: string; notes: string | null }>;
    /** Last 7 days (oldest to newest) with hours worked per day for the weekly bar chart */
    weeklyHours?: Array<{ date: string; day: string; hours: number }>;
    /** Admin only: team summary with today_lates, yesterday_absents, today_leaves */
    teamAttendanceSummary?: {
        today_lates: Array<{ employee_id: number; name: string; department: string; position: string; date: string; time_in: string | null }>;
        yesterday_absents: Array<{ employee_id: number; name: string; department: string; position: string; date: string }>;
        today_leaves: unknown[];
        /** For pie chart: today's counts by status (Present, Late, On Leave) */
        today_status_counts?: { present: number; late: number; on_leave: number };
        total_employees?: number;
        /** This week's status counts per day (Present, Late, On Leave) – always full week for graph */
        weekly_status_counts?: Array<{ date: string; day: string; label: string; present: number; late: number; on_leave: number }>;
    };
    /** Admin only: all employees attendance (for table in Everyone tab with filters) */
    teamAttendance?: {
        data: Array<{
            id: number;
            employee_id: number;
            employee_code?: string;
            name?: string;
            department?: string;
            position?: string;
            date: string;
            time_in: string | null;
            time_out: string | null;
            morning_time_in: string | null;
            morning_time_out: string | null;
            afternoon_time_in: string | null;
            afternoon_time_out: string | null;
            overtime_time_in: string | null;
            overtime_time_out: string | null;
            status: string;
        }>;
        links: unknown;
        meta: { current_page: number; last_page: number; per_page: number; total: number; from: number | null; to: number | null };
    };
    teamTableStartDate?: string | null;
    teamTableEndDate?: string | null;
    teamSearch?: string | null;
    teamStatus?: string | null;
    /** Admin only: employees list for On Leave add form */
    employees?: Array<{ id: number; name: string; employee_code?: string }>;
    /** Admin only: leave types for On Leave add/edit form */
    leaveTypes?: Array<{ id: number; name: string; annual_leaves?: number }>;
    /** Admin only: flat list of employee leave records for current month (On Leave modal CRUD) */
    employeesOnLeaveRecords?: Array<{
        id: number;
        employee_id: number;
        employee_name: string;
        leave_type_id: number;
        leave_type_name: string;
        date: string;
        notes: string | null;
    }>;
    /** Admin only: overtime records for current month (Overtime card/modal) */
    overtimeRecords?: Array<{ id: number; employee_id: number; employee_name: string; date: string }>;
    /** Admin only: employees whose attendance is in warning/memorandum status (Warning tab) */
    teamWarningEmployees?: Array<{
        id: number;
        name: string;
        department?: string;
        position?: string;
        late_count: number;
        absent_count: number;
        last_late_date?: string | null;
        last_absent_date?: string | null;
        status: 'warning' | 'memorandum';
    }>;
    /** Admin only: team weekly status counts for bar chart (Present, Late, On Leave per day) */
    teamWeeklyStatusCounts?: Array<{
        date: string;
        day: string;
        present: number;
        late: number;
        on_leave: number;
    }>;
    /** Current user's unresolved warning/memo records (for My Attendance: Generate Memorandum) */
    myActiveWarningMemos?: Array<{ id: number; type: string; reason_type: string }>;
    /** Admin only: biometric logs for today (Biometric Logs tab) */
    biometricLogs?: {
        data: Array<{
            id: number;
            employee_code: string;
            date: string;
            time: string;
            scan_time: string;
        }>;
        links: unknown;
        meta: { current_page: number; last_page: number; per_page: number; total: number; from: number | null; to: number | null };
    };
}>();

const normalizedRole = computed(() => (props.currentUser?.role ?? '').toString().toLowerCase());
const isAdmin = computed(() => normalizedRole.value === 'admin');

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Attendance',
        href: '/attendance',
    },
];

interface EmployeeWarning {
    id: number;
    name: string;
    lateCount: number;
    absentCount: number;
    status: string;
    lastLateDate: string;
    lastAbsentDate: string;
    /** Unresolved warning/memo record IDs (for Mark Resolved & Generate Memorandum) */
    warning_memo_ids: number[];
}

interface EmployeeWarningMemoSummary {
    id: number;
    type: string;
    reason_type: string;
    notes: string | null;
    acknowledged_at: string | null;
    resolved_at: string | null;
    created_at: string;
}

// Current date information (use Manila for display when needed)
const currentDate = new Date();
const currentDay = currentDate.getDate();
const currentMonth = currentDate.toLocaleString('default', { month: 'long' });
const currentYear = currentDate.getFullYear();

// Stats from backend (current month/year)
const monthlyAttendanceStats = computed(() => ({
    totalLateThisMonth: props.myLateCount ?? 0,
    totalAbsentThisMonth: props.myAbsentCount ?? 0,
}));
const yearlyAbsentCount = computed(() => props.myYearlyAbsentCount ?? 0);

// Late/absent/leave dates for My Attendance modals (from backend)
const myLateDates = computed(() => props.myLateDates ?? []);
const myAbsentDates = computed(() => props.myAbsentDates ?? []);
const myLeaveDates = computed(() => {
    const details = (props as any).myLeaveDetails ?? [];
    return (details as Array<{ date: string }>).map((leave) => leave.date);
});

// Thresholds from attendance settings
// Lates: warning at 3, memorandum at 4
// Absences: warning at 3, memorandum at 4
const warningThresholds = computed(() => {
    const settings = (props as any).attendanceSettings ?? {};
    return {
        lateWarning: settings.late_threshold_warning ?? 3,
        lateMemo: settings.late_threshold_memo ?? 4,
        absentWarning: settings.absent_threshold_warning ?? 3,
        absentMemo: settings.absent_threshold_memo ?? 4,
    };
});

// Current user's warning status (My Attendance banner)
const currentUserWarning = computed(() => {
    const lateCountMonth = monthlyAttendanceStats.value.totalLateThisMonth;
    const yearlyAbsent = yearlyAbsentCount.value;
    const thresholds = warningThresholds.value;

    // Rules (aligned with backend thresholds):
    // - Lates (per month): warning at >= lateWarning, memorandum at >= lateMemo
    // - Absences (per year): warning at >= absentWarning, memorandum at >= absentMemo
    const hasLateMemorandum = lateCountMonth >= thresholds.lateMemo;
    const hasAbsentMemorandum = yearlyAbsent >= thresholds.absentMemo;

    if (hasLateMemorandum || hasAbsentMemorandum) {
        const reasons: string[] = [];
        if (hasLateMemorandum) {
            reasons.push(`Lates this month: ${lateCountMonth} (memorandum at ≥ ${thresholds.lateMemo})`);
        }
        if (hasAbsentMemorandum) {
            reasons.push(`Absences this year: ${yearlyAbsent} (memorandum at ≥ ${thresholds.absentMemo})`);
        }
        return {
            level: 'memorandum',
            title: 'Attendance MEMORANDUM Required',
            message: `You have triggered the attendance memorandum rules. Please see HR immediately for formal disciplinary action.`,
            details: reasons.join(' | '),
            color: 'red'
        };
    }

    // Warning (approaching) rules:
    const hasLateWarning = lateCountMonth >= thresholds.lateWarning && lateCountMonth < thresholds.lateMemo;
    const hasAbsentWarning = yearlyAbsent >= thresholds.absentWarning && yearlyAbsent < thresholds.absentMemo;

    if (hasLateWarning || hasAbsentWarning) {
        const reasons: string[] = [];
        if (hasLateWarning) {
            reasons.push(`Lates this month: ${lateCountMonth} (warning at ≥ ${thresholds.lateWarning}, memorandum at ≥ ${thresholds.lateMemo})`);
        }
        if (hasAbsentWarning) {
            reasons.push(`Absences this year: ${yearlyAbsent} (warning at ≥ ${thresholds.absentWarning}, memorandum at ≥ ${thresholds.absentMemo})`);
        }
        return {
            level: 'warning',
            title: 'Attendance WARNING',
            message: `You are close to triggering a formal attendance memorandum. Please improve your attendance to avoid disciplinary action.`,
            details: reasons.join(' | '),
            color: 'orange'
        };
    }
    
    return null;
});

// Current user's active memos (type === 'memo') for Generate Memorandum in My Attendance
const myActiveMemos = computed(() => {
    const list = props.myActiveWarningMemos ?? [];
    return list.filter((m) => m.type === 'memo');
});

const openMemoPdf = (warningMemoId: number) => {
    const url = `/api/employee-warnings-memos/${warningMemoId}/export-pdf`;
    window.open(url, '_blank', 'noopener,noreferrer');
};

const resolveWarningMemo = async (warningMemoId: number) => {
    try {
        await api.post(`/employee-warnings-memos/${warningMemoId}/resolve`);
        toast.success('Memorandum marked as resolved.');
        router.reload();
    } catch (err: unknown) {
        const e = err as { response?: { data?: { message?: string } } };
        toast.error(e.response?.data?.message ?? 'Failed to resolve.');
    }
};

const acknowledgeWarningMemo = async (warningMemoId: number) => {
    try {
        await api.post(`/employee-warnings-memos/${warningMemoId}/acknowledge`);
        toast.success('Warning marked as acknowledged.');
        router.reload();
    } catch (err: unknown) {
        const e = err as { response?: { data?: { message?: string } } };
        toast.error(e.response?.data?.message ?? 'Failed to acknowledge.');
    }
};

// Team attendance: use summary from backend (today_status_counts when available)
const teamAttendanceData = computed(() => {
    const summary = props.teamAttendanceSummary;
    const counts = summary?.today_status_counts;
    if (counts) {
        return {
            present: counts.present ?? 0,
            late: counts.late ?? 0,
            onLeave: counts.on_leave ?? 0,
            absent: summary?.yesterday_absents?.length ?? 0
        };
    }
    if (summary) {
        return {
            present: 0,
            late: summary.today_lates?.length ?? 0,
            onLeave: summary.today_leaves?.length ?? 0,
            absent: summary.yesterday_absents?.length ?? 0
        };
    }
    // If no summary from backend, return zeros (no static dummy data)
    return { present: 0, late: 0, onLeave: 0, absent: 0 };
});

// Summary line for pie chart: "14 present, 5 late, 3 on leave · 22 total"
const pieChartSummaryLine = computed(() => {
    const d = teamAttendanceData.value;
    const total = d.present + d.late + d.onLeave;
    const parts = [];
    if (d.present > 0) parts.push(`${d.present} present`);
    if (d.late > 0) parts.push(`${d.late} late`);
    if (d.onLeave > 0) parts.push(`${d.onLeave} on leave`);
    const line = parts.length ? parts.join(', ') : 'No attendance today';
    return total > 0 ? `${line} · ${total} total` : line;
});

const totalEmployeesForChart = computed(() => props.teamAttendanceSummary?.total_employees ?? 0);

// Employees on leave: from backend (current month records)
const employeesOnLeaveList = computed(() => props.employeesOnLeaveRecords ?? []);

// Overtime records from backend (current month)
const overtimeRecordsList = computed(() => props.overtimeRecords ?? []);

// Biometric logs for today (admin only)
const biometricLogsList = computed(() => props.biometricLogs?.data ?? []);

// Weekly status counts for bar chart (team-wide Present / Late / On Leave per day)
// Prefer backend weekly_status_counts (always this week); fallback to teamAttendance.data when not admin
const weeklyStatusCounts = computed(() => {
    const fromBackend = (props.teamAttendanceSummary as { weekly_status_counts?: Array<{ date: string; day: string; label: string; present: number; late: number; on_leave: number }> })?.weekly_status_counts;
    if (fromBackend?.length) {
        return fromBackend;
    }

    // Fallback: build from teamAttendance data (last 7 working days in current month, excluding Sundays)
    const today = new Date();
    const currentMonth = today.getMonth();
    const days: Array<{ date: string; label: string }> = [];
    let offset = 0;
    while (days.length < 7) {
        const d = new Date(today);
        d.setDate(d.getDate() - offset);
        offset++;
        if (d.getMonth() !== currentMonth) break;
        if (d.getDay() === 0) continue;
        const dateStr = toYmdLocal(d);
        const label = d.toLocaleDateString('en-US', { month: 'short', day: '2-digit' });
        days.unshift({ date: dateStr, label });
    }
    if (!props.teamAttendance?.data?.length) {
        return days.map(({ date, label }) => ({
            date,
            label,
            present: 0,
            late: 0,
            on_leave: 0,
        }));
    }
    return days.map(({ date, label }) => {
        const dayRecords = props.teamAttendance!.data.filter((record) => record.date === date);
        return {
            date,
            label,
            present: dayRecords.filter((r) => r.status === 'Present').length,
            late: dayRecords.filter((r) => r.status === 'Late').length,
            on_leave: dayRecords.filter((r) => r.status === 'Leave').length,
        };
    });
});

// Modal states
const showOnLeaveModal = ref(false);
const showOvertimeModal = ref(false);
/** On Leave dialog: single dialog with view = list | add | edit | confirmDelete */
const onLeaveDialogView = ref<'list' | 'add' | 'edit' | 'confirmDelete'>('list');
const confirmDeleteLeaveRecord = ref<{ id: number; employee_name: string; date: string } | null>(null);
/** Overtime dialog: single dialog with view = list | add | edit | confirmDelete */
const overtimeDialogView = ref<'list' | 'add' | 'edit' | 'confirmDelete'>('list');
const confirmDeleteOvertimeRecord = ref<{ id: number; name: string; date: string } | null>(null);
const editOvertimeForm = ref({
    id: 0,
    employee_name: '',
    date: ''
});
const showEmployeeDetailModal = ref(false);
const selectedEmployee = ref<EmployeeWarning | null>(null);
const selectedEmployeeWarnings = ref<EmployeeWarningMemoSummary[]>([]);
const selectedEmployeeWarningsLoading = ref(false);
const selectedEmployeeWarningsError = ref<string | null>(null);
// My Attendance: modals for late/absent dates
const showMyLateDatesModal = ref(false);
const showMyAbsentDatesModal = ref(false);
// Everyone tab: modals for today's late / yesterday's absent
const showTodayLateModal = ref(false);
const showYesterdayAbsentModal = ref(false);

// Form data for adding new leave (employee_id, leave_type_id as string for Select)
const newLeaveForm = ref({
    employee_id: '',
    leave_type_id: '',
    startDate: '',
    endDate: '',
    notes: ''
});

// Form data for editing a single leave record
const editLeaveForm = ref({
    id: 0,
    employee_id: 0,
    employee_name: '',
    leave_type_id: 0,
    date: '',
    notes: ''
});
const leaveFormSubmitting = ref(false);
const leaveFormError = ref('');

const newOvertimeForm = ref({
    employee_id: '',
    date: ''
});

// Leave types and employees for dropdowns (from props when admin)
const leaveTypesList = computed(() => props.leaveTypes ?? []);
const employeesList = computed(() => props.employees ?? []);

// Employee warnings for Warning tab – built from teamAttendanceSummary (current month/year)
const employeeWarnings = computed<EmployeeWarning[]>(() => {
    const summary = props.teamAttendanceSummary as
        | {
              warning_employees?: Array<{
                  employee_id: number;
                  employee_code?: string;
                  name?: string;
                  department?: string;
                  position?: string;
                  late_count: number;
                  absent_count: number;
                  warning_memo_ids?: number[];
                  last_late_date?: string | null;
                  last_absent_date?: string | null;
              }>;
              memo_employees?: Array<{
                  employee_id: number;
                  employee_code?: string;
                  name?: string;
                  department?: string;
                  position?: string;
                  late_count: number;
                  absent_count: number;
                  warning_memo_ids?: number[];
                  last_late_date?: string | null;
                  last_absent_date?: string | null;
              }>;
          }
        | undefined;

    if (!summary) return [];

    const warnings: EmployeeWarning[] = [];

    for (const e of summary.warning_employees ?? []) {
        warnings.push({
            id: e.employee_id,
            name: e.name ?? `Employee ${e.employee_id}`,
            lateCount: e.late_count,
            absentCount: e.absent_count,
            status: 'warning',
            lastLateDate: e.last_late_date ?? '—',
            lastAbsentDate: e.last_absent_date ?? '—',
            warning_memo_ids: e.warning_memo_ids ?? [],
        });
    }

    for (const e of summary.memo_employees ?? []) {
        warnings.push({
            id: e.employee_id,
            name: e.name ?? `Employee ${e.employee_id}`,
            lateCount: e.late_count,
            absentCount: e.absent_count,
            status: 'memorandum',
            lastLateDate: e.last_late_date ?? '—',
            lastAbsentDate: e.last_absent_date ?? '—',
            warning_memo_ids: e.warning_memo_ids ?? [],
        });
    }

    return warnings;
});

const getWarningColor = (status: string) => {
    switch (status) {
        case 'memorandum': return 'bg-red-500 hover:bg-red-600 text-white';
        case 'warning': return 'bg-orange-500 hover:bg-orange-600 text-white';
        default: return 'bg-green-500 hover:bg-green-600 text-white';
    }
};

const memorandumWarnings = computed(() => 
    employeeWarnings.value.filter(emp => emp.status === 'memorandum')
);

const warningEmployees = computed(() => 
    employeeWarnings.value.filter(emp => emp.status === 'warning')
);

// Refresh card data from backend without relying on placeholder/static values.
const refreshAttendanceCards = () => {
    router.reload({
        only: [
            'myLateCount',
            'myAbsentCount',
            'myYearlyAbsentCount',
            'myConsecutiveAbsences',
            'myLateDates',
            'myAbsentDates',
            'myLeaveDetails',
            'teamAttendanceSummary',
            'employeesOnLeaveRecords',
            'overtimeRecords',
        ],
    });
};
// On Leave CRUD
const editOnLeave = (record: { id: number; employee_id: number; employee_name: string; leave_type_id: number; date: string; notes: string | null }) => {
    editLeaveForm.value = {
        id: record.id,
        employee_id: record.employee_id,
        employee_name: record.employee_name,
        leave_type_id: record.leave_type_id,
        date: record.date,
        notes: record.notes ?? ''
    };
    leaveFormError.value = '';
    onLeaveDialogView.value = 'edit';
};

const openConfirmDeleteLeave = (record: { id: number; employee_name: string; date: string }) => {
    confirmDeleteLeaveRecord.value = { id: record.id, employee_name: record.employee_name, date: record.date };
    onLeaveDialogView.value = 'confirmDelete';
};

const confirmDeleteLeave = async () => {
    if (!confirmDeleteLeaveRecord.value) return;
    const recordId = confirmDeleteLeaveRecord.value.id;
    try {
        await api.delete(`/employee-leaves/${recordId}`);
        toast.success('Leave record deleted successfully.');
        onLeaveDialogView.value = 'list';
        confirmDeleteLeaveRecord.value = null;
        showOnLeaveModal.value = false;
        refreshAttendanceCards();
    } catch (err: unknown) {
        const e = err as { response?: { data?: { message?: string } } };
        alert(e.response?.data?.message ?? 'Failed to delete leave record.');
    }
};

const backToOnLeaveList = () => {
    onLeaveDialogView.value = 'list';
    leaveFormError.value = '';
};

const submitEditLeaveForm = async () => {
    if (!editLeaveForm.value.id || !editLeaveForm.value.date || !editLeaveForm.value.leave_type_id) {
        leaveFormError.value = 'Please fill date and leave type.';
        return;
    }
    leaveFormError.value = '';
    leaveFormSubmitting.value = true;
    try {
        await api.put(`/employee-leaves/${editLeaveForm.value.id}`, {
            leave_type_id: Number(editLeaveForm.value.leave_type_id),
            date: editLeaveForm.value.date,
            notes: editLeaveForm.value.notes || null
        });
        toast.success('Leave record updated successfully.');
        onLeaveDialogView.value = 'list';
        showOnLeaveModal.value = false;
        refreshAttendanceCards();
    } catch (err: unknown) {
        const e = err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } };
        const msg = e.response?.data?.message ?? 'Failed to update leave.';
        const errors = e.response?.data?.errors;
        leaveFormError.value = errors ? Object.values(errors).flat().join(' ') : msg;
    } finally {
        leaveFormSubmitting.value = false;
    }
};

const editOvertime = (record: { id: number; employee_name: string; date: string }) => {
    editOvertimeForm.value = {
        id: record.id,
        employee_name: record.employee_name,
        date: record.date
    };
    overtimeFormError.value = '';
    overtimeDialogView.value = 'edit';
};

const openConfirmDeleteOvertime = (record: { id: number; employee_name: string; date: string }) => {
    confirmDeleteOvertimeRecord.value = { id: record.id, name: record.employee_name, date: record.date };
    overtimeDialogView.value = 'confirmDelete';
};

const confirmDeleteOvertime = async () => {
    if (!confirmDeleteOvertimeRecord.value) return;
    try {
        await api.delete(`/employee-overtime/${confirmDeleteOvertimeRecord.value.id}`);
        toast.success('Overtime record deleted successfully.');
        overtimeDialogView.value = 'list';
        confirmDeleteOvertimeRecord.value = null;
        showOvertimeModal.value = false;
        refreshAttendanceCards();
    } catch (err: unknown) {
        const e = err as { response?: { data?: { message?: string } } };
        alert(e.response?.data?.message ?? 'Failed to delete overtime record.');
    }
};

const backToOvertimeList = () => {
    overtimeFormError.value = '';
    overtimeDialogView.value = 'list';
};

const addOnLeave = () => {
    leaveFormError.value = '';
    onLeaveDialogView.value = 'add';
};

const addOvertime = () => {
    overtimeFormError.value = '';
    overtimeDialogView.value = 'add';
};

function parseDateRange(start: string, end: string): string[] {
    const dates: string[] = [];
    const startD = new Date(start + 'T12:00:00');
    const endD = new Date(end + 'T12:00:00');
    if (startD.getTime() > endD.getTime()) return dates;
    const current = new Date(startD);
    while (current.getTime() <= endD.getTime()) {
        dates.push(toYmdLocal(current));
        current.setDate(current.getDate() + 1);
    }
    return dates;
}

const submitLeaveForm = async () => {
    const { employee_id, leave_type_id, startDate, endDate, notes } = newLeaveForm.value;
    const eid = Number(employee_id);
    const ltid = Number(leave_type_id);
    if (!eid || !ltid || !startDate) {
        leaveFormError.value = 'Please select employee, leave type, and start date.';
        return;
    }
    const end = endDate || startDate;
    const dates = parseDateRange(startDate, end);
    if (dates.length === 0) {
        leaveFormError.value = 'Invalid date range.';
        return;
    }
    leaveFormError.value = '';
    leaveFormSubmitting.value = true;
    try {
        for (const date of dates) {
            await api.post('/employee-leaves', {
                employee_id: eid,
                leave_type_id: ltid,
                date,
                notes: notes || null
            });
        }
        const dayCount = dates.length;
        toast.success(`Leave record${dayCount > 1 ? 's' : ''} added successfully (${dayCount} ${dayCount === 1 ? 'day' : 'days'}).`);
        resetLeaveForm();
        onLeaveDialogView.value = 'list';
        showOnLeaveModal.value = false;
        refreshAttendanceCards();
    } catch (err: unknown) {
        const e = err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } };
        const msg = e.response?.data?.message ?? 'Failed to add leave.';
        const errors = e.response?.data?.errors;
        leaveFormError.value = errors ? Object.values(errors).flat().join(' ') : msg;
    } finally {
        leaveFormSubmitting.value = false;
    }
};

const overtimeFormSubmitting = ref(false);
const overtimeFormError = ref('');

const submitOvertimeForm = async () => {
    const eid = Number(newOvertimeForm.value.employee_id);
    const date = newOvertimeForm.value.date;
    if (!eid || !date) {
        overtimeFormError.value = 'Please select employee and date.';
        return;
    }
    overtimeFormError.value = '';
    overtimeFormSubmitting.value = true;
    try {
        await api.post('/employee-overtime', { employee_id: eid, date });
        toast.success('Overtime record added successfully.');
        resetOvertimeForm();
        overtimeDialogView.value = 'list';
        showOvertimeModal.value = false;
        refreshAttendanceCards();
    } catch (err: unknown) {
        const e = err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } };
        const msg = e.response?.data?.message ?? 'Failed to add overtime.';
        const errors = e.response?.data?.errors;
        overtimeFormError.value = errors ? Object.values(errors).flat().join(' ') : msg;
    } finally {
        overtimeFormSubmitting.value = false;
    }
};

const submitEditOvertimeForm = async () => {
    if (!editOvertimeForm.value.id || !editOvertimeForm.value.date) {
        overtimeFormError.value = 'Please fill in date.';
        return;
    }
    overtimeFormError.value = '';
    overtimeFormSubmitting.value = true;
    try {
        await api.put(`/employee-overtime/${editOvertimeForm.value.id}`, { date: editOvertimeForm.value.date });
        toast.success('Overtime record updated successfully.');
        overtimeDialogView.value = 'list';
        showOvertimeModal.value = false;
        refreshAttendanceCards();
    } catch (err: unknown) {
        const e = err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } };
        const msg = e.response?.data?.message ?? 'Failed to update overtime.';
        const errors = e.response?.data?.errors;
        overtimeFormError.value = errors ? Object.values(errors).flat().join(' ') : msg;
    } finally {
        overtimeFormSubmitting.value = false;
    }
};

const resetLeaveForm = () => {
    newLeaveForm.value = {
        employee_id: '',
        leave_type_id: '',
        startDate: '',
        endDate: '',
        notes: ''
    };
    leaveFormError.value = '';
};

const resetOvertimeForm = () => {
    newOvertimeForm.value = {
        employee_id: '',
        date: ''
    };
    overtimeFormError.value = '';
};

// Employee detail modal handlers
const loadEmployeeWarningsFor = async (employeeId: number) => {
    selectedEmployeeWarningsLoading.value = true;
    selectedEmployeeWarningsError.value = null;
    selectedEmployeeWarnings.value = [];
    try {
        const response = await api.get('/employee-warnings-memos', {
            params: {
                employee_id: employeeId,
                resolved: 'false',
            },
        });
        const list = (response.data?.warning_memos ?? []) as Array<{
            id: number;
            type: string;
            reason_type: string;
            notes: string | null;
            acknowledged_at: string | null;
            resolved_at: string | null;
            created_at: string;
        }>;
        selectedEmployeeWarnings.value = list.map((wm) => ({
            id: wm.id,
            type: wm.type,
            reason_type: wm.reason_type,
            notes: wm.notes,
            acknowledged_at: wm.acknowledged_at,
            resolved_at: wm.resolved_at,
            created_at: wm.created_at,
        }));
    } catch (err: unknown) {
        const e = err as { response?: { data?: { message?: string } } };
        selectedEmployeeWarningsError.value = e.response?.data?.message ?? 'Failed to load warning/memo records.';
        toast.error(selectedEmployeeWarningsError.value);
        selectedEmployeeWarnings.value = [];
    } finally {
        selectedEmployeeWarningsLoading.value = false;
    }
};

const viewEmployeeDetails = async (employee: EmployeeWarning) => {
    selectedEmployee.value = employee;
    showEmployeeDetailModal.value = true;
    await loadEmployeeWarningsFor(employee.id);
};

const getStatusDescription = (status: string) => {
    switch (status) {
        case 'memorandum':
            return 'This employee has exceeded monthly attendance thresholds and requires formal disciplinary action through a written memorandum.';
        case 'warning':
            return 'This employee is approaching monthly attendance limits and should receive a verbal warning and monitoring.';
        default:
            return 'Employee attendance is within acceptable limits.';
    }
};

const getRecommendedActions = (status: string) => {
    switch (status) {
        case 'memorandum':
            return [
                'Issue formal written memorandum',
                'Schedule disciplinary meeting',
                'Document in employee file',
                'Set improvement timeline',
                'Consider probationary period'
            ];
        case 'warning':
            return [
                'Conduct verbal counseling session',
                'Review attendance policy',
                'Set clear expectations',
                'Monitor closely for 30 days',
                'Provide support if needed'
            ];
        default:
            return ['Continue regular monitoring'];
    }
};

const pieChartData = computed(() => ({
    labels: ['Present', 'Late', 'On Leave'],
    datasets: [{
        data: [
            teamAttendanceData.value.present,
            teamAttendanceData.value.late,
            teamAttendanceData.value.onLeave
        ],
        backgroundColor: [
            'rgba(34, 197, 94, 0.85)',   // Green for Present
            'rgba(249, 115, 22, 0.85)',  // Orange for Late
            'rgba(59, 130, 246, 0.85)'   // Blue for On Leave
        ],
        borderColor: [
            'rgba(34, 197, 94, 1)',
            'rgba(249, 115, 22, 1)',
            'rgba(59, 130, 246, 1)'
        ],
        borderWidth: 2,
        hoverOffset: 12,
        hoverBorderWidth: 3
    }]
}));

const pieChartOptions = ref({
    responsive: true,
    maintainAspectRatio: false,
    cutout: '55%', // Doughnut style – less plain than full pie
    plugins: {
        legend: {
            position: 'bottom' as const,
            labels: {
                padding: 24,
                usePointStyle: true,
                pointStyle: 'circle',
                font: {
                    size: 14,
                    weight: 600 as any
                },
                color: '#374151',
                boxWidth: 12,
                boxHeight: 12
            }
        },
        tooltip: {
            backgroundColor: 'rgba(17, 24, 39, 0.95)',
            titleColor: '#f9fafb',
            bodyColor: '#f9fafb',
            borderColor: 'rgba(75, 85, 99, 0.4)',
            borderWidth: 1,
            cornerRadius: 8,
            padding: 14,
            displayColors: true,
            callbacks: {
                label: (context: { label: string; parsed: number }) => {
                    const label = context.label;
                    const value = context.parsed;
                    const total = teamAttendanceData.value.present + teamAttendanceData.value.late +
                                 teamAttendanceData.value.onLeave;
                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : '0';
                    return `${label}: ${value} employees (${percentage}%)`;
                }
            }
        },
        title: {
            display: true,
            text: "Today's status",
            font: { size: 14 },
            color: '#6b7280',
            padding: { bottom: 12 }
        }
    }
});

// Weekly team status bar chart (Present, Late, On Leave per day)
const weeklyStatusBarData = computed(() => {
    const data = weeklyStatusCounts.value;
    if (!data.length) {
        return {
            labels: [],
            datasets: [],
        };
    }

    const labels = data.map((d) => d.label || d.date);

    return {
        labels,
        datasets: [
            {
                label: 'Present',
                data: data.map((d) => d.present),
                backgroundColor: 'rgba(34, 197, 94, 0.85)', // Green
            },
            {
                label: 'Late',
                data: data.map((d) => d.late),
                backgroundColor: 'rgba(249, 115, 22, 0.85)', // Orange
            },
            {
                label: 'On Leave',
                data: data.map((d) => d.on_leave),
                backgroundColor: 'rgba(59, 130, 246, 0.85)', // Blue
            },
        ],
    };
});

// Summary line for weekly status chart (similar to pie chart summary)
const weeklyStatusSummaryLine = computed(() => {
    const data = weeklyStatusCounts.value;
    if (!data.length) {
        return 'No attendance data for the last 7 working days.';
    }

    const totals = data.reduce(
        (acc, day) => {
            acc.present += day.present;
            acc.late += day.late;
            acc.onLeave += day.on_leave;
            return acc;
        },
        { present: 0, late: 0, onLeave: 0 }
    );

    const segments: string[] = [];
    if (totals.present > 0) segments.push(`${totals.present} present`);
    if (totals.late > 0) segments.push(`${totals.late} late`);
    if (totals.onLeave > 0) segments.push(`${totals.onLeave} on leave`);

    const main =
        segments.length > 0 ? segments.join(', ') : 'No attendance activity recorded';

    return `${main} · Last 7 working days (excluding Sundays)`;
});

// Biometric logs: bar chart data (this month's scans per day) – bar is clearer than line for counts
const biometricBarData = computed(() => {
    const logs = biometricLogsList.value;
    if (!logs.length) {
        return {
            labels: [],
            datasets: [],
        };
    }

    const bucket: Record<string, number> = {};
    for (const log of logs) {
        const dateLabel = new Date(log.date + 'T12:00:00').toLocaleDateString('en-US', {
            month: 'short',
            day: '2-digit',
        });
        bucket[dateLabel] = (bucket[dateLabel] ?? 0) + 1;
    }

    const labels: string[] = [];
    const counts: number[] = [];
    Object.keys(bucket)
        .sort()
        .forEach((key) => {
            labels.push(key);
            counts.push(bucket[key]);
        });

    return {
        labels,
        datasets: [
            {
                label: 'Scans per day',
                data: counts,
                backgroundColor: 'rgba(59, 130, 246, 0.85)',
            },
        ],
    };
});

const biometricBarOptions = ref({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: true,
            position: 'top' as const,
        },
        tooltip: {
            mode: 'index' as const,
            intersect: false,
        },
    },
    scales: {
        x: {
            title: {
                display: true,
                text: 'Day',
            },
        },
        y: {
            beginAtZero: true,
            ticks: {
                precision: 0,
                stepSize: 1,
            },
            title: {
                display: true,
                text: 'Number of scans',
            },
        },
    },
});

const weeklyStatusBarOptions = ref({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'top' as const,
        },
        tooltip: {
            mode: 'index' as const,
            intersect: false,
        },
    },
    scales: {
        x: {
            stacked: false,
        },
        y: {
            beginAtZero: true,
            ticks: {
                precision: 0,
            },
        },
    },
});

// Weekly hours from backend (last 7 days); fallback to empty for chart
const weeklyHoursData = computed(() => {
    const raw = props.weeklyHours ?? [];
    if (raw.length === 0) {
        return Array.from({ length: 7 }, (_, i) => {
            const d = new Date();
            d.setDate(d.getDate() - (6 - i));
            return {
                day: d.toLocaleDateString('en-US', { weekday: 'short' }),
                hours: 0,
                date: toYmdLocal(d)
            };
        });
    }
    return raw.map((row) => ({
        day: row.day,
        hours: row.hours,
        date: row.date
    }));
});

// Total hours this week (for card subtitle, rounded to 2 decimals)
const weeklyHoursTotalRaw = computed(() =>
    weeklyHoursData.value.reduce((sum, d) => sum + (d.hours ?? 0), 0)
);
const weeklyHoursTotal = computed(() =>
    Number.isFinite(weeklyHoursTotalRaw.value)
        ? Number(weeklyHoursTotalRaw.value.toFixed(2))
        : 0
);

// Chart configuration (driven by weeklyHoursData from backend)
const chartData = computed(() => ({
    labels: weeklyHoursData.value.map(d => d.day),
    datasets: [{
        label: 'Hours Worked',
        data: weeklyHoursData.value.map(d => d.hours),
        backgroundColor: weeklyHoursData.value.map(d =>
            (d.hours ?? 0) === 0 ? 'rgba(156, 163, 175, 0.6)' : 'rgba(59, 130, 246, 0.8)'
        ),
        borderColor: weeklyHoursData.value.map(d =>
            (d.hours ?? 0) === 0 ? 'rgba(156, 163, 175, 1)' : 'rgba(59, 130, 246, 1)'
        ),
        borderWidth: 2,
        borderRadius: 6,
        borderSkipped: false,
    }]
}));

const chartOptions = ref({
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
            displayColors: false,
            callbacks: {
                title: (context: any) => {
                    const index = context[0].dataIndex;
                    const data = weeklyHoursData.value[index];
                    const dateStr = data?.date ? new Date(data.date + 'T12:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) : '';
                    return `${data?.day ?? ''} (${dateStr})`;
                },
                label: (context: any) => {
                    const hours = context.parsed.y;
                    return hours === 0 ? 'No work day' : `${hours} hours worked`;
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
                    size: 12
                },
                callback: function(value: any) {
                    return value + 'h';
                }
            },
            grid: {
                color: 'rgba(156, 163, 175, 0.2)',
                drawBorder: false
            },
            title: {
                display: true,
                text: 'Hours',
                color: '#6b7280',
                font: {
                    size: 13,
                    weight: 600 as any
                }
            }
        },
        x: {
            ticks: {
                color: '#9ca3af',
                font: {
                    size: 12
                }
            },
            grid: {
                display: false
            },
            title: {
                display: true,
                text: 'Day',
                color: '#6b7280',
                font: {
                    size: 13,
                    weight: 600 as any
                }
            }
        }
    }
});

// Format time from backend (H:i:s or H:i) to "08:30 AM" (Manila / 12h display)
function formatTimeDisplay(t: string | null | undefined): string {
    if (!t) return '-';
    const parts = t.split(':');
    const h = parseInt(parts[0], 10);
    const m = parts[1] ? parseInt(parts[1], 10) : 0;
    if (Number.isNaN(h)) return '-';
    const period = h >= 12 ? 'PM' : 'AM';
    const h12 = h % 12 || 12;
    return `${h12}:${m.toString().padStart(2, '0')} ${period}`;
}

// DTR records from backend (myAttendance.data) – already latest-first from API
const dtrRecords = computed(() => {
    const data = props.myAttendance?.data ?? [];
    return data.map((row: {
        id: number;
        date: string;
        time_in?: string | null;
        time_out?: string | null;
        morning_time_in: string | null;
        morning_time_out: string | null;
        afternoon_time_in: string | null;
        afternoon_time_out: string | null;
        overtime_time_in: string | null;
        overtime_time_out: string | null;
        status: string | null;
    }) => {
        const d = new Date(row.date + 'T12:00:00');
        const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        // Prefer authoritative leave/absent info from backend summaries
        const isLeaveDate = myLeaveDates.value.includes(row.date);
        const isAbsentDate = myAbsentDates.value.includes(row.date);
        const hasNoTime = !(row.morning_time_in || row.morning_time_out || row.afternoon_time_in || row.afternoon_time_out || row.time_in || row.time_out);
        let normalizedStatus = (row.status ?? '').toLowerCase();
        if (isLeaveDate) {
            normalizedStatus = 'leave';
        } else if (isAbsentDate || (hasNoTime && normalizedStatus !== 'leave')) {
            normalizedStatus = 'absent';
        }

        // Debug: log raw vs parsed dates to help spot any parsing mismatches
        // between backend (Y-m-d) and frontend Date handling.
        console.log('[DTR DEBUG]', {
            rawDate: row.date,
            jsDateDefault: new Date(row.date).toISOString(),
            jsDateWithNoon: d.toISOString(),
            toYmdLocalFromNoon: toYmdLocal(d),
            statusFromBackend: row.status,
            normalizedStatus,
            isAbsentDate,
            hasNoTime,
        });

        // When status is leave, we don't want to show any times in the DTR
        const isLeaveRow = normalizedStatus === 'leave';

        return {
            id: row.id,
            date: row.date,
            day: dayNames[d.getDay()],
            morning: {
                timeIn: isLeaveRow ? '-' : formatTimeDisplay(row.morning_time_in),
                timeOut: isLeaveRow ? '-' : formatTimeDisplay(row.morning_time_out)
            },
            afternoon: {
                timeIn: isLeaveRow ? '-' : formatTimeDisplay(row.afternoon_time_in),
                timeOut: isLeaveRow ? '-' : formatTimeDisplay(row.afternoon_time_out)
            },
            overtime: {
                timeIn: isLeaveRow ? '-' : formatTimeDisplay(row.overtime_time_in),
                timeOut: isLeaveRow ? '-' : formatTimeDisplay(row.overtime_time_out)
            },
            status: normalizedStatus
        };
    });
});

const dtrMeta = computed(() => props.myAttendance?.meta ?? { current_page: 1, last_page: 1, per_page: 15, total: 0, from: null, to: null });

const getStatusColor = (status: string) => {
    switch (status) {
        case 'present': return 'bg-green-500 hover:bg-green-600 text-white';
        case 'late': return 'bg-orange-500 hover:bg-orange-600 text-white';
        case 'absent': return 'bg-red-500 hover:bg-red-600 text-white';
        case 'leave': return 'bg-violet-500 hover:bg-violet-600 text-white';
        case 'holiday': return 'bg-blue-500 hover:bg-blue-600 text-white';
        case 'weekend': return 'bg-gray-500 hover:bg-gray-600 text-white';
        default: return '';
    }
};

// Generate DTR PDF: dialog with employee/month/year selection
const canGenerateDTR = computed(() => Boolean(props.currentUser?.id));
const showGenerateDTRModal = ref(false);
// Holidays dialog (Everyone tab): list + add/edit/delete
const showHolidaysModal = ref(false);
const holidaysList = ref<Array<{ id: number; name: string; date: string }>>([]);
const holidaysLoading = ref(false);
const holidayForm = ref({ name: '', date: '' });
const holidayEditId = ref<number | null>(null);
const holidayFormSubmitting = ref(false);

const openHolidaysDialog = async () => {
    showHolidaysModal.value = true;
    holidayEditId.value = null;
    holidayForm.value = { name: '', date: '' };
    await fetchHolidays();
};

const fetchHolidays = async () => {
    holidaysLoading.value = true;
    try {
        const { data } = await api.get<{ holidays: Array<{ id: number; name: string; date: string }> }>('/holidays');
        holidaysList.value = data.holidays ?? [];
    } catch {
        toast.error('Failed to load holidays');
        holidaysList.value = [];
    } finally {
        holidaysLoading.value = false;
    }
};

const saveHoliday = async () => {
    const name = (holidayForm.value.name || '').trim();
    const date = (holidayForm.value.date || '').trim();
    if (!name || !date) {
        toast.error('Name and date are required');
        return;
    }
    holidayFormSubmitting.value = true;
    try {
        if (holidayEditId.value != null) {
            await api.put(`/holidays/${holidayEditId.value}`, { name, date });
            toast.success('Holiday updated');
        } else {
            await api.post('/holidays', { name, date });
            toast.success('Holiday added');
        }
        holidayEditId.value = null;
        holidayForm.value = { name: '', date: '' };
        await fetchHolidays();
    } catch (e: unknown) {
        const msg = (e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } })?.response?.data;
        const message = msg?.message || (msg?.errors ? Object.values(msg.errors).flat().join(' ') : 'Failed to save holiday');
        toast.error(message);
    } finally {
        holidayFormSubmitting.value = false;
    }
};

const startEditHoliday = (h: { id: number; name: string; date: string }) => {
    holidayEditId.value = h.id;
    holidayForm.value = { name: h.name, date: h.date };
};

const deleteHoliday = async (h: { id: number; name: string; date: string }) => {
    if (!confirm(`Delete holiday "${h.name}" (${h.date})?`)) return;
    try {
        await api.delete(`/holidays/${h.id}`);
        toast.success('Holiday deleted');
        await fetchHolidays();
        if (holidayEditId.value === h.id) {
            holidayEditId.value = null;
            holidayForm.value = { name: '', date: '' };
        }
    } catch {
        toast.error('Failed to delete holiday');
    }
};

const cancelHolidayForm = () => {
    holidayEditId.value = null;
    holidayForm.value = { name: '', date: '' };
};

const selectedDTRMonth = ref(new Date().getMonth() + 1);
const selectedDTRYear = ref(new Date().getFullYear());
const selectedDTREmployeeId = ref<string>('');
// When true, Generate DTR is for the current user (My Attendance tab).
// When false (admin in Everyone tab), user selects an employee.
const generateDTRForCurrentUser = ref(false);

const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
const dtrYearOptions = computed(() => {
    const y = new Date().getFullYear();
    return Array.from({ length: 10 }, (_, i) => y - 5 + i);
});

// Open Generate DTR for the current user (My Attendance tab)
const openGenerateDTRForSelf = () => {
    if (!canGenerateDTR.value) return;
    generateDTRForCurrentUser.value = true;
    selectedDTRMonth.value = new Date().getMonth() + 1;
    selectedDTRYear.value = new Date().getFullYear();
    if (props.currentUser?.id) {
        selectedDTREmployeeId.value = String(props.currentUser.id);
    }
    showGenerateDTRModal.value = true;
};

// Open Generate DTR for a selected employee (Everyone tab, admin only)
const openGenerateDTRForEmployee = () => {
    if (!canGenerateDTR.value || !isAdmin.value) return;
    generateDTRForCurrentUser.value = false;
    selectedDTRMonth.value = new Date().getMonth() + 1;
    selectedDTRYear.value = new Date().getFullYear();
    selectedDTREmployeeId.value = '';
    showGenerateDTRModal.value = true;
};

const confirmGenerateDTR = () => {
    let targetEmployeeId: number | null = null;
    if (generateDTRForCurrentUser.value || !isAdmin.value) {
        // Always current user (My Attendance), regardless of admin status
        if (!props.currentUser?.id) return;
        targetEmployeeId = props.currentUser.id;
    } else {
        // Admin generating for another employee from Everyone tab
        const eid = Number(selectedDTREmployeeId.value);
        if (!eid) {
            alert('Please select an employee.');
            return;
        }
        targetEmployeeId = eid;
    }
    const url = `${window.location.origin}/api/attendance/${targetEmployeeId}/export-pdf?month=${selectedDTRMonth.value}&year=${selectedDTRYear.value}`;
    window.open(url, '_blank');
    showGenerateDTRModal.value = false;
};

// DTR table: change month/year filter (reload with start_date, end_date)
const dtrFilterMonth = ref<string>('1');
const dtrFilterYear = ref<string>(String(new Date().getFullYear()));

const applyDTRFilter = () => {
    const month = parseInt(dtrFilterMonth.value, 10) || new Date().getMonth() + 1;
    const year = parseInt(dtrFilterYear.value, 10) || new Date().getFullYear();
    const start = `${year}-${String(month).padStart(2, '0')}-01`;
    const lastDay = new Date(year, month, 0).getDate();
    const end = `${year}-${String(month).padStart(2, '0')}-${String(lastDay).padStart(2, '0')}`;
    router.get('/attendance', { start_date: start, end_date: end, my_attendance_page: 1 });
};

// All employees attendance table (My Attendance tab, admin only): filters and pagination
const teamTableRange = ref<'today' | 'week' | 'month'>('week');
const teamSearchInput = ref('');
// Use 'all' as sentinel for "All statuses" (SelectItem cannot have empty string value)
const teamStatusFilter = ref<'all' | string>('all');

const applyTeamTableFilter = () => {
    const now = new Date();
    const todayStr = toYmdLocal(now);
    const params: Record<string, string | number> = {
        start_date: props.dtrStartDate ?? todayStr,
        end_date: props.dtrEndDate ?? todayStr,
        my_attendance_page: 1,
        team_attendance_page: 1,
        team_search: teamSearchInput.value.trim() || '',
        team_status: teamStatusFilter.value === 'all' ? '' : (teamStatusFilter.value || ''),
    };
    if (teamTableRange.value === 'today') {
        params.team_start_date = todayStr;
        params.team_end_date = todayStr;
    } else if (teamTableRange.value === 'week') {
        params.team_start_date = getStartOfWeekYmd(now);
        params.team_end_date = todayStr;
    } else {
        const y = now.getFullYear();
        const m = String(now.getMonth() + 1).padStart(2, '0');
        params.team_start_date = `${y}-${m}-01`;
        params.team_end_date = todayStr;
    }
    router.get('/attendance', params);
};

const teamTableMeta = computed(() => props.teamAttendance?.meta ?? { current_page: 1, last_page: 1, per_page: 15, total: 0, from: null, to: null });

const goToTeamTablePage = (page: number) => {
    const now = new Date();
    const todayStr = toYmdLocal(now);
    const params: Record<string, string | number> = {
        start_date: props.dtrStartDate ?? todayStr,
        end_date: props.dtrEndDate ?? todayStr,
        my_attendance_page: 1,
        team_attendance_page: page,
        team_search: (props.teamSearch ?? '').toString(),
        team_status: (props.teamStatus ?? '').toString(),
    };
    params.team_start_date = (props.teamTableStartDate ?? todayStr).toString();
    params.team_end_date = (props.teamTableEndDate ?? todayStr).toString();
    router.get('/attendance', params);
};

// Sync team table filter state from props (e.g. after apply or page load)
function syncTeamTableFiltersFromProps() {
    const start = props.teamTableStartDate ?? null;
    const end = props.teamTableEndDate ?? null;
    const now = new Date();
    const todayStr = toYmdLocal(now);
    const startOfWeekStr = getStartOfWeekYmd(now);
    const firstOfMonthStr = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-01`;
    if (start && end) {
        if (start === todayStr && end === todayStr) {
            teamTableRange.value = 'today';
        } else if (start === startOfWeekStr && end === todayStr) {
            teamTableRange.value = 'week';
        } else if (start === firstOfMonthStr && end === todayStr) {
            teamTableRange.value = 'month';
        } else {
            teamTableRange.value = 'month';
        }
    }
    teamSearchInput.value = (props.teamSearch ?? '').toString();
    const statusFromProps = (props.teamStatus ?? '').toString();
    teamStatusFilter.value = statusFromProps === '' ? 'all' : statusFromProps;
}
onMounted(syncTeamTableFiltersFromProps);
watch(
    () => [props.teamTableStartDate, props.teamTableEndDate, props.teamSearch, props.teamStatus],
    syncTeamTableFiltersFromProps
);

watch(showOnLeaveModal, (open) => {
    if (!open) onLeaveDialogView.value = 'list';
});

watch(showOvertimeModal, (open) => {
    if (!open) overtimeDialogView.value = 'list';
});

const dtrPeriodLabel = computed(() => {
    if (props.dtrStartDate && props.dtrEndDate) {
        const start = new Date(props.dtrStartDate + 'T12:00:00');
        const monthName = start.toLocaleString('default', { month: 'long' });
        const year = start.getFullYear();
        return `${monthName} ${year}`;
    }
    return `${currentMonth} ${currentYear}`;
});

const goToDTRPage = (page: number) => {
    const params: Record<string, string | number> = { my_attendance_page: page };
    if (props.dtrStartDate) params.start_date = props.dtrStartDate;
    if (props.dtrEndDate) params.end_date = props.dtrEndDate;
    router.get('/attendance', params);
};

// Sync DTR filter dropdowns from props (current period)
watch(() => [props.dtrStartDate, props.dtrEndDate], ([start]) => {
    if (start && typeof start === 'string') {
        const parts = start.split('-');
        if (parts.length >= 2) {
            dtrFilterMonth.value = parts[1];
            dtrFilterYear.value = parts[0];
        }
    }
}, { immediate: true });

// --- ATTENDANCE DEBUG: copy the output below to share ---
function getAttendanceDebugPayload() {
    const nav = typeof performance !== 'undefined' && performance.getEntriesByType ? performance.getEntriesByType('navigation')[0] : null;
    const loadType = (nav as PerformanceNavigationTiming & { type?: string })?.type ?? 'unknown';
    const summary = props.teamAttendanceSummary as Record<string, unknown> | undefined;
    const summaryWarningEmployees = summary?.warning_employees as Array<Record<string, unknown>> | undefined;
    const summaryMemoEmployees = summary?.memo_employees as Array<Record<string, unknown>> | undefined;
    const payload = {
        _instruction: 'Copy this entire object (from { to }) and paste to share with developer.',
        timestamp: new Date().toISOString(),
        loadType: loadType === 'navigate' ? 'navigate (clicked to Attendance)' : loadType === 'reload' ? 'reload (F5 / refresh)' : loadType,
        // 1. My Attendance – Daily Time Record
        myAttendance: {
            dtrStartDate: props.dtrStartDate,
            dtrEndDate: props.dtrEndDate,
            dataLength: props.myAttendance?.data?.length ?? 0,
            meta: props.myAttendance?.meta ?? null,
            datesInData: props.myAttendance?.data?.length ? props.myAttendance!.data.map((r: { date: string }) => r.date).slice(0, 20) : [],
            datesInDataLast: props.myAttendance?.data?.length ? props.myAttendance!.data.map((r: { date: string }) => r.date).slice(-5) : [],
        },
        dtrRecordsLength: dtrRecords.value.length,
        // 2. Everyone tab – This Week's Team Status & All Employees Attendance
        everyone: {
            teamTableStartDate: props.teamTableStartDate,
            teamTableEndDate: props.teamTableEndDate,
            teamAttendanceDataLength: props.teamAttendance?.data?.length ?? 0,
            teamAttendanceMeta: props.teamAttendance?.meta ?? null,
            teamAttendanceDatesSample: props.teamAttendance?.data?.length ? [...new Set(props.teamAttendance!.data.map((r: { date: string }) => r.date))].sort().slice(0, 15) : [],
            weeklyStatusCounts: weeklyStatusCounts.value,
            teamAttendanceSummaryKeys: summary ? Object.keys(summary) : [],
            today_status_counts: summary?.today_status_counts ?? null,
        },
        // 3. Warning tab
        warning: {
            warning_employees_count: summaryWarningEmployees?.length ?? 0,
            memo_employees_count: summaryMemoEmployees?.length ?? 0,
            employeeWarningsLength: employeeWarnings.value.length,
            memorandumWarningsLength: memorandumWarnings.value.length,
            warningEmployeesLength: warningEmployees.value.length,
            memorandumWarningsSample: memorandumWarnings.value.slice(0, 2).map((e: EmployeeWarning) => ({ id: e.id, name: e.name, warning_memo_ids: e.warning_memo_ids })),
            warningEmployeesSample: warningEmployees.value.slice(0, 2).map((e: EmployeeWarning) => ({ id: e.id, name: e.name, warning_memo_ids: e.warning_memo_ids })),
        },
    };
    return payload;
}
function logAttendanceDebug() {
    const payload = getAttendanceDebugPayload();
    console.log('%c--- ATTENDANCE DEBUG (copy below) ---', 'font-weight:bold; font-size:12px;');
    console.log(JSON.stringify(payload, null, 2));
    console.log('%c--- END ATTENDANCE DEBUG ---', 'font-weight:bold; font-size:12px;');
}
onMounted(() => { logAttendanceDebug(); });
// Re-log when date range or data length changes (e.g. after filter or Inertia reload)
watch(
    () => [
        props.dtrStartDate,
        props.dtrEndDate,
        props.teamTableStartDate,
        props.teamTableEndDate,
        props.myAttendance?.data?.length,
        props.teamAttendance?.data?.length,
    ],
    () => { logAttendanceDebug(); }
);
</script>

<template>
    <Head title="Attendance" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="relative min-h-[100vh] p-6 flex-1 border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border">
            <Tabs default-value="my-attendance" class="w-full">
                <TabsList :class="['grid w-full mb-6', isAdmin ? 'grid-cols-4' : 'grid-cols-1']">
                    <TabsTrigger value="my-attendance">
                        <div class="flex items-center justify-center gap-2">
                            <Calendar :size="16" class="shrink-0" />
                            <span class="hidden sm:inline">My Attendance</span>
                        </div>
                    </TabsTrigger>
                    <TabsTrigger v-if="isAdmin" value="everyone">
                        <div class="flex items-center justify-center gap-2">
                            <Users :size="16" class="shrink-0" />
                            <span class="hidden sm:inline">Everyone</span>
                        </div>
                    </TabsTrigger>
                    <TabsTrigger v-if="isAdmin" value="warning">
                        <div class="flex items-center justify-center gap-2">
                            <AlertTriangle :size="16" class="shrink-0" />
                            <span class="hidden sm:inline">Warning</span>
                        </div>
                    </TabsTrigger>
                    <TabsTrigger v-if="isAdmin" value="biometric">
                        <div class="flex items-center justify-center gap-2">
                            <Clock :size="16" class="shrink-0" />
                            <span class="hidden sm:inline">Biometric Logs</span>
                        </div>
                    </TabsTrigger>
                </TabsList>

                <!-- My Attendance Tab -->
                <TabsContent value="my-attendance">
                    <div class="space-y-6">
                        <!-- User Greeting Header with Warning -->
                        <div class="flex items-start justify-between gap-6">
                            <div>
                                <h1 class="text-3xl font-semibold text-foreground">Hello, {{ props.currentUser?.name ?? 'User' }}</h1>
                                <p class="text-muted-foreground mt-1">It's {{ currentMonth }} {{ currentDay }}, {{ currentYear }}</p>
                            </div>

                            <!-- Warning Message Box -->
                                <div class="flex-shrink-0 max-w-md">
                                <!-- Actual warning / memorandum (no Acknowledge – only admin can resolve from Warning tab) -->
                                <div v-if="currentUserWarning">
                                    <div v-if="currentUserWarning.level === 'memorandum'" 
                                         class="flex items-center justify-between gap-3 p-3 border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-950/20 rounded-lg">
                                        <div class="flex items-center gap-3 flex-1 min-w-0">
                                            <AlertTriangle :size="16" class="text-red-600 flex-shrink-0" />
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-2 mb-1">
                                                    <h4 class="font-medium text-sm text-red-800 dark:text-red-400">{{ currentUserWarning.title }}</h4>
                                                </div>
                                                <p class="text-xs text-red-600 dark:text-red-400 font-medium mb-1">{{ currentUserWarning.details }}</p>
                                                <p class="text-xs text-red-700 dark:text-red-300">{{ currentUserWarning.message }}</p>
                                            </div>
                                        </div>
                                        <Button 
                                            v-if="myActiveMemos.length"
                                            size="sm" 
                                            variant="outline"
                                            class="flex-shrink-0 h-7 px-3 text-xs border-red-300 text-red-700 hover:bg-red-100 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-900/30"
                                            @click="openMemoPdf(myActiveMemos[0].id)"
                                        >
                                            <Download :size="12" class="mr-1" />
                                            Generate Memorandum
                                        </Button>
                                    </div>
                                    
                                    <div v-else-if="currentUserWarning.level === 'warning'" 
                                         class="flex items-center justify-between p-3 border border-orange-200 dark:border-orange-800 bg-orange-50 dark:bg-orange-950/20 rounded-lg">
                                        <div class="flex items-center gap-3 flex-1">
                                            <AlertTriangle :size="16" class="text-orange-600 flex-shrink-0" />
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-2 mb-1">
                                                    <h4 class="font-medium text-sm text-orange-800 dark:text-orange-400">{{ currentUserWarning.title }}</h4>
                                                </div>
                                                <p class="text-xs text-orange-600 dark:text-orange-400 font-medium mb-1">{{ currentUserWarning.details }}</p>
                                                <p class="text-xs text-orange-700 dark:text-orange-300">{{ currentUserWarning.message }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Always-visible neutral info when no active warning/memo -->
                                <div v-else class="flex items-center gap-3 p-3 border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-950/20 rounded-lg">
                                    <div>
                                        <h4 class="font-medium text-sm text-emerald-800 dark:text-emerald-400">
                                            Attendance status: In good standing
                                        </h4>
                                        <p class="text-xs text-emerald-700 dark:text-emerald-300 mt-1">
                                            Keep your monthly lates and absences low to avoid warnings or memorandums.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Monthly Statistics Cards (clickable: show dates in modal) -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Total Late This Month -->
                            <Card
                                class="border-orange-200 dark:border-orange-800 hover:shadow-lg transition-shadow cursor-pointer hover:scale-[1.02] transform"
                                @click="showMyLateDatesModal = true"
                            >
                                <CardHeader class="pb-3">
                                    <CardTitle class="flex items-center gap-3 text-orange-700 dark:text-orange-400">
                                        <div class="p-2 bg-orange-100 dark:bg-orange-900/30 rounded-lg">
                                            <Clock :size="20" />
                                        </div>
                                        <div>
                                            <div class="text-lg font-semibold">Late Arrivals</div>
                                            <div class="text-sm text-muted-foreground font-normal">This {{ currentMonth }}</div>
                                        </div>
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div class="flex items-center justify-between">
                                        <div class="text-4xl font-bold text-orange-600 dark:text-orange-400">
                                            {{ monthlyAttendanceStats.totalLateThisMonth }}
                                        </div>
                                        <div class="text-right text-sm text-muted-foreground">
                                            <div>{{ monthlyAttendanceStats.totalLateThisMonth === 1 ? 'day' : 'days' }}</div>
                                            <div class="text-xs">out of {{ new Date().getDate() }} days</div>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            <!-- Total Absent This Month -->
                            <Card
                                class="border-red-200 dark:border-red-800 hover:shadow-lg transition-shadow cursor-pointer hover:scale-[1.02] transform"
                                @click="showMyAbsentDatesModal = true"
                            >
                                <CardHeader class="pb-3">
                                    <CardTitle class="flex items-center gap-3 text-red-700 dark:text-red-400">
                                        <div class="p-2 bg-red-100 dark:bg-red-900/30 rounded-lg">
                                            <UserX :size="20" />
                                        </div>
                                        <div>
                                            <div class="text-lg font-semibold">Absences</div>
                                            <div class="text-sm text-muted-foreground font-normal">This {{ currentMonth }}</div>
                                        </div>
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div class="flex items-center justify-between">
                                        <div class="text-4xl font-bold text-red-600 dark:text-red-400">
                                            {{ monthlyAttendanceStats.totalAbsentThisMonth }}
                                        </div>
                                        <div class="text-right text-sm text-muted-foreground">
                                            <div>{{ monthlyAttendanceStats.totalAbsentThisMonth === 1 ? 'day' : 'days' }}</div>
                                            <div class="text-xs">out of {{ new Date().getDate() }} days</div>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        <!-- My Attendance: Late dates modal -->
                        <Dialog :open="showMyLateDatesModal" @update:open="(open) => { if (!open) showMyLateDatesModal = false }">
                            <DialogContent class="sm:max-w-md bg-card text-foreground border-border">
                                <DialogHeader>
                                    <DialogTitle>Late Arrivals – {{ currentMonth }}</DialogTitle>
                                    <DialogDescription>Dates when you were marked late this month.</DialogDescription>
                                </DialogHeader>
                                <ScrollArea class="max-h-[50vh] pr-4">
                                    <ul class="space-y-2">
                                        <li
                                            v-for="dateStr in (myLateDates || [])"
                                            :key="dateStr"
                                            class="py-2 px-3 rounded-lg border border-border text-sm"
                                        >
                                            {{ new Date(dateStr + 'T12:00:00').toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' }) }}
                                        </li>
                                        <li v-if="!(myLateDates?.length)" class="text-muted-foreground text-sm py-4 text-center">
                                            No late dates this month.
                                        </li>
                                    </ul>
                                </ScrollArea>
                                <div class="flex justify-end pt-4 border-t border-border">
                                    <Button variant="outline" @click="showMyLateDatesModal = false">Close</Button>
                                </div>
                            </DialogContent>
                        </Dialog>

                        <!-- My Attendance: Absent dates modal -->
                        <Dialog :open="showMyAbsentDatesModal" @update:open="(open) => { if (!open) showMyAbsentDatesModal = false }">
                            <DialogContent class="sm:max-w-md bg-card text-foreground border-border">
                                <DialogHeader>
                                    <DialogTitle>Absences – {{ currentMonth }}</DialogTitle>
                                    <DialogDescription>Dates when you were marked absent this month.</DialogDescription>
                                </DialogHeader>
                                <ScrollArea class="max-h-[50vh] pr-4">
                                    <ul class="space-y-2">
                                        <li
                                            v-for="dateStr in (myAbsentDates || [])"
                                            :key="dateStr"
                                            class="py-2 px-3 rounded-lg border border-border text-sm"
                                        >
                                            {{ new Date(dateStr + 'T12:00:00').toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' }) }}
                                        </li>
                                        <li v-if="!(myAbsentDates?.length)" class="text-muted-foreground text-sm py-4 text-center">
                                            No absent dates this month.
                                        </li>
                                    </ul>
                                </ScrollArea>
                                <div class="flex justify-end pt-4 border-t border-border">
                                    <Button variant="outline" @click="showMyAbsentDatesModal = false">Close</Button>
                                </div>
                            </DialogContent>
                        </Dialog>

                        <!-- Weekly Hours Graph (data from backend) -->
                        <Card>
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2">
                                    <Clock :size="20" />
                                    Weekly Hours Overview
                                </CardTitle>
                                <CardDescription>
                                    Hours worked over the past 7 days
                                    <span v-if="weeklyHoursTotal > 0" class="font-medium text-foreground">
                                        — Total: {{ weeklyHoursTotal }}h this week
                                    </span>
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div style="height: 300px;">
                                    <Bar :data="chartData" :options="chartOptions" />
                                </div>
                            </CardContent>
                        </Card>

                        <!-- DTR Table (from database, latest first) -->
                        <Card>
                            <CardHeader>
                                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <CardTitle class="flex items-center gap-2">
                                            <Calendar :size="20" />
                                            {{ dtrPeriodLabel }} - Daily Time Record
                                        </CardTitle>
                                        <CardDescription>Attendance records for the selected period</CardDescription>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <Select v-model="dtrFilterMonth">
                                            <SelectTrigger class="w-[130px]">
                                                <SelectValue placeholder="Month" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="(name, i) in monthNames" :key="i" :value="String(i + 1)">
                                                    {{ name }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <Select v-model="dtrFilterYear">
                                            <SelectTrigger class="w-[100px]">
                                                <SelectValue placeholder="Year" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="y in dtrYearOptions" :key="y" :value="String(y)">
                                                    {{ y }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <Button variant="outline" size="sm" @click="applyDTRFilter">Apply</Button>
                                        <Button variant="outline" class="gap-2" :disabled="!canGenerateDTR" @click="openGenerateDTRForSelf">
                                            <Download :size="16" />
                                            Generate DTR
                                        </Button>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <!-- Desktop Table -->
                                <div v-if="dtrRecords.length > 0" class="hidden md:block overflow-x-auto">
                                    <table class="w-full border-collapse">
                                        <thead>
                                            <tr class="border-b">
                                                <th class="text-left p-3 font-medium text-muted-foreground">Date</th>
                                                <th class="text-left p-3 font-medium text-muted-foreground">Day</th>
                                                <th class="text-left p-3 font-medium text-muted-foreground" colspan="2">Morning Session</th>
                                                <th class="text-left p-3 font-medium text-muted-foreground" colspan="2">Afternoon Session</th>
                                                <th class="text-left p-3 font-medium text-muted-foreground" colspan="2">Overtime</th>
                                                <th class="text-left p-3 font-medium text-muted-foreground">Status</th>
                                            </tr>
                                            <tr class="border-b bg-muted/30">
                                                <th class="p-3"></th>
                                                <th class="p-3"></th>
                                                <th class="text-left p-3 text-sm font-medium text-muted-foreground">Time In</th>
                                                <th class="text-left p-3 text-sm font-medium text-muted-foreground">Time Out</th>
                                                <th class="text-left p-3 text-sm font-medium text-muted-foreground">Time In</th>
                                                <th class="text-left p-3 text-sm font-medium text-muted-foreground">Time Out</th>
                                                <th class="text-left p-3 text-sm font-medium text-muted-foreground">Time In</th>
                                                <th class="text-left p-3 text-sm font-medium text-muted-foreground">Time Out</th>
                                                <th class="p-3"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="record in dtrRecords" :key="record.id" class="border-b hover:bg-muted/50">
                                                <td class="p-3 font-medium">{{ new Date(record.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) }}</td>
                                                <td class="p-3 text-muted-foreground">{{ record.day }}</td>
                                                <td class="p-3 text-sm">{{ record.morning.timeIn || '-' }}</td>
                                                <td class="p-3 text-sm">{{ record.morning.timeOut || '-' }}</td>
                                                <td class="p-3 text-sm">{{ record.afternoon.timeIn || '-' }}</td>
                                                <td class="p-3 text-sm">{{ record.afternoon.timeOut || '-' }}</td>
                                                <td class="p-3 text-sm">{{ record.overtime?.timeIn || '-' }}</td>
                                                <td class="p-3 text-sm">{{ record.overtime?.timeOut || '-' }}</td>
                                                <td class="p-3">
                                                    <Badge :class="['capitalize', getStatusColor(record.status)]">
                                                        {{ record.status }}
                                                    </Badge>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Mobile Cards -->
                                <div v-if="dtrRecords.length > 0" class="md:hidden space-y-3">
                                    <div v-for="record in dtrRecords" :key="record.id" class="p-4 border rounded-lg">
                                        <div class="flex justify-between items-start mb-3">
                                            <div>
                                                <div class="font-medium">{{ new Date(record.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) }}</div>
                                                <div class="text-sm text-muted-foreground">{{ record.day }}</div>
                                            </div>
                                            <Badge :class="['capitalize', getStatusColor(record.status)]">
                                                {{ record.status }}
                                            </Badge>
                                        </div>
                                        
                                        <!-- Morning Session -->
                                        <div class="mb-3">
                                            <div class="text-sm font-medium text-muted-foreground mb-1">Morning Session</div>
                                            <div class="grid grid-cols-2 gap-2 text-sm">
                                                <div>
                                                    <span class="text-muted-foreground">In:</span>
                                                    <span class="ml-1">{{ record.morning.timeIn || '-' }}</span>
                                                </div>
                                                <div>
                                                    <span class="text-muted-foreground">Out:</span>
                                                    <span class="ml-1">{{ record.morning.timeOut || '-' }}</span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Afternoon Session -->
                                        <div class="mb-3">
                                            <div class="text-sm font-medium text-muted-foreground mb-1">Afternoon Session</div>
                                            <div class="grid grid-cols-2 gap-2 text-sm">
                                                <div>
                                                    <span class="text-muted-foreground">In:</span>
                                                    <span class="ml-1">{{ record.afternoon.timeIn || '-' }}</span>
                                                </div>
                                                <div>
                                                    <span class="text-muted-foreground">Out:</span>
                                                    <span class="ml-1">{{ record.afternoon.timeOut || '-' }}</span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Overtime -->
                                        <div class="mb-3">
                                            <div class="text-sm font-medium text-muted-foreground mb-1">Overtime</div>
                                            <div class="grid grid-cols-2 gap-2 text-sm">
                                                <div>
                                                    <span class="text-muted-foreground">In:</span>
                                                    <span class="ml-1">{{ record.overtime?.timeIn || '-' }}</span>
                                                </div>
                                                <div>
                                                    <span class="text-muted-foreground">Out:</span>
                                                    <span class="ml-1">{{ record.overtime?.timeOut || '-' }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Empty state -->
                                <div v-if="dtrRecords.length === 0" class="py-12 text-center text-muted-foreground">
                                    No attendance records for this period.
                                </div>

                                <!-- Pagination -->
                                <div v-if="dtrMeta.total > 0" class="flex items-center justify-between mt-6">
                                    <div class="text-sm text-muted-foreground">
                                        Showing {{ dtrMeta.from ?? 0 }} to {{ dtrMeta.to ?? 0 }} of {{ dtrMeta.total }} records
                                    </div>
                                    <Pagination
                                        :page="dtrMeta.current_page"
                                        :items-per-page="dtrMeta.per_page"
                                        :total="dtrMeta.total"
                                        class="justify-end"
                                        @update:page="goToDTRPage"
                                    >
                                        <PaginationContent v-slot="{ items }">
                                            <PaginationPrevious />
                                            <template v-for="(item, index) in items" :key="index">
                                                <PaginationItem
                                                    v-if="item.type === 'page'"
                                                    :value="item.value"
                                                    :is-active="item.value === dtrMeta.current_page"
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
                    </div>
                </TabsContent>
                <!-- Everyone Tab (admin only) -->
                <TabsContent v-if="isAdmin" value="everyone">
                    <div class="space-y-6">
                        <!-- Date Header -->
                        <div>
                            <h2 class="text-2xl font-semibold text-foreground">{{ currentMonth }} {{ currentDay }}, {{ currentYear }}</h2>
                            <p class="text-muted-foreground mt-1">Team attendance overview</p>
                        </div>

                        <!-- Statistics Cards -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                            <!-- Total Today's Late (clickable: show list in modal) -->
                            <Card
                                class="h-full min-h-[150px] border-orange-200 dark:border-orange-800 hover:shadow-lg transition-transform cursor-pointer hover:scale-[1.02] transform"
                                @click="showTodayLateModal = true"
                            >
                                <CardHeader class="px-6 pt-5 pb-4">
                                    <CardTitle class="flex items-center gap-3 text-orange-700 dark:text-orange-400">
                                        <div class="p-2 bg-orange-100 dark:bg-orange-900/30 rounded-lg">
                                            <Clock :size="20" />
                                        </div>
                                        <div>
                                            <div class="text-lg font-semibold">Today's Late</div>
                                            <div class="text-sm text-muted-foreground font-normal">Employees</div>
                                        </div>
                                    </CardTitle>
                                </CardHeader>
                                <CardContent class="px-6 pb-5">
                                    <div class="flex items-center justify-between">
                                        <div class="text-4xl font-bold text-orange-600 dark:text-orange-400">
                                            {{ teamAttendanceData.late }}
                                        </div>
                                        <div class="text-right text-sm text-muted-foreground">
                                            <div>employees</div>
                                            <div class="text-xs">out of {{ totalEmployeesForChart || '—' }} total</div>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            <!-- Total Yesterday's Absents (clickable: show list in modal) -->
                            <Card
                                class="h-full min-h-[150px] border-red-200 dark:border-red-800 hover:shadow-lg transition-transform cursor-pointer hover:scale-[1.02] transform"
                                @click="showYesterdayAbsentModal = true"
                            >
                                <CardHeader class="px-6 pt-5 pb-4">
                                    <CardTitle class="flex items-center gap-3 text-red-700 dark:text-red-400">
                                        <div class="p-2 bg-red-100 dark:bg-red-900/30 rounded-lg">
                                            <UserX :size="20" />
                                        </div>
                                        <div>
                                            <div class="text-lg font-semibold">Yesterday's Absent</div>
                                            <div class="text-sm text-muted-foreground font-normal">Employees</div>
                                        </div>
                                    </CardTitle>
                                </CardHeader>
                                <CardContent class="px-6 pb-5">
                                    <div class="flex items-center justify-between">
                                        <div class="text-4xl font-bold text-red-600 dark:text-red-400">
                                            {{ teamAttendanceData.absent }}
                                        </div>
                                        <div class="text-right text-sm text-muted-foreground">
                                            <div>employees</div>
                                            <div class="text-xs">out of {{ totalEmployeesForChart || '—' }} total</div>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            <!-- Total On Leave -->
                            <Dialog v-model:open="showOnLeaveModal">
                                <DialogTrigger as-child>
                                    <Card class="h-full min-h-[150px] border-blue-200 dark:border-blue-800 hover:shadow-lg transition-transform cursor-pointer hover:scale-[1.02] transform">
                                        <CardHeader class="px-6 pt-5 pb-4">
                                            <CardTitle class="flex items-center gap-3 text-blue-700 dark:text-blue-400">
                                                <div class="p-2 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                                                    <Calendar :size="20" />
                                                </div>
                                                <div>
                                                    <div class="text-lg font-semibold">On Leave</div>
                                                    <div class="text-sm text-muted-foreground font-normal">Employees</div>
                                                </div>
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent class="px-6 pb-5">
                                            <div class="flex items-center justify-between">
                                                <div class="text-4xl font-bold text-blue-600 dark:text-blue-400">
                                                    {{ teamAttendanceData.onLeave }}
                                                </div>
                                                <div class="text-right text-sm text-muted-foreground">
                                                    <div>employees</div>
                                                    <div class="text-xs">currently away</div>
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </DialogTrigger>
                                <DialogContent class="max-w-4xl max-h-[80vh] pt-10 px-6 pb-6">
                                    <!-- List view -->
                                    <template v-if="onLeaveDialogView === 'list'">
                                        <DialogHeader>
                                            <div class="flex items-center justify-between">
                                                <DialogTitle class="text-xl font-semibold">Employees on Leave</DialogTitle>
                                                <Button size="sm" @click="addOnLeave" class="gap-2">
                                                    <Plus :size="16" />
                                                    Add
                                                </Button>
                                            </div>
                                            <DialogDescription>
                                                Current employees who are on approved leave
                                            </DialogDescription>
                                        </DialogHeader>
                                        <ScrollArea class="max-h-96 pr-4">
                                            <div class="space-y-3">
                                                <div v-for="record in employeesOnLeaveList" :key="record.id" 
                                                     class="flex items-center justify-between p-4 border rounded-lg hover:bg-muted/50">
                                                    <div class="flex-1">
                                                        <div class="font-medium text-base">{{ record.employee_name }}</div>
                                                        <div class="text-sm text-muted-foreground mt-1">
                                                            {{ record.leave_type_name }} • {{ record.date }}{{ record.notes ? ' · ' + record.notes : '' }}
                                                        </div>
                                                    </div>
                                                    <div class="flex items-center gap-2">
                                                        <Button size="sm" variant="outline" @click="editOnLeave(record)" class="gap-1">
                                                            <Edit :size="14" />
                                                            Edit
                                                        </Button>
                                                        <Button size="sm" variant="destructive" @click="openConfirmDeleteLeave({ id: record.id, employee_name: record.employee_name, date: record.date })" class="gap-1">
                                                            <Trash2 :size="14" />
                                                            Delete
                                                        </Button>
                                                    </div>
                                                </div>
                                                <div v-if="!employeesOnLeaveList.length" class="py-8 text-center text-muted-foreground text-sm">
                                                    No leave records this month.
                                                </div>
                                            </div>
                                        </ScrollArea>
                                    </template>
                                    <!-- Add view -->
                                    <template v-else-if="onLeaveDialogView === 'add'">
                                        <DialogHeader>
                                            <DialogTitle>Add Leave</DialogTitle>
                                            <DialogDescription>
                                                Add employee leave for one or more days. Use same start and end for a single day.
                                            </DialogDescription>
                                        </DialogHeader>
                                        <div class="space-y-4">
                                            <div>
                                                <Label for="add-leave-employee">Employee</Label>
                                                <Select v-model="newLeaveForm.employee_id">
                                                    <SelectTrigger id="add-leave-employee">
                                                        <SelectValue placeholder="Select employee" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem v-for="emp in employeesList" :key="emp.id" :value="String(emp.id)">
                                                            {{ emp.name }}
                                                        </SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            <div>
                                                <Label for="add-leave-type">Leave Type</Label>
                                                <Select v-model="newLeaveForm.leave_type_id">
                                                    <SelectTrigger id="add-leave-type">
                                                        <SelectValue placeholder="Select leave type" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem v-for="lt in leaveTypesList" :key="lt.id" :value="String(lt.id)">
                                                            {{ lt.name }}
                                                        </SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                    <Label for="start-date">Start Date</Label>
                                                    <Input id="start-date" type="date" v-model="newLeaveForm.startDate" />
                                                </div>
                                                <div>
                                                    <Label for="end-date">End Date (optional)</Label>
                                                    <Input id="end-date" type="date" v-model="newLeaveForm.endDate" placeholder="Same as start for one day" />
                                                </div>
                                            </div>
                                            <div>
                                                <Label for="add-leave-notes">Notes (Optional)</Label>
                                                <Textarea id="add-leave-notes" v-model="newLeaveForm.notes" placeholder="Additional details..." />
                                            </div>
                                            <p v-if="leaveFormError" class="text-sm text-destructive">{{ leaveFormError }}</p>
                                            <div class="flex justify-end gap-2">
                                                <Button variant="outline" @click="backToOnLeaveList" :disabled="leaveFormSubmitting">Cancel</Button>
                                                <Button @click="submitLeaveForm" :disabled="leaveFormSubmitting">
                                                    {{ leaveFormSubmitting ? 'Adding...' : 'Add Leave' }}
                                                </Button>
                                            </div>
                                        </div>
                                    </template>
                                    <!-- Edit view -->
                                    <template v-else-if="onLeaveDialogView === 'edit'">
                                        <DialogHeader>
                                            <DialogTitle>Edit Leave</DialogTitle>
                                            <DialogDescription>
                                                Update leave record for {{ editLeaveForm.employee_name }}
                                            </DialogDescription>
                                        </DialogHeader>
                                        <div class="space-y-4">
                                            <div>
                                                <Label for="edit-leave-type">Leave Type</Label>
                                                <Select v-model="editLeaveForm.leave_type_id">
                                                    <SelectTrigger id="edit-leave-type">
                                                        <SelectValue placeholder="Select leave type" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem v-for="lt in leaveTypesList" :key="lt.id" :value="String(lt.id)">
                                                            {{ lt.name }}
                                                        </SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            <div>
                                                <Label for="edit-leave-date">Date</Label>
                                                <Input id="edit-leave-date" type="date" v-model="editLeaveForm.date" />
                                            </div>
                                            <div>
                                                <Label for="edit-leave-notes">Notes (Optional)</Label>
                                                <Textarea id="edit-leave-notes" v-model="editLeaveForm.notes" placeholder="Additional details..." />
                                            </div>
                                            <p v-if="leaveFormError" class="text-sm text-destructive">{{ leaveFormError }}</p>
                                            <div class="flex justify-end gap-2">
                                                <Button variant="outline" @click="backToOnLeaveList" :disabled="leaveFormSubmitting">Cancel</Button>
                                                <Button @click="submitEditLeaveForm" :disabled="leaveFormSubmitting">
                                                    {{ leaveFormSubmitting ? 'Saving...' : 'Save' }}
                                                </Button>
                                            </div>
                                        </div>
                                    </template>
                                    <!-- Delete confirmation view -->
                                    <template v-else-if="onLeaveDialogView === 'confirmDelete'">
                                        <DialogHeader>
                                            <DialogTitle>Delete leave record?</DialogTitle>
                                            <DialogDescription>
                                                Remove leave for {{ confirmDeleteLeaveRecord?.employee_name }} on {{ confirmDeleteLeaveRecord?.date }}? This cannot be undone.
                                            </DialogDescription>
                                        </DialogHeader>
                                        <div class="flex justify-end gap-2 pt-4">
                                            <Button variant="outline" @click="backToOnLeaveList">Cancel</Button>
                                            <Button variant="destructive" @click="confirmDeleteLeave">Delete</Button>
                                        </div>
                                    </template>
                                </DialogContent>
                            </Dialog>

                            <!-- Today's Late modal (list of employees) -->
                            <Dialog :open="showTodayLateModal" @update:open="(open) => { if (!open) showTodayLateModal = false }">
                                <DialogContent class="sm:max-w-2xl bg-card text-foreground border-border max-h-[80vh] pt-10 px-6 pb-6">
                                    <DialogHeader>
                                        <DialogTitle>Today's Late</DialogTitle>
                                        <DialogDescription>Employees marked late today.</DialogDescription>
                                    </DialogHeader>
                                    <ScrollArea class="max-h-[50vh] pr-4">
                                        <div class="space-y-2">
                                            <div
                                                v-for="emp in (teamAttendanceSummary?.today_lates || [])"
                                                :key="emp.employee_id"
                                                class="flex items-center justify-between py-2 px-3 rounded-lg border border-border text-sm"
                                            >
                                                <div>
                                                    <span class="font-medium">{{ emp.name }}</span>
                                                    <span class="text-muted-foreground ml-2">{{ emp.department }} · {{ emp.position }}</span>
                                                </div>
                                                <span v-if="emp.time_in" class="text-muted-foreground text-xs">Time in: {{ emp.time_in }}</span>
                                            </div>
                                            <div v-if="!(teamAttendanceSummary?.today_lates?.length)" class="text-muted-foreground text-sm py-4 text-center">
                                                No employees late today.
                                            </div>
                                        </div>
                                    </ScrollArea>
                                    <div class="flex justify-end pt-4 border-t border-border">
                                        <Button variant="outline" @click="showTodayLateModal = false">Close</Button>
                                    </div>
                                </DialogContent>
                            </Dialog>

                            <!-- Yesterday's Absent modal (list of employees) -->
                            <Dialog :open="showYesterdayAbsentModal" @update:open="(open) => { if (!open) showYesterdayAbsentModal = false }">
                                <DialogContent class="sm:max-w-2xl bg-card text-foreground border-border max-h-[80vh] pt-10 px-6 pb-6">
                                    <DialogHeader>
                                        <DialogTitle>Yesterday's Absent</DialogTitle>
                                        <DialogDescription>Employees marked absent yesterday.</DialogDescription>
                                    </DialogHeader>
                                    <ScrollArea class="max-h-[50vh] pr-4">
                                        <div class="space-y-2">
                                            <div
                                                v-for="emp in (teamAttendanceSummary?.yesterday_absents || [])"
                                                :key="emp.employee_id"
                                                class="flex items-center justify-between py-2 px-3 rounded-lg border border-border text-sm"
                                            >
                                                <div>
                                                    <span class="font-medium">{{ emp.name }}</span>
                                                    <span class="text-muted-foreground ml-2">{{ emp.department }} · {{ emp.position }}</span>
                                                </div>
                                            </div>
                                            <div v-if="!(teamAttendanceSummary?.yesterday_absents?.length)" class="text-muted-foreground text-sm py-4 text-center">
                                                No employees absent yesterday.
                                            </div>
                                        </div>
                                    </ScrollArea>
                                    <div class="flex justify-end pt-4 border-t border-border">
                                        <Button variant="outline" @click="showYesterdayAbsentModal = false">Close</Button>
                                    </div>
                                </DialogContent>
                            </Dialog>

                            <!-- Overtime -->
                            <Dialog v-model:open="showOvertimeModal">
                                <DialogTrigger as-child>
                                    <Card class="h-full min-h-[150px] border-purple-200 dark:border-purple-800 hover:shadow-lg transition-transform cursor-pointer hover:scale-[1.02] transform">
                                        <CardHeader class="px-6 pt-5 pb-4">
                                            <CardTitle class="flex items-center gap-3 text-purple-700 dark:text-purple-400">
                                                <div class="p-2 bg-purple-100 dark:bg-purple-900/30 rounded-lg">
                                                    <Clock :size="20" />
                                                </div>
                                                <div>
                                                    <div class="text-lg font-semibold">Overtime</div>
                                                    <div class="text-sm text-muted-foreground font-normal">This Month</div>
                                                </div>
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent class="px-6 pb-5">
                                            <div class="flex items-center justify-between">
                                                <div class="text-4xl font-bold text-purple-600 dark:text-purple-400">
                                                    {{ overtimeRecordsList.length }}
                                                </div>
                                                <div class="text-right text-sm text-muted-foreground">
                                                    <div>records</div>
                                                    <div class="text-xs">this month</div>
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </DialogTrigger>
                                <DialogContent class="max-w-4xl max-h-[80vh] pt-10 px-6 pb-6">
                                    <!-- List view -->
                                    <template v-if="overtimeDialogView === 'list'">
                                        <DialogHeader>
                                            <div class="flex items-center justify-between">
                                                <DialogTitle class="text-xl font-semibold">Overtime Records</DialogTitle>
                                                <Button size="sm" @click="addOvertime" class="gap-2">
                                                    <Plus :size="16" />
                                                    Add
                                                </Button>
                                            </div>
                                            <DialogDescription>
                                                Employee overtime records for this month
                                            </DialogDescription>
                                        </DialogHeader>
                                        <ScrollArea class="max-h-96 pr-4">
                                            <div class="space-y-3">
                                                <div v-for="record in overtimeRecordsList" :key="record.id" 
                                                     class="flex items-center justify-between p-3 border rounded-lg hover:bg-muted/50">
                                                    <div class="flex-1">
                                                        <div class="font-medium text-sm">{{ record.employee_name }}</div>
                                                        <div class="text-xs text-muted-foreground mt-1">
                                                            {{ record.date }}
                                                        </div>
                                                    </div>
                                                    <div class="flex items-center gap-2">
                                                        <Button size="sm" variant="outline" @click="editOvertime(record)" class="gap-1 h-7 px-2 text-xs">
                                                            <Edit :size="12" />
                                                            Edit
                                                        </Button>
                                                        <Button size="sm" variant="destructive" @click="openConfirmDeleteOvertime(record)" class="gap-1 h-7 px-2 text-xs">
                                                            <Trash2 :size="12" />
                                                            Delete
                                                        </Button>
                                                    </div>
                                                </div>
                                                <div v-if="!overtimeRecordsList.length" class="py-8 text-center text-muted-foreground text-sm">
                                                    No overtime records this month.
                                                </div>
                                            </div>
                                        </ScrollArea>
                                    </template>
                                    <!-- Add view -->
                                    <template v-else-if="overtimeDialogView === 'add'">
                                        <DialogHeader>
                                            <DialogTitle>Add Overtime</DialogTitle>
                                            <DialogDescription>
                                                Approve overtime for an employee on a date
                                            </DialogDescription>
                                        </DialogHeader>
                                        <div class="space-y-4">
                                            <div>
                                                <Label for="add-overtime-employee">Employee</Label>
                                                <Select v-model="newOvertimeForm.employee_id">
                                                    <SelectTrigger id="add-overtime-employee">
                                                        <SelectValue placeholder="Select employee" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem v-for="emp in employeesList" :key="emp.id" :value="String(emp.id)">
                                                            {{ emp.name }}
                                                        </SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            <div>
                                                <Label for="add-overtime-date">Date</Label>
                                                <Input id="add-overtime-date" type="date" v-model="newOvertimeForm.date" />
                                            </div>
                                            <p v-if="overtimeFormError" class="text-sm text-destructive">{{ overtimeFormError }}</p>
                                            <div class="flex justify-end gap-2">
                                                <Button variant="outline" @click="backToOvertimeList" :disabled="overtimeFormSubmitting">Cancel</Button>
                                                <Button @click="submitOvertimeForm" :disabled="overtimeFormSubmitting">
                                                    {{ overtimeFormSubmitting ? 'Adding...' : 'Add Overtime' }}
                                                </Button>
                                            </div>
                                        </div>
                                    </template>
                                    <!-- Edit view -->
                                    <template v-else-if="overtimeDialogView === 'edit'">
                                        <DialogHeader>
                                            <DialogTitle>Edit Overtime</DialogTitle>
                                            <DialogDescription>
                                                Update date for {{ editOvertimeForm.employee_name }}
                                            </DialogDescription>
                                        </DialogHeader>
                                        <div class="space-y-4">
                                            <div>
                                                <Label for="edit-overtime-date">Date</Label>
                                                <Input id="edit-overtime-date" type="date" v-model="editOvertimeForm.date" />
                                            </div>
                                            <p v-if="overtimeFormError" class="text-sm text-destructive">{{ overtimeFormError }}</p>
                                            <div class="flex justify-end gap-2">
                                                <Button variant="outline" @click="backToOvertimeList" :disabled="overtimeFormSubmitting">Cancel</Button>
                                                <Button @click="submitEditOvertimeForm" :disabled="overtimeFormSubmitting">
                                                    {{ overtimeFormSubmitting ? 'Saving...' : 'Save' }}
                                                </Button>
                                            </div>
                                        </div>
                                    </template>
                                    <!-- Delete confirmation view -->
                                    <template v-else-if="overtimeDialogView === 'confirmDelete'">
                                        <DialogHeader>
                                            <DialogTitle>Delete overtime record?</DialogTitle>
                                            <DialogDescription>
                                                Remove overtime for {{ confirmDeleteOvertimeRecord?.name }} on {{ confirmDeleteOvertimeRecord?.date }}? This cannot be undone.
                                            </DialogDescription>
                                        </DialogHeader>
                                        <div class="flex justify-end gap-2 pt-4">
                                            <Button variant="outline" @click="backToOvertimeList">Cancel</Button>
                                            <Button variant="destructive" @click="confirmDeleteOvertime">Delete</Button>
                                        </div>
                                    </template>
                                </DialogContent>
                            </Dialog>
                        </div>

                        <!-- Weekly Status Bar Chart + Pie Chart -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <!-- Weekly Status Bar Chart -->
                            <Card>
                                <CardHeader>
                                    <CardTitle class="flex items-center gap-2">
                                        <Users :size="20" />
                                        This Week's Team Status
                                    </CardTitle>
                                    <CardDescription>Present, Late, and On Leave per day this week</CardDescription>
                                </CardHeader>
                                <CardContent class="space-y-4">
                                    <div style="height: 380px;">
                                        <Bar :data="weeklyStatusBarData" :options="weeklyStatusBarOptions" />
                                    </div>
                                    <div class="flex flex-col gap-1 border-t border-border pt-4">
                                        <p class="text-sm font-medium text-foreground">
                                            {{ weeklyStatusSummaryLine }}
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>

                            <!-- Pie Chart -->
                            <Card>
                                <CardHeader>
                                    <CardTitle class="flex items-center gap-2">
                                        <Users :size="20" />
                                        Team Attendance Breakdown
                                    </CardTitle>
                                    <CardDescription>Present, Late, and On Leave — today’s distribution</CardDescription>
                                </CardHeader>
                                <CardContent class="space-y-4">
                                    <div style="height: 380px;">
                                        <Pie :data="pieChartData" :options="pieChartOptions" />
                                    </div>
                                    <div class="flex flex-col gap-1 border-t border-border pt-4">
                                        <p class="text-sm font-medium text-foreground">
                                            {{ pieChartSummaryLine }}
                                        </p>
                                        <p v-if="totalEmployeesForChart > 0" class="text-xs text-muted-foreground">
                                            {{ teamAttendanceData.present + teamAttendanceData.late + teamAttendanceData.onLeave }} of {{ totalEmployeesForChart }} employees accounted for today
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        <!-- All employees attendance table: Today / This month, search, status filter -->
                        <Card class="mt-6">
                            <CardHeader>
                                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <CardTitle class="flex items-center gap-2">
                                            <Users :size="20" />
                                            All Employees Attendance
                                        </CardTitle>
                                        <CardDescription>Today, this week, or this month's attendance with search and filters</CardDescription>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <div class="flex rounded-lg border border-border overflow-hidden">
                                            <button
                                                type="button"
                                                class="px-3 py-2 text-sm font-medium transition-colors"
                                                :class="teamTableRange === 'today' ? 'bg-primary text-primary-foreground' : 'bg-muted/50 text-muted-foreground hover:bg-muted'"
                                                @click="teamTableRange = 'today'"
                                            >
                                                Today
                                            </button>
                                            <button
                                                type="button"
                                                class="px-3 py-2 text-sm font-medium transition-colors border-l border-border"
                                                :class="teamTableRange === 'week' ? 'bg-primary text-primary-foreground' : 'bg-muted/50 text-muted-foreground hover:bg-muted'"
                                                @click="teamTableRange = 'week'"
                                            >
                                                This week
                                            </button>
                                            <button
                                                type="button"
                                                class="px-3 py-2 text-sm font-medium transition-colors border-l border-border"
                                                :class="teamTableRange === 'month' ? 'bg-primary text-primary-foreground' : 'bg-muted/50 text-muted-foreground hover:bg-muted'"
                                                @click="teamTableRange = 'month'"
                                            >
                                                This month
                                            </button>
                                        </div>
                                        <div class="relative">
                                            <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                                            <Input
                                                v-model="teamSearchInput"
                                                placeholder="Search by name or code..."
                                                class="pl-8 w-[200px] sm:w-[220px]"
                                                @keydown.enter="applyTeamTableFilter"
                                            />
                                        </div>
                                        <Select v-model="teamStatusFilter">
                                            <SelectTrigger class="w-[140px]">
                                                <SelectValue placeholder="Status" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">All statuses</SelectItem>
                                                <SelectItem value="Present">Present</SelectItem>
                                                <SelectItem value="Late">Late</SelectItem>
                                                <SelectItem value="Absent">Absent</SelectItem>
                                                <SelectItem value="Leave">Leave</SelectItem>
                                                <SelectItem value="Holiday">Holiday</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <Button size="sm" @click="applyTeamTableFilter">Apply</Button>
                                        <Button
                                            v-if="isAdmin"
                                            variant="outline"
                                            size="sm"
                                            class="gap-2"
                                            @click="openHolidaysDialog"
                                        >
                                            <Calendar :size="16" />
                                            Holidays
                                        </Button>
                                        <Button
                                            v-if="isAdmin"
                                            variant="outline"
                                            size="sm"
                                            class="gap-2"
                                            @click="openGenerateDTRForEmployee"
                                        >
                                            <Download :size="16" />
                                            Generate DTR
                                        </Button>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div v-if="(teamAttendance?.data?.length ?? 0) > 0" class="overflow-x-auto">
                                    <table class="w-full border-collapse">
                                        <thead>
                                            <tr class="border-b">
                                                <th class="text-left p-3 font-medium text-muted-foreground">Date</th>
                                                <th class="text-left p-3 font-medium text-muted-foreground">Employee</th>
                                                <th class="text-left p-3 font-medium text-muted-foreground">Department</th>
                                                <th class="text-left p-3 font-medium text-muted-foreground">Time In</th>
                                                <th class="text-left p-3 font-medium text-muted-foreground">Time Out</th>
                                                <th class="text-left p-3 font-medium text-muted-foreground">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="row in teamAttendance!.data" :key="row.id" class="border-b hover:bg-muted/50">
                                                <td class="p-3 text-sm font-medium">{{ new Date(row.date + 'T12:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) }}</td>
                                                <td class="p-3">
                                                    <div class="font-medium">{{ row.name ?? '—' }}</div>
                                                    <div class="text-xs text-muted-foreground">{{ row.employee_code ?? '' }}</div>
                                                </td>
                                                <td class="p-3 text-sm text-muted-foreground">{{ row.department ?? '—' }}</td>
                                                <td class="p-3 text-sm">{{ formatTimeDisplay(row.time_in) }}</td>
                                                <td class="p-3 text-sm">{{ formatTimeDisplay(row.time_out) }}</td>
                                                <td class="p-3">
                                                    <Badge :class="['capitalize', getStatusColor((row.status ?? '').toLowerCase())]">
                                                        {{ row.status ?? '—' }}
                                                    </Badge>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div v-else class="py-12 text-center text-muted-foreground">
                                    No attendance records for the selected period and filters.
                                </div>
                                <div v-if="teamTableMeta.total > 0" class="flex items-center justify-between mt-6">
                                    <div class="text-sm text-muted-foreground">
                                        Showing {{ teamTableMeta.from ?? 0 }} to {{ teamTableMeta.to ?? 0 }} of {{ teamTableMeta.total }} records
                                    </div>
                                    <Pagination
                                        :page="teamTableMeta.current_page"
                                        :items-per-page="teamTableMeta.per_page"
                                        :total="teamTableMeta.total"
                                        class="justify-end"
                                        @update:page="goToTeamTablePage"
                                    >
                                        <PaginationContent v-slot="{ items }">
                                            <PaginationPrevious />
                                            <template v-for="(item, index) in items" :key="index">
                                                <PaginationItem
                                                    v-if="item.type === 'page'"
                                                    :value="item.value"
                                                    :is-active="item.value === teamTableMeta.current_page"
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
                    </div>
                </TabsContent>
                <!-- Warning Tab (admin only) -->
                <TabsContent v-if="isAdmin" value="warning">
                    <div class="space-y-6">
                        <!-- Warning Header -->
                        <div>
                            <h2 class="text-2xl font-semibold text-foreground">Employee Attendance Warnings</h2>
                            <p class="text-muted-foreground mt-1">
                                Warning at 3 lates/absences, memorandum at 4 lates/absences.
                            </p>
                        </div>

                        <!-- MEMORANDUM Level Warnings -->
                        <Card>
                            <CardHeader class="pb-3">
                                <CardTitle class="text-lg font-semibold text-red-700 dark:text-red-400">
                                    MEMORANDUM Level ({{ memorandumWarnings.length }})
                                </CardTitle>
                                <CardDescription class="text-sm">
                                    Employees who have exceeded monthly thresholds and require formal disciplinary action
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div v-if="memorandumWarnings.length" class="space-y-2">
                                    <div
                                        v-for="employee in memorandumWarnings"
                                        :key="employee.id"
                                        class="flex items-center justify-between gap-2 p-3 border rounded-lg hover:bg-muted/30 flex-wrap"
                                    >
                                        <div class="flex-1 min-w-0">
                                            <div class="font-medium text-sm">{{ employee.name }}</div>
                                            <div class="text-xs text-muted-foreground mt-1">
                                                Late this month: {{ employee.lateCount }} | Absences: {{ employee.absentCount }}
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2 flex-shrink-0">
                                            <Button
                                                size="sm"
                                                :class="getWarningColor(employee.status)"
                                                @click="viewEmployeeDetails(employee)"
                                                class="text-xs px-3 py-1 h-7"
                                            >
                                                MEMORANDUM
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                class="text-xs px-3 py-1 h-7"
                                                :disabled="!employee.warning_memo_ids?.length"
                                                :title="!employee.warning_memo_ids?.length ? 'Issue a memorandum first from employee detail' : ''"
                                                @click="employee.warning_memo_ids?.length && acknowledgeWarningMemo(employee.warning_memo_ids[0])"
                                            >
                                                Acknowledged
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                class="text-xs px-3 py-1 h-7 gap-1"
                                                :disabled="!employee.warning_memo_ids?.length"
                                                :title="!employee.warning_memo_ids?.length ? 'Issue a memorandum first' : ''"
                                                @click="employee.warning_memo_ids?.length && openMemoPdf(employee.warning_memo_ids[0])"
                                            >
                                                <Download :size="12" />
                                                Generate Memorandum
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="secondary"
                                                class="text-xs px-3 py-1 h-7"
                                                :disabled="!employee.warning_memo_ids?.length"
                                                :title="!employee.warning_memo_ids?.length ? 'Issue a memorandum first' : ''"
                                                @click="employee.warning_memo_ids?.length && resolveWarningMemo(employee.warning_memo_ids[0])"
                                            >
                                                Resolved
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="text-sm text-muted-foreground">
                                    No employees currently in memorandum level.
                                </div>
                            </CardContent>
                        </Card>

                        <!-- WARNING Level -->
                        <Card>
                            <CardHeader class="pb-3">
                                <CardTitle class="text-lg font-semibold text-orange-700 dark:text-orange-400">
                                    WARNING Level ({{ warningEmployees.length }})
                                </CardTitle>
                                <CardDescription class="text-sm">
                                    Employees approaching monthly limits who need verbal counseling
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div v-if="warningEmployees.length" class="space-y-2">
                                    <div
                                        v-for="employee in warningEmployees"
                                        :key="employee.id"
                                        class="flex items-center justify-between gap-2 p-3 border rounded-lg hover:bg-muted/30 flex-wrap"
                                    >
                                        <div class="flex-1 min-w-0">
                                            <div class="font-medium text-sm">{{ employee.name }}</div>
                                            <div class="text-xs text-muted-foreground mt-1">
                                                Late this month: {{ employee.lateCount }} | Absences: {{ employee.absentCount }}
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2 flex-shrink-0">
                                            <Button
                                                size="sm"
                                                :class="getWarningColor(employee.status)"
                                                @click="viewEmployeeDetails(employee)"
                                                class="text-xs px-3 py-1 h-7"
                                            >
                                                WARNING
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                class="text-xs px-3 py-1 h-7"
                                                :disabled="!employee.warning_memo_ids?.length"
                                                :title="!employee.warning_memo_ids?.length ? 'Issue a warning first from employee detail' : ''"
                                                @click="employee.warning_memo_ids?.length && acknowledgeWarningMemo(employee.warning_memo_ids[0])"
                                            >
                                                Acknowledged
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="text-sm text-muted-foreground">
                                    No employees currently in warning level.
                                </div>
                            </CardContent>
                        </Card>

                        <!-- No Warnings Message -->
                        <Card v-if="memorandumWarnings.length === 0 && warningEmployees.length === 0">
                            <CardContent class="text-center py-12">
                                <div class="text-green-600 dark:text-green-400 mb-4">
                                    <Users :size="48" class="mx-auto" />
                                </div>
                                <h3 class="text-lg font-semibold text-green-700 dark:text-green-400 mb-2">
                                    All Clear!
                                </h3>
                                <p class="text-muted-foreground">
                                    No employees currently have attendance warnings or violations.
                                </p>
                            </CardContent>
                        </Card>
                    </div>
                </TabsContent>
                <!-- Biometric Logs Tab (admin only, today's data) -->
                <TabsContent v-if="isAdmin" value="biometric">
                    <div class="space-y-6">
                        <div>
                            <h2 class="text-2xl font-semibold text-foreground">Biometric Logs (This Month)</h2>
                            <p class="text-muted-foreground mt-1">
                                Scan events for {{ currentMonth }} {{ currentYear }}.
                            </p>
                        </div>

                        <!-- Bar Chart: scans per day (clearer than line for count-by-day) -->
                        <Card>
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2">
                                    <Clock :size="20" />
                                    Scan Activity by Day
                                </CardTitle>
                                <CardDescription>Number of biometric scans per day this month</CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div style="height: 320px;">
                                    <Bar :data="biometricBarData" :options="biometricBarOptions" />
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Logs Table -->
                        <Card>
                            <CardHeader>
                                <CardTitle class="flex items-center gap-2">
                                    <Users :size="20" />
                                    Biometric Log Entries
                                </CardTitle>
                                <CardDescription>Raw scan records for today (employee code and timestamp)</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div v-if="biometricLogsList.length" class="overflow-x-auto">
                                    <table class="w-full border-collapse text-sm">
                                        <thead>
                                            <tr class="border-b">
                                                <th class="text-left p-3 font-medium text-muted-foreground">Employee ID</th>
                                                <th class="text-left p-3 font-medium text-muted-foreground">Date</th>
                                                <th class="text-left p-3 font-medium text-muted-foreground">Time</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="log in biometricLogsList"
                                                :key="log.id"
                                                class="border-b last:border-0 hover:bg-muted/50"
                                            >
                                                <td class="p-3">{{ log.employee_code }}</td>
                                                <td class="p-3">{{ log.date }}</td>
                                                <td class="p-3">{{ log.time }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div v-else class="py-8 text-center text-sm text-muted-foreground">
                                    No biometric logs recorded for this month.
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </TabsContent>
            </Tabs>

            <!-- Holidays dialog (Everyone tab): add, edit, delete -->
            <Dialog v-model:open="showHolidaysModal">
                <DialogContent class="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Holidays</DialogTitle>
                        <DialogDescription>
                            Manage company holidays. These dates are used for attendance and DTR.
                        </DialogDescription>
                    </DialogHeader>
                    <div class="space-y-4 py-4">
                        <!-- Add / Edit form -->
                        <div class="grid grid-cols-[1fr_1fr_auto] gap-2 items-end">
                            <div class="space-y-2">
                                <Label for="holiday-name">Name</Label>
                                <Input
                                    id="holiday-name"
                                    v-model="holidayForm.name"
                                    placeholder="e.g. Christmas Day"
                                />
                            </div>
                            <div class="space-y-2">
                                <Label for="holiday-date">Date</Label>
                                <Input
                                    id="holiday-date"
                                    v-model="holidayForm.date"
                                    type="date"
                                />
                            </div>
                            <div class="flex gap-2">
                                <Button
                                    size="sm"
                                    :disabled="holidayFormSubmitting"
                                    @click="saveHoliday"
                                >
                                    {{ holidayEditId != null ? 'Update' : 'Add' }}
                                </Button>
                                <Button
                                    v-if="holidayEditId != null"
                                    size="sm"
                                    variant="ghost"
                                    @click="cancelHolidayForm"
                                >
                                    Cancel
                                </Button>
                            </div>
                        </div>
                        <!-- List -->
                        <div>
                            <div v-if="holidaysLoading" class="text-sm text-muted-foreground py-4">
                                Loading holidays...
                            </div>
                            <div v-else-if="holidaysList.length === 0" class="text-sm text-muted-foreground py-4">
                                No holidays defined. Add one above.
                            </div>
                            <ScrollArea v-else class="h-[240px] rounded-md border">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b bg-muted/50">
                                            <th class="text-left p-2 font-medium">Date</th>
                                            <th class="text-left p-2 font-medium">Name</th>
                                            <th class="w-[100px] p-2 text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="h in holidaysList"
                                            :key="h.id"
                                            class="border-b hover:bg-muted/30"
                                        >
                                            <td class="p-2">{{ new Date(h.date + 'T12:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) }}</td>
                                            <td class="p-2 font-medium">{{ h.name }}</td>
                                            <td class="p-2 text-right">
                                                <Button size="sm" variant="ghost" class="h-8 w-8 p-0" @click="startEditHoliday(h)">
                                                    <Edit :size="14" />
                                                </Button>
                                                <Button size="sm" variant="ghost" class="h-8 w-8 p-0 text-destructive hover:text-destructive" @click="deleteHoliday(h)">
                                                    <Trash2 :size="14" />
                                                </Button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </ScrollArea>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>

            <!-- Generate DTR PDF dialog (current user in My Attendance, or selected employee in Everyone tab) -->
            <Dialog v-model:open="showGenerateDTRModal">
                <DialogContent class="max-w-sm">
                    <DialogHeader>
                        <DialogTitle>Generate DTR (PDF)</DialogTitle>
                        <DialogDescription>
                            Select the employee and the month and year for the Daily Time Record. The PDF will open in a new tab.
                        </DialogDescription>
                    </DialogHeader>
                    <div class="space-y-4 py-4">
                        <!-- Employee selector only when admin is generating for another employee (Everyone tab) -->
                        <div v-if="isAdmin && !generateDTRForCurrentUser" class="space-y-2">
                            <Label for="dtr-pdf-employee">Employee</Label>
                            <Select v-model="selectedDTREmployeeId">
                                <SelectTrigger id="dtr-pdf-employee">
                                    <SelectValue placeholder="Select employee" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="emp in employeesList"
                                        :key="emp.id"
                                        :value="String(emp.id)"
                                    >
                                        {{ emp.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="dtr-pdf-month">Month</Label>
                                <Select v-model="selectedDTRMonth">
                                    <SelectTrigger id="dtr-pdf-month">
                                        <SelectValue placeholder="Month" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="(name, i) in monthNames" :key="i" :value="i + 1">
                                            {{ name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div class="space-y-2">
                                <Label for="dtr-pdf-year">Year</Label>
                                <Select v-model="selectedDTRYear">
                                    <SelectTrigger id="dtr-pdf-year">
                                        <SelectValue placeholder="Year" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="y in dtrYearOptions" :key="y" :value="y">
                                            {{ y }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                        <div class="flex justify-end gap-2">
                            <Button variant="outline" @click="showGenerateDTRModal = false">Cancel</Button>
                            <Button class="gap-2" @click="confirmGenerateDTR">
                                <Download :size="16" />
                                Generate PDF
                            </Button>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>

            <!-- Employee Detail Modal -->
            <Dialog v-model:open="showEmployeeDetailModal">
                <DialogContent class="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle class="flex items-center gap-2">
                            <Badge :class="getWarningColor(selectedEmployee?.status ?? '')" class="text-xs">
                                {{ selectedEmployee?.status?.toUpperCase() }}
                            </Badge>
                            {{ selectedEmployee?.name }}
                        </DialogTitle>
                        <DialogDescription>
                            {{ getStatusDescription(selectedEmployee?.status ?? '') }}
                        </DialogDescription>
                    </DialogHeader>
                    <div class="space-y-6">
                        <!-- Active Warnings / Memos (fetched from backend) -->
                        <div>
                            <h4 class="font-medium mb-3">Active Warnings / Memorandums</h4>
                            <div v-if="selectedEmployeeWarningsLoading" class="text-sm text-muted-foreground">
                                Loading records...
                            </div>
                            <div v-else-if="selectedEmployeeWarningsError" class="text-sm text-red-600">
                                {{ selectedEmployeeWarningsError }}
                            </div>
                            <div v-else-if="!selectedEmployeeWarnings.length" class="text-sm text-muted-foreground">
                                No active warning or memorandum records found for this employee.
                            </div>
                            <div v-else class="space-y-2">
                                <div
                                    v-for="wm in selectedEmployeeWarnings"
                                    :key="wm.id"
                                    class="border rounded-lg p-3 text-sm space-y-1"
                                >
                                    <div class="flex items-center justify-between">
                                        <div class="font-medium">
                                            {{ wm.type.toUpperCase() }} — {{ wm.reason_type.replace('_', ' ') }}
                                        </div>
                                        <div class="text-xs text-muted-foreground">
                                            {{ wm.created_at }}
                                        </div>
                                    </div>
                                    <div v-if="wm.notes" class="text-xs text-muted-foreground">
                                        Notes: {{ wm.notes }}
                                    </div>
                                    <div class="flex flex-wrap gap-2 text-[11px] mt-1">
                                        <span
                                            v-if="wm.acknowledged_at"
                                            class="inline-flex items-center px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300"
                                        >
                                            Acknowledged: {{ wm.acknowledged_at }}
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300"
                                        >
                                            Not yet acknowledged
                                        </span>
                                        <span
                                            v-if="wm.resolved_at"
                                            class="inline-flex items-center px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 dark:bg-slate-900/30 dark:text-slate-300"
                                        >
                                            Resolved: {{ wm.resolved_at }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Attendance Summary (derived stats) -->
                        <div>
                            <h4 class="font-medium mb-3">Attendance Summary</h4>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="p-3 border rounded-lg">
                                    <div class="text-sm text-muted-foreground">Late Arrivals</div>
                                    <div class="text-2xl font-bold text-orange-600">{{ selectedEmployee?.lateCount }}</div>
                                    <div class="text-xs text-muted-foreground">Last: {{ selectedEmployee?.lastLateDate }}</div>
                                </div>
                                <div class="p-3 border rounded-lg">
                                    <div class="text-sm text-muted-foreground">Absences</div>
                                    <div class="text-2xl font-bold text-red-600">{{ selectedEmployee?.absentCount }}</div>
                                    <div class="text-xs text-muted-foreground">Last: {{ selectedEmployee?.lastAbsentDate }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Recommended Actions -->
                        <div>
                            <h4 class="font-medium mb-3">Recommended Actions</h4>
                            <ul class="space-y-2">
                                <li v-for="action in getRecommendedActions(selectedEmployee?.status ?? '')" :key="action" 
                                    class="flex items-center gap-2 text-sm">
                                    <div class="w-1.5 h-1.5 bg-primary rounded-full"></div>
                                    {{ action }}
                                </li>
                            </ul>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>