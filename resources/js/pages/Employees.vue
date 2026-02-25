<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger, DialogFooter, DialogDescription } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Users, Building2, Search, Plus, Trash2, Edit, MoreVertical, Eye, Mail, Phone, Calendar, UserCircle, Check, X, Filter, Shield, Key, Loader2 } from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';
import api from '@/lib/axios';
import { toast } from 'vue-sonner';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Label } from '@/components/ui/label';
import {
  Pagination,
  PaginationContent,
  PaginationEllipsis,
  PaginationItem,
  PaginationNext,
  PaginationPrevious,
} from "@/components/ui/pagination";

// Define props from Inertia
interface Employee {
    id: number;
    employee_code: string;
    name: string;
    first_name: string;
    last_name: string;
    department: string;
    department_id: number | null;
    position: string;
    position_id: number | null;
    role: string;
    email: string;
    contact_number: string | null;
    birth_date: string | null;
    hire_date: string | null;
    employment_status: 'active' | 'inactive';
    inactive_reason: 'terminated' | 'resigned' | 'retired' | 'end_of_contract' | 'other' | null;
    inactive_reason_notes: string | null;
    inactive_date: string | null;
    length_of_service: string;
    avatar: string | null;
}

interface Department {
    id: number;
    code: string;
    name: string;
}

interface Position {
    id: number;
    name: string;
    department: string;
    department_id: number;
}

interface CurrentUser {
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
    hire_date: string | null;
    employment_status: 'active' | 'inactive';
    inactive_reason: 'terminated' | 'resigned' | 'retired' | 'end_of_contract' | 'other' | null;
    inactive_reason_notes: string | null;
    inactive_date: string | null;
    length_of_service: string;
    avatar: string | null;
}

const props = defineProps<{
    employees: Employee[];
    departments: Department[];
    positions: Position[];
    currentUser: CurrentUser;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Employees',
        href: '/employees',
    },
];

// Transform currentUser from backend format to component format
const currentUser = ref({
    id: props.currentUser.id,
    name: props.currentUser.name,
    employeeCode: props.currentUser.employee_code,
    department: props.currentUser.department,
    department_id: props.currentUser.department_id,
    position: props.currentUser.position,
    position_id: props.currentUser.position_id,
    role: props.currentUser.role, // Keep as enum value for conditional rendering
    email: props.currentUser.email,
    contactNum: props.currentUser.contact_number || '',
    birthday: props.currentUser.birth_date || '',
    hireDate: props.currentUser.hire_date || '',
    employmentStatus: props.currentUser.employment_status || 'active',
    inactiveReason: props.currentUser.inactive_reason || '',
    inactiveReasonNotes: props.currentUser.inactive_reason_notes || '',
    inactiveDate: props.currentUser.inactive_date || '',
    lengthOfService: props.currentUser.length_of_service || 'N/A',
    avatar: props.currentUser.avatar || ''
});

// User Info Card edit dialog state
// Note: This matches Employee model structure for API integration
// Fields: first_name, last_name, employee_code, position_id, email, contact_number, birth_date, avatar
const isUserInfoDialogOpen = ref(false);
const originalAvatarUrl = ref<string | null>(null); // Track original avatar URL to detect changes
const editedUserInfo = ref({
    first_name: '',
    last_name: '',
    employee_code: '',
    position_id: null as number | null,
    email: '',
    contact_number: '',
    birth_date: '',
    avatar: '',
    newPassword: '',
    confirmPassword: ''
});

// Function to handle user info avatar upload
const handleUserInfoAvatarUpload = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    
    if (file && (file.type === 'image/png' || file.type === 'image/jpeg')) {
        const reader = new FileReader();
        reader.onload = (e) => {
            editedUserInfo.value.avatar = e.target?.result as string;
        };
        reader.readAsDataURL(file);
    } else {
        toast.error('Please upload a PNG or JPG image.');
    }
};

// Function to open user info edit dialog
const openUserInfoEdit = () => {
    // Populate form with current user data
    // Use first_name and last_name from props (controller sends them)
    editedUserInfo.value.first_name = props.currentUser.first_name;
    editedUserInfo.value.last_name = props.currentUser.last_name;
    editedUserInfo.value.employee_code = currentUser.value.employeeCode;
    // Use position_id from currentUser (already set from props)
    editedUserInfo.value.position_id = currentUser.value.position_id;
    editedUserInfo.value.email = currentUser.value.email;
    editedUserInfo.value.contact_number = currentUser.value.contactNum;
    editedUserInfo.value.birth_date = currentUser.value.birthday;
    // Store original avatar URL to detect if it changed
    originalAvatarUrl.value = currentUser.value.avatar || null;
    editedUserInfo.value.avatar = currentUser.value.avatar || '';
    editedUserInfo.value.newPassword = '';
    editedUserInfo.value.confirmPassword = '';
    isUserInfoDialogOpen.value = true;
};

// Function to close user info edit dialog
const closeUserInfoEdit = () => {
    editedUserInfo.value.newPassword = '';
    editedUserInfo.value.confirmPassword = '';
    isUserInfoDialogOpen.value = false;
};

// Function to save user info changes
const saveUserInfo = async () => {
    // Prevent double submission
    if (isSubmittingEmployee.value) {
        return;
    }
    
    // Validate password if provided
    if (editedUserInfo.value.newPassword) {
        if (editedUserInfo.value.newPassword !== editedUserInfo.value.confirmPassword) {
            toast.error('Passwords do not match!');
            return;
        }
        if (editedUserInfo.value.newPassword.length < 6) {
            toast.error('Password must be at least 6 characters long!');
            return;
        }
    }
    
    // Check if avatar is a new Base64 image or unchanged
    let avatarToSend: string | null = null;
    if (editedUserInfo.value.avatar) {
        // If it's a Base64 string (new upload), send it
        if (editedUserInfo.value.avatar.startsWith('data:image/')) {
            avatarToSend = editedUserInfo.value.avatar;
        }
        // If it's the same URL as original, send null (keep existing)
        // If it's different URL (shouldn't happen, but handle it), send null
        // The backend will keep the existing avatar if null/empty
    }
    
    // Prepare data matching Employee model structure
    const employeePayload: any = {
        first_name: editedUserInfo.value.first_name.trim(),
        last_name: editedUserInfo.value.last_name.trim(),
        employee_code: editedUserInfo.value.employee_code.trim(),
        position_id: editedUserInfo.value.position_id || null,
        email: editedUserInfo.value.email.trim(),
        contact_number: editedUserInfo.value.contact_number?.trim() || null,
        birth_date: editedUserInfo.value.birth_date || null,
        avatar: avatarToSend,
        role: currentUser.value.role // Keep current role
    };
    
    // Add password only if provided
    if (editedUserInfo.value.newPassword) {
        employeePayload.password = editedUserInfo.value.newPassword;
    }
    
    isSubmittingEmployee.value = true;
    try {
        const response = await api.put(`/employees/${currentUser.value.id}`, employeePayload);
        
        // Update local state with response data
        const updatedEmployee = response.data.employee;
        currentUser.value.name = updatedEmployee.name;
        currentUser.value.employeeCode = updatedEmployee.employee_code;
        const position = positions.value.find(p => p.id === updatedEmployee.position_id);
        currentUser.value.position = position?.name || updatedEmployee.position;
        currentUser.value.position_id = updatedEmployee.position_id;
        currentUser.value.email = updatedEmployee.email;
        currentUser.value.contactNum = updatedEmployee.contact_number || '';
        currentUser.value.birthday = updatedEmployee.birth_date || '';
        currentUser.value.avatar = updatedEmployee.avatar || '';
        
        toast.success(response.data.message || 'Profile updated successfully');
        closeUserInfoEdit();
        
        // Reload page to refresh CSRF token and ensure data consistency
        setTimeout(() => {
            window.location.reload();
        }, 500); // Small delay to show the success toast
    } catch (error: any) {
        // Show specific error message
        if (error.response?.data?.message) {
            toast.error(error.response.data.message);
        } else if (error.response?.data?.errors) {
            const errors = error.response.data.errors;
            const errorMessages = Object.values(errors).flat().join(', ');
            toast.error(errorMessages || 'Failed to update profile');
        } else {
            toast.error('Failed to update profile. Please try again.');
        }
        console.error('Error updating profile:', error);
    } finally {
        isSubmittingEmployee.value = false;
    }
};

// Departments from backend (already formatted correctly)
const departments = ref(props.departments);

// Transform employees from backend format to component format
// Backend provides: employee_code, contact_number, birth_date
// Component expects: employeeCode, contactNum, birthday
const allEmployees = ref(props.employees.map(emp => ({
    id: emp.id,
    employeeCode: emp.employee_code,
    name: emp.name,
    department: emp.department,
    department_id: emp.department_id,
    position: emp.position,
    position_id: emp.position_id,
    role: emp.role === 'employee' ? 'Employee' : 
          emp.role === 'department_manager' ? 'Department Manager' : 'Admin',
    email: emp.email,
    contactNum: emp.contact_number || '',
    birthday: emp.birth_date || '',
    hireDate: emp.hire_date || '',
    employmentStatus: emp.employment_status || 'active',
    inactiveReason: emp.inactive_reason || '',
    inactiveReasonNotes: emp.inactive_reason_notes || '',
    inactiveDate: emp.inactive_date || '',
    lengthOfService: emp.length_of_service || 'N/A',
    avatar: emp.avatar || ''
})));

// Search states
const employeeSearch = ref('');

// Filter states for All Employees tab
const isFilterOpen = ref(false);
const departmentFilter = ref('all');
const tempDepartmentFilter = ref('all');
const roleFilter = ref('all');
const tempRoleFilter = ref('all');

// Filter states for Departments tab
const isDepartmentTabFilterOpen = ref(false);
const departmentTabPositionFilter = ref('all');
const tempDepartmentTabPositionFilter = ref('all');
const departmentTabRoleFilter = ref('all');
const tempDepartmentTabRoleFilter = ref('all');

// Pagination state
const currentPage = ref(1);
const itemsPerPage = 10;

// Computed filtered employees for Departments tab (only current user's department)
const filteredDepartmentEmployees = computed(() => {
    let filtered = allEmployees.value.filter(emp => 
        emp.department === currentUser.value.department && 
        emp.id !== currentUser.value.id // Exclude current user
    );
    
    // Search filter
    if (employeeSearch.value) {
        const search = employeeSearch.value.toLowerCase();
        filtered = filtered.filter(emp => 
            emp.name.toLowerCase().includes(search) ||
            emp.employeeCode.toLowerCase().includes(search) ||
            emp.position.toLowerCase().includes(search)
        );
    }
    
    // Position filter
    if (departmentTabPositionFilter.value !== 'all') {
        filtered = filtered.filter(emp => emp.position === departmentTabPositionFilter.value);
    }
    
    // Role filter
    if (departmentTabRoleFilter.value !== 'all') {
        filtered = filtered.filter(emp => emp.role === departmentTabRoleFilter.value);
    }
    
    return filtered;
});

// Computed filtered employees for All Employees tab
const filteredEmployees = computed(() => {
    let filtered = allEmployees.value.filter(emp => emp.id !== currentUser.value.id); // Exclude current user
    
    // Search filter
    if (employeeSearch.value) {
        const search = employeeSearch.value.toLowerCase();
        filtered = filtered.filter(emp => 
            emp.name.toLowerCase().includes(search) ||
            emp.department.toLowerCase().includes(search) ||
            emp.employeeCode.toLowerCase().includes(search)
        );
    }
    
    // Department filter
    if (departmentFilter.value !== 'all') {
        filtered = filtered.filter(emp => emp.department === departmentFilter.value);
    }
    
    // Role filter
    if (roleFilter.value !== 'all') {
        filtered = filtered.filter(emp => emp.role === roleFilter.value);
    }
    
    return filtered;
});

// Paginated employees for Departments tab
const paginatedDepartmentEmployees = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    return filteredDepartmentEmployees.value.slice(start, end);
});

// Paginated employees for All Employees tab
const paginatedEmployees = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    return filteredEmployees.value.slice(start, end);
});

// Get unique departments for filter
const uniqueDepartments = computed(() => {
    return [...new Set(allEmployees.value.map(emp => emp.department))].sort();
});

// Get unique roles for filter
const uniqueRoles = computed(() => {
    return [...new Set(allEmployees.value.map(emp => emp.role))].sort();
});

// Count active filters for Departments tab
const activeDepartmentTabFiltersCount = computed(() => {
    let count = 0;
    if (departmentTabPositionFilter.value !== 'all') count++;
    if (departmentTabRoleFilter.value !== 'all') count++;
    return count;
});

// Count active filters for All Employees tab
const activeFiltersCount = computed(() => {
    let count = 0;
    if (departmentFilter.value !== 'all') count++;
    if (roleFilter.value !== 'all') count++;
    return count;
});

// Filter functions
const openFilter = () => {
    tempDepartmentFilter.value = departmentFilter.value;
    tempRoleFilter.value = roleFilter.value;
    isFilterOpen.value = true;
};

const applyFilter = () => {
    departmentFilter.value = tempDepartmentFilter.value;
    roleFilter.value = tempRoleFilter.value;
    isFilterOpen.value = false;
};

const resetFilter = () => {
    tempDepartmentFilter.value = 'all';
    tempRoleFilter.value = 'all';
    departmentFilter.value = 'all';
    roleFilter.value = 'all';
    isFilterOpen.value = false;
};

// Filter functions for Departments tab
const openDepartmentTabFilter = () => {
    tempDepartmentTabPositionFilter.value = departmentTabPositionFilter.value;
    tempDepartmentTabRoleFilter.value = departmentTabRoleFilter.value;
    isDepartmentTabFilterOpen.value = true;
};

const applyDepartmentTabFilter = () => {
    departmentTabPositionFilter.value = tempDepartmentTabPositionFilter.value;
    departmentTabRoleFilter.value = tempDepartmentTabRoleFilter.value;
    isDepartmentTabFilterOpen.value = false;
};

const resetDepartmentTabFilter = () => {
    tempDepartmentTabPositionFilter.value = 'all';
    tempDepartmentTabRoleFilter.value = 'all';
    departmentTabPositionFilter.value = 'all';
    departmentTabRoleFilter.value = 'all';
    isDepartmentTabFilterOpen.value = false;
};

// Position dialog state
const isPositionDialogOpen = ref(false);
const positionDepartmentFilter = ref('all'); // Filter for positions by department in All Employees tab

// Department dialog state
const isDepartmentDialogOpen = ref(false);
const isAddingDepartment = ref(false);
const isSubmittingDepartment = ref(false);
const newDepartmentCode = ref('');
const newDepartmentName = ref('');
const editingDepartmentId = ref<number | null>(null);

// Delete confirmation dialog state
const isDeleteDialogOpen = ref(false);
const deleteType = ref<'department' | 'position' | 'employee' | null>(null);
const deleteItemId = ref<number | null>(null);
const deleteTimer = ref(5);
const deleteTimerInterval = ref<number | null>(null);
const dialogToReopenAfterDelete = ref<'position' | 'department' | null>(null); // Track which dialog to reopen if delete is cancelled

// Function to add new department
const addNewDepartment = async () => {
    // Prevent double submission
    if (isSubmittingDepartment.value) {
        return;
    }
    
    if (!newDepartmentCode.value.trim() || !newDepartmentName.value.trim()) {
        toast.error('Department code and name are required!');
        return;
    }
    
    // Prepare data matching Department model structure
    const departmentPayload = {
        code: newDepartmentCode.value.trim().toUpperCase(),
        name: newDepartmentName.value.trim(),
    }
    
    isSubmittingDepartment.value = true;
    try {
        const response = await api.post('/departments', departmentPayload);
        
        // Add new department to local state
        departments.value.push(response.data.department);
        
        // Reset form
        newDepartmentCode.value = '';
        newDepartmentName.value = '';
        isAddingDepartment.value = false;
        
        toast.success('Department created successfully');
    } catch (error: any) {
        if (error.response?.status === 422) {
            // Validation errors
            const errors = error.response.data.errors;
            const errorMessages = Object.values(errors).flat().join(', ');
            toast.error(errorMessages || 'Validation failed');
        } else {
            toast.error(error.response?.data?.message || 'Failed to create department');
        }
    } finally {
        isSubmittingDepartment.value = false;
    }
};

// Function to cancel adding department
const cancelAddDepartment = () => {
    newDepartmentCode.value = '';
    newDepartmentName.value = '';
    isAddingDepartment.value = false;
};

// Function to start editing a department
const startEditDepartment = (department: any) => {
    editingDepartmentId.value = department.id;
};

// Function to save edited department
const saveEditDepartment = async () => {
    // Prevent double submission
    if (isSubmittingDepartment.value) {
        return;
    }
    
    const department = departments.value.find(d => d.id === editingDepartmentId.value);
    if (!department) {
        editingDepartmentId.value = null;
        return;
    }
    
    if (!department.code.trim() || !department.name.trim()) {
        toast.error('Department code and name are required!');
        return;
    }
    
    // Prepare data matching Department model structure
    const departmentPayload = {
        code: department.code.trim().toUpperCase(),
        name: department.name.trim(),
    }
    
    isSubmittingDepartment.value = true;
    try {
        const response = await api.put(`/departments/${editingDepartmentId.value}`, departmentPayload);
        
        // Update local state with response data
        const index = departments.value.findIndex(d => d.id === editingDepartmentId.value);
        if (index !== -1) {
            departments.value[index] = response.data.department;
        }
        
        toast.success('Department updated successfully');
        editingDepartmentId.value = null;
    } catch (error: any) {
        if (error.response?.status === 422) {
            // Validation errors
            const errors = error.response.data.errors;
            const errorMessages = Object.values(errors).flat().join(', ');
            toast.error(errorMessages || 'Validation failed');
        } else {
            toast.error(error.response?.data?.message || 'Failed to update department');
        }
    } finally {
        isSubmittingDepartment.value = false;
    }
};

// Function to cancel editing department
const cancelEditDepartment = () => {
    editingDepartmentId.value = null;
};

// Function to open delete confirmation dialog
const openDeleteDialog = (type: 'department' | 'position' | 'employee', id: number) => {
    deleteType.value = type;
    deleteItemId.value = id;
    deleteTimer.value = 5;
    isDeleteDialogOpen.value = true;
    
    // Start countdown timer
    deleteTimerInterval.value = window.setInterval(() => {
        deleteTimer.value--;
        if (deleteTimer.value <= 0) {
            if (deleteTimerInterval.value) {
                clearInterval(deleteTimerInterval.value);
            }
        }
    }, 1000);
};

// Function to close delete dialog
const closeDeleteDialog = () => {
    if (deleteTimerInterval.value) {
        clearInterval(deleteTimerInterval.value);
    }
    isDeleteDialogOpen.value = false;
    deleteType.value = null;
    deleteItemId.value = null;
    deleteTimer.value = 5;
    
    // Reopen the dialog that was open before delete if delete was cancelled
    if (dialogToReopenAfterDelete.value === 'position') {
        isPositionDialogOpen.value = true;
    } else if (dialogToReopenAfterDelete.value === 'department') {
        isDepartmentDialogOpen.value = true;
    }
    dialogToReopenAfterDelete.value = null;
};

// Function to confirm delete
const confirmDelete = async () => {
    if (deleteItemId.value === null) return;
    
    try {
        if (deleteType.value === 'department') {
            await api.delete(`/departments/${deleteItemId.value}`);
            departments.value = departments.value.filter(d => d.id !== deleteItemId.value);
            toast.success('Department deleted successfully');
        } else if (deleteType.value === 'position') {
            await api.delete(`/positions/${deleteItemId.value}`);
            positions.value = positions.value.filter(p => p.id !== deleteItemId.value);
            toast.success('Position deleted successfully');
        } else if (deleteType.value === 'employee') {
            await api.delete(`/employees/${deleteItemId.value}`);
            allEmployees.value = allEmployees.value.filter(e => e.id !== deleteItemId.value);
            toast.success('Employee deleted successfully');
        }
        
        // Clear the dialog to reopen since item is deleted
        dialogToReopenAfterDelete.value = null;
        
        if (deleteTimerInterval.value) {
            clearInterval(deleteTimerInterval.value);
        }
        isDeleteDialogOpen.value = false;
        deleteType.value = null;
        deleteItemId.value = null;
        deleteTimer.value = 5;
    } catch (error: any) {
        if (error.response?.status === 422) {
            toast.error(error.response.data.message || 'Cannot delete this item');
        } else {
            toast.error(error.response?.data?.message || 'Failed to delete item');
        }
    }
};

// Function to delete department (kept for compatibility)
const deleteDepartment = (departmentId: number) => {
    // Close department dialog if open and remember to reopen if delete is cancelled
    if (isDepartmentDialogOpen.value) {
        isDepartmentDialogOpen.value = false;
        dialogToReopenAfterDelete.value = 'department';
    }
    openDeleteDialog('department', departmentId);
};

// Employee details dialog state
const isEmployeeDetailsOpen = ref(false);
const selectedEmployee = ref<any>(null);
const isEditMode = ref(false);
const isFromAllEmployeesTab = ref(false); // Track which tab opened the dialog

// Function to handle avatar upload
const handleAvatarUpload = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    
    if (file && (file.type === 'image/png' || file.type === 'image/jpeg')) {
        const reader = new FileReader();
        reader.onload = (e) => {
            if (selectedEmployee.value) {
                selectedEmployee.value.avatar = e.target?.result as string;
            }
        };
        reader.readAsDataURL(file);
    } else {
        toast.error('Please upload a PNG or JPG image.');
    }
};

// Function to handle new employee avatar upload
const handleNewEmployeeAvatarUpload = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    
    if (file && (file.type === 'image/png' || file.type === 'image/jpeg')) {
        const reader = new FileReader();
        reader.onload = (e) => {
            newEmployee.value.avatar = e.target?.result as string;
        };
        reader.readAsDataURL(file);
    } else {
        toast.error('Please upload a PNG or JPG image.');
    }
};

// Add employee dialog state
// Note: Matches Employee model structure for API integration
// Fields: first_name, last_name, department_id, position_id, employee_code, email, contact_number, birth_date, avatar, role
const isAddEmployeeDialogOpen = ref(false);
const addEmployeeStep = ref(1); // Track current step (1 or 2)
const isAddingFromDepartmentsTab = ref(false); // Track if adding from Departments tab
const isSubmittingEmployee = ref(false);
const newEmployee = ref({
    first_name: '',
    last_name: '',
    department_id: null as number | null,
    position_id: null as number | null,
    employee_code: '',
    email: '',
    contact_number: '',
    birth_date: '',
    hire_date: '',
    password: '',
    confirmPassword: '',
    avatar: '',
    role: 'employee' as 'employee' | 'department_manager' | 'admin', // Default role
    employment_status: 'active' as 'active' | 'inactive',
    inactive_reason: '' as '' | 'terminated' | 'resigned' | 'retired' | 'end_of_contract' | 'other',
    inactive_reason_notes: '',
    inactive_date: '',
});

// Function to open add employee dialog
const openAddEmployeeDialog = (fromDepartmentsTab = false) => {
    isAddingFromDepartmentsTab.value = fromDepartmentsTab;
    
    // Reset form
    // If from Departments tab, auto-set department_id to current user's department
    const currentUserDept = departments.value.find(d => d.name === currentUser.value.department);
    newEmployee.value = {
        first_name: '',
        last_name: '',
        department_id: fromDepartmentsTab ? (currentUserDept?.id || null) : null,
        position_id: null,
        employee_code: '',
        email: '',
        contact_number: '',
        birth_date: '',
        hire_date: '',
        password: '',
        confirmPassword: '',
        avatar: '',
        role: 'employee',
        employment_status: 'active',
        inactive_reason: '',
        inactive_reason_notes: '',
        inactive_date: '',
    };
    addEmployeeStep.value = 1; // Reset to first step
    isAddEmployeeDialogOpen.value = true;
};

// Function to go to next step
const goToNextStep = () => {
    if (newEmployee.value.first_name.trim() && 
        newEmployee.value.last_name.trim() &&
        newEmployee.value.department_id && 
        newEmployee.value.employee_code.trim()) {
        addEmployeeStep.value = 2;
    }
};

// Function to go back to previous step
const goToPreviousStep = () => {
    addEmployeeStep.value = 1;
};

// Function to add new employee
const addNewEmployee = async () => {
    // Prevent double submission
    if (isSubmittingEmployee.value) {
        return;
    }
    
    // Validate password match
    if (newEmployee.value.password !== newEmployee.value.confirmPassword) {
        toast.error('Passwords do not match!');
        return;
    }
    
    if (!newEmployee.value.email.trim() || !newEmployee.value.password.trim()) {
        toast.error('Email and password are required!');
        return;
    }
    
    // Validate required fields
    if (!newEmployee.value.first_name.trim() || !newEmployee.value.last_name.trim()) {
        toast.error('First name and last name are required!');
        return;
    }
    
    if (!newEmployee.value.department_id) {
        toast.error('Department is required!');
        return;
    }
    
    if (!newEmployee.value.employee_code.trim()) {
        toast.error('Employee code is required!');
        return;
    }

    if (newEmployee.value.employment_status === 'inactive') {
        if (!newEmployee.value.inactive_reason) {
            toast.error('Inactive reason is required when employee is inactive.');
            return;
        }
        if (!newEmployee.value.inactive_date) {
            toast.error('Inactive date is required when employee is inactive.');
            return;
        }
    }
    
    // Prepare data matching Employee model structure
    // Only send avatar if it's a Base64 string (new upload), otherwise send null
    let avatarToSend: string | null = null;
    if (newEmployee.value.avatar && newEmployee.value.avatar.startsWith('data:image/')) {
        avatarToSend = newEmployee.value.avatar;
    }
    
    const employeePayload = {
        first_name: newEmployee.value.first_name.trim(),
        last_name: newEmployee.value.last_name.trim(),
        department_id: newEmployee.value.department_id,
        position_id: newEmployee.value.position_id || null,
        employee_code: newEmployee.value.employee_code.trim(),
        email: newEmployee.value.email.trim(),
        contact_number: newEmployee.value.contact_number?.trim() || null,
        birth_date: newEmployee.value.birth_date || null,
        hire_date: newEmployee.value.hire_date || null,
        avatar: avatarToSend,
        role: newEmployee.value.role,
        employment_status: newEmployee.value.employment_status,
        inactive_reason: newEmployee.value.employment_status === 'inactive' ? (newEmployee.value.inactive_reason || null) : null,
        inactive_reason_notes: newEmployee.value.employment_status === 'inactive' ? (newEmployee.value.inactive_reason_notes?.trim() || null) : null,
        inactive_date: newEmployee.value.employment_status === 'inactive' ? (newEmployee.value.inactive_date || null) : null,
        password: newEmployee.value.password
    };
    
    isSubmittingEmployee.value = true;
    try {
        const response = await api.post('/employees', employeePayload);
        
        // Transform response to match frontend format
        const newEmp = {
            id: response.data.employee.id,
            employeeCode: response.data.employee.employee_code,
            name: response.data.employee.name,
            first_name: response.data.employee.first_name,
            last_name: response.data.employee.last_name,
            department: response.data.employee.department,
            department_id: response.data.employee.department_id,
            position: response.data.employee.position,
            position_id: response.data.employee.position_id,
            role: formatRole(response.data.employee.role),
            email: response.data.employee.email,
            contactNum: response.data.employee.contact_number || '',
            birthday: response.data.employee.birth_date || '',
            hireDate: response.data.employee.hire_date || '',
            employmentStatus: response.data.employee.employment_status || 'active',
            inactiveReason: response.data.employee.inactive_reason || '',
            inactiveReasonNotes: response.data.employee.inactive_reason_notes || '',
            inactiveDate: response.data.employee.inactive_date || '',
            lengthOfService: response.data.employee.length_of_service || 'N/A',
            avatar: response.data.employee.avatar || ''
        };
        
        allEmployees.value.push(newEmp);
        toast.success(response.data.message || 'Employee created successfully');
        isAddEmployeeDialogOpen.value = false;
        addEmployeeStep.value = 1; // Reset step
        
        // Reset form
        const currentUserDept = departments.value.find(d => d.name === currentUser.value.department);
        newEmployee.value = {
            first_name: '',
            last_name: '',
            department_id: isAddingFromDepartmentsTab.value ? (currentUserDept?.id || null) : null,
            position_id: null,
            employee_code: '',
            email: '',
            contact_number: '',
            birth_date: '',
            hire_date: '',
            password: '',
            confirmPassword: '',
            avatar: '',
            role: 'employee',
            employment_status: 'active',
            inactive_reason: '',
            inactive_reason_notes: '',
            inactive_date: '',
        };
    } catch (error: any) {
        // Show specific error message
        if (error.response?.data?.message) {
            toast.error(error.response.data.message);
        } else if (error.response?.data?.errors) {
            const errors = error.response.data.errors;
            const errorMessages = Object.values(errors).flat().join(', ');
            toast.error(errorMessages || 'Failed to create employee');
        } else {
            toast.error('Failed to create employee. Please try again.');
        }
        console.error('Error creating employee:', error);
    } finally {
        isSubmittingEmployee.value = false;
    }
};

// Function to view employee details
const viewEmployeeDetails = (employee: any, fromAllEmployees = false) => {
    const nameParts = employee.name.split(' ');
    const first_name = nameParts[0] || '';
    const last_name = nameParts.slice(1).join(' ') || '';
    
    // Find department and position IDs (in production, these would come from backend)
    const department = departments.value.find(d => d.name === employee.department);
    const position = positions.value.find(p => p.name === employee.position);
    
    // Map role display value to enum value
    let role: 'employee' | 'department_manager' | 'admin' = 'employee';
    if (employee.role === 'Admin') role = 'admin';
    else if (employee.role === 'Department Manager') role = 'department_manager';
    
    selectedEmployee.value = { 
        ...employee,
        first_name,
        last_name,
        department_id: department?.id || null,
        position_id: position?.id || null,
        employee_code: employee.employeeCode,
        contact_number: employee.contactNum,
        birth_date: employee.birthday,
        hire_date: employee.hireDate,
        employment_status: employee.employmentStatus || 'active',
        inactive_reason: employee.inactiveReason || '',
        inactive_reason_notes: employee.inactiveReasonNotes || '',
        inactive_date: employee.inactiveDate || '',
        length_of_service: employee.lengthOfService || 'N/A',
        role,
        newPassword: '',
        confirmPassword: ''
    };
    isEditMode.value = false;
    isFromAllEmployeesTab.value = fromAllEmployees;
    isEmployeeDetailsOpen.value = true;
};

// Function to toggle edit mode
const toggleEditMode = () => {
    if (isEditMode.value) {
        // If canceling edit, restore original data and clear password fields
        const employee = allEmployees.value.find(e => e.id === selectedEmployee.value.id);
        if (employee) {
            const nameParts = employee.name.split(' ');
            selectedEmployee.value.first_name = nameParts[0] || '';
            selectedEmployee.value.last_name = nameParts.slice(1).join(' ') || '';
            const department = departments.value.find(d => d.name === employee.department);
            const position = positions.value.find(p => p.name === employee.position);
            selectedEmployee.value.department_id = department?.id || null;
            selectedEmployee.value.position_id = position?.id || null;
        }
        selectedEmployee.value.newPassword = '';
        selectedEmployee.value.confirmPassword = '';
    }
    isEditMode.value = !isEditMode.value;
};

// Function to save employee details
const saveEmployeeDetails = async () => {
    // Prevent double submission
    if (isSubmittingEmployee.value) {
        return;
    }
    
    // Validate password if provided
    if (selectedEmployee.value.newPassword) {
        if (selectedEmployee.value.newPassword !== selectedEmployee.value.confirmPassword) {
            toast.error('Passwords do not match!');
            return;
        }
        if (selectedEmployee.value.newPassword.length < 6) {
            toast.error('Password must be at least 6 characters long!');
            return;
        }
    }

    if (selectedEmployee.value.employment_status === 'inactive') {
        if (!selectedEmployee.value.inactive_reason) {
            toast.error('Inactive reason is required when employee is inactive.');
            return;
        }
        if (!selectedEmployee.value.inactive_date) {
            toast.error('Inactive date is required when employee is inactive.');
            return;
        }
    }
    
    // Check if avatar is a new Base64 image or unchanged
    let avatarToSend: string | null = null;
    if (selectedEmployee.value.avatar) {
        // If it's a Base64 string (new upload), send it
        if (selectedEmployee.value.avatar.startsWith('data:image/')) {
            avatarToSend = selectedEmployee.value.avatar;
        }
        // If it's a URL string (unchanged avatar), send null (keep existing)
        // The backend will keep the existing avatar if null/empty
    }
    
    // Convert role display value back to enum value if needed
    let roleValue: 'employee' | 'department_manager' | 'admin' = selectedEmployee.value.role;
    if (typeof selectedEmployee.value.role === 'string') {
        if (selectedEmployee.value.role === 'Admin') roleValue = 'admin';
        else if (selectedEmployee.value.role === 'Department Manager') roleValue = 'department_manager';
        else if (selectedEmployee.value.role === 'Employee') roleValue = 'employee';
    }
    
    // Prepare data matching Employee model structure
    const employeePayload: any = {
        first_name: selectedEmployee.value.first_name.trim(),
        last_name: selectedEmployee.value.last_name.trim(),
        department_id: selectedEmployee.value.department_id || null,
        position_id: selectedEmployee.value.position_id || null,
        employee_code: selectedEmployee.value.employee_code.trim(),
        email: selectedEmployee.value.email.trim(),
        contact_number: selectedEmployee.value.contact_number?.trim() || null,
        birth_date: selectedEmployee.value.birth_date || null,
        hire_date: selectedEmployee.value.hire_date || null,
        avatar: avatarToSend,
        role: roleValue,
        employment_status: selectedEmployee.value.employment_status || 'active',
        inactive_reason: selectedEmployee.value.employment_status === 'inactive' ? (selectedEmployee.value.inactive_reason || null) : null,
        inactive_reason_notes: selectedEmployee.value.employment_status === 'inactive' ? (selectedEmployee.value.inactive_reason_notes?.trim() || null) : null,
        inactive_date: selectedEmployee.value.employment_status === 'inactive' ? (selectedEmployee.value.inactive_date || null) : null,
    };
    
    // Add password only if provided
    if (selectedEmployee.value.newPassword) {
        employeePayload.password = selectedEmployee.value.newPassword;
    }
    
    isSubmittingEmployee.value = true;
    try {
        const response = await api.put(`/employees/${selectedEmployee.value.id}`, employeePayload);
        
        // Update local state with response data
        const updatedEmployee = response.data.employee;
        selectedEmployee.value.name = updatedEmployee.name;
        selectedEmployee.value.first_name = updatedEmployee.first_name;
        selectedEmployee.value.last_name = updatedEmployee.last_name;
        selectedEmployee.value.department = updatedEmployee.department;
        selectedEmployee.value.department_id = updatedEmployee.department_id;
        selectedEmployee.value.position = updatedEmployee.position;
        selectedEmployee.value.position_id = updatedEmployee.position_id;
        selectedEmployee.value.employeeCode = updatedEmployee.employee_code;
        selectedEmployee.value.contactNum = updatedEmployee.contact_number || '';
        selectedEmployee.value.birthday = updatedEmployee.birth_date || '';
        selectedEmployee.value.hireDate = updatedEmployee.hire_date || '';
        selectedEmployee.value.hire_date = updatedEmployee.hire_date || '';
        selectedEmployee.value.employmentStatus = updatedEmployee.employment_status || 'active';
        selectedEmployee.value.employment_status = updatedEmployee.employment_status || 'active';
        selectedEmployee.value.inactiveReason = updatedEmployee.inactive_reason || '';
        selectedEmployee.value.inactive_reason = updatedEmployee.inactive_reason || '';
        selectedEmployee.value.inactiveReasonNotes = updatedEmployee.inactive_reason_notes || '';
        selectedEmployee.value.inactive_reason_notes = updatedEmployee.inactive_reason_notes || '';
        selectedEmployee.value.inactiveDate = updatedEmployee.inactive_date || '';
        selectedEmployee.value.inactive_date = updatedEmployee.inactive_date || '';
        selectedEmployee.value.lengthOfService = updatedEmployee.length_of_service || 'N/A';
        selectedEmployee.value.length_of_service = updatedEmployee.length_of_service || 'N/A';
        selectedEmployee.value.avatar = updatedEmployee.avatar || '';
        selectedEmployee.value.role = formatRole(updatedEmployee.role);
        
        // Update the employee in the list
        const index = allEmployees.value.findIndex(e => e.id === selectedEmployee.value.id);
        if (index !== -1) {
            allEmployees.value[index] = {
                ...allEmployees.value[index],
                ...selectedEmployee.value
            };
        }
        
        toast.success(response.data.message || 'Employee updated successfully');
        isEditMode.value = false;
        selectedEmployee.value.newPassword = '';
        selectedEmployee.value.confirmPassword = '';
    } catch (error: any) {
        // Show specific error message
        if (error.response?.data?.message) {
            toast.error(error.response.data.message);
        } else if (error.response?.data?.errors) {
            const errors = error.response.data.errors;
            const errorMessages = Object.values(errors).flat().join(', ');
            toast.error(errorMessages || 'Failed to update employee');
        } else {
            toast.error('Failed to update employee. Please try again.');
        }
        console.error('Error updating employee:', error);
    } finally {
        isSubmittingEmployee.value = false;
    }
};

// Watch for department changes in All Employees tab to reset position
watch(() => selectedEmployee.value?.department_id, (newDeptId, oldDeptId) => {
    if (isFromAllEmployeesTab.value && isEditMode.value && newDeptId !== oldDeptId && oldDeptId !== undefined) {
        // Reset position_id when department changes (positions are department-specific)
        selectedEmployee.value.position_id = null;
    }
});

// Add position state
// Note: Matches Position model structure for API integration
// Fields: department_id (required), name (required)
const isAddingPosition = ref(false);
const isSubmittingPosition = ref(false);
const newPositionName = ref('');
const newPositionDepartment = ref<number | string>(''); // Can be department ID or name for local handling

// Function to add new position
const addNewPosition = async () => {
    // Prevent double submission
    if (isSubmittingPosition.value) {
        return;
    }
    
    if (!newPositionName.value.trim()) {
        toast.error('Position name is required!');
        return;
    }
    
    // Find department ID
    let departmentId: number | null = null;
    if (typeof newPositionDepartment.value === 'number') {
        departmentId = newPositionDepartment.value;
    } else {
        const department = departments.value.find(d => 
            d.name === newPositionDepartment.value || d.id === Number(newPositionDepartment.value)
        );
        departmentId = department?.id || null;
    }
    
    if (!departmentId) {
        toast.error('Department is required!');
        return;
    }
    
    // Prepare data matching Position model structure
    const positionPayload = {
        department_id: departmentId,
        name: newPositionName.value.trim(),
    }
    
    isSubmittingPosition.value = true;
    try {
        const response = await api.post('/positions', positionPayload);
        
        // Add new position to local state
        positions.value.push(response.data.position);
        
        // Reset form
        newPositionName.value = '';
        newPositionDepartment.value = '';
        isAddingPosition.value = false;
        
        toast.success('Position created successfully');
    } catch (error: any) {
        if (error.response?.status === 422) {
            // Validation errors
            const errors = error.response.data.errors;
            const errorMessages = Object.values(errors).flat().join(', ');
            toast.error(errorMessages || 'Validation failed');
        } else {
            toast.error(error.response?.data?.message || 'Failed to create position');
        }
    } finally {
        isSubmittingPosition.value = false;
    }
};

// Function to cancel adding position
const cancelAddPosition = () => {
    newPositionName.value = '';
    newPositionDepartment.value = '';
    isAddingPosition.value = false;
};

// Function to close position dialog and reset filter
const closePositionDialog = () => {
    positionDepartmentFilter.value = 'all';
    isPositionDialogOpen.value = false;
};

// Edit position state
const editingPositionId = ref<number | null>(null);

// Function to start editing a position
const startEditPosition = (position: any) => {
    editingPositionId.value = position.id;
};

// Function to save edited position
const saveEditPosition = async () => {
    // Prevent double submission
    if (isSubmittingPosition.value) {
        return;
    }
    
    const position = positions.value.find(p => p.id === editingPositionId.value);
    if (!position) {
        editingPositionId.value = null;
        return;
    }
    
    if (!position.name.trim()) {
        toast.error('Position name is required!');
        return;
    }
    
    // Get department_id from position (should be set when editing)
    const departmentId = position.department_id || null;
    
    if (!departmentId) {
        toast.error('Department is required!');
        return;
    }
    
    // Prepare data matching Position model structure
    const positionPayload = {
        department_id: departmentId,
        name: position.name.trim(),
    }
    
    isSubmittingPosition.value = true;
    try {
        const response = await api.put(`/positions/${editingPositionId.value}`, positionPayload);
        
        // Update local state with response data
        const index = positions.value.findIndex(p => p.id === editingPositionId.value);
        if (index !== -1) {
            positions.value[index] = response.data.position;
        }
        
        toast.success('Position updated successfully');
        editingPositionId.value = null;
    } catch (error: any) {
        if (error.response?.status === 422) {
            // Validation errors
            const errors = error.response.data.errors;
            const errorMessages = Object.values(errors).flat().join(', ');
            toast.error(errorMessages || 'Validation failed');
        } else {
            toast.error(error.response?.data?.message || 'Failed to update position');
        }
    } finally {
        isSubmittingPosition.value = false;
    }
};

// Function to cancel editing position
const cancelEditPosition = () => {
    editingPositionId.value = null;
};

// Function to delete position
const deletePosition = (positionId: number) => {
    // Close position dialog if open and remember to reopen if delete is cancelled
    if (isPositionDialogOpen.value) {
        isPositionDialogOpen.value = false;
        dialogToReopenAfterDelete.value = 'position';
    }
    openDeleteDialog('position', positionId);
};

// Function to delete employee
const deleteEmployee = (employeeId: number) => {
    openDeleteDialog('employee', employeeId);
};

// Positions from backend (already formatted correctly)
const positions = ref(props.positions);

// Helper function to format role for display
const formatRole = (role: string): string => {
    if (role === 'admin') return 'Admin';
    if (role === 'department_manager') return 'Department Manager';
    if (role === 'employee') return 'Employee';
    return role;
};

const roleBadgeClasses: Record<string, string> = {
    admin: 'bg-purple-500/15 text-purple-200 border-purple-500/40',
    'department manager': 'bg-blue-500/15 text-blue-300 border-blue-500/40',
    employee: 'bg-emerald-500/15 text-emerald-300 border-emerald-500/40',
};

const getRoleBadgeClass = (role: string): string => {
    const normalized = role.toLowerCase();
    return roleBadgeClasses[normalized] ?? 'bg-slate-500/15 text-slate-300 border-slate-500/40';
};

const formatInactiveReason = (reason: string): string => {
    if (!reason) return 'N/A';
    if (reason === 'end_of_contract') return 'End of Contract';
    return reason
        .split('_')
        .map((p) => p.charAt(0).toUpperCase() + p.slice(1))
        .join(' ');
};

const getEmploymentStatusLabel = (status: string, reason?: string): string => {
    if ((status || '').toLowerCase() === 'inactive') {
        return reason ? `Inactive - ${formatInactiveReason(reason)}` : 'Inactive';
    }
    return 'Active';
};

const getEmploymentStatusBadgeClass = (status: string): string => {
    return (status || '').toLowerCase() === 'inactive'
        ? 'bg-rose-500/15 text-rose-300 border-rose-500/40'
        : 'bg-emerald-500/15 text-emerald-300 border-emerald-500/40';
};

// Computed property for current user's formatted role
const currentUserRoleDisplay = computed(() => formatRole(currentUser.value.role));

// Computed properties for role-based permissions
const canViewAllEmployees = computed(() => currentUser.value.role === 'admin');
const canAddEmployee = computed(() => currentUser.value.role !== 'employee');
const canEditEmployee = computed(() => currentUser.value.role !== 'employee');
const canDeleteEmployee = computed(() => currentUser.value.role !== 'employee');
const canAddPosition = computed(() => currentUser.value.role !== 'employee');
const canEditPosition = computed(() => currentUser.value.role !== 'employee');
const canDeletePosition = computed(() => currentUser.value.role !== 'employee');
const canAddDepartment = computed(() => currentUser.value.role === 'admin');
const canEditDepartment = computed(() => currentUser.value.role === 'admin');
const canDeleteDepartment = computed(() => currentUser.value.role === 'admin');

// Filtered positions based on selected department in add employee form
const filteredPositionsForEmployee = computed(() => {
    if (!newEmployee.value.department_id) {
        return [];
    }
    return positions.value.filter(pos => pos.department_id === newEmployee.value.department_id);
});

// Filtered positions for current user's department
const filteredPositionsForCurrentUser = computed(() => {
    if (!currentUser.value.department) {
        return [];
    }
    return positions.value.filter(pos => pos.department === currentUser.value.department);
});

// Filtered positions for selected employee's department
const filteredPositionsForSelectedEmployee = computed(() => {
    if (!selectedEmployee.value?.department_id) {
        return [];
    }
    return positions.value.filter(pos => pos.department_id === selectedEmployee.value.department_id);
});

// Filtered positions for All Employees tab Position dialog
const filteredPositionsByDepartment = computed(() => {
    if (positionDepartmentFilter.value === 'all') {
        return positions.value;
    }
    // Find department ID if filter is by name
    const department = departments.value.find(d => d.name === positionDepartmentFilter.value);
    if (department) {
        return positions.value.filter(pos => pos.department_id === department.id);
    }
    return positions.value;
});
</script>

<template>
    <Head title="Department" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="relative min-h-[100vh] p-6 flex-1 border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border">
            <Tabs default-value="departments" class="w-full">
                <TabsList :class="canViewAllEmployees ? 'grid w-full grid-cols-2 mb-6' : 'grid w-full grid-cols-1 mb-6'">
                    <TabsTrigger value="departments">
                        <div class="flex items-center gap-2">
                            <Building2 :size="16" />
                            <span>My Department</span>
                        </div>
                    </TabsTrigger>
                    <TabsTrigger v-if="canViewAllEmployees" value="employees">
                        <div class="flex items-center gap-2">
                            <Users :size="16" />
                            <span>All Employees</span>
                        </div>
                    </TabsTrigger>
                </TabsList>

                <!-- Departments Tab -->
                <TabsContent value="departments">
                    <!-- User Info Card -->
                    <Card class="mb-6 border-2 shadow-lg">
                        <CardContent class="px-8 py-2 relative">
                            <!-- Edit Button - Top Right aligned with name -->
                            <div v-if="canEditEmployee" class="absolute top-2 right-8">
                                <Button 
                                    variant="ghost" 
                                    size="icon" 
                                    class="h-8 w-8"
                                    @click="openUserInfoEdit"
                                >
                                    <Edit :size="16" />
                                </Button>
                            </div>
                            
                            <div class="flex flex-col md:flex-row items-center md:items-center gap-8">
                                <!-- Avatar -->
                                <div class="flex-shrink-0 mx-4 md:mx-6">
                                    <div class="w-40 h-40 md:w-36 md:h-36 rounded-full bg-gradient-to-br from-primary via-primary/90 to-primary/70 flex items-center justify-center shadow-xl ring-4 ring-primary/10 overflow-hidden">
                                        <img v-if="currentUser.avatar" :src="currentUser.avatar" alt="Avatar" class="w-full h-full object-cover" />
                                        <span v-else class="text-5xl md:text-4xl font-bold text-white">
                                            {{ currentUser.name.split(' ').map(n => n[0]).join('') }}
                                        </span>
                                    </div>
                                </div>
                                
                                <!-- User Info -->
                                <div class="flex-1 w-full">
                                    <div class="space-y-4">
                                        <!-- Name and Position -->
                                        <div class="text-center md:text-left">
                                            <h2 class="text-2xl font-bold mb-1">{{ currentUser.name }}</h2>
                                            <p class="text-base text-muted-foreground">{{ currentUser.position }}</p>
                                        </div>
                                        
                                        <!-- Info Grid -->
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
                                            <!-- Employee Code -->
                                            <div class="flex items-center gap-3">
                                                <div class="w-10 h-10 rounded-lg bg-muted flex items-center justify-center">
                                                    <UserCircle :size="18" class="text-muted-foreground" />
                                                </div>
                                                <div>
                                                    <p class="text-xs text-muted-foreground mb-0.5">Employee Code</p>
                                                    <p class="text-sm font-mono font-semibold">{{ currentUser.employeeCode }}</p>
                                                </div>
                                            </div>
                                            
                                            <!-- Department -->
                                            <div class="flex items-center gap-3">
                                                <div class="w-10 h-10 rounded-lg bg-muted flex items-center justify-center">
                                                    <Building2 :size="18" class="text-muted-foreground" />
                                                </div>
                                                <div>
                                                    <p class="text-xs text-muted-foreground mb-0.5">Department</p>
                                                    <Badge variant="outline" class="gap-1.5 mt-0.5">
                                                        {{ currentUser.department }}
                                                    </Badge>
                                                </div>
                                            </div>
                                            
                                            <!-- Role -->
                                            <div class="flex items-center gap-3">
                                                <div class="w-10 h-10 rounded-lg bg-muted flex items-center justify-center">
                                                    <Shield :size="18" class="text-muted-foreground" />
                                                </div>
                                                <div>
                                                    <p class="text-xs text-muted-foreground mb-0.5">Role</p>
                                                    <Badge 
                                                        variant="outline" 
                                                        class="gap-1.5 mt-0.5"
                                                        :class="getRoleBadgeClass(currentUserRoleDisplay)"
                                                    >
                                                        {{ currentUserRoleDisplay }}
                                                    </Badge>
                                                </div>
                                            </div>
                                            
                                            <!-- Email -->
                                            <div class="flex items-center gap-3">
                                                <div class="w-10 h-10 rounded-lg bg-muted flex items-center justify-center">
                                                    <Mail :size="18" class="text-muted-foreground" />
                                                </div>
                                                <div>
                                                    <p class="text-xs text-muted-foreground mb-0.5">Email</p>
                                                    <p class="text-sm font-medium break-all">{{ currentUser.email }}</p>
                                                </div>
                                            </div>
                                            
                                            <!-- Contact Number -->
                                            <div class="flex items-center gap-3">
                                                <div class="w-10 h-10 rounded-lg bg-muted flex items-center justify-center">
                                                    <Phone :size="18" class="text-muted-foreground" />
                                                </div>
                                                <div>
                                                    <p class="text-xs text-muted-foreground mb-0.5">Contact</p>
                                                    <p class="text-sm font-medium">{{ currentUser.contactNum }}</p>
                                                </div>
                                            </div>
                                            
                                            <!-- Birthday -->
                                            <div class="flex items-center gap-3">
                                                <div class="w-10 h-10 rounded-lg bg-muted flex items-center justify-center">
                                                    <Calendar :size="18" class="text-muted-foreground" />
                                                </div>
                                                <div>
                                                    <p class="text-xs text-muted-foreground mb-0.5">Birthday</p>
                                                    <p class="text-sm font-medium">{{ new Date(currentUser.birthday).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Search Bar with Filter and Position Button -->
                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 mb-6">
                        <div class="relative flex-1">
                            <Search :size="16" class="absolute left-3 top-1/2 transform -translate-y-1/2 text-muted-foreground" />
                            <Input
                                v-model="employeeSearch"
                                placeholder="Search by name, position, or employee code..."
                                class="w-full pl-10"
                            />
                        </div>
                        <div class="flex items-center gap-2 sm:gap-3">
                            <Popover v-model:open="isDepartmentTabFilterOpen">
                                <PopoverTrigger as-child>
                                    <Button variant="outline" class="gap-2 flex-1 sm:flex-none" @click="openDepartmentTabFilter">
                                        <Filter :size="16" />
                                        <span class="hidden sm:inline">Filter</span>
                                        <Badge v-if="activeDepartmentTabFiltersCount > 0" variant="secondary" class="ml-1">
                                            {{ activeDepartmentTabFiltersCount }}
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
                                            <Label class="text-sm font-medium">Position</Label>
                                            <Select v-model="tempDepartmentTabPositionFilter">
                                                <SelectTrigger>
                                                    <SelectValue placeholder="Filter by position" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="all">All Positions</SelectItem>
                                                    <SelectItem 
                                                        v-for="position in filteredPositionsForCurrentUser" 
                                                        :key="position.id" 
                                                        :value="position.name"
                                                    >
                                                        {{ position.name }}
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div class="space-y-2">
                                            <Label class="text-sm font-medium">Role</Label>
                                            <Select v-model="tempDepartmentTabRoleFilter">
                                                <SelectTrigger>
                                                    <SelectValue placeholder="Filter by role" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="all">All Roles</SelectItem>
                                                    <SelectItem 
                                                        v-for="role in uniqueRoles" 
                                                        :key="role" 
                                                        :value="role"
                                                    >
                                                        {{ role }}
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </div>
                                    <div class="flex gap-2 pt-2">
                                        <Button variant="outline" class="flex-1" @click="resetDepartmentTabFilter">
                                            Reset
                                        </Button>
                                        <Button class="flex-1" @click="applyDepartmentTabFilter">
                                            Apply
                                        </Button>
                                    </div>
                                </div>
                            </PopoverContent>
                            </Popover>
                            <Dialog v-model:open="isPositionDialogOpen">
                                <DialogTrigger as-child>
                                    <Button variant="outline" class="gap-2 flex-1 sm:flex-none sm:min-w-[120px]">
                                        <span class="hidden sm:inline">Position</span>
                                        <span class="sm:hidden">Pos</span>
                                    </Button>
                                </DialogTrigger>
                            <DialogContent class="sm:max-w-[500px] bg-background">
                                <DialogHeader>
                                    <DialogTitle class="text-xl">Positions ({{ filteredPositionsForCurrentUser.length }})</DialogTitle>
                                </DialogHeader>
                                
                                <!-- Add Position Form -->
                                <div v-if="isAddingPosition && canAddPosition" class="p-3 rounded-lg border border-primary/50 bg-muted/30 mb-4">
                                    <div class="space-y-3">
                                        <Input 
                                            v-model="newPositionName"
                                            placeholder="Position name"
                                        />
                                        <Select v-model="newPositionDepartment" :disabled="true">
                                            <SelectTrigger>
                                                <SelectValue :placeholder="currentUser.department" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem :value="departments.find((d: { id: number; name: string }) => d.name === currentUser.department)?.id || ''">
                                                    {{ currentUser.department }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <div class="flex gap-2">
                                            <Button size="sm" @click="addNewPosition" :disabled="isSubmittingPosition" class="flex-1 gap-2">
                                                <Loader2 v-if="isSubmittingPosition" :size="14" class="animate-spin" />
                                                <Plus v-else :size="14" />
                                                {{ isSubmittingPosition ? 'Adding...' : 'Add' }}
                                            </Button>
                                            <Button size="sm" variant="outline" @click="cancelAddPosition">
                                                Cancel
                                            </Button>
                                        </div>
                                    </div>
                                </div>

                                <ScrollArea class="h-[400px] pr-4">
                                    <div class="space-y-3 py-2">
                                        <div 
                                            v-for="position in filteredPositionsForCurrentUser" 
                                            :key="position.id"
                                            class="flex items-center gap-3 p-3 rounded-lg bg-muted/50 hover:bg-muted transition-colors"
                                        >
                                            <template v-if="editingPositionId === position.id">
                                                <div class="flex-1 space-y-2">
                                                    <Input v-model="position.name" placeholder="Position name" />
                                                    <Select :model-value="position.department_id" :disabled="true">
                                                        <SelectTrigger class="text-xs">
                                                            <SelectValue :placeholder="currentUser.department" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem :value="departments.find((d: { id: number; name: string }) => d.name === currentUser.department)?.id || ''">
                                                                {{ currentUser.department }}
                                                            </SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <Button variant="ghost" size="icon" class="h-8 w-8" @click="saveEditPosition" :disabled="isSubmittingPosition">
                                                        <Loader2 v-if="isSubmittingPosition" :size="14" class="animate-spin" />
                                                        <Check v-else :size="14" />
                                                    </Button>
                                                    <Button variant="ghost" size="icon" class="h-8 w-8" @click="cancelEditPosition">
                                                        <X :size="14" />
                                                    </Button>
                                                </div>
                                            </template>
                                            <template v-else>
                                                <div class="flex-1 min-w-0">
                                                    <p class="font-medium truncate">{{ position.name }}</p>
                                                    <p class="text-xs text-muted-foreground">{{ position.department }}</p>
                                                </div>
                                                <div v-if="canEditPosition || canDeletePosition" class="flex items-center gap-2">
                                                    <Button v-if="canEditPosition" variant="ghost" size="icon" class="h-8 w-8" @click="startEditPosition(position)">
                                                        <Edit :size="14" />
                                                    </Button>
                                                    <Button v-if="canDeletePosition" variant="ghost" size="icon" class="h-8 w-8 text-destructive hover:text-destructive" @click="deletePosition(position.id)">
                                                        <Trash2 :size="14" />
                                                    </Button>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </ScrollArea>

                                <div class="border-t pt-4 flex items-center justify-between">
                                    <Button v-if="canAddPosition" variant="link" class="gap-2 text-primary" @click="() => { const dept = departments.find((d: { id: number; name: string }) => d.name === currentUser.department); newPositionDepartment = dept?.id || ''; isAddingPosition = true; }">
                                        <Plus :size="16" />
                                        Add Position
                                    </Button>
                                    <Button variant="outline" @click="isPositionDialogOpen = false">
                                        Close
                                    </Button>
                                </div>
                            </DialogContent>
                        </Dialog>
                        <Button v-if="canAddEmployee" variant="outline" class="gap-2 flex-1 sm:flex-none" @click="openAddEmployeeDialog(true)">
                            <Plus :size="16" />
                            <span>Add</span>
                        </Button>
                    </div>
                </div>

                    <!-- Employee Data Table -->
                    <Card>
                        <CardHeader>
                            <CardTitle>All Employees</CardTitle>
                            <CardDescription>Complete employee directory with contact information</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <!-- Desktop Table View -->
                            <div class="hidden md:block relative w-full overflow-auto">
                                <table class="w-full caption-bottom text-sm">
                                    <thead class="[&_tr]:border-b">
                                        <tr class="border-b transition-colors hover:bg-muted/50">
                                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Name</th>
                                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Department</th>
                                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Position</th>
                                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Role</th>
                                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Status</th>
                                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Length of Service</th>
                                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Employee Code</th>
                                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Email</th>
                                        </tr>
                                    </thead>
                                    <tbody class="[&_tr:last-child]:border-0">
                                        <tr v-for="employee in paginatedDepartmentEmployees" :key="employee.id" class="border-b transition-colors hover:bg-muted/50">
                                            <td class="p-4 align-middle font-medium">{{ employee.name }}</td>
                                            <td class="p-4 align-middle">
                                                <Badge variant="outline">{{ employee.department }}</Badge>
                                            </td>
                                            <td class="p-4 align-middle text-sm">{{ employee.position }}</td>
                                            <td class="p-4 align-middle">
                                                <Badge 
                                                    variant="outline"
                                                    :class="getRoleBadgeClass(employee.role)"
                                                >
                                                    {{ employee.role }}
                                                </Badge>
                                            </td>
                                            <td class="p-4 align-middle">
                                                <Badge
                                                    variant="outline"
                                                    :class="getEmploymentStatusBadgeClass(employee.employmentStatus)"
                                                >
                                                    {{ getEmploymentStatusLabel(employee.employmentStatus, employee.inactiveReason) }}
                                                </Badge>
                                            </td>
                                            <td class="p-4 align-middle text-sm">{{ employee.lengthOfService || 'N/A' }}</td>
                                            <td class="p-4 align-middle font-mono text-xs">{{ employee.employeeCode }}</td>
                                            <td class="p-4 align-middle text-sm text-muted-foreground">{{ employee.email }}</td>
                                            <td class="p-4 align-middle text-right">
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger as-child>
                                                        <Button variant="ghost" size="icon" class="h-8 w-8">
                                                            <MoreVertical :size="16" />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <DropdownMenuItem class="gap-2" @click="viewEmployeeDetails(employee)">
                                                            <Eye :size="16" />
                                                            View Details
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem v-if="canDeleteEmployee" class="gap-2 text-destructive focus:text-destructive" @click="deleteEmployee(employee.id)">
                                                            <Trash2 :size="16" />
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
                                    v-for="employee in paginatedDepartmentEmployees" 
                                    :key="employee.id"
                                    class="p-4 rounded-lg border"
                                >
                                    <div class="flex justify-between items-start mb-3">
                                        <div class="flex-1">
                                            <h3 class="font-semibold">{{ employee.name }}</h3>
                                            <p class="text-xs text-muted-foreground font-mono">{{ employee.employeeCode }}</p>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <Badge variant="outline" class="text-xs">{{ employee.department }}</Badge>
                                            <DropdownMenu>
                                                <DropdownMenuTrigger as-child>
                                                    <Button variant="ghost" size="icon" class="h-8 w-8">
                                                        <MoreVertical :size="16" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem class="gap-2" @click="viewEmployeeDetails(employee)">
                                                        <Eye :size="16" />
                                                        View Details
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem v-if="canDeleteEmployee" class="gap-2 text-destructive focus:text-destructive" @click="deleteEmployee(employee.id)">
                                                        <Trash2 :size="16" />
                                                        Delete
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </div>
                                    </div>
                                    <div class="space-y-2 text-sm">
                                        <div class="flex gap-2">
                                            <span class="text-muted-foreground w-24">Position:</span>
                                            <span>{{ employee.position }}</span>
                                        </div>
                                        <div class="flex gap-2">
                                            <span class="text-muted-foreground w-24">Role:</span>
                                            <Badge 
                                                variant="outline" 
                                                class="text-xs"
                                                :class="getRoleBadgeClass(employee.role)"
                                            >
                                                {{ employee.role }}
                                            </Badge>
                                        </div>
                                        <div class="flex gap-2">
                                            <span class="text-muted-foreground w-24">Status:</span>
                                            <Badge
                                                variant="outline"
                                                class="text-xs"
                                                :class="getEmploymentStatusBadgeClass(employee.employmentStatus)"
                                            >
                                                {{ getEmploymentStatusLabel(employee.employmentStatus, employee.inactiveReason) }}
                                            </Badge>
                                        </div>
                                        <div class="flex gap-2">
                                            <span class="text-muted-foreground w-24">Service:</span>
                                            <span>{{ employee.lengthOfService || 'N/A' }}</span>
                                        </div>
                                        <div class="flex gap-2">
                                            <span class="text-muted-foreground w-24">Email:</span>
                                            <span>{{ employee.email }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Pagination -->
                            <div class="mt-6">
                                <Pagination v-model:page="currentPage" :items-per-page="itemsPerPage" :total="filteredDepartmentEmployees.length" class="justify-end">
                                    <PaginationContent v-slot="{ items }">
                                        <PaginationPrevious />
                                        
                                        <template v-for="(item, index) in items" :key="index">
                                            <PaginationItem
                                                v-if="item.type === 'page'"
                                                :value="item.value"
                                                :is-active="item.value === currentPage"
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

                <!-- All Employees Tab -->
                <TabsContent value="employees">
                    <Card>
                        <CardHeader>
                            <CardTitle>All Employees</CardTitle>
                            <CardDescription>Complete employee directory with contact information</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <!-- Search Bar with Filter, Position, and Add Buttons -->
                            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 mb-6">
                                <div class="relative flex-1">
                                    <Search :size="16" class="absolute left-3 top-1/2 transform -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        v-model="employeeSearch"
                                        placeholder="Search by name, department, or employee code..."
                                        class="w-full pl-10"
                                    />
                                </div>
                                <div class="flex items-center gap-2 sm:gap-3">
                                    <Popover v-model:open="isFilterOpen">
                                        <PopoverTrigger as-child>
                                            <Button variant="outline" class="gap-2 flex-1 sm:flex-none" @click="openFilter">
                                                <Filter :size="16" />
                                                <span class="hidden sm:inline">Filter</span>
                                                <Badge v-if="activeFiltersCount > 0" variant="secondary" class="ml-1">
                                                    {{ activeFiltersCount }}
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
                                                    <Label class="text-sm font-medium">Department</Label>
                                                    <Select v-model="tempDepartmentFilter">
                                                        <SelectTrigger>
                                                            <SelectValue placeholder="Filter by department" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="all">All Departments</SelectItem>
                                                            <SelectItem v-for="dept in uniqueDepartments" :key="dept" :value="dept">
                                                                {{ dept }}
                                                            </SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                                <div class="space-y-2">
                                                    <Label class="text-sm font-medium">Role</Label>
                                                    <Select v-model="tempRoleFilter">
                                                        <SelectTrigger>
                                                            <SelectValue placeholder="Filter by role" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="all">All Roles</SelectItem>
                                                            <SelectItem 
                                                                v-for="role in uniqueRoles" 
                                                                :key="role" 
                                                                :value="role"
                                                            >
                                                                {{ role }}
                                                            </SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            </div>
                                            <div class="flex gap-2 pt-2">
                                                <Button variant="outline" class="flex-1" @click="resetFilter">
                                                    Reset
                                                </Button>
                                                <Button class="flex-1" @click="applyFilter">
                                                    Apply
                                                </Button>
                                            </div>
                                        </div>
                                    </PopoverContent>
                                    </Popover>
                                    <Dialog v-model:open="isPositionDialogOpen">
                                        <DialogTrigger as-child>
                                            <Button variant="outline" class="gap-2 flex-1 sm:flex-none sm:min-w-[120px]">
                                                <span class="hidden sm:inline">Position</span>
                                                <span class="sm:hidden">Pos</span>
                                            </Button>
                                        </DialogTrigger>
                                    <DialogContent class="sm:max-w-[500px] bg-background">
                                        <DialogHeader>
                                            <DialogTitle class="text-xl">Positions ({{ filteredPositionsByDepartment.length }})</DialogTitle>
                                        </DialogHeader>
                                        
                                        <!-- Department Filter -->
                                        <div class="mb-4">
                                            <Label class="text-sm font-medium mb-2 block">Filter by Department</Label>
                                            <Select v-model="positionDepartmentFilter">
                                                <SelectTrigger>
                                                    <SelectValue placeholder="All Departments" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="all">All Departments</SelectItem>
                                                    <SelectItem v-for="dept in uniqueDepartments" :key="dept" :value="dept">
                                                        {{ dept }}
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        
                                        <!-- Add Position Form -->
                                        <div v-if="isAddingPosition && canAddPosition" class="p-3 rounded-lg border border-primary/50 bg-muted/30 mb-4">
                                            <div class="space-y-3">
                                                <Input 
                                                    v-model="newPositionName"
                                                    placeholder="Position name"
                                                />
                                                <Select v-model="newPositionDepartment">
                                                    <SelectTrigger>
                                                        <SelectValue placeholder="Select department" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem v-for="dept in departments" :key="dept.id" :value="dept.id">
                                                            {{ dept.name }}
                                                        </SelectItem>
                                                    </SelectContent>
                                                </Select>
                                                <div class="flex gap-2">
                                                    <Button size="sm" @click="addNewPosition" :disabled="isSubmittingPosition" class="flex-1 gap-2">
                                                        <Loader2 v-if="isSubmittingPosition" :size="14" class="animate-spin" />
                                                        {{ isSubmittingPosition ? 'Adding...' : 'Add' }}
                                                    </Button>
                                                    <Button size="sm" variant="outline" @click="cancelAddPosition">
                                                        Cancel
                                                    </Button>
                                                </div>
                                            </div>
                                        </div>

                                        <ScrollArea class="h-[400px] pr-4">
                                            <div class="space-y-3 py-2">
                                                <div 
                                                    v-for="position in filteredPositionsByDepartment" 
                                                    :key="position.id"
                                                    class="flex items-center gap-3 p-3 rounded-lg bg-muted/50 hover:bg-muted transition-colors"
                                                >
                                                    <template v-if="editingPositionId === position.id">
                                                        <div class="flex-1 space-y-2">
                                                            <Input v-model="position.name" placeholder="Position name" />
                                                            <Select v-model="position.department_id">
                                                                <SelectTrigger class="text-xs">
                                                                    <SelectValue placeholder="Select department" />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    <SelectItem v-for="dept in departments" :key="dept.id" :value="dept.id">
                                                                        {{ dept.name }}
                                                                    </SelectItem>
                                                                </SelectContent>
                                                            </Select>
                                                        </div>
                                                        <div class="flex items-center gap-2">
                                                            <Button variant="ghost" size="icon" class="h-8 w-8" @click="saveEditPosition" :disabled="isSubmittingPosition">
                                                                <Loader2 v-if="isSubmittingPosition" :size="14" class="animate-spin" />
                                                                <Check v-else :size="14" />
                                                            </Button>
                                                            <Button variant="ghost" size="icon" class="h-8 w-8" @click="cancelEditPosition">
                                                                <X :size="14" />
                                                            </Button>
                                                        </div>
                                                    </template>
                                                    <template v-else>
                                                        <div class="flex-1 min-w-0">
                                                            <p class="font-medium truncate">{{ position.name }}</p>
                                                            <p class="text-xs text-muted-foreground">{{ position.department }}</p>
                                                        </div>
                                                        <div v-if="canEditPosition || canDeletePosition" class="flex items-center gap-2">
                                                            <Button v-if="canEditPosition" variant="ghost" size="icon" class="h-8 w-8" @click="startEditPosition(position)">
                                                                <Edit :size="14" />
                                                            </Button>
                                                            <Button v-if="canDeletePosition" variant="ghost" size="icon" class="h-8 w-8 text-destructive hover:text-destructive" @click="deletePosition(position.id)">
                                                                <Trash2 :size="14" />
                                                            </Button>
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>
                                        </ScrollArea>

                                        <div class="border-t pt-4 flex items-center justify-between">
                                            <Button v-if="canAddPosition" variant="link" class="gap-2 text-primary" @click="isAddingPosition = true">
                                                <Plus :size="16" />
                                                Add Position
                                            </Button>
                                            <Button variant="outline" @click="closePositionDialog">
                                                Close
                                            </Button>
                                        </div>
                                    </DialogContent>
                                    </Dialog>
                                    <Dialog v-model:open="isDepartmentDialogOpen">
                                        <DialogTrigger v-if="canAddDepartment" as-child>
                                            <Button variant="outline" class="gap-2 flex-1 sm:flex-none sm:min-w-[120px]">
                                                <span class="hidden sm:inline">Department</span>
                                                <span class="sm:hidden">Dept</span>
                                            </Button>
                                        </DialogTrigger>
                                    <DialogContent class="sm:max-w-[500px] bg-background">
                                        <DialogHeader>
                                            <DialogTitle class="text-xl">Departments ({{ departments.length }})</DialogTitle>
                                        </DialogHeader>
                                        
                                        <!-- Add Department Form -->
                                        <div v-if="isAddingDepartment && canAddDepartment" class="p-3 rounded-lg border border-primary/50 bg-muted/30 mb-4">
                                            <div class="space-y-3">
                                                <Input 
                                                    v-model="newDepartmentCode"
                                                    placeholder="Department code"
                                                />
                                                <Input 
                                                    v-model="newDepartmentName"
                                                    placeholder="Department name"
                                                />
                                                <div class="flex gap-2">
                                                    <Button size="sm" @click="addNewDepartment" :disabled="isSubmittingDepartment" class="flex-1 gap-2">
                                                        <Loader2 v-if="isSubmittingDepartment" :size="14" class="animate-spin" />
                                                        <Plus v-else :size="14" />
                                                        {{ isSubmittingDepartment ? 'Adding...' : 'Add' }}
                                                    </Button>
                                                    <Button size="sm" variant="outline" @click="cancelAddDepartment">
                                                        Cancel
                                                    </Button>
                                                </div>
                                            </div>
                                        </div>

                                        <ScrollArea class="h-[400px] pr-4">
                                            <div class="space-y-3 py-2">
                                                <div 
                                                    v-for="department in departments" 
                                                    :key="department.id"
                                                    class="flex items-center gap-3 p-3 rounded-lg bg-muted/50 hover:bg-muted transition-colors"
                                                >
                                                    <template v-if="editingDepartmentId === department.id">
                                                        <div class="flex-1 space-y-2">
                                                            <Input v-model="department.code" placeholder="Department code" class="font-mono" />
                                                            <Input v-model="department.name" placeholder="Department name" />
                                                        </div>
                                                        <div class="flex items-center gap-2">
                                                            <Button variant="ghost" size="icon" class="h-8 w-8" @click="saveEditDepartment" :disabled="isSubmittingDepartment">
                                                                <Loader2 v-if="isSubmittingDepartment" :size="14" class="animate-spin" />
                                                                <Check v-else :size="14" />
                                                            </Button>
                                                            <Button variant="ghost" size="icon" class="h-8 w-8" @click="cancelEditDepartment">
                                                                <X :size="14" />
                                                            </Button>
                                                        </div>
                                                    </template>
                                                    <template v-else>
                                                        <div class="flex-1 min-w-0">
                                                            <p class="font-medium truncate">{{ department.name }}</p>
                                                            <p class="text-xs text-muted-foreground font-mono">{{ department.code }}</p>
                                                        </div>
                                                        <div v-if="canEditDepartment || canDeleteDepartment" class="flex items-center gap-2">
                                                            <Button v-if="canEditDepartment" variant="ghost" size="icon" class="h-8 w-8" @click="startEditDepartment(department)">
                                                                <Edit :size="14" />
                                                            </Button>
                                                            <Button v-if="canDeleteDepartment" variant="ghost" size="icon" class="h-8 w-8 text-destructive hover:text-destructive" @click="deleteDepartment(department.id)">
                                                                <Trash2 :size="14" />
                                                            </Button>
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>
                                        </ScrollArea>

                                        <div class="border-t pt-4 flex items-center justify-between">
                                            <Button v-if="canAddDepartment" variant="link" class="gap-2 text-primary" @click="isAddingDepartment = true">
                                                <Plus :size="16" />
                                                Add Department
                                            </Button>
                                            <Button variant="outline" @click="isDepartmentDialogOpen = false">
                                                Close
                                            </Button>
                                        </div>
                                    </DialogContent>
                                    </Dialog>
                                    <Button v-if="canAddEmployee" variant="outline" class="gap-2 flex-1 sm:flex-none" @click="openAddEmployeeDialog(false)">
                                        <Plus :size="16" />
                                        <span>Add</span>
                                    </Button>
                                </div>
                            </div>

                            <!-- Desktop Table View -->
                            <div class="hidden md:block relative w-full overflow-auto">
                                <table class="w-full caption-bottom text-sm">
                                    <thead class="[&_tr]:border-b">
                                        <tr class="border-b transition-colors hover:bg-muted/50">
                                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Name</th>
                                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Department</th>
                                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Position</th>
                                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Role</th>
                                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Status</th>
                                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Length of Service</th>
                                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Employee Code</th>
                                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Email</th>
                                        </tr>
                                    </thead>
                                    <tbody class="[&_tr:last-child]:border-0">
                                        <tr v-for="employee in paginatedEmployees" :key="employee.id" class="border-b transition-colors hover:bg-muted/50">
                                            <td class="p-4 align-middle font-medium">{{ employee.name }}</td>
                                            <td class="p-4 align-middle">
                                                <Badge variant="outline">{{ employee.department }}</Badge>
                                            </td>
                                            <td class="p-4 align-middle text-sm">{{ employee.position }}</td>
                                            <td class="p-4 align-middle">
                                                <Badge 
                                                    variant="outline"
                                                    :class="getRoleBadgeClass(employee.role)"
                                                >
                                                    {{ employee.role }}
                                                </Badge>
                                            </td>
                                            <td class="p-4 align-middle">
                                                <Badge
                                                    variant="outline"
                                                    :class="getEmploymentStatusBadgeClass(employee.employmentStatus)"
                                                >
                                                    {{ getEmploymentStatusLabel(employee.employmentStatus, employee.inactiveReason) }}
                                                </Badge>
                                            </td>
                                            <td class="p-4 align-middle text-sm">{{ employee.lengthOfService || 'N/A' }}</td>
                                            <td class="p-4 align-middle font-mono text-xs">{{ employee.employeeCode }}</td>
                                            <td class="p-4 align-middle text-sm text-muted-foreground">{{ employee.email }}</td>
                                            <td class="p-4 align-middle text-right">
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger as-child>
                                                        <Button variant="ghost" size="icon" class="h-8 w-8">
                                                            <MoreVertical :size="16" />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <DropdownMenuItem class="gap-2" @click="viewEmployeeDetails(employee, true)">
                                                            <Eye :size="16" />
                                                            View Details
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem v-if="canDeleteEmployee" class="gap-2 text-destructive focus:text-destructive" @click="deleteEmployee(employee.id)">
                                                            <Trash2 :size="16" />
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
                                    v-for="employee in paginatedEmployees" 
                                    :key="employee.id"
                                    class="p-4 rounded-lg border"
                                >
                                    <div class="flex justify-between items-start mb-3">
                                        <div class="flex-1">
                                            <h3 class="font-semibold">{{ employee.name }}</h3>
                                            <p class="text-xs text-muted-foreground font-mono">{{ employee.employeeCode }}</p>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <Badge variant="outline" class="text-xs">{{ employee.department }}</Badge>
                                            <DropdownMenu>
                                                <DropdownMenuTrigger as-child>
                                                    <Button variant="ghost" size="icon" class="h-8 w-8">
                                                        <MoreVertical :size="16" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem class="gap-2" @click="viewEmployeeDetails(employee, true)">
                                                        <Eye :size="16" />
                                                        View Details
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem v-if="canDeleteEmployee" class="gap-2 text-destructive focus:text-destructive" @click="deleteEmployee(employee.id)">
                                                        <Trash2 :size="16" />
                                                        Delete
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </div>
                                    </div>
                                    <div class="space-y-2 text-sm">
                                        <div class="flex gap-2">
                                            <span class="text-muted-foreground w-24">Position:</span>
                                            <span>{{ employee.position }}</span>
                                        </div>
                                        <div class="flex gap-2">
                                            <span class="text-muted-foreground w-24">Role:</span>
                                            <Badge 
                                                variant="outline" 
                                                class="text-xs"
                                                :class="getRoleBadgeClass(employee.role)"
                                            >
                                                {{ employee.role }}
                                            </Badge>
                                        </div>
                                        <div class="flex gap-2">
                                            <span class="text-muted-foreground w-24">Status:</span>
                                            <Badge
                                                variant="outline"
                                                class="text-xs"
                                                :class="getEmploymentStatusBadgeClass(employee.employmentStatus)"
                                            >
                                                {{ getEmploymentStatusLabel(employee.employmentStatus, employee.inactiveReason) }}
                                            </Badge>
                                        </div>
                                        <div class="flex gap-2">
                                            <span class="text-muted-foreground w-24">Service:</span>
                                            <span>{{ employee.lengthOfService || 'N/A' }}</span>
                                        </div>
                                        <div class="flex gap-2">
                                            <span class="text-muted-foreground w-24">Email:</span>
                                            <span>{{ employee.email }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Pagination -->
                            <div class="mt-6">
                                <Pagination v-model:page="currentPage" :items-per-page="itemsPerPage" :total="filteredEmployees.length" class="justify-end">
                                    <PaginationContent v-slot="{ items }">
                                        <PaginationPrevious />
                                        
                                        <template v-for="(item, index) in items" :key="index">
                                            <PaginationItem
                                                v-if="item.type === 'page'"
                                                :value="item.value"
                                                :is-active="item.value === currentPage"
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
            </Tabs>

                    <!-- Add Employee Dialog -->
                    <Dialog v-model:open="isAddEmployeeDialogOpen" @update:open="(open) => { if (!open) isAddingFromDepartmentsTab = false; }">
                        <DialogContent class="sm:max-w-[500px] bg-background">
                            <DialogHeader>
                                <DialogTitle class="text-xl">Add New Employee</DialogTitle>
                            </DialogHeader>
                            
                            <!-- Step 1: Basic Information -->
                            <div v-if="addEmployeeStep === 1" class="space-y-4">
                                <!-- Avatar Upload -->
                                <div class="flex justify-center">
                                    <div class="relative">
                                        <div class="w-24 h-24 rounded-full bg-gradient-to-br from-primary/80 to-primary flex items-center justify-center overflow-hidden border-2 border-border">
                                            <img v-if="newEmployee.avatar" :src="newEmployee.avatar" alt="Avatar" class="w-full h-full object-cover" />
                                            <span v-else class="text-3xl font-bold text-white">
                                                {{ newEmployee.first_name && newEmployee.last_name ? (newEmployee.first_name[0] + newEmployee.last_name[0]) : '?' }}
                                            </span>
                                        </div>
                                        <label for="new-employee-avatar" class="absolute -bottom-1 -right-1 w-8 h-8 bg-white dark:bg-gray-800 border-2 border-primary rounded-full flex items-center justify-center cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors shadow-lg">
                                            <Edit :size="14" class="text-primary" />
                                            <input id="new-employee-avatar" type="file" accept="image/png,image/jpeg" class="hidden" @change="handleNewEmployeeAvatarUpload" />
                                        </label>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div class="space-y-2">
                                        <Label class="text-sm font-medium">First Name *</Label>
                                        <Input v-model="newEmployee.first_name" placeholder="First name" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label class="text-sm font-medium">Last Name *</Label>
                                        <Input v-model="newEmployee.last_name" placeholder="Last name" />
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <Label class="text-sm font-medium">Employee Code *</Label>
                                    <Input v-model="newEmployee.employee_code" placeholder="e.g., EMP016" class="font-mono" />
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div class="space-y-2">
                                        <Label class="text-sm font-medium">Department *</Label>
                                        <Select v-model="newEmployee.department_id" :disabled="isAddingFromDepartmentsTab" @update:model-value="newEmployee.position_id = null">
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select department" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="dept in departments" :key="dept.id" :value="dept.id">
                                                    {{ dept.name }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div class="space-y-2">
                                        <Label class="text-sm font-medium">Position</Label>
                                        <Select v-model="newEmployee.position_id" :disabled="!newEmployee.department_id">
                                            <SelectTrigger>
                                                <SelectValue :placeholder="newEmployee.department_id ? 'Select position' : 'Select department first'" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="pos in filteredPositionsForEmployee" :key="pos.id" :value="pos.id">
                                                    {{ pos.name }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <Label class="text-sm font-medium">Contact Number</Label>
                                    <Input v-model="newEmployee.contact_number" placeholder="+63 917 123 4567" />
                                </div>

                                <div class="space-y-2">
                                    <Label class="text-sm font-medium">Birthday</Label>
                                    <Input v-model="newEmployee.birth_date" type="date" />
                                </div>

                                <div class="space-y-2">
                                    <Label class="text-sm font-medium">Hire Date</Label>
                                    <Input v-model="newEmployee.hire_date" type="date" />
                                </div>

                                <div class="space-y-2">
                                    <Label class="text-sm font-medium">Employment Status</Label>
                                    <Select v-model="newEmployee.employment_status">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select status" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="active">Active</SelectItem>
                                            <SelectItem value="inactive">Inactive</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div v-if="newEmployee.employment_status === 'inactive'" class="space-y-2">
                                    <Label class="text-sm font-medium">Inactive Reason</Label>
                                    <Select v-model="newEmployee.inactive_reason">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select reason" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="terminated">Terminated</SelectItem>
                                            <SelectItem value="resigned">Resigned</SelectItem>
                                            <SelectItem value="retired">Retired</SelectItem>
                                            <SelectItem value="end_of_contract">End of Contract</SelectItem>
                                            <SelectItem value="other">Other</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div v-if="newEmployee.employment_status === 'inactive'" class="space-y-2">
                                    <Label class="text-sm font-medium">Inactive Date</Label>
                                    <Input v-model="newEmployee.inactive_date" type="date" />
                                </div>

                                <div v-if="newEmployee.employment_status === 'inactive'" class="space-y-2">
                                    <Label class="text-sm font-medium">Reason Notes</Label>
                                    <Input v-model="newEmployee.inactive_reason_notes" placeholder="Optional notes" />
                                </div>

                                <div class="flex justify-end gap-2 pt-4 border-t">
                                    <Button variant="outline" @click="isAddEmployeeDialogOpen = false">
                                        Cancel
                                    </Button>
                                    <Button @click="goToNextStep">
                                        Next
                                    </Button>
                                </div>
                            </div>

                            <!-- Step 2: Account Information -->
                            <div v-if="addEmployeeStep === 2" class="space-y-4">
                                <div class="space-y-2">
                                    <Label class="text-sm font-medium">Email *</Label>
                                    <Input v-model="newEmployee.email" type="email" placeholder="email@company.com" />
                                </div>

                                <div class="space-y-2">
                                    <Label class="text-sm font-medium">Password *</Label>
                                    <Input v-model="newEmployee.password" type="password" placeholder="Enter password" />
                                </div>

                                <div class="space-y-2">
                                    <Label class="text-sm font-medium">Confirm Password *</Label>
                                    <Input v-model="newEmployee.confirmPassword" type="password" placeholder="Confirm password" />
                                </div>

                                <div class="flex justify-between gap-2 pt-4 border-t">
                                    <Button variant="outline" @click="goToPreviousStep">
                                        Back
                                    </Button>
                                    <div class="flex gap-2">
                                        <Button variant="outline" @click="isAddEmployeeDialogOpen = false">
                                            Cancel
                                        </Button>
                                        <Button @click="addNewEmployee" :disabled="isSubmittingEmployee" class="gap-2">
                                            <Loader2 v-if="isSubmittingEmployee" :size="16" class="animate-spin" />
                                            <Plus v-else :size="16" />
                                            {{ isSubmittingEmployee ? 'Adding Employee...' : 'Add Employee' }}
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </DialogContent>
                    </Dialog>

            <!-- Employee Details Dialog (Outside Tabs) -->
            <Dialog v-model:open="isEmployeeDetailsOpen">
                <DialogContent class="sm:max-w-[500px] bg-background">
                    <DialogHeader>
                        <DialogTitle class="text-lg">Employee Details</DialogTitle>
                    </DialogHeader>
                    
                    <ScrollArea v-if="selectedEmployee" class="max-h-[60vh] pr-4">
                        <!-- Edit Mode -->
                        <div v-if="isEditMode" class="space-y-3 py-2">
                            <!-- Avatar Upload -->
                            <div class="flex justify-center pb-1">
                                <div class="relative">
                                    <div class="w-20 h-20 rounded-full bg-gradient-to-br from-primary/80 to-primary flex items-center justify-center overflow-hidden border-2 border-border">
                                        <img v-if="selectedEmployee.avatar" :src="selectedEmployee.avatar" alt="Avatar" class="w-full h-full object-cover" />
                                        <span v-else class="text-2xl font-bold text-white">
                                            {{ selectedEmployee.name.split(' ').map((n: string) => n[0]).join('') }}
                                        </span>
                                    </div>
                                    <label for="avatar-upload-2" class="absolute -bottom-1 -right-1 w-7 h-7 bg-white dark:bg-gray-800 border-2 border-primary rounded-full flex items-center justify-center cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors shadow-lg">
                                        <Edit :size="12" class="text-primary" />
                                        <input id="avatar-upload-2" type="file" accept="image/png,image/jpeg" class="hidden" @change="handleAvatarUpload" />
                                    </label>
                                </div>
                            </div>

                            <!-- Name Fields -->
                            <div class="grid grid-cols-2 gap-3">
                                <div class="space-y-1.5">
                                    <Label class="text-xs font-medium">First Name</Label>
                                    <Input v-model="selectedEmployee.first_name" placeholder="First Name" class="h-9 text-sm" />
                                </div>
                                <div class="space-y-1.5">
                                    <Label class="text-xs font-medium">Last Name</Label>
                                    <Input v-model="selectedEmployee.last_name" placeholder="Last Name" class="h-9 text-sm" />
                                </div>
                            </div>

                            <!-- Department -->
                            <div class="space-y-1.5">
                                <Label class="text-xs font-medium">Department</Label>
                                <Select 
                                    v-if="isFromAllEmployeesTab" 
                                    v-model="selectedEmployee.department_id"
                                >
                                    <SelectTrigger class="h-9 text-sm">
                                        <SelectValue placeholder="Select department" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="dept in departments" :key="dept.id" :value="dept.id">
                                            {{ dept.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <Badge v-else variant="outline" class="gap-1.5 text-xs">
                                    
                                    {{ selectedEmployee.department }}
                                </Badge>
                            </div>

                            <!-- Position -->
                            <div class="space-y-1.5">
                                <Label class="text-xs font-medium">Position</Label>
                                <Select v-model="selectedEmployee.position_id">
                                    <SelectTrigger class="h-9 text-sm">
                                        <SelectValue placeholder="Select position" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem 
                                            v-for="position in filteredPositionsForSelectedEmployee" 
                                            :key="position.id" 
                                            :value="position.id"
                                        >
                                            {{ position.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <!-- Role (All Employees Tab only) -->
                            <div v-if="isFromAllEmployeesTab" class="space-y-1.5">
                                <Label class="text-xs font-medium">Role</Label>
                                <Select v-model="selectedEmployee.role">
                                    <SelectTrigger class="h-9 text-sm">
                                        <SelectValue placeholder="Select role" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="admin">Admin</SelectItem>
                                        <SelectItem value="department_manager">Department Manager</SelectItem>
                                        <SelectItem value="employee">Employee</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <!-- Employee Code -->
                            <div class="space-y-1.5">
                                <Label class="text-xs font-medium">Employee Code</Label>
                                <Input v-model="selectedEmployee.employee_code" placeholder="Employee Code" class="font-mono h-9 text-sm" />
                            </div>

                            <!-- Email -->
                            <div class="space-y-1.5">
                                <Label class="text-xs font-medium">Email</Label>
                                <Input v-model="selectedEmployee.email" type="email" placeholder="Email" class="h-9 text-sm" />
                            </div>

                            <!-- Contact Number -->
                            <div class="space-y-1.5">
                                <Label class="text-xs font-medium">Contact Number</Label>
                                <Input v-model="selectedEmployee.contact_number" placeholder="Contact Number" class="h-9 text-sm" />
                            </div>

                            <!-- Birthday -->
                            <div class="space-y-1.5">
                                <Label class="text-xs font-medium">Birthday</Label>
                                <Input v-model="selectedEmployee.birth_date" type="date" class="h-9 text-sm" />
                            </div>

                            <div class="space-y-1.5">
                                <Label class="text-xs font-medium">Hire Date</Label>
                                <Input v-model="selectedEmployee.hire_date" type="date" class="h-9 text-sm" />
                            </div>

                            <div class="space-y-1.5">
                                <Label class="text-xs font-medium">Employment Status</Label>
                                <Select v-model="selectedEmployee.employment_status">
                                    <SelectTrigger class="h-9 text-sm">
                                        <SelectValue placeholder="Select status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="active">Active</SelectItem>
                                        <SelectItem value="inactive">Inactive</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div v-if="selectedEmployee.employment_status === 'inactive'" class="space-y-1.5">
                                <Label class="text-xs font-medium">Inactive Reason</Label>
                                <Select v-model="selectedEmployee.inactive_reason">
                                    <SelectTrigger class="h-9 text-sm">
                                        <SelectValue placeholder="Select reason" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="terminated">Terminated</SelectItem>
                                        <SelectItem value="resigned">Resigned</SelectItem>
                                        <SelectItem value="retired">Retired</SelectItem>
                                        <SelectItem value="end_of_contract">End of Contract</SelectItem>
                                        <SelectItem value="other">Other</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div v-if="selectedEmployee.employment_status === 'inactive'" class="space-y-1.5">
                                <Label class="text-xs font-medium">Inactive Date</Label>
                                <Input v-model="selectedEmployee.inactive_date" type="date" class="h-9 text-sm" />
                            </div>

                            <div v-if="selectedEmployee.employment_status === 'inactive'" class="space-y-1.5">
                                <Label class="text-xs font-medium">Reason Notes</Label>
                                <Input v-model="selectedEmployee.inactive_reason_notes" class="h-9 text-sm" placeholder="Optional notes" />
                            </div>
                        </div>

                        <!-- View Mode -->
                        <div v-else class="py-2">
                            <!-- Avatar and Name -->
                            <div class="flex flex-col items-center mb-4 pb-4 border-b">
                                <div class="relative mb-3">
                                    <div class="w-24 h-24 rounded-full bg-gradient-to-br from-primary/80 to-primary flex items-center justify-center overflow-hidden border-2 border-border shadow-lg">
                                        <img v-if="selectedEmployee.avatar" :src="selectedEmployee.avatar" alt="Avatar" class="w-full h-full object-cover" />
                                        <span v-else class="text-3xl font-bold text-white">
                                            {{ selectedEmployee.name.split(' ').map((n: string) => n[0]).join('') }}
                                        </span>
                                    </div>
                                </div>
                                <h3 class="text-xl font-bold mb-1">{{ selectedEmployee.name }}</h3>
                                <p class="text-sm text-muted-foreground">{{ selectedEmployee.position }}</p>
                            </div>

                            <!-- Info Grid -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <!-- Employee Code -->
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg bg-muted flex items-center justify-center shrink-0">
                                        <UserCircle :size="18" class="text-muted-foreground" />
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs text-muted-foreground mb-0.5">Employee Code</p>
                                        <p class="text-sm font-mono font-semibold truncate">{{ selectedEmployee.employeeCode }}</p>
                                    </div>
                                </div>

                                <!-- Department -->
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg bg-muted flex items-center justify-center shrink-0">
                                        <Building2 :size="18" class="text-muted-foreground" />
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs text-muted-foreground mb-0.5">Department</p>
                                        <Badge variant="outline" class="gap-1.5 text-xs">
                                            
                                            {{ selectedEmployee.department }}
                                        </Badge>
                                    </div>
                                </div>

                                <!-- Role -->
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg bg-muted flex items-center justify-center shrink-0">
                                        <Shield :size="18" class="text-muted-foreground" />
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs text-muted-foreground mb-0.5">Role</p>
                                        <Badge 
                                            variant="outline" 
                                            class="gap-1.5 text-xs"
                                            :class="getRoleBadgeClass(formatRole(selectedEmployee.role))"
                                        >
                                            {{ formatRole(selectedEmployee.role) }}
                                        </Badge>
                                    </div>
                                </div>

                                <!-- Employment Status -->
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg bg-muted flex items-center justify-center shrink-0">
                                        <UserCircle :size="18" class="text-muted-foreground" />
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs text-muted-foreground mb-0.5">Employment Status</p>
                                        <Badge
                                            variant="outline"
                                            class="gap-1.5 text-xs"
                                            :class="getEmploymentStatusBadgeClass(selectedEmployee.employmentStatus)"
                                        >
                                            {{ getEmploymentStatusLabel(selectedEmployee.employmentStatus, selectedEmployee.inactiveReason) }}
                                        </Badge>
                                    </div>
                                </div>

                                <!-- Contact Number -->
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg bg-muted flex items-center justify-center shrink-0">
                                        <Phone :size="18" class="text-muted-foreground" />
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs text-muted-foreground mb-0.5">Contact</p>
                                        <p class="text-sm font-medium">{{ selectedEmployee.contactNum }}</p>
                                    </div>
                                </div>

                                <!-- Birthday -->
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg bg-muted flex items-center justify-center shrink-0">
                                        <Calendar :size="18" class="text-muted-foreground" />
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs text-muted-foreground mb-0.5">Birthday</p>
                                        <p class="text-sm font-medium">{{ selectedEmployee.birthday ? new Date(selectedEmployee.birthday).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A' }}</p>
                                    </div>
                                </div>

                                <!-- Length of Service -->
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg bg-muted flex items-center justify-center shrink-0">
                                        <Calendar :size="18" class="text-muted-foreground" />
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs text-muted-foreground mb-0.5">Length of Service</p>
                                        <p class="text-sm font-medium">{{ selectedEmployee.lengthOfService || 'N/A' }}</p>
                                    </div>
                                </div>

                                <!-- Email - Full Width -->
                                <div class="flex items-start gap-3 md:col-span-2">
                                    <div class="w-10 h-10 rounded-lg bg-muted flex items-center justify-center shrink-0">
                                        <Mail :size="18" class="text-muted-foreground" />
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs text-muted-foreground mb-0.5">Email</p>
                                        <p class="text-sm font-medium break-words">{{ selectedEmployee.email }}</p>
                                    </div>
                                </div>

                                <div
                                    v-if="selectedEmployee.employmentStatus === 'inactive'"
                                    class="flex items-start gap-3 md:col-span-2"
                                >
                                    <div class="w-10 h-10 rounded-lg bg-muted flex items-center justify-center shrink-0">
                                        <Shield :size="18" class="text-muted-foreground" />
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs text-muted-foreground mb-0.5">Inactive Details</p>
                                        <p class="text-sm font-medium">
                                            {{ formatInactiveReason(selectedEmployee.inactiveReason || '') }}
                                            <span v-if="selectedEmployee.inactiveDate">
                                                - {{ new Date(selectedEmployee.inactiveDate).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) }}
                                            </span>
                                        </p>
                                        <p v-if="selectedEmployee.inactiveReasonNotes" class="text-xs text-muted-foreground mt-1">
                                            {{ selectedEmployee.inactiveReasonNotes }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Password Fields (Edit Mode Only) -->
                        <div v-if="isEditMode" class="space-y-3 pt-2 border-t">
                            <div class="space-y-1.5">
                                <div class="flex items-center gap-2">
                                    <Key :size="14" class="text-muted-foreground" />
                                    <Label class="text-xs font-medium">New Password (leave blank to keep current)</Label>
                                </div>
                                <Input v-model="selectedEmployee.newPassword" type="password" placeholder="Enter new password" class="h-9 text-sm" />
                            </div>

                            <div v-if="selectedEmployee.newPassword" class="space-y-1.5">
                                <div class="flex items-center gap-2">
                                    <Key :size="14" class="text-muted-foreground" />
                                    <Label class="text-xs font-medium">Confirm New Password</Label>
                                </div>
                                <Input v-model="selectedEmployee.confirmPassword" type="password" placeholder="Confirm new password" class="h-9 text-sm" />
                            </div>
                        </div>
                    </ScrollArea>

                    <DialogFooter class="gap-2 pt-2">
                        <Button variant="outline" @click="isEmployeeDetailsOpen = false" class="h-9 text-sm">
                            Close
                        </Button>
                        <Button v-if="!isEditMode && canEditEmployee" @click="toggleEditMode" class="h-9 text-sm">
                            Edit
                        </Button>
                        <template v-else-if="canEditEmployee">
                            <Button variant="outline" @click="toggleEditMode" class="h-9 text-sm">
                                Cancel
                            </Button>
                            <Button @click="saveEmployeeDetails" :disabled="isSubmittingEmployee" class="h-9 text-sm gap-2">
                                <Loader2 v-if="isSubmittingEmployee" :size="14" class="animate-spin" />
                                {{ isSubmittingEmployee ? 'Saving...' : 'Save Changes' }}
                            </Button>
                        </template>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Delete Confirmation Dialog -->
            <Dialog v-model:open="isDeleteDialogOpen">
                <DialogContent class="sm:max-w-[425px]">
                    <DialogHeader>
                        <DialogTitle>Are you sure?</DialogTitle>
                        <DialogDescription>
                            This action cannot be undone. This will permanently delete this 
                            <span v-if="deleteType === 'department'">department</span>
                            <span v-else-if="deleteType === 'position'">position</span>
                            <span v-else>employee</span>.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter class="gap-2">
                        <Button variant="outline" @click="closeDeleteDialog">Cancel</Button>
                        <Button 
                            variant="destructive"
                            @click="confirmDelete" 
                            :disabled="deleteTimer > 0"
                        >
                            {{ deleteTimer > 0 ? `Delete (${deleteTimer}s)` : 'Delete' }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- User Info Edit Dialog -->
            <Dialog v-model:open="isUserInfoDialogOpen">
                <DialogContent class="sm:max-w-[500px] bg-background">
                    <DialogHeader>
                        <DialogTitle class="text-lg">Edit Profile</DialogTitle>
                        <DialogDescription class="text-sm">Update your profile information</DialogDescription>
                    </DialogHeader>
                    
                    <ScrollArea class="max-h-[60vh] pr-4">
                        <div class="space-y-3 py-2">
                        <!-- Avatar Upload -->
                        <div class="flex justify-center pb-1">
                            <div class="relative">
                                <div class="w-20 h-20 rounded-full bg-gradient-to-br from-primary/80 to-primary flex items-center justify-center overflow-hidden border-2 border-border">
                                    <img v-if="editedUserInfo.avatar" :src="editedUserInfo.avatar" alt="Avatar" class="w-full h-full object-cover" />
                                    <span v-else class="text-2xl font-bold text-white">
                                        {{ editedUserInfo.first_name && editedUserInfo.last_name ? (editedUserInfo.first_name[0] + editedUserInfo.last_name[0]) : (currentUser.name.split(' ').map(n => n[0]).join('')) }}
                                    </span>
                                </div>
                                <label for="user-info-avatar-upload" class="absolute -bottom-1 -right-1 w-7 h-7 bg-white dark:bg-gray-800 border-2 border-primary rounded-full flex items-center justify-center cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors shadow-lg">
                                    <Edit :size="12" class="text-primary" />
                                    <input id="user-info-avatar-upload" type="file" accept="image/png,image/jpeg" class="hidden" @change="handleUserInfoAvatarUpload" />
                                </label>
                            </div>
                        </div>

                        <!-- Name Fields -->
                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1.5">
                                <Label class="text-xs font-medium">First Name</Label>
                                <Input v-model="editedUserInfo.first_name" placeholder="First Name" class="h-9 text-sm" />
                            </div>
                            <div class="space-y-1.5">
                                <Label class="text-xs font-medium">Last Name</Label>
                                <Input v-model="editedUserInfo.last_name" placeholder="Last Name" class="h-9 text-sm" />
                            </div>
                        </div>

                        <!-- Position -->
                        <div class="space-y-1.5">
                            <Label class="text-xs font-medium">Position</Label>
                            <Select v-model="editedUserInfo.position_id">
                                <SelectTrigger class="h-9 text-sm">
                                    <SelectValue placeholder="Select position" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem 
                                        v-for="position in filteredPositionsForCurrentUser" 
                                        :key="position.id" 
                                        :value="position.id"
                                    >
                                        {{ position.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <!-- Employee Code -->
                        <div class="space-y-1.5">
                            <Label class="text-xs font-medium">Employee Code</Label>
                            <Input v-model="editedUserInfo.employee_code" placeholder="Employee Code" class="font-mono h-9 text-sm" />
                        </div>

                        <!-- Email -->
                        <div class="space-y-1.5">
                            <Label class="text-xs font-medium">Email</Label>
                            <Input v-model="editedUserInfo.email" type="email" placeholder="Email" class="h-9 text-sm" />
                        </div>

                        <!-- Contact Number -->
                        <div class="space-y-1.5">
                            <Label class="text-xs font-medium">Contact Number</Label>
                            <Input v-model="editedUserInfo.contact_number" placeholder="Contact Number" class="h-9 text-sm" />
                        </div>

                        <!-- Birthday -->
                        <div class="space-y-1.5">
                            <Label class="text-xs font-medium">Birthday</Label>
                            <Input v-model="editedUserInfo.birth_date" type="date" class="h-9 text-sm" />
                        </div>

                        <!-- Department (Read-only) -->
                        <div class="space-y-1.5">
                            <Label class="text-xs font-medium">Department</Label>
                            <Badge variant="outline" class="gap-1.5 text-xs">
                                <Building2 :size="12" />
                                {{ currentUser.department }}
                            </Badge>
                            <p class="text-xs text-muted-foreground">Department cannot be changed</p>
                        </div>

                        <!-- Password Fields -->
                        <div class="space-y-1.5 pt-1 border-t">
                            <div class="flex items-center gap-2">
                                <Key :size="14" class="text-muted-foreground" />
                                <Label class="text-xs font-medium">New Password (leave blank to keep current)</Label>
                            </div>
                            <Input v-model="editedUserInfo.newPassword" type="password" placeholder="Enter new password" class="h-9 text-sm" />
                        </div>

                        <div v-if="editedUserInfo.newPassword" class="space-y-1.5">
                            <div class="flex items-center gap-2">
                                <Key :size="14" class="text-muted-foreground" />
                                <Label class="text-xs font-medium">Confirm New Password</Label>
                            </div>
                            <Input v-model="editedUserInfo.confirmPassword" type="password" placeholder="Confirm new password" class="h-9 text-sm" />
                        </div>
                        </div>
                    </ScrollArea>

                    <DialogFooter class="gap-2 pt-2">
                        <Button variant="outline" @click="closeUserInfoEdit" class="h-9 text-sm">Cancel</Button>
                        <Button @click="saveUserInfo" :disabled="isSubmittingEmployee" class="h-9 text-sm gap-2">
                            <Loader2 v-if="isSubmittingEmployee" :size="14" class="animate-spin" />
                            {{ isSubmittingEmployee ? 'Saving...' : 'Save Changes' }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>

