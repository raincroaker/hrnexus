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
import { Users, Building2, Search, Plus, Trash2, Edit, MoreVertical, Eye, Mail, Phone, Calendar, UserCircle, Check, X, Filter, Shield, Key } from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';
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

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Employees',
        href: '/employees',
    },
];

// Current user info
const currentUser = ref({
    name: 'Jerick Cruza',
    employeeCode: 'EMP9999',
    department: 'IT',
    position: 'Senior Developer',
    role: 'Admin',
    email: 'cruza.jerick@hrnexus.com',
    contactNum: '09092140862',
    birthday: '2004-04-17',
    avatar: ''
});

// User Info Card edit dialog state
const isUserInfoDialogOpen = ref(false);
const editedUserInfo = ref({
    firstName: '',
    lastName: '',
    employeeCode: '',
    position: '',
    email: '',
    contactNum: '',
    birthday: '',
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
        alert('Please upload a PNG or JPG image.');
    }
};

// Function to open user info edit dialog
const openUserInfoEdit = () => {
    // Populate form with current user data
    const nameParts = currentUser.value.name.split(' ');
    editedUserInfo.value.firstName = nameParts[0] || '';
    editedUserInfo.value.lastName = nameParts.slice(1).join(' ') || '';
    editedUserInfo.value.employeeCode = currentUser.value.employeeCode;
    editedUserInfo.value.position = currentUser.value.position;
    editedUserInfo.value.email = currentUser.value.email;
    editedUserInfo.value.contactNum = currentUser.value.contactNum;
    editedUserInfo.value.birthday = currentUser.value.birthday;
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
const saveUserInfo = () => {
    // Validate password if provided
    if (editedUserInfo.value.newPassword) {
        if (editedUserInfo.value.newPassword !== editedUserInfo.value.confirmPassword) {
            alert('Passwords do not match!');
            return;
        }
        if (editedUserInfo.value.newPassword.length < 6) {
            alert('Password must be at least 6 characters long!');
            return;
        }
        // Here you would typically update the password on the backend
    }
    
    // Combine firstName and lastName
    if (editedUserInfo.value.firstName && editedUserInfo.value.lastName) {
        currentUser.value.name = `${editedUserInfo.value.firstName} ${editedUserInfo.value.lastName}`;
    }
    
    // Update other fields
    currentUser.value.employeeCode = editedUserInfo.value.employeeCode;
    currentUser.value.position = editedUserInfo.value.position;
    currentUser.value.email = editedUserInfo.value.email;
    currentUser.value.contactNum = editedUserInfo.value.contactNum;
    currentUser.value.birthday = editedUserInfo.value.birthday;
    currentUser.value.avatar = editedUserInfo.value.avatar;
    
    // Clear password fields
    editedUserInfo.value.newPassword = '';
    editedUserInfo.value.confirmPassword = '';
    
    // Here you would typically save to backend
    closeUserInfoEdit();
};

// Dummy data for departments
const departments = ref([
    { id: 1, code: 'ENG', name: 'Engineering', employeeCount: 12, manager: 'John Doe', description: 'Software development and technical operations' },
    { id: 2, code: 'MKT', name: 'Marketing', employeeCount: 8, manager: 'Jane Smith', description: 'Brand management and digital marketing' },
    { id: 3, code: 'HR', name: 'HR', employeeCount: 5, manager: 'Sarah Williams', description: 'Human resources and recruitment' },
    { id: 4, code: 'SAL', name: 'Sales', employeeCount: 10, manager: 'Tom Brown', description: 'Sales and business development' },
    { id: 5, code: 'FIN', name: 'Finance', employeeCount: 6, manager: 'Emily Davis', description: 'Financial planning and accounting' },
    { id: 6, code: 'IT', name: 'IT', employeeCount: 7, manager: 'Alex Chen', description: 'IT infrastructure and support' },
    { id: 7, code: 'DES', name: 'Design', employeeCount: 4, manager: 'Lisa Park', description: 'UI/UX and graphic design' },
    { id: 8, code: 'QA', name: 'QA', employeeCount: 5, manager: 'Mark Rodriguez', description: 'Quality assurance and testing' },
    { id: 9, code: 'OPS', name: 'Operations', employeeCount: 9, manager: 'Rachel Kim', description: 'Business operations and logistics' },
]);

// Dummy data for all employees with complete information
const allEmployees = ref([
    { id: 1, userId: 'USR001', employeeCode: 'EMP001', name: 'John Doe', department: 'Engineering', position: 'Senior Developer', role: 'Admin', email: 'john.doe@company.com', contactNum: '+63 917 123 4567', birthday: '1990-05-15' },
    { id: 2, userId: 'USR002', employeeCode: 'EMP002', name: 'Jane Smith', department: 'Marketing', position: 'Marketing Manager', role: 'Department Manager', email: 'jane.smith@company.com', contactNum: '+63 918 234 5678', birthday: '1988-08-22' },
    { id: 3, userId: 'USR003', employeeCode: 'EMP003', name: 'Mike Johnson', department: 'Engineering', position: 'Frontend Developer', role: 'Employee', email: 'mike.johnson@company.com', contactNum: '+63 919 345 6789', birthday: '1992-03-10' },
    { id: 4, userId: 'USR004', employeeCode: 'EMP004', name: 'Sarah Williams', department: 'HR', position: 'HR Specialist', role: 'Department Manager', email: 'sarah.williams@company.com', contactNum: '+63 920 456 7890', birthday: '1991-11-30' },
    { id: 5, userId: 'USR005', employeeCode: 'EMP005', name: 'Tom Brown', department: 'Sales', position: 'Sales Executive', role: 'Employee', email: 'tom.brown@company.com', contactNum: '+63 921 567 8901', birthday: '1989-07-18' },
    { id: 6, userId: 'USR006', employeeCode: 'EMP006', name: 'Emily Davis', department: 'Finance', position: 'Accountant', role: 'Employee', email: 'emily.davis@company.com', contactNum: '+63 922 678 9012', birthday: '1993-02-25' },
    { id: 7, userId: 'USR007', employeeCode: 'EMP007', name: 'David Wilson', department: 'Engineering', position: 'Backend Developer', role: 'Employee', email: 'david.wilson@company.com', contactNum: '+63 923 789 0123', birthday: '1991-09-12' },
    { id: 8, userId: 'USR008', employeeCode: 'EMP008', name: 'Alex Chen', department: 'IT', position: 'System Admin', role: 'Department Manager', email: 'alex.chen@company.com', contactNum: '+63 924 890 1234', birthday: '1990-12-05' },
    { id: 9, userId: 'USR009', employeeCode: 'EMP009', name: 'Lisa Park', department: 'Design', position: 'UI Designer', role: 'Employee', email: 'lisa.park@company.com', contactNum: '+63 925 901 2345', birthday: '1994-04-20' },
    { id: 10, userId: 'USR010', employeeCode: 'EMP010', name: 'Mark Rodriguez', department: 'QA', position: 'QA Tester', role: 'Employee', email: 'mark.rodriguez@company.com', contactNum: '+63 926 012 3456', birthday: '1992-06-08' },
    { id: 11, userId: 'USR011', employeeCode: 'EMP011', name: 'Rachel Kim', department: 'Operations', position: 'Operations Manager', role: 'Department Manager', email: 'rachel.kim@company.com', contactNum: '+63 927 123 4567', birthday: '1987-10-14' },
    { id: 12, userId: 'USR012', employeeCode: 'EMP012', name: 'Chris Anderson', department: 'Engineering', position: 'DevOps Engineer', role: 'Employee', email: 'chris.anderson@company.com', contactNum: '+63 928 234 5678', birthday: '1991-01-28' },
    { id: 13, userId: 'USR013', employeeCode: 'EMP013', name: 'Amanda Lee', department: 'Marketing', position: 'Content Writer', role: 'Employee', email: 'amanda.lee@company.com', contactNum: '+63 929 345 6789', birthday: '1993-08-16' },
    { id: 14, userId: 'USR014', employeeCode: 'EMP014', name: 'Robert Taylor', department: 'Sales', position: 'Sales Representative', role: 'Employee', email: 'robert.taylor@company.com', contactNum: '+63 930 456 7890', birthday: '1990-03-22' },
    { id: 15, userId: 'USR015', employeeCode: 'EMP015', name: 'Jessica Martinez', department: 'Finance', position: 'Financial Analyst', role: 'Employee', email: 'jessica.martinez@company.com', contactNum: '+63 931 567 8901', birthday: '1992-11-09' },
]);

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
    let filtered = allEmployees.value.filter(emp => emp.department === currentUser.value.department);
    
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
    let filtered = allEmployees.value;
    
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
const addNewDepartment = () => {
    if (newDepartmentCode.value.trim() && newDepartmentName.value.trim()) {
        const newDept = {
            id: departments.value.length + 1,
            code: newDepartmentCode.value,
            name: newDepartmentName.value,
            manager: '',
            description: '',
            employeeCount: 0
        };
        departments.value.push(newDept);
        
        // Reset form
        newDepartmentCode.value = '';
        newDepartmentName.value = '';
        isAddingDepartment.value = false;
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
const saveEditDepartment = () => {
    editingDepartmentId.value = null;
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
const confirmDelete = () => {
    if (deleteItemId.value === null) return;
    
    if (deleteType.value === 'department') {
        departments.value = departments.value.filter(d => d.id !== deleteItemId.value);
    } else if (deleteType.value === 'position') {
        positions.value = positions.value.filter(p => p.id !== deleteItemId.value);
    } else if (deleteType.value === 'employee') {
        allEmployees.value = allEmployees.value.filter(e => e.id !== deleteItemId.value);
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
        alert('Please upload a PNG or JPG image.');
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
        alert('Please upload a PNG or JPG image.');
    }
};

// Add employee dialog state
const isAddEmployeeDialogOpen = ref(false);
const addEmployeeStep = ref(1); // Track current step (1 or 2)
const isAddingFromDepartmentsTab = ref(false); // Track if adding from Departments tab
const newEmployee = ref({
    firstName: '',
    lastName: '',
    department: '',
    position: '',
    employeeCode: '',
    email: '',
    contactNum: '',
    birthday: '',
    password: '',
    confirmPassword: '',
    avatar: ''
});

// Function to open add employee dialog
const openAddEmployeeDialog = (fromDepartmentsTab = false) => {
    isAddingFromDepartmentsTab.value = fromDepartmentsTab;
    
    // Reset form
    newEmployee.value = {
        firstName: '',
        lastName: '',
        department: fromDepartmentsTab ? currentUser.value.department : '', // Auto-set department if from Departments tab
        position: '',
        employeeCode: '',
        email: '',
        contactNum: '',
        birthday: '',
        password: '',
        confirmPassword: '',
        avatar: ''
    };
    addEmployeeStep.value = 1; // Reset to first step
    isAddEmployeeDialogOpen.value = true;
};

// Function to go to next step
const goToNextStep = () => {
    if (newEmployee.value.firstName.trim() && 
        newEmployee.value.lastName.trim() &&
        newEmployee.value.department.trim() && 
        newEmployee.value.employeeCode.trim()) {
        addEmployeeStep.value = 2;
    }
};

// Function to go back to previous step
const goToPreviousStep = () => {
    addEmployeeStep.value = 1;
};

// Function to add new employee
const addNewEmployee = () => {
    // Validate password match
    if (newEmployee.value.password !== newEmployee.value.confirmPassword) {
        alert('Passwords do not match!');
        return;
    }
    
    if (newEmployee.value.email.trim() && 
        newEmployee.value.password.trim()) {
        
        const fullName = `${newEmployee.value.firstName} ${newEmployee.value.lastName}`;
        
        const newEmp = {
            id: allEmployees.value.length + 1,
            userId: `USR${String(allEmployees.value.length + 1).padStart(3, '0')}`,
            employeeCode: newEmployee.value.employeeCode,
            name: fullName,
            department: newEmployee.value.department,
            position: newEmployee.value.position,
            role: 'Employee', // Default role
            email: newEmployee.value.email,
            contactNum: newEmployee.value.contactNum,
            birthday: newEmployee.value.birthday,
            avatar: newEmployee.value.avatar
        };
        
        allEmployees.value.push(newEmp);
        isAddEmployeeDialogOpen.value = false;
        addEmployeeStep.value = 1; // Reset step
        
        // Reset form
        newEmployee.value = {
            firstName: '',
            lastName: '',
            department: '',
            position: '',
            employeeCode: '',
            email: '',
            contactNum: '',
            birthday: '',
            password: '',
            confirmPassword: '',
            avatar: ''
        };
    }
};

// Function to view employee details
const viewEmployeeDetails = (employee: any, fromAllEmployees = false) => {
    const nameParts = employee.name.split(' ');
    const firstName = nameParts[0] || '';
    const lastName = nameParts.slice(1).join(' ') || '';
    
    selectedEmployee.value = { 
        ...employee,
        firstName,
        lastName,
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
        // If canceling edit, restore original name and clear password fields
        const employee = allEmployees.value.find(e => e.id === selectedEmployee.value.id);
        if (employee) {
            const nameParts = employee.name.split(' ');
            selectedEmployee.value.firstName = nameParts[0] || '';
            selectedEmployee.value.lastName = nameParts.slice(1).join(' ') || '';
        }
        selectedEmployee.value.newPassword = '';
        selectedEmployee.value.confirmPassword = '';
    }
    isEditMode.value = !isEditMode.value;
};

// Function to save employee details
const saveEmployeeDetails = () => {
    // Validate password if provided
    if (selectedEmployee.value.newPassword) {
        if (selectedEmployee.value.newPassword !== selectedEmployee.value.confirmPassword) {
            alert('Passwords do not match!');
            return;
        }
        if (selectedEmployee.value.newPassword.length < 6) {
            alert('Password must be at least 6 characters long!');
            return;
        }
        // Here you would typically update the password on the backend
        // For now, we'll just clear the password fields
    }
    
    // Combine firstName and lastName back to name
    if (selectedEmployee.value.firstName && selectedEmployee.value.lastName) {
        selectedEmployee.value.name = `${selectedEmployee.value.firstName} ${selectedEmployee.value.lastName}`;
    }
    
    // Update the employee in the list
    const index = allEmployees.value.findIndex(e => e.id === selectedEmployee.value.id);
    if (index !== -1) {
        allEmployees.value[index] = { ...selectedEmployee.value };
    }
    
    // Clear password fields
    selectedEmployee.value.newPassword = '';
    selectedEmployee.value.confirmPassword = '';
    
    // Here you would typically save to backend
    isEditMode.value = false;
};

// Watch for department changes in All Employees tab to reset position
watch(() => selectedEmployee.value?.department, (newDept, oldDept) => {
    if (isFromAllEmployeesTab.value && isEditMode.value && newDept !== oldDept && oldDept !== undefined) {
        // Reset position when department changes
        selectedEmployee.value.position = '';
    }
});

// Add position state
const isAddingPosition = ref(false);
const newPositionName = ref('');
const newPositionDepartment = ref('');

// Function to add new position
const addNewPosition = () => {
    if (newPositionName.value.trim() && newPositionDepartment.value.trim()) {
        const newPosition = {
            id: positions.value.length + 1,
            name: newPositionName.value,
            department: newPositionDepartment.value
        };
        positions.value.push(newPosition);
        
        // Reset form
        newPositionName.value = '';
        newPositionDepartment.value = '';
        isAddingPosition.value = false;
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
const saveEditPosition = () => {
    editingPositionId.value = null;
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

// Dummy data for positions
const positions = ref([
    { id: 1, name: 'Senior Developer', department: 'Engineering' },
    { id: 2, name: 'Marketing Manager', department: 'Marketing' },
    { id: 3, name: 'HR Specialist', department: 'HR' },
    { id: 4, name: 'Sales Executive', department: 'Sales' },
    { id: 5, name: 'Accountant', department: 'Finance' },
    { id: 6, name: 'Frontend Developer', department: 'Engineering' },
    { id: 7, name: 'Backend Developer', department: 'Engineering' },
    { id: 8, name: 'UI Designer', department: 'Design' },
]);

// Filtered positions based on selected department in add employee form
const filteredPositionsForEmployee = computed(() => {
    if (!newEmployee.value.department) {
        return [];
    }
    return positions.value.filter(pos => pos.department === newEmployee.value.department);
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
    if (!selectedEmployee.value?.department) {
        return [];
    }
    return positions.value.filter(pos => pos.department === selectedEmployee.value.department);
});

// Filtered positions for All Employees tab Position dialog
const filteredPositionsByDepartment = computed(() => {
    if (positionDepartmentFilter.value === 'all') {
        return positions.value;
    }
    return positions.value.filter(pos => pos.department === positionDepartmentFilter.value);
});
</script>

<template>
    <Head title="Department" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="relative min-h-[100vh] p-6 flex-1 border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border">
            <Tabs default-value="departments" class="w-full">
                <TabsList class="grid w-full grid-cols-2 mb-6">
                    <TabsTrigger value="departments">
                        <div class="flex items-center gap-2">
                            <Building2 :size="16" />
                            <span>My Department</span>
                        </div>
                    </TabsTrigger>
                    <TabsTrigger value="employees">
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
                            <div class="absolute top-2 right-8">
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
                                                    <Badge variant="outline" class="gap-1.5 mt-0.5">
                                                        {{ currentUser.role }}
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
                                <div v-if="isAddingPosition" class="p-3 rounded-lg border border-primary/50 bg-muted/30 mb-4">
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
                                                <SelectItem :value="currentUser.department">
                                                    {{ currentUser.department }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <div class="flex gap-2">
                                            <Button size="sm" @click="addNewPosition" class="flex-1 gap-2">
                                                <Plus :size="14" />
                                                Add
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
                                                    <Select v-model="position.department" :disabled="true">
                                                        <SelectTrigger class="text-xs">
                                                            <SelectValue :placeholder="currentUser.department" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem :value="currentUser.department">
                                                                {{ currentUser.department }}
                                                            </SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <Button variant="ghost" size="icon" class="h-8 w-8" @click="saveEditPosition">
                                                        <Check :size="14" />
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
                                                <div class="flex items-center gap-2">
                                                    <Button variant="ghost" size="icon" class="h-8 w-8" @click="startEditPosition(position)">
                                                        <Edit :size="14" />
                                                    </Button>
                                                    <Button variant="ghost" size="icon" class="h-8 w-8 text-destructive hover:text-destructive" @click="deletePosition(position.id)">
                                                        <Trash2 :size="14" />
                                                    </Button>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </ScrollArea>

                                <div class="border-t pt-4 flex items-center justify-between">
                                    <Button variant="link" class="gap-2 text-primary" @click="() => { newPositionDepartment = currentUser.department; isAddingPosition = true; }">
                                        <Plus :size="16" />
                                        Add Position
                                    </Button>
                                    <Button variant="outline" @click="isPositionDialogOpen = false">
                                        Close
                                    </Button>
                                </div>
                            </DialogContent>
                        </Dialog>
                        <Button variant="outline" class="gap-2 flex-1 sm:flex-none" @click="openAddEmployeeDialog(true)">
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
                                                <Badge variant="outline">{{ employee.role }}</Badge>
                                            </td>
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
                                                        <DropdownMenuItem class="gap-2 text-destructive focus:text-destructive" @click="deleteEmployee(employee.id)">
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
                                                    <DropdownMenuItem class="gap-2 text-destructive focus:text-destructive" @click="deleteEmployee(employee.id)">
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
                                            <Badge variant="outline" class="text-xs">{{ employee.role }}</Badge>
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
                                        <div v-if="isAddingPosition" class="p-3 rounded-lg border border-primary/50 bg-muted/30 mb-4">
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
                                                        <SelectItem v-for="dept in uniqueDepartments" :key="dept" :value="dept">
                                                            {{ dept }}
                                                        </SelectItem>
                                                    </SelectContent>
                                                </Select>
                                                <div class="flex gap-2">
                                                    <Button size="sm" @click="addNewPosition" class="flex-1">
                                                        Add
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
                                                            <Select v-model="position.department">
                                                                <SelectTrigger class="text-xs">
                                                                    <SelectValue placeholder="Select department" />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    <SelectItem v-for="dept in uniqueDepartments" :key="dept" :value="dept">
                                                                        {{ dept }}
                                                                    </SelectItem>
                                                                </SelectContent>
                                                            </Select>
                                                        </div>
                                                        <div class="flex items-center gap-2">
                                                            <Button variant="ghost" size="icon" class="h-8 w-8" @click="saveEditPosition">
                                                                <Check :size="14" />
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
                                                        <div class="flex items-center gap-2">
                                                            <Button variant="ghost" size="icon" class="h-8 w-8" @click="startEditPosition(position)">
                                                                <Edit :size="14" />
                                                            </Button>
                                                            <Button variant="ghost" size="icon" class="h-8 w-8 text-destructive hover:text-destructive" @click="deletePosition(position.id)">
                                                                <Trash2 :size="14" />
                                                            </Button>
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>
                                        </ScrollArea>

                                        <div class="border-t pt-4 flex items-center justify-between">
                                            <Button variant="link" class="gap-2 text-primary" @click="isAddingPosition = true">
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
                                        <DialogTrigger as-child>
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
                                        <div v-if="isAddingDepartment" class="p-3 rounded-lg border border-primary/50 bg-muted/30 mb-4">
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
                                                    <Button size="sm" @click="addNewDepartment" class="flex-1 gap-2">
                                                        <Plus :size="14" />
                                                        Add
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
                                                            <Button variant="ghost" size="icon" class="h-8 w-8" @click="saveEditDepartment">
                                                                <Check :size="14" />
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
                                                            <Badge variant="outline" class="mt-1 text-xs">{{ department.employeeCount }} employees</Badge>
                                                        </div>
                                                        <div class="flex items-center gap-2">
                                                            <Button variant="ghost" size="icon" class="h-8 w-8" @click="startEditDepartment(department)">
                                                                <Edit :size="14" />
                                                            </Button>
                                                            <Button variant="ghost" size="icon" class="h-8 w-8 text-destructive hover:text-destructive" @click="deleteDepartment(department.id)">
                                                                <Trash2 :size="14" />
                                                            </Button>
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>
                                        </ScrollArea>

                                        <div class="border-t pt-4 flex items-center justify-between">
                                            <Button variant="link" class="gap-2 text-primary" @click="isAddingDepartment = true">
                                                <Plus :size="16" />
                                                Add Department
                                            </Button>
                                            <Button variant="outline" @click="isDepartmentDialogOpen = false">
                                                Close
                                            </Button>
                                        </div>
                                    </DialogContent>
                                    </Dialog>
                                    <Button variant="outline" class="gap-2 flex-1 sm:flex-none" @click="openAddEmployeeDialog(false)">
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
                                                <Badge variant="outline">{{ employee.role }}</Badge>
                                            </td>
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
                                                        <DropdownMenuItem class="gap-2 text-destructive focus:text-destructive" @click="deleteEmployee(employee.id)">
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
                                                    <DropdownMenuItem class="gap-2 text-destructive focus:text-destructive" @click="deleteEmployee(employee.id)">
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
                                            <Badge variant="outline" class="text-xs">{{ employee.role }}</Badge>
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
                                                {{ newEmployee.firstName && newEmployee.lastName ? (newEmployee.firstName[0] + newEmployee.lastName[0]) : '?' }}
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
                                        <Input v-model="newEmployee.firstName" placeholder="First name" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label class="text-sm font-medium">Last Name *</Label>
                                        <Input v-model="newEmployee.lastName" placeholder="Last name" />
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <Label class="text-sm font-medium">Employee Code *</Label>
                                    <Input v-model="newEmployee.employeeCode" placeholder="e.g., EMP016" class="font-mono" />
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div class="space-y-2">
                                        <Label class="text-sm font-medium">Department *</Label>
                                        <Select v-model="newEmployee.department" :disabled="isAddingFromDepartmentsTab" @update:model-value="newEmployee.position = ''">
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select department" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="dept in uniqueDepartments" :key="dept" :value="dept">
                                                    {{ dept }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div class="space-y-2">
                                        <Label class="text-sm font-medium">Position</Label>
                                        <Select v-model="newEmployee.position" :disabled="!newEmployee.department">
                                            <SelectTrigger>
                                                <SelectValue :placeholder="newEmployee.department ? 'Select position' : 'Select department first'" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="pos in filteredPositionsForEmployee" :key="pos.id" :value="pos.name">
                                                    {{ pos.name }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <Label class="text-sm font-medium">Contact Number</Label>
                                    <Input v-model="newEmployee.contactNum" placeholder="+63 917 123 4567" />
                                </div>

                                <div class="space-y-2">
                                    <Label class="text-sm font-medium">Birthday</Label>
                                    <Input v-model="newEmployee.birthday" type="date" />
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
                                        <Button @click="addNewEmployee" class="gap-2">
                                            <Plus :size="16" />
                                            Add Employee
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
                                    <Input v-model="selectedEmployee.firstName" placeholder="First Name" class="h-9 text-sm" />
                                </div>
                                <div class="space-y-1.5">
                                    <Label class="text-xs font-medium">Last Name</Label>
                                    <Input v-model="selectedEmployee.lastName" placeholder="Last Name" class="h-9 text-sm" />
                                </div>
                            </div>

                            <!-- Department -->
                            <div class="space-y-1.5">
                                <Label class="text-xs font-medium">Department</Label>
                                <Select 
                                    v-if="isFromAllEmployeesTab" 
                                    v-model="selectedEmployee.department"
                                >
                                    <SelectTrigger class="h-9 text-sm">
                                        <SelectValue placeholder="Select department" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="dept in uniqueDepartments" :key="dept" :value="dept">
                                            {{ dept }}
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
                                <Select v-model="selectedEmployee.position">
                                    <SelectTrigger class="h-9 text-sm">
                                        <SelectValue placeholder="Select position" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem 
                                            v-for="position in filteredPositionsForSelectedEmployee" 
                                            :key="position.id" 
                                            :value="position.name"
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
                                        <SelectItem value="Admin">Admin</SelectItem>
                                        <SelectItem value="Department Manager">Department Manager</SelectItem>
                                        <SelectItem value="Employee">Employee</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <!-- Employee Code -->
                            <div class="space-y-1.5">
                                <Label class="text-xs font-medium">Employee Code</Label>
                                <Input v-model="selectedEmployee.employeeCode" placeholder="Employee Code" class="font-mono h-9 text-sm" />
                            </div>

                            <!-- Email -->
                            <div class="space-y-1.5">
                                <Label class="text-xs font-medium">Email</Label>
                                <Input v-model="selectedEmployee.email" type="email" placeholder="Email" class="h-9 text-sm" />
                            </div>

                            <!-- Contact Number -->
                            <div class="space-y-1.5">
                                <Label class="text-xs font-medium">Contact Number</Label>
                                <Input v-model="selectedEmployee.contactNum" placeholder="Contact Number" class="h-9 text-sm" />
                            </div>

                            <!-- Birthday -->
                            <div class="space-y-1.5">
                                <Label class="text-xs font-medium">Birthday</Label>
                                <Input v-model="selectedEmployee.birthday" type="date" class="h-9 text-sm" />
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

                                <!-- Role (All Employees Tab only) -->
                                <div v-if="isFromAllEmployeesTab" class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg bg-muted flex items-center justify-center shrink-0">
                                        <Shield :size="18" class="text-muted-foreground" />
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs text-muted-foreground mb-0.5">Role</p>
                                        <Badge variant="outline" class="gap-1.5 text-xs">
                                            
                                            {{ selectedEmployee.role }}
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
                        <Button v-if="!isEditMode" @click="toggleEditMode" class="h-9 text-sm">
                            Edit
                        </Button>
                        <template v-else>
                            <Button variant="outline" @click="toggleEditMode" class="h-9 text-sm">
                                Cancel
                            </Button>
                            <Button @click="saveEmployeeDetails" class="h-9 text-sm">
                                Save Changes
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
                                        {{ editedUserInfo.firstName && editedUserInfo.lastName ? (editedUserInfo.firstName[0] + editedUserInfo.lastName[0]) : (currentUser.name.split(' ').map(n => n[0]).join('')) }}
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
                                <Input v-model="editedUserInfo.firstName" placeholder="First Name" class="h-9 text-sm" />
                            </div>
                            <div class="space-y-1.5">
                                <Label class="text-xs font-medium">Last Name</Label>
                                <Input v-model="editedUserInfo.lastName" placeholder="Last Name" class="h-9 text-sm" />
                            </div>
                        </div>

                        <!-- Position -->
                        <div class="space-y-1.5">
                            <Label class="text-xs font-medium">Position</Label>
                            <Select v-model="editedUserInfo.position">
                                <SelectTrigger class="h-9 text-sm">
                                    <SelectValue placeholder="Select position" />
                                </SelectTrigger>
                                <SelectContent>
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

                        <!-- Employee Code -->
                        <div class="space-y-1.5">
                            <Label class="text-xs font-medium">Employee Code</Label>
                            <Input v-model="editedUserInfo.employeeCode" placeholder="Employee Code" class="font-mono h-9 text-sm" />
                        </div>

                        <!-- Email -->
                        <div class="space-y-1.5">
                            <Label class="text-xs font-medium">Email</Label>
                            <Input v-model="editedUserInfo.email" type="email" placeholder="Email" class="h-9 text-sm" />
                        </div>

                        <!-- Contact Number -->
                        <div class="space-y-1.5">
                            <Label class="text-xs font-medium">Contact Number</Label>
                            <Input v-model="editedUserInfo.contactNum" placeholder="Contact Number" class="h-9 text-sm" />
                        </div>

                        <!-- Birthday -->
                        <div class="space-y-1.5">
                            <Label class="text-xs font-medium">Birthday</Label>
                            <Input v-model="editedUserInfo.birthday" type="date" class="h-9 text-sm" />
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
                        <Button @click="saveUserInfo" class="h-9 text-sm">Save Changes</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>

