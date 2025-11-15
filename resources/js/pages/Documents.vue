<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Head } from '@inertiajs/vue3'
import { type BreadcrumbItem } from '@/types';

import {
  Cpu, Plus, Search, FolderIcon, SendIcon, Trash2 as Trash2Icon, MoreVertical, Edit3,
  ChevronDown, ChevronLeft, ChevronRight, X, Loader2, Eye, Lock, Users, Tag, Download, Info, Clock,
  Pencil, Trash2, RefreshCw, SendHorizontal,
} from 'lucide-vue-next'
import {
  DropdownMenu,
  DropdownMenuTrigger,
  DropdownMenuContent,
  DropdownMenuLabel,
  DropdownMenuItem,
  DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Badge } from '@/components/ui/badge'
import { ScrollArea } from '@/components/ui/scroll-area'
import { Separator } from '@/components/ui/separator'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'

// ============================================================================
// BREADCRUMBS
// ============================================================================
const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Documents',
        href: '/documents',
    },  
    ];  

// ============================================================================
// TOAST NOTIFICATIONS
// ============================================================================
type ToastVariant = 'default' | 'success' | 'warning' | 'error'
const toasts = ref<{ id: number; text: string; variant: ToastVariant }[]>([])

/**
 * Display a toast notification
 * @param text - Message to display
 * @param variant - Toast style variant
 */
const showToast = (text: string, variant: ToastVariant = 'default') => {
  const id = Date.now() + Math.random()
  toasts.value.push({ id, text, variant })
  setTimeout(() => {
    toasts.value = toasts.value.filter(t => t.id !== id)
  }, 2600)
}

// ============================================================================
// CURRENT USER
// ============================================================================
const currentUser = ref<{ name: string; department: string; isAdmin: boolean }>({
  name: 'Jennifer',
  department: 'HR',
  isAdmin: true,
})

// ============================================================================
// AI ASSISTANT
// ============================================================================
const aiDialogOpen = ref(false)
const aiPrompt = ref('')
const aiMessages = ref<{ sender: 'user' | 'ai'; text: string }[]>([])
const aiLoading = ref(false)

// Add: search mode, recent searches, and results
const searchMode = ref<'keywords' | 'context'>('keywords')
const recentSearches = ref<string[]>([])
const aiSearchResults = ref<Array<{
  id: number
  name: string
  type: string
  department: string
  access: string
  description: string
  confidence: number
}>>([])

/**
 * Close AI dialog and reset state
 */
const handleAIClose = () => {
  aiDialogOpen.value = false
  aiPrompt.value = ''
  aiMessages.value = []
  aiSearchResults.value = []
}

/**
 * Send message to AI assistant
 */
const sendToAI = async () => {
  if (!aiPrompt.value.trim()) return

  // log user message and store recent searches
  aiMessages.value.push({ sender: 'user', text: aiPrompt.value })
  if (!recentSearches.value.includes(aiPrompt.value)) {
    recentSearches.value.unshift(aiPrompt.value)
    if (recentSearches.value.length > 5) recentSearches.value.pop()
  }

  aiLoading.value = true
  const input = aiPrompt.value
  aiPrompt.value = ''

  setTimeout(() => {
    aiSearchResults.value = []
    if (searchMode.value === 'keywords') {
      performKeywordSearch(input)
    } else {
      performContextualSearch(input)
    }
    aiLoading.value = false
  }, 700)
}

// Add: helper to safely read tags
const getTags = (file: any): string[] =>
  Array.isArray(file?.tags) ? file.tags.filter((t: any) => typeof t === 'string') : []

// Add: keyword search (safe)
const performKeywordSearch = (query: string) => {
  const keywords = query.toLowerCase().split(' ').filter(Boolean)
  const results = files.value
    .filter((file: any) => {
      const searchText = `${file?.name ?? ''} ${file?.description ?? ''} ${getTags(file).join(' ')}`.toLowerCase()
      return keywords.some((kw) => kw && searchText.includes(kw))
    })
    .map((file: any) => ({
      ...file,
      tags: getTags(file), // This ensures `tags` is always a safe array
      confidence: calculateConfidence(file, query),
    }))
    .sort((a: any, b: any) => b.confidence - a.confidence)

  aiSearchResults.value = results.slice(0, 5)

  aiMessages.value.push({
    sender: 'ai',
    text:
      results.length > 0
        ? `I found ${results.length} relevant files for "${query}". Here are the top matches with confidence levels:`
        : `I couldn't find files matching your keywords. Try different terms or switch to contextual search.`,
  })
}

// Add: contextual search (safe)
const performContextualSearch = (query: string) => {
  const q = query.toLowerCase()
  const results = files.value
    .filter((file: any) => {
      const contextMatches = [
        (file?.department ?? '').toLowerCase(),
        (file?.type ?? '').toLowerCase(),
        (file?.description ?? '').toLowerCase(),
        ...getTags(file).map((t) => t.toLowerCase()),
      ]
      return contextMatches.some((m) => m && (q.includes(m) || m.includes(q)))
    })
    .map((file: any) => ({
      ...file,
      confidence: calculateContextualConfidence(file, query),
    }))
    .sort((a: any, b: any) => b.confidence - a.confidence)

  aiSearchResults.value = results.slice(0, 5)

  aiMessages.value.push({
    sender: 'ai',
    text:
      results.length > 0
        ? `Based on your context, I found ${results.length} relevant documents. Here are the most relevant ones:`
        : `I couldn't find documents related to your context. Try being more specific or switch to keyword search.`,
  })
}

// Add: confidence calculation for keywords
const calculateConfidence = (file: any, query: string) => {
  const keywords = query.toLowerCase().split(' ').filter(Boolean)
  if (keywords.length === 0) return 0

  const searchText = `${file?.name ?? ''} ${file?.description ?? ''} ${getTags(file).join(' ')}`.toLowerCase()
  let matches = 0
  keywords.forEach((kw) => {
    if (searchText.includes(kw)) matches++
  })
  return Math.round((matches / keywords.length) * 100)
}

// Add: confidence calculation for context
const calculateContextualConfidence = (file: any, query: string) => {
  const q = query.toLowerCase()
  let score = 0

  if (q.includes((file?.department ?? '').toLowerCase())) score += 40
  if (q.includes((file?.type ?? '').toLowerCase())) score += 30
  if ((file?.description ?? '').toLowerCase().includes(q)) score += 20

  const tagMatches = getTags(file).filter(
    (tag) => q.includes(tag.toLowerCase()) || tag.toLowerCase().includes(q)
  ).length
  score += tagMatches * 10

  return Math.min(score, 100)
}

// ============================================================================
// FILTERS & DROPDOWNS
// ============================================================================
const search = ref('')
const selectedType = ref('All')
const selectedDept = ref('All')
const selectedAccess = ref<'All' | 'Public' | 'Private' | 'Department'>('All')
const fileTypes = ['All', 'PDF', 'Word', 'Excel', 'PPT']
const departments = ['All', 'HR', 'Finance', 'Design', 'Admin', 'Sales', 'IT', 'Engineering', 'Legal']
const accesses = ['All', 'Public', 'Private', 'Department']

// ============================================================================
// REQUEST FILTERS
// ============================================================================
const selectedReqType = ref('All')
const selectedReqDept = ref('All')
const selectedReqStatus = ref<'All' | 'Pending' | 'Approved' | 'Rejected'>('All')
const requestTypes = ['All', 'PDF', 'Word', 'Excel', 'PPT']
const requestStatuses = ['All', 'Pending', 'Approved', 'Rejected']

/**
 * Infer file type from filename extension
 */
const inferTypeFromName = (name: string) => {
  const ext = (name.split('.').pop() || '').toLowerCase()
  if (['doc', 'docx'].includes(ext)) return 'Word'
  if (ext === 'pdf') return 'PDF'
  if (['xls', 'xlsx'].includes(ext)) return 'Excel'
  if (['ppt', 'pptx'].includes(ext)) return 'PPT'
  return (ext || 'OTHER').toUpperCase()
}

// ============================================================================
// TABS & NAVIGATION
// ============================================================================
const navLinks = [
  { title: 'All Files', icon: FolderIcon },
  { title: 'My Files', icon: FolderIcon },
  { title: 'Request', icon: SendIcon },
  { title: 'Trash', icon: Trash2Icon },
]
const activeTab = ref('All Files')
const requestView = ref<'To You' | 'By You'>('To You')

// ============================================================================
// DATA STORES
// ============================================================================
const files = ref<any[]>([
  { id: 1, name: 'Memorandum_Payroll_Update.docx', uploader: 'Jennifer', type: 'Word', department: 'HR', access: 'Private', created: '2025-01-12', size: '240KB', tags: ['Memo', 'Payroll'], description: 'Payroll changes for 2025' },
  { id: 2, name: 'Q1_Townhall_Deck.pptx', uploader: 'Jennifer', type: 'PPT', department: 'IT', access: 'Department', created: '2025-01-07', size: '2.1MB', tags: ['Presentation'], description: 'Slides for Q1 townhall' },
  { id: 3, name: 'Benefits_Enrollment_Guide.pdf', uploader: 'Jennifer', type: 'PDF', department: 'Admin', access: 'Public', created: '2025-01-03', size: '1.1MB', tags: ['Benefits', 'Guide'], description: 'How to enroll' },
  { id: 4, name: 'Onboarding_Checklist.xlsx', uploader: 'Jennifer', type: 'Excel', department: 'HR', access: 'Department', created: '2025-01-02', size: '380KB', tags: ['Checklist'], description: 'Onboarding steps' },
  { id: 5, name: 'Leave_Policy_2025.pdf', uploader: 'Jennifer', type: 'PDF', department: 'Admin', access: 'Private', created: '2025-01-11', size: '760KB', tags: ['Policy', 'Leave'], description: 'Updated leave policy' },
  { id: 6, name: 'Recruitment_Playbook.docx', uploader: 'Jennifer', type: 'Word', department: 'Finance', access: 'Public', created: '2025-01-15', size: '320KB', tags: ['Hiring', 'Guide'], description: 'Recruitment process' },
  { id: 7, name: 'Compensation_Framework.pdf', uploader: 'Jennifer', type: 'PDF', department: 'Legal', access: 'Private', created: '2025-01-10', size: '900KB', tags: ['Compensation'], description: 'Compensation methodology' },
  { id: 8, name: 'Employee_Handbook_2025.pdf', uploader: 'Jerick', type: 'PDF', department: 'Finance', access: 'Public', created: '2025-01-04', size: '1.2MB', tags: ['Policy'], description: 'Company handbook' },
  { id: 9, name: 'Leave_Balance_Template.xlsx', uploader: 'Jemiah', type: 'Excel', department: 'HR', access: 'Department', created: '2025-01-09', size: '310KB', tags: ['Form', 'Leave'], description: 'Template for balances' },
  { id: 10, name: 'Recruitment_Pipeline.xlsx', uploader: 'Howard', type: 'Excel', department: 'HR', access: 'Private', created: '2025-01-05', size: '520KB', tags: ['Hiring', 'Pipeline'], description: 'Pipeline sheet' },
  { id: 11, name: 'Training_Calendar_Q1.pdf', uploader: 'Lance', type: 'PDF', department: 'Legal', access: 'Public', created: '2025-01-06', size: '650KB', tags: ['Training'], description: 'Q1 training calendar' },
  { id: 12, name: 'Exit_Interview_Form.docx', uploader: 'Jemiah', type: 'Word', department: 'HR', access: 'Private', created: '2025-01-08', size: '180KB', tags: ['Exit', 'Form'], description: 'Exit interview form' },
  { id: 13, name: 'Performance_Template.xlsx', uploader: 'Jerick', type: 'Excel', department: 'Sales', access: 'Department', created: '2025-01-01', size: '220KB', tags: ['Performance', 'Template'], description: 'Performance template' },
  { id: 14, name: 'Diversity_Report_2024.pdf', uploader: 'Abby', type: 'PDF', department: 'Legal', access: 'Private', created: '2024-12-20', size: '1.8MB', tags: ['DEI', 'Report'], description: 'DEI snapshot' },
  { id: 15, name: 'Sales_Hiring_Forecast.xlsx', uploader: 'Honey Grace', type: 'Excel', department: 'Finance', access: 'Department', created: '2025-01-13', size: '440KB', tags: ['Forecast', 'Hiring'], description: 'Forecast numbers' },
  { id: 16, name: 'Org_Chart_2025.pptx', uploader: 'Jerick', type: 'PPT', department: 'Admin', access: 'Public', created: '2025-01-14', size: '1.9MB', tags: ['Org', 'Structure'], description: 'Org chart' },
])

/**
 * Requests data - FIXED: In "To You" view, only current user can approve/review
 * When status is Approved/Rejected in "To You", approvedBy should be currentUser.name
 */
const requests = ref<any[]>([
  { id: 201, name: 'Salary_Adjustments_2025.docx', requester: 'Jemiah', requestedAt: '2025-01-10', status: 'Pending', department: 'HR', approvedBy: '', access: 'Department' },
  { id: 202, name: 'Training_Catalog_2025.pdf', requester: 'Vince', requestedAt: '2025-01-08', status: 'Approved', department: 'HR', approvedBy: 'Jennifer', decisionAt: '2025-01-09', access: 'Public' },
  { id: 203, name: 'Policy_Retirement_Benefits.docx', requester: 'Honey Grace', requestedAt: '2025-01-05', status: 'Rejected', department: 'HR', approvedBy: 'Jennifer', decisionAt: '2025-01-06', access: 'Private' },
  { id: 206, name: 'Travel_Reimbursement_Form.docx', requester: 'Jerick', requestedAt: '2025-01-11', status: 'Pending', department: 'HR', approvedBy: '', access: 'Department' },
  { id: 204, name: 'Performance_Review_Guide.docx', requester: 'Jennifer', requestedAt: '2025-01-06', status: 'Pending', department: 'HR', approvedBy: '', access: 'Department' },
  { id: 205, name: 'New_Hire_Checklist.docx', requester: 'Jennifer', requestedAt: '2025-01-03', status: 'Approved', department: 'HR', approvedBy: 'Angela', decisionAt: '2025-01-04', access: 'Public' },
  { id: 207, name: 'Remote_Work_Policy.docx', requester: 'Jennifer', requestedAt: '2025-01-12', status: 'Rejected', department: 'HR', approvedBy: 'Lance', decisionAt: '2025-01-13', access: 'Private' },
])

const trashFiles = ref<any[]>([
  { id: 901, name: 'Old_Onboarding_Checklist_2023.xlsx', type: 'Excel', department: 'HR', deletedAt: new Date(Date.now() - 5 * 24 * 60 * 60 * 1000).toISOString(), deletedBy: 'System' },
  { id: 902, name: 'Legacy_Policy_2019.pdf', type: 'PDF', department: 'HR', deletedAt: new Date(Date.now() - 10 * 24 * 60 * 60 * 1000).toISOString(), deletedBy: 'System' },
  { id: 903, name: 'Benefits_Brochure_2022.pdf', type: 'PDF', department: 'HR', deletedAt: new Date(Date.now() - 2 * 24 * 60 * 60 * 1000).toISOString(), deletedBy: 'System' },
])

// ============================================================================
// PAGINATION
// ============================================================================
const itemsPerPage = ref(12)
const currentPage = ref(1)

// ============================================================================
// STYLING HELPERS
// ============================================================================
/**
 * Get color class for file type badge
 */
const typeColor = (type: string) => {
  switch ((type || '').toUpperCase()) {
    case 'PDF': return 'bg-red-500'
    case 'WORD': return 'bg-blue-500'
    case 'EXCEL': return 'bg-green-500'
    case 'PPT': return 'bg-orange-500'
    default: return 'bg-gray-400'
  }
}

/**
 * Get icon component for access level
 */
const accessIconComponent = (access: string) => {
  switch (access) {
    case 'Public': return Eye
    case 'Private': return Lock
    case 'Department': return Users
    default: return Eye
  }
}

/**
 * Get color class for access icon
 */
const accessIconColor = (access: string) => {
  switch (access) {
    case 'Public': return 'text-emerald-500'
    case 'Private': return 'text-amber-500'
    case 'Department': return 'text-indigo-500'
    default: return 'text-gray-400'
  }
}

/**
 * Tag color palette for consistent coloring
 */
const tagColorPalette = [
  'bg-blue-100 text-blue-700',
  'bg-purple-100 text-purple-700',
  'bg-green-100 text-green-700',
  'bg-pink-100 text-pink-700',
  'bg-yellow-100 text-yellow-700',
  'bg-orange-100 text-orange-700',
  'bg-teal-100 text-teal-700',
  'bg-cyan-100 text-cyan-700',
]

/**
 * Get deterministic color index from string
 */
const colorIndexFromString = (s: string) => {
  let h = 0
  for (let i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) >>> 0
  return h % tagColorPalette.length
}

/**
 * Get tag color class by tag name
 */
const getTagColorByName = (tag: string) => tagColorPalette[colorIndexFromString(tag)]

/**
 * Get status badge color class
 */
const statusColor = (status: string) => {
  switch (status) {
    case 'Approved': return 'bg-green-100 text-green-700'
    case 'Pending': return 'bg-amber-100 text-amber-700'
    case 'Rejected': return 'bg-red-100 text-red-700'
    default: return 'bg-gray-100 text-gray-700'
  }
}

/**
 * Get status dot color class
 */
const statusDot = (status: string) => {
  switch (status) {
    case 'Approved': return 'bg-green-500'
    case 'Pending': return 'bg-amber-500'
    case 'Rejected': return 'bg-red-500'
    default: return 'bg-gray-400'
  }
}

/**
 * Get type chip color for filter container
 */
const typeChipColor = (type: string) => {
  switch (type) {
    case 'PDF': return 'bg-red-100 text-red-700'
    case 'Word': return 'bg-blue-100 text-blue-700'
    case 'Excel': return 'bg-green-100 text-green-700'
    case 'PPT': return 'bg-orange-100 text-orange-700'
    default: return 'bg-gray-100 text-gray-700'
  }
}

/**
 * Get access chip color for filter container
 */
const accessChipColor = (access: string) => {
  switch (access) {
    case 'Public': return 'bg-emerald-100 text-emerald-700'
    case 'Private': return 'bg-amber-100 text-amber-700'
    case 'Department': return 'bg-indigo-100 text-indigo-700'
    default: return 'bg-gray-100 text-gray-700'
  }
}

const deptChipColor = 'bg-sky-100 text-sky-700'

// ============================================================================
// USER DIRECTORY
// ============================================================================
const userDirectory: Record<string, { position: string; department: string }> = {
  'Jennifer': { position: 'Admin', department: 'HR' },
  'Jerick': { position: 'Administrator', department: 'Admin' },
  'Jemiah': { position: 'Head Manager', department: 'HR' },
  'Howard': { position: 'Recruitment Lead', department: 'HR' },
  'Lance': { position: 'Training Coordinator', department: 'Legal' },
  'Honey Grace': { position: 'Finance Manager', department: 'Finance' },
  'Abby': { position: 'Legal Officer', department: 'Legal' },
  'Vince': { position: 'Chief Operating Officer', department: 'Operations' },
  'Angela': { position: 'Head Manager', department: 'HR' },
  'Merliah': { position: 'HR Manager', department: 'HR' },
}

/**
 * Get user metadata (name, position, department)
 */
const getUserMeta = (name: string, fallbackDept?: string) => {
  const meta = userDirectory[name] || { position: 'Staff', department: fallbackDept || '—' }
  return { name, position: meta.position, department: meta.department }
}

// ============================================================================
// TAG MANAGEMENT
// ============================================================================
const tagOptions = ref<string[]>([
  'Memo', 'Policy', 'Leave', 'Hiring', 'Guide', 'Training', 'Form', 'DEI', 'Report', 'Performance', 'Template', 'Org', 'Structure', 'Forecast', 'Pipeline', 'Benefits', 'Checklist', 'Presentation', 'Handbook'
])

/**
 * Add tag from options to a tag list
 */
const addTagFromOptions = (listRef: { value: string[] }, tag: string) => {
  if (!listRef.value.includes(tag)) listRef.value = [...listRef.value, tag]
}

/**
 * Edit a tag option name
 */
const editOptionTag = (oldName: string, newName: string) => {
  const idx = tagOptions.value.findIndex(t => t === oldName)
  if (idx >= 0 && newName.trim() && !tagOptions.value.includes(newName.trim())) {
    tagOptions.value.splice(idx, 1, newName.trim())
    showToast('Tag updated', 'success')
  }
}

/**
 * Delete a tag option
 */
const deleteOptionTag = (name: string) => {
  tagOptions.value = tagOptions.value.filter(t => t !== name)
  showToast('Tag removed', 'warning')
}

// Tag admin modals state
const tagEditModalOpen = ref(false)
const tagDeleteModalOpen = ref(false)
const tagAddModalOpen = ref(false)
const tagToEdit = ref<string>('')
const tagEditText = ref<string>('')
const tagToDelete = ref<string>('')
const newAdminTag = ref<string>('')

/**
 * Open tag edit modal
 */
const openAdminTagEdit = (name: string) => {
  tagToEdit.value = name
  tagEditText.value = name
  tagEditModalOpen.value = true
}

/**
 * Confirm tag edit
 */
const confirmAdminTagEdit = () => {
  const next = tagEditText.value.trim()
  if (!next || next === tagToEdit.value) {
    tagEditModalOpen.value = false
    return
  }
  editOptionTag(tagToEdit.value, next)
  tagEditModalOpen.value = false
  tagToEdit.value = ''
  tagEditText.value = ''
}

/**
 * Open tag delete modal
 */
const openAdminTagDelete = (name: string) => {
  tagToDelete.value = name
  tagDeleteModalOpen.value = true
}

/**
 * Confirm tag delete
 */
const confirmAdminTagDelete = () => {
  deleteOptionTag(tagToDelete.value)
  tagDeleteModalOpen.value = false
  tagToDelete.value = ''
}

/**
 * Open tag add modal
 */
const openAdminTagAdd = () => {
  newAdminTag.value = ''
  tagAddModalOpen.value = true
}

/**
 * Confirm tag add
 */
const confirmAdminTagAdd = () => {
  const t = newAdminTag.value.trim()
  if (!t || tagOptions.value.includes(t)) {
    tagAddModalOpen.value = false
    return
  }
  tagOptions.value = [t, ...tagOptions.value]
  showToast('Tag added', 'success')
  tagAddModalOpen.value = false
}

// ============================================================================
// FILTERING & PAGINATION LOGIC
// ============================================================================
/**
 * Base search filter
 */
const baseFilesFilteredBySearch = (list: any[]) => {
  const q = search.value.trim().toLowerCase()
  if (!q) return list
  return list.filter(f => (f.name || '').toLowerCase().includes(q))
}

/**
 * Apply type filter
 */
const applyTypeFilter = (list: any[]) => {
  if (selectedType.value === 'All') return list
  const t = selectedType.value.toLowerCase()
  return list.filter(f => f.type && f.type.toString().toLowerCase() === t)
}

/**
 * Apply department filter
 */
const applyDeptFilter = (list: any[]) => {
  if (selectedDept.value === 'All') return list
  return list.filter(f => (f.department || '') === selectedDept.value)
}

/**
 * Apply access filter
 */
const applyAccessFilter = (list: any[]) => {
  if (selectedAccess.value === 'All') return list
  if (selectedAccess.value === 'Public') return list.filter(f => f.access === 'Public')
  if (selectedAccess.value === 'Private') return list.filter(f => f.access === 'Private')
  if (selectedAccess.value === 'Department') {
    if (selectedDept.value === 'All') return list.filter(f => f.access === 'Department')
    return list.filter(f => f.access === 'Department' && f.department === selectedDept.value)
  }
  return list
}

/**
 * Filtered files based on active tab and filters
 */
const filteredFiles = computed(() => {
  if (activeTab.value === 'Trash' || activeTab.value === 'Request') return []
  let list = files.value.slice()
  if (activeTab.value === 'My Files') list = list.filter(f => f.uploader === currentUser.value.name)
  list = applyTypeFilter(list)
  list = applyDeptFilter(list)
  list = applyAccessFilter(list)
  list = baseFilesFilteredBySearch(list)
  return list
})

/**
 * Base filtered requests (before pagination)
 * "To You" shows requests NOT made by current user (for approval)
 * "By You" shows requests made by current user
 */
const filteredRequestsBase = computed(() => {
  let list = requests.value.slice()
  // Filter by view: "By You" = requests made by current user, "To You" = requests NOT made by current user
  if (requestView.value === 'By You') {
    list = list.filter(r => (r.requester || '') === currentUser.value.name)
  } else {
    // "To You" = requests assigned to current user for approval
    list = list.filter(r => (r.requester || '') !== currentUser.value.name)
  }
  // Apply filters
  if (selectedReqType.value !== 'All') list = list.filter(r => inferTypeFromName(r.name) === selectedReqType.value)
  if (selectedReqDept.value !== 'All') list = list.filter(r => (r.department || '') === selectedReqDept.value)
  if (selectedReqStatus.value !== 'All') list = list.filter(r => (r.status || '') === selectedReqStatus.value)
  // Apply search
  const q = search.value.trim().toLowerCase()
  if (q) list = list.filter(r => (r.name || '').toLowerCase().includes(q) || (r.requester || '').toLowerCase().includes(q))
  return list
})

/**
 * Filtered requests for current tab
 */
const filteredRequests = computed(() => {
  if (activeTab.value !== 'Request') return []
  return filteredRequestsBase.value
})

/**
 * Calculate total pages for pagination
 */
const totalPages = computed(() => {
  const count = activeTab.value === 'Request' ? filteredRequests.value.length : filteredFiles.value.length
  return Math.max(1, Math.ceil(count / itemsPerPage.value))
})

/**
 * Paginated files
 */
const paginatedFiles = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage.value
  return filteredFiles.value.slice(start, start + itemsPerPage.value)
})

/**
 * Paginated requests
 */
const paginatedRequests = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage.value
  return filteredRequests.value.slice(start, start + itemsPerPage.value)
})

/**
 * Reset to page 1 when filters change
 */
watch([filteredFiles, () => activeTab.value, requestView, selectedReqType, selectedReqDept, selectedReqStatus], () => {
  currentPage.value = 1
})

/**
 * Pagination navigation
 */
const goToPage = (n: number) => {
  if (n >= 1 && n <= totalPages.value) currentPage.value = n
}

const nextPage = () => {
  if (currentPage.value < totalPages.value) currentPage.value++
}

const prevPage = () => {
  if (currentPage.value > 1) currentPage.value--
}

// ============================================================================
// REQUEST SUMMARY COUNTS
// ============================================================================
const reqCounts = computed(() => {
  const list = filteredRequestsBase.value
  const approved = list.filter(r => r.status === 'Approved').length
  const pending = list.filter(r => r.status === 'Pending').length
  const rejected = list.filter(r => r.status === 'Rejected').length
  return { approved, pending, rejected, total: list.length }
})

// ============================================================================
// TAB COUNTS
// ============================================================================
const tabCounts = computed<Record<string, number>>(() => {
  const allFilesList = applyAccessFilter(applyDeptFilter(applyTypeFilter(baseFilesFilteredBySearch(files.value.slice()))))
  const myFilesList = allFilesList.filter(f => f.uploader === currentUser.value.name)
  return {
    'All Files': allFilesList.length,
    'My Files': myFilesList.length,
    'Request': filteredRequestsBase.value.length,
    'Trash': trashFiles.value.length,
  }
})

// ============================================================================
// MODAL STATE MANAGEMENT
// ============================================================================
const selectedFile = ref<any | null>(null)
const detailModalOpen = ref(false)
const selectedRequest = ref<any | null>(null)
const requestDetailModalOpen = ref(false)

// Dialog states
const shareDialogOpen = ref(false)
const accessDialogOpen = ref(false)
const renameDialogOpen = ref(false)
const requestRenameDialogOpen = ref(false)
const downloadDialogOpen = ref(false)
const manageTagsDialogOpen = ref(false)
const uploadDialogOpen = ref(false)
const manageStatusDialogOpen = ref(false)
const requestAccessDialogOpen = ref(false)
const removeDialogOpen = ref(false)
const restrictedDialogOpen = ref(false)
const restrictedFor = ref<any | null>(null)

// ============================================================================
// FILE ACTIONS
// ============================================================================
/**
 * Open file details modal
 */
const openDetails = (file: any) => {
  selectedFile.value = { ...file }
  detailModalOpen.value = true
}

/**
 * Close file details modal
 */
const closeDetails = () => {
  detailModalOpen.value = false
  selectedFile.value = null
}

/**
 * Open request details modal
 */
const openRequestDetails = (req: any) => {
  selectedRequest.value = req
  requestDetailModalOpen.value = true
}

/**
 * Disable card click (prevent accidental navigation)
 */
const handleCardClick = (_: any, __: MouseEvent) => { }

// ============================================================================
// EDIT DETAILS
// ============================================================================
const editDialogOpen = ref(false)
const dialogFile = ref<any | null>(null)
const tempTags = ref<string[]>([])
const newTag = ref('')
const showTagsPanelEdit = ref(false)

/**
 * Open edit details modal
 */
const openEditDetails = (file: any) => {
  dialogFile.value = { ...file }
  tempTags.value = file.tags ? [...file.tags] : []
  newTag.value = ''
  showTagsPanelEdit.value = false
  editDialogOpen.value = true
}

/**
 * Add tag to edit list
 */
const addTag = () => {
  const t = newTag.value.trim()
  if (!t || tempTags.value.includes(t)) return
  tempTags.value = [...tempTags.value, t]
  newTag.value = ''
}

/**
 * Remove tag from edit list
 */
const removeTag = (tag: string) => {
  tempTags.value = tempTags.value.filter(t => t !== tag)
}

/**
 * Save edited details
 */
const saveEditDetails = () => {
  if (!dialogFile.value) return
  files.value = files.value.map(f =>
    f.id === dialogFile.value.id
      ? {
        ...f,
        description: dialogFile.value.description || '',
        department: dialogFile.value.department,
        access: dialogFile.value.access,
        tags: tempTags.value,
        updated: new Date().toISOString().split('T')[0],
      }
      : f
  )
  showToast('Details saved', 'success')
  editDialogOpen.value = false
  dialogFile.value = null
  tempTags.value = []
  newTag.value = ''
  showTagsPanelEdit.value = false
}

// ============================================================================
// CLIPBOARD UTILITY
// ============================================================================
const copied = ref(false)

/**
 * Copy text to clipboard
 */
const copyToClipboard = async (text: string) => {
  try {
    if (!navigator.clipboard) throw new Error('Clipboard API not available')
    await navigator.clipboard.writeText(text)
    copied.value = true
    setTimeout(() => copied.value = false, 1800)
  } catch {
    try {
      const ta = document.createElement('textarea')
      ta.value = text
      ta.style.position = 'fixed'
      ta.style.left = '-9999px'
      document.body.appendChild(ta)
      ta.select()
      document.execCommand('copy')
      document.body.removeChild(ta)
      copied.value = true
      setTimeout(() => copied.value = false, 1800)
    } catch {
      alert('Copy not supported in this browser.')
    }
  }
}

// ============================================================================
// DIALOG OPENERS
// ============================================================================
const openShare = (file: any) => {
  dialogFile.value = file
  shareDialogOpen.value = true
  copied.value = false
}

const openManageAccess = (file: any) => {
  dialogFile.value = file
  accessDialogOpen.value = true
}

const openRename = (file: any) => {
  dialogFile.value = file
  tempRename.value = file.name
  renameDialogOpen.value = true
}

const openRequestRename = (req: any) => {
  selectedRequest.value = req
  tempRequestRename.value = req.name
  requestRenameDialogOpen.value = true
}

const openDownload = (fileOrReq: any) => {
  dialogFile.value = fileOrReq
  downloadDialogOpen.value = true
}

const openManageTags = (file: any) => {
  dialogFile.value = file
  tempTags.value = file.tags ? [...file.tags] : []
  newTag.value = ''
  manageTagsDialogOpen.value = true
}

// ============================================================================
// ACCESS MANAGEMENT
// ============================================================================
/**
 * Cycle access level: Public -> Private -> Department -> Public
 */
const handleManageAccessConfirm = () => {
  if (!dialogFile.value) return
  const cycle = (val: string) => {
    if (val === 'Public') return 'Private'
    if (val === 'Private') return 'Department'
    return 'Public'
  }
  const newAccess = cycle(dialogFile.value.access || 'Public')
  files.value = files.value.map(f => f.id === dialogFile.value!.id ? { ...f, access: newAccess } : f)
  showToast('Access updated', 'success')
  accessDialogOpen.value = false
}

// ============================================================================
// RENAME ACTIONS
// ============================================================================
const tempRename = ref('')
const tempRequestRename = ref('')

/**
 * Confirm file rename
 */
const handleRenameConfirm = () => {
  if (!dialogFile.value || !tempRename.value) {
    renameDialogOpen.value = false
    return
  }
  files.value = files.value.map(f => f.id === dialogFile.value.id ? { ...f, name: tempRename.value.trim() } : f)
  showToast('File renamed', 'success')
  renameDialogOpen.value = false
}

/**
 * Confirm request rename
 */
const handleRequestRenameConfirm = () => {
  if (!selectedRequest.value || !tempRequestRename.value) {
    requestRenameDialogOpen.value = false
    return
  }
  requests.value = requests.value.map(r => r.id === selectedRequest.value.id ? { ...r, name: tempRequestRename.value.trim() } : r)
  showToast('Request updated', 'success')
  requestRenameDialogOpen.value = false
}

/**
 * Confirm download
 */
const handleDownloadConfirm = () => {
  showToast('Download started', 'success')
  downloadDialogOpen.value = false
}

// ============================================================================
// REQUEST STATUS MANAGEMENT
// ============================================================================
const tempStatusFile = ref<'Pending' | 'Approved' | 'Rejected'>('Pending')
const tempApprover = ref('')

/**
 * Open manage status modal
 */
const openManageStatus = (req: any) => {
  selectedRequest.value = req
  tempStatusFile.value = (req.status as any) || 'Pending'
  tempApprover.value = req.approvedBy || ''
  manageStatusDialogOpen.value = true
}

/**
 * Confirm status change
 */
const handleManageStatusConfirm = () => {
  if (!selectedRequest.value) return
  const decisionAt = new Date().toISOString()
  requests.value = requests.value.map(r =>
    r.id === selectedRequest.value!.id
      ? { ...r, status: tempStatusFile.value, approvedBy: tempApprover.value, decisionAt }
      : r
  )
  if (tempStatusFile.value === 'Approved') showToast('Request approved', 'success')
  if (tempStatusFile.value === 'Rejected') showToast('Request cancelled', 'warning')
  manageStatusDialogOpen.value = false
}

// ============================================================================
// REQUEST ACCESS MANAGEMENT
// ============================================================================
const requestAccess = ref<'Public' | 'Private' | 'Department'>('Department')

/**
 * Open manage request access modal
 */
const openManageAccessRequest = (req: any) => {
  selectedRequest.value = req
  requestAccess.value = (req.access as any) || 'Department'
  requestAccessDialogOpen.value = true
}

/**
 * Confirm request access change
 */
const handleManageAccessRequestConfirm = () => {
  if (!selectedRequest.value) return
  requests.value = requests.value.map(r =>
    r.id === selectedRequest.value!.id ? { ...r, access: requestAccess.value } : r
  )
  showToast('Request access updated', 'success')
  requestAccessDialogOpen.value = false
}

// ============================================================================
// REQUEST STATUS UPDATE (FIXED LOGIC)
// ============================================================================

const updateRequestStatus = (reqId: number, newStatus: 'Pending' | 'Approved' | 'Rejected') => {
  requests.value = requests.value.map(r => {
    if (r.id !== reqId) return r

    // This ensures consistency - only you can approve in "To You" tab
    let approvedBy = r.approvedBy || ''

    if (requestView.value === 'To You') {
      // In "To You" view, when approving/rejecting, it's always the current user
      if (newStatus === 'Approved' || newStatus === 'Rejected') {
        approvedBy = currentUser.value.name
      }
      // If resubmitting to pending, keep existing approver or clear it
      else if (newStatus === 'Pending') {
        approvedBy = ''
      }
    } else {
      // In "By You" view, preserve existing approver or use current user
      if (newStatus === 'Pending') {
        approvedBy = ''
      } else {
        approvedBy = r.approvedBy || currentUser.value.name
      }
    }

    return {
      ...r,
      status: newStatus,
      approvedBy,
      decisionAt: newStatus === 'Pending' ? r.decisionAt : new Date().toISOString(),
    }
  })

  if (newStatus === 'Approved') showToast('Request approved', 'success')
  else if (newStatus === 'Rejected') showToast('Request cancelled', 'warning')
  else showToast('Request resubmitted', 'success')
}

// ============================================================================
// UPLOAD FUNCTIONALITY
// ============================================================================
const uploadDescription = ref('')
const uploadDepartment = ref('HR')
const uploadAccess = ref<'Public' | 'Private' | 'Department'>('Department')
const uploadTags = ref<string[]>([])
const showTagsPanelUpload = ref(false)
const uploadNewTag = ref('')
const uploadFile = ref<File | null>(null)

/**
 * Handle file input change
 */
const onFileChange = (e: Event) => {
  const target = e.target as HTMLInputElement
  uploadFile.value = target.files?.[0] || null
}

/**
 * Add tag to upload form
 */
const addUploadTag = () => {
  const t = uploadNewTag.value.trim()
  if (!t || uploadTags.value.includes(t)) return
  uploadTags.value = [...uploadTags.value, t]
  uploadNewTag.value = ''
}

/**
 * Remove tag from upload form
 */
const removeUploadTag = (tag: string) => {
  uploadTags.value = uploadTags.value.filter(t => t !== tag)
}

/**
 * Handle upload submission
 */
const handleUploadSubmit = () => {
  if (!uploadFile.value) return
  if (uploadFile.value.size > 10 * 1024 * 1024) {
    alert('File size exceeds 10MB limit.')
    return
  }
  const ext = (uploadFile.value.name.split('.').pop() || '').toLowerCase()
  const type = ext === 'pdf' ? 'PDF' :
    (['doc', 'docx'].includes(ext) ? 'Word' :
      (['xls', 'xlsx'].includes(ext) ? 'Excel' :
        (['ppt', 'pptx'].includes(ext) ? 'PPT' : ext.toUpperCase())))
  const newFile = {
    id: Date.now(),
    name: uploadFile.value.name,
    uploader: currentUser.value.name,
    type,
    department: uploadDepartment.value,
    access: uploadAccess.value,
    tags: uploadTags.value.length > 0 ? uploadTags.value : ['Uploaded'],
    created: new Date().toISOString().split('T')[0],
    size: `${Math.round(uploadFile.value.size / 1024)} KB`,
    description: uploadDescription.value,
  }
  files.value = [newFile, ...files.value]
  showToast('File uploaded', 'success')
  uploadDialogOpen.value = false
  uploadDescription.value = ''
  uploadDepartment.value = 'HR'
  uploadAccess.value = 'Department'
  uploadTags.value = []
  uploadNewTag.value = ''
  uploadFile.value = null
}

// ============================================================================
// TRASH ACTIONS
// ============================================================================
const lastDeleted = ref<any | null>(null)
const showDeletionBanner = ref(false)
let deletionBannerTimer: any = null

/**
 * Move file to trash
 */
const moveToTrash = (file: any) => {
  const deletedAt = new Date().toISOString()
  files.value = files.value.filter(f => f.id !== file.id)
  const trashed = { ...file, deletedAt, deletedBy: currentUser.value.name }
  trashFiles.value = [trashed, ...trashFiles.value]
  lastDeleted.value = trashed
  showDeletionBanner.value = true
  if (deletionBannerTimer) clearTimeout(deletionBannerTimer)
  deletionBannerTimer = setTimeout(() => {
    showDeletionBanner.value = false
    lastDeleted.value = null
  }, 8000)
  showToast('Moved to Trash', 'warning')
}

/**
 * Restore file from trash
 */
const restoreFromTrash = (t: any) => {
  const restored = { ...t }
  delete restored.deletedAt
  delete restored.deletedBy
  files.value = [restored, ...files.value]
  trashFiles.value = trashFiles.value.filter(f => f.id !== t.id)
  showToast('File restored', 'success')
}

/**
 * Permanently delete file
 */
const deletePermanently = (t: any) => {
  if (!confirm(`Permanently delete "${t.name}"? This cannot be undone.`)) return
  trashFiles.value = trashFiles.value.filter(f => f.id !== t.id)
  showToast('File deleted permanently', 'error')
}

/**
 * Restore all files from trash
 */
const restoreAll = () => {
  if (!trashFiles.value.length) return
  const restored = trashFiles.value.map(t => {
    const r = { ...t }
    delete r.deletedAt
    delete r.deletedBy
    return r
  })
  files.value = [...restored, ...files.value]
  trashFiles.value = []
  showToast('All files restored', 'success')
}

/**
 * Delete all files from trash
 */
const deleteAll = () => {
  if (!trashFiles.value.length) return
  if (!confirm('Permanently delete all items in Trash? This cannot be undone.')) return
  trashFiles.value = []
  showToast('Trash emptied', 'error')
}

/**
 * Calculate days until permanent deletion
 */
const daysUntilPermanent = (deletedAt: string | Date | undefined) => {
  if (!deletedAt) return 30
  const then = new Date(deletedAt).getTime()
  const now = Date.now()
  const msInDay = 1000 * 60 * 60 * 24
  const remaining = Math.ceil((then + 30 * msInDay - now) / msInDay)
  return remaining > 0 ? remaining : 0
}

// ============================================================================
// FILE VISIBILITY & ACCESS CONTROL
// ============================================================================
/**
 * Check if current user can view file
 */
const canViewFile = (file: any) => {
  if (file.access === 'Public') return true
  if (file.access === 'Private') return file.uploader === currentUser.value.name || currentUser.value.isAdmin
  if (file.access === 'Department') return file.department === currentUser.value.department || currentUser.value.isAdmin
  return false
}

/**
 * Open restricted access dialog
 */
const openRestrictedDialog = (file: any) => {
  restrictedFor.value = file
  restrictedDialogOpen.value = true
}

/**
 * Send access request for restricted file
 */
const sendAccessRequest = () => {
  if (!restrictedFor.value) return
  const newReq = {
    id: Date.now(),
    name: restrictedFor.value.name,
    requester: currentUser.value.name,
    requestedAt: new Date().toISOString().split('T')[0],
    status: 'Pending',
    department: restrictedFor.value.department,
    approvedBy: '',
    access: restrictedFor.value.access,
  }
  requests.value = [newReq, ...requests.value]
  restrictedDialogOpen.value = false
  showToast('Access request sent!', 'success')
}

// ============================================================================
// REMOVE DIALOG HANDLERS
// ============================================================================
const removeTarget = ref<{ kind: 'file' | 'request'; item: any } | null>(null)

/**
 * Open remove dialog for file
 */
const openRemoveDialogForFile = (file: any) => {
  removeTarget.value = { kind: 'file', item: file }
  removeDialogOpen.value = true
}

/**
 * Open remove dialog for request
 */
const openRemoveDialogForRequest = (req: any) => {
  removeTarget.value = { kind: 'request', item: req }
  removeDialogOpen.value = true
}

/**
 * Confirm removal action
 */
const confirmRemove = () => {
  if (!removeTarget.value) return
  if (removeTarget.value.kind === 'file') {
    moveToTrash(removeTarget.value.item)
  } else {
    requests.value = requests.value.filter(r => r.id !== removeTarget.value!.item.id)
    showToast('Request cancelled', 'warning')
  }
  removeDialogOpen.value = false
  removeTarget.value = null
}

// ============================================================================
// DOCUMENT OPEN ACTIONS
// ============================================================================
/**
 * Open document for request (if approved)
 */
const openDocumentForRequest = (req: any) => {
  if (req.status === 'Approved') {
    showToast('Access granted', 'success')
  } else {
    showToast('Document not approved yet', 'warning')
  }
}

/**
 * Open document file (check access first)
 */
const openDocumentFile = (file: any) => {
  if (canViewFile(file)) {
    showToast('Opening document…', 'success')
  } else {
    openRestrictedDialog(file)
  }
}
</script>

<template>

  <Head title="Documents" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="relative min-h-[100vh] p-6 flex-1 border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border">
      <Tabs :model-value="activeTab" @update:model-value="(val: string | number) => activeTab = String(val)" class="w-full">
        <TabsList class="grid w-full grid-cols-4 mb-6">
          <TabsTrigger 
            v-for="item in navLinks" 
            :key="item.title" 
            :value="item.title"
          >
            <div class="flex items-center gap-2 whitespace-nowrap">
              <component :is="item.icon" :size="16" class="shrink-0" />
              <span>{{ item.title }}</span>
              <Badge v-if="tabCounts[item.title] !== undefined" variant="secondary" class="ml-1 shrink-0">
                {{ tabCounts[item.title] }}
              </Badge>
        </div>
          </TabsTrigger>
        </TabsList>

        <!-- All Files Tab -->
        <TabsContent value="All Files">
          <Card class="mb-6">
            <CardHeader>
              <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div>
                  <CardTitle>All Files</CardTitle>
                  <CardDescription>Browse and manage all documents</CardDescription>
          </div>
                <div class="flex gap-2">
                  <Button 
                    @click="aiDialogOpen = true"
                    class="bg-gradient-to-r from-pink-500 to-purple-500 hover:from-pink-600 hover:to-purple-600 text-white"
                  >
                    <Cpu :size="16" class="mr-2" />
                <span class="hidden sm:inline">Smart AI</span>
                <span class="sm:hidden">AI</span>
                </Button>
                  <Button 
                    @click="uploadDialogOpen = true"
                    class="bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white"
                  >
                    <Plus :size="16" class="mr-2" />
                    Upload
                </Button>
                  </div>
          </div>
            </CardHeader>
            <CardContent>
              <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 mb-6">
                <div class="relative flex-1">
                  <Search :size="16" class="absolute left-3 top-1/2 transform -translate-y-1/2 text-muted-foreground" />
                  <Input
                    v-model="search"
                    placeholder="Search Files"
                    class="w-full pl-10"
                  />
          </div>
        </div>

              <!-- Filters Section -->
              <div class="flex flex-col sm:flex-row gap-2 sm:gap-3 mb-4">
            <!-- Type Filter -->
            <DropdownMenu>
              <DropdownMenuTrigger as-child>
                <Button variant="outline"
                  class="flex-1 flex items-center justify-between px-3 sm:px-4 py-2 sm:py-2.5 rounded-lg sm:rounded-xl border border-gray-300 bg-white text-xs sm:text-sm hover:shadow-sm hover:border-blue-300 transition-all">
                  <div class="flex items-center gap-1.5 sm:gap-2 truncate">
                    <span class="font-medium">Type</span>
                    <span class="text-gray-300">|</span>
                    <span class="text-gray-600 ml-1 truncate">{{ selectedType }}</span>
                  </div>
                  <ChevronDown class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-gray-400 flex-shrink-0 ml-2" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent side="bottom" align="end" class="w-[200px] sm:w-[240px]">
                <DropdownMenuLabel>Select File Type</DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem v-for="t in fileTypes" :key="t" @click="selectedType = t"
                  :class="[selectedType === t ? 'bg-blue-50 text-blue-700' : 'hover:bg-gray-50']">
                  {{ t }}
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>

            <!-- Department Filter -->
            <DropdownMenu>
              <DropdownMenuTrigger as-child>
                <Button variant="outline"
                  class="flex-1 flex items-center justify-between px-3 sm:px-4 py-2 sm:py-2.5 rounded-lg sm:rounded-xl border border-gray-300 bg-white text-xs sm:text-sm hover:shadow-sm hover:border-blue-300 transition-all">
                  <div class="flex items-center gap-1.5 sm:gap-2 truncate">
                    <span class="font-medium">Department</span>
                    <span class="text-gray-300">|</span>
                    <span class="text-gray-600 ml-1 truncate">{{ selectedDept }}</span>
                  </div>
                  <ChevronDown class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-gray-400 flex-shrink-0 ml-2" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent side="bottom" align="end" class="w-[200px] sm:w-[240px]">
                <DropdownMenuLabel>Select Department</DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem v-for="d in departments" :key="d" @click="selectedDept = d"
                  :class="[selectedDept === d ? 'bg-blue-50 text-blue-700' : 'hover:bg-gray-50']">
                  {{ d }}
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>

            <!-- Access Filter -->
            <DropdownMenu>
              <DropdownMenuTrigger as-child>
                <Button variant="outline"
                  class="flex-1 flex items-center justify-between px-3 sm:px-4 py-2 sm:py-2.5 rounded-lg sm:rounded-xl border border-gray-300 bg-white text-xs sm:text-sm hover:shadow-sm hover:border-blue-300 transition-all">
                  <div class="flex items-center gap-1.5 sm:gap-2 truncate">
                    <span class="font-medium">Access</span>
                    <span class="text-gray-300">|</span>
                    <span class="text-gray-600 ml-1 truncate">{{ selectedAccess }}</span>
                  </div>
                  <ChevronDown class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-gray-400 flex-shrink-0 ml-2" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent side="bottom" align="end" class="w-[200px] sm:w-[240px]">
                <DropdownMenuLabel>Select Access</DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem @click="selectedAccess = 'All'"
                  :class="[selectedAccess === 'All' ? 'bg-blue-50 text-blue-700' : 'hover:bg-gray-50']">
                  All
                </DropdownMenuItem>
                <DropdownMenuItem @click="selectedAccess = 'Public'"
                  :class="[selectedAccess === 'Public' ? 'bg-blue-50 text-blue-700' : 'hover:bg-gray-50']">
                  Public
                </DropdownMenuItem>
                <DropdownMenuItem @click="selectedAccess = 'Private'"
                  :class="[selectedAccess === 'Private' ? 'bg-blue-50 text-blue-700' : 'hover:bg-gray-50']">
                  Private
                </DropdownMenuItem>
                <DropdownMenuItem @click="selectedAccess = 'Department'"
                  :class="[selectedAccess === 'Department' ? 'bg-blue-50 text-blue-700' : 'hover:bg-gray-50']">
                  Department
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>

          <!-- Active Filter Chips Container  -->
          <div
            class="mt-2 sm:mt-3 rounded-lg sm:rounded-xl border border-gray-200 bg-gray-50/60 px-2.5 sm:px-3 py-2 sm:py-2.5">
            <div class="flex flex-wrap items-center gap-1.5 sm:gap-2">
              <span class="text-[10px] sm:text-xs text-gray-500"
                v-if="selectedType !== 'All' || selectedDept !== 'All' || selectedAccess !== 'All'">
                Active:
              </span>

              <Badge v-if="selectedType !== 'All'"
                :class="`${typeChipColor(selectedType)} text-[10px] sm:text-xs flex items-center gap-1 px-1.5 sm:px-2 py-0.5`">
                {{ selectedType }}
                <button @click="selectedType = 'All'" class="hover:opacity-70">
                  <X class="w-2.5 h-2.5 sm:w-3 sm:h-3" />
                </button>
              </Badge>

              <Badge v-if="selectedDept !== 'All'"
                :class="`${deptChipColor} text-[10px] sm:text-xs flex items-center gap-1 px-1.5 sm:px-2 py-0.5`">
                {{ selectedDept }}
                <button @click="selectedDept = 'All'" class="hover:opacity-70">
                  <X class="w-2.5 h-2.5 sm:w-3 sm:h-3" />
                </button>
              </Badge>

              <Badge v-if="selectedAccess !== 'All'"
                :class="`${accessChipColor(selectedAccess)} text-[10px] sm:text-xs flex items-centxer gap-1 px-1.5 sm:px-2 py-0.5`">
                {{ selectedAccess }}
                <button @click="selectedAccess = 'All'" class="hover:opacity-70">
                  <X class="w-2.5 h-2.5 sm:w-3 sm:h-3" />
                </button>
              </Badge>

              <Badge v-if="selectedType !== 'All' || selectedDept !== 'All' || selectedAccess !== 'All'"
                class="bg-gray-100 text-gray-700 text-[10px] sm:text-xs flex items-center gap-1 px-1.5 sm:px-2 py-0.5 cursor-pointer hover:bg-gray-200"
                @click="selectedType = 'All'; selectedDept = 'All'; selectedAccess = 'All'">
                <X class="w-2.5 h-2.5 sm:w-3 sm:h-3" /> Clear all
              </Badge>
            </div>
          </div>

              <!-- File Grid -->
              <div v-if="paginatedFiles.length"
                class="grid gap-4 sm:gap-5 grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 mt-6">
                <div v-for="file in paginatedFiles" :key="file.id"
                  class="relative bg-white border border-gray-200 rounded-xl sm:rounded-2xl p-4 sm:p-5 shadow-sm hover:shadow-lg transform hover:scale-[1.02] transition-all">
                  <!-- Dropdown Menu -->
                  <div class="absolute right-2 sm:right-3 top-2 sm:top-3 flex items-center gap-1 z-10">
                    <DropdownMenu>
                      <DropdownMenuTrigger as-child>
                        <Button variant="ghost" size="icon"
                          class="h-7 w-7 sm:h-8 sm:w-8 p-1.5 rounded-md hover:bg-gray-100 transition-all">
                          <MoreVertical class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-gray-500" />
                        </Button>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent side="bottom" align="start" :sideOffset="4" class="w-[200px] sm:w-[240px]">
                        <DropdownMenuItem @click="openEditDetails(file)">
                          <Edit3 class="w-3.5 h-3.5 sm:w-4 sm:h-4 mr-2 text-gray-500" /> Edit Details
                        </DropdownMenuItem>
                        <DropdownMenuItem @click="openRename(file)">
                          <Edit3 class="w-3.5 h-3.5 sm:w-4 sm:h-4 mr-2 text-gray-500" /> Rename
                        </DropdownMenuItem>
                        <DropdownMenuItem @click="openDownload(file)">
                          <Download class="w-3.5 h-3.5 sm:w-4 sm:h-4 mr-2 text-gray-500" /> Download
                        </DropdownMenuItem>
                        <DropdownMenuItem @click="openDetails(file)">
                          <Info class="w-3.5 h-3.5 sm:w-4 sm:h-4 mr-2 text-gray-500" /> Details & Activity
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem class="text-red-600" @click.stop="openRemoveDialogForFile(file)">
                          <Trash2Icon class="w-3.5 h-3.5 sm:w-4 sm:h-4 mr-2" /> Remove
                        </DropdownMenuItem>
                      </DropdownMenuContent>
                    </DropdownMenu>
        </div>

                  <!-- Card body -->
                  <div>
                    <div
                      :class="['mx-auto w-16 h-16 sm:w-20 sm:h-20 flex items-center justify-center rounded-lg sm:rounded-xl text-white font-bold text-xs sm:text-sm shadow-md mb-3 sm:mb-4', typeColor(file.type)]">
                      {{ file.type }}
                    </div>

                    <h3 class="text-xs sm:text-sm font-semibold text-gray-900 text-center truncate" :title="file.name">
                      {{ file.name }}
                    </h3>

                    <div class="flex flex-col items-center mt-2">
                      <div
                        class="flex flex-wrap justify-center gap-x-1.5 sm:gap-x-2 gap-y-1 text-[10px] sm:text-xs text-gray-600">
                        <span class="whitespace-nowrap">{{ file.department }}</span>
                        <span class="text-gray-300">|</span>
                        <span class="whitespace-nowrap inline-flex items-center gap-1 sm:gap-1.5">
                          <component :is="accessIconComponent(file.access)" class="w-3 h-3 sm:w-3.5 sm:h-3.5"
                            :class="accessIconColor(file.access)" />
                          <span class="font-medium">{{ file.access }}</span>
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div v-else class="text-center text-gray-500 py-8 sm:py-10 text-sm sm:text-base">
                No files found. Try adjusting filters or uploading a new document.
              </div>

              <!-- Pagination -->
              <div v-if="totalPages > 1" class="flex justify-center mt-6 sm:mt-8 pb-4 sm:pb-6">
                <nav
                  class="inline-flex items-center bg-white border border-gray-200 rounded-full shadow-md overflow-hidden">
                  <Button variant="ghost" size="icon" @click="prevPage" :disabled="currentPage === 1"
                    class="h-8 w-8 sm:h-9 sm:w-9 disabled:opacity-50 hover:bg-blue-50 transition-all">
                    <ChevronLeft class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-gray-600" />
                  </Button>
                  <div class="flex items-center gap-0.5 sm:gap-1 px-1 sm:px-2 py-1 sm:py-2">
                    <button v-for="n in totalPages" :key="n" @click="goToPage(n)" :class="[
                      'w-7 h-7 sm:w-8 sm:h-8 flex items-center justify-center font-semibold text-xs sm:text-sm rounded-lg transition-all',
                      currentPage === n
                        ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-md'
                        : 'text-gray-600 hover:bg-blue-50'
                    ]">
                      {{ n }}
                    </button>
                  </div>
                  <Button variant="ghost" size="icon" @click="nextPage" :disabled="currentPage === totalPages"
                    class="h-8 w-8 sm:h-9 sm:w-9 disabled:opacity-50 hover:bg-blue-50 transition-all">
                    <ChevronRight class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-gray-600" />
                  </Button>
                </nav>
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <!-- My Files Tab -->
        <TabsContent value="My Files">
          <Card>
            <CardHeader>
              <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div>
                  <CardTitle>My Files</CardTitle>
                  <CardDescription>Files uploaded by you</CardDescription>
                </div>
                <Button 
                  @click="uploadDialogOpen = true"
                  class="bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white"
                >
                  <Plus :size="16" class="mr-2" />
                  Upload
                </Button>
              </div>
            </CardHeader>
            <CardContent>
              <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 mb-6">
                <div class="relative flex-1">
                  <Search :size="16" class="absolute left-3 top-1/2 transform -translate-y-1/2 text-muted-foreground" />
                  <Input
                    v-model="search"
                    placeholder="Search my files"
                    class="w-full pl-10"
                  />
                </div>
              </div>
              <!-- Filters Section -->
              <div class="flex flex-col sm:flex-row gap-2 sm:gap-3 mb-4">
                <!-- Type Filter -->
                <DropdownMenu>
                  <DropdownMenuTrigger as-child>
                    <Button variant="outline" class="flex-1">
                      <span class="font-medium">Type</span>
                      <span class="text-gray-300 mx-2">|</span>
                      <span class="text-gray-600">{{ selectedType }}</span>
                      <ChevronDown class="w-4 h-4 ml-2" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent>
                    <DropdownMenuLabel>Select File Type</DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem v-for="t in fileTypes" :key="t" @click="selectedType = t">
                      {{ t }}
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
                <!-- Department Filter -->
                <DropdownMenu>
                  <DropdownMenuTrigger as-child>
                    <Button variant="outline" class="flex-1">
                      <span class="font-medium">Department</span>
                      <span class="text-gray-300 mx-2">|</span>
                      <span class="text-gray-600">{{ selectedDept }}</span>
                      <ChevronDown class="w-4 h-4 ml-2" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent>
                    <DropdownMenuLabel>Select Department</DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem v-for="d in departments" :key="d" @click="selectedDept = d">
                      {{ d }}
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
                <!-- Access Filter -->
                <DropdownMenu>
                  <DropdownMenuTrigger as-child>
                    <Button variant="outline" class="flex-1">
                      <span class="font-medium">Access</span>
                      <span class="text-gray-300 mx-2">|</span>
                      <span class="text-gray-600">{{ selectedAccess }}</span>
                      <ChevronDown class="w-4 h-4 ml-2" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent>
                    <DropdownMenuLabel>Select Access</DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem v-for="a in accesses.filter(a => a !== 'All')" :key="a" @click="selectedAccess = a as any">
                      {{ a }}
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              </div>
              <!-- File grid - same as All Files but filtered for current user -->
              <div v-if="paginatedFiles.length"
                class="grid gap-4 sm:gap-5 grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 mt-6">
                <div v-for="file in paginatedFiles" :key="file.id"
                  class="relative bg-white border border-gray-200 rounded-xl sm:rounded-2xl p-4 sm:p-5 shadow-sm hover:shadow-lg transform hover:scale-[1.02] transition-all">
                  <!-- Same card structure as All Files -->
                  <div class="absolute right-2 sm:right-3 top-2 sm:top-3 flex items-center gap-1 z-10">
                    <DropdownMenu>
                      <DropdownMenuTrigger as-child>
                        <Button variant="ghost" size="icon" class="h-7 w-7 sm:h-8 sm:w-8 p-1.5 rounded-md hover:bg-gray-100 transition-all">
                          <MoreVertical class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-gray-500" />
                        </Button>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent side="bottom" align="start" :sideOffset="4" class="w-[200px] sm:w-[240px]">
                        <DropdownMenuItem @click="openEditDetails(file)">
                          <Edit3 class="w-3.5 h-3.5 sm:w-4 sm:h-4 mr-2 text-gray-500" /> Edit Details
                        </DropdownMenuItem>
                        <DropdownMenuItem @click="openRename(file)">
                          <Edit3 class="w-3.5 h-3.5 sm:w-4 sm:h-4 mr-2 text-gray-500" /> Rename
                        </DropdownMenuItem>
                        <DropdownMenuItem @click="openDownload(file)">
                          <Download class="w-3.5 h-3.5 sm:w-4 sm:h-4 mr-2 text-gray-500" /> Download
                        </DropdownMenuItem>
                        <DropdownMenuItem @click="openDetails(file)">
                          <Info class="w-3.5 h-3.5 sm:w-4 sm:h-4 mr-2 text-gray-500" /> Details & Activity
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem class="text-red-600" @click.stop="openRemoveDialogForFile(file)">
                          <Trash2Icon class="w-3.5 h-3.5 sm:w-4 sm:h-4 mr-2" /> Remove
                        </DropdownMenuItem>
                      </DropdownMenuContent>
                    </DropdownMenu>
                  </div>
                  <div>
                    <div :class="['mx-auto w-16 h-16 sm:w-20 sm:h-20 flex items-center justify-center rounded-lg sm:rounded-xl text-white font-bold text-xs sm:text-sm shadow-md mb-3 sm:mb-4', typeColor(file.type)]">
                      {{ file.type }}
                    </div>
                    <h3 class="text-xs sm:text-sm font-semibold text-gray-900 text-center truncate" :title="file.name">
                      {{ file.name }}
                    </h3>
                    <div class="flex flex-col items-center mt-2">
                      <div class="flex flex-wrap justify-center gap-x-1.5 sm:gap-x-2 gap-y-1 text-[10px] sm:text-xs text-gray-600">
                        <span class="whitespace-nowrap">{{ file.department }}</span>
                        <span class="text-gray-300">|</span>
                        <span class="whitespace-nowrap inline-flex items-center gap-1 sm:gap-1.5">
                          <component :is="accessIconComponent(file.access)" class="w-3 h-3 sm:w-3.5 sm:h-3.5" :class="accessIconColor(file.access)" />
                          <span class="font-medium">{{ file.access }}</span>
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <div v-else class="text-center text-gray-500 py-8 sm:py-10 text-sm sm:text-base">
                No files found. Try adjusting filters or uploading a new document.
              </div>
              <!-- Pagination -->
              <div v-if="totalPages > 1" class="flex justify-center mt-6 sm:mt-8 pb-4 sm:pb-6">
                <nav class="inline-flex items-center bg-white border border-gray-200 rounded-full shadow-md overflow-hidden">
                  <Button variant="ghost" size="icon" @click="prevPage" :disabled="currentPage === 1" class="h-8 w-8 sm:h-9 sm:w-9 disabled:opacity-50 hover:bg-blue-50 transition-all">
                    <ChevronLeft class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-gray-600" />
                  </Button>
                  <div class="flex items-center gap-0.5 sm:gap-1 px-1 sm:px-2 py-1 sm:py-2">
                    <button v-for="n in totalPages" :key="n" @click="goToPage(n)" :class="[
                      'w-7 h-7 sm:w-8 sm:h-8 flex items-center justify-center font-semibold text-xs sm:text-sm rounded-lg transition-all',
                      currentPage === n ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-md' : 'text-gray-600 hover:bg-blue-50'
                    ]">
                      {{ n }}
                    </button>
                  </div>
                  <Button variant="ghost" size="icon" @click="nextPage" :disabled="currentPage === totalPages" class="h-8 w-8 sm:h-9 sm:w-9 disabled:opacity-50 hover:bg-blue-50 transition-all">
                    <ChevronRight class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-gray-600" />
                  </Button>
                </nav>
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <!-- Request Tab -->
        <TabsContent value="Request">
          <Card>
            <CardHeader>
              <CardTitle>Document Requests</CardTitle>
              <CardDescription>Manage document access requests</CardDescription>
            </CardHeader>
            <CardContent>
              <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 mb-6">
                <div class="relative flex-1">
                  <Search :size="16" class="absolute left-3 top-1/2 transform -translate-y-1/2 text-muted-foreground" />
                  <Input
                    v-model="search"
                    placeholder="Search Requests"
                    class="w-full pl-10"
                  />
                </div>
              </div>

              <!-- Sub-tabs for Request -->
              <div class="flex flex-wrap gap-2 items-center mb-4">
                <Button 
                  @click="requestView = 'To You'" 
                  :variant="requestView === 'To You' ? 'default' : 'outline'"
                  :class="requestView === 'To You' ? 'bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white' : ''"
                >
                  TO YOU
                </Button>
                <Button 
                  @click="requestView = 'By You'"
                  :variant="requestView === 'By You' ? 'default' : 'outline'"
                  :class="requestView === 'By You' ? 'bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white' : ''"
                >
                  BY YOU
                </Button>
              </div>

              <!-- Request Summary Counts -->
              <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 sm:gap-3 mb-6">
                <div class="rounded-lg sm:rounded-xl border border-green-200 bg-green-50 p-2.5 sm:p-3 flex items-center justify-between">
                  <div>
                    <div class="text-[10px] sm:text-[11px] text-green-700">Approved</div>
                    <div class="text-lg sm:text-xl font-bold text-green-700">{{ reqCounts.approved }}</div>
                  </div>
                  <div class="w-1.5 h-1.5 sm:w-2 sm:h-2 rounded-full bg-green-500"></div>
                </div>
                <div class="rounded-lg sm:rounded-xl border border-amber-200 bg-amber-50 p-2.5 sm:p-3 flex items-center justify-between">
                  <div>
                    <div class="text-[10px] sm:text-[11px] text-amber-700">Pending</div>
                    <div class="text-lg sm:text-xl font-bold text-amber-700">{{ reqCounts.pending }}</div>
                  </div>
                  <div class="w-1.5 h-1.5 sm:w-2 sm:h-2 rounded-full bg-amber-500"></div>
                </div>
                <div class="rounded-lg sm:rounded-xl border border-red-200 bg-red-50 p-2.5 sm:p-3 flex items-center justify-between">
                  <div>
                    <div class="text-[10px] sm:text-[11px] text-red-700">Rejected</div>
                    <div class="text-lg sm:text-xl font-bold text-red-700">{{ reqCounts.rejected }}</div>
                  </div>
                  <div class="w-1.5 h-1.5 sm:w-2 sm:h-2 rounded-full bg-red-500"></div>
                </div>
              </div>

              <!-- Request Filters -->
              <div class="flex flex-col sm:flex-row gap-2 sm:gap-3 mb-4">
                <!-- Type Filter -->
                <DropdownMenu>
                  <DropdownMenuTrigger as-child>
                    <Button variant="outline" class="flex-1">
                      <span class="font-medium">Type</span>
                      <span class="text-gray-300 mx-2">|</span>
                      <span class="text-gray-600">{{ selectedReqType }}</span>
                      <ChevronDown class="w-4 h-4 ml-2" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent>
                    <DropdownMenuLabel>Select Type</DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem v-for="t in requestTypes" :key="t" @click="selectedReqType = t">
                      {{ t }}
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
                <!-- Department Filter -->
                <DropdownMenu>
                  <DropdownMenuTrigger as-child>
                    <Button variant="outline" class="flex-1">
                      <span class="font-medium">Department</span>
                      <span class="text-gray-300 mx-2">|</span>
                      <span class="text-gray-600">{{ selectedReqDept }}</span>
                      <ChevronDown class="w-4 h-4 ml-2" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent>
                    <DropdownMenuLabel>Select Department</DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem v-for="d in departments" :key="d" @click="selectedReqDept = d">
                      {{ d }}
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
                <!-- Status Filter -->
                <DropdownMenu>
                  <DropdownMenuTrigger as-child>
                    <Button variant="outline" class="flex-1">
                      <span class="font-medium">Status</span>
                      <span class="text-gray-300 mx-2">|</span>
                      <span class="text-gray-600">{{ selectedReqStatus }}</span>
                      <ChevronDown class="w-4 h-4 ml-2" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent>
                    <DropdownMenuLabel>Select Status</DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem v-for="s in requestStatuses" :key="s" @click="selectedReqStatus = s as any">
                      {{ s }}
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              </div>

              <!-- Request Grid -->
            <div>
              <div v-if="paginatedRequests.length"
                class="grid gap-4 sm:gap-5 grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">

                <!-- Request Cards -->
                <div v-for="req in paginatedRequests" :key="req.id"
                  class="relative bg-white border border-gray-200 rounded-xl sm:rounded-2xl p-4 sm:p-5 shadow-sm hover:shadow-lg transform hover:scale-[1.02] transition-all">

                  <!-- 3-dot menu (context-aware) -->
                  <div class="absolute right-2 sm:right-3 top-2 sm:top-3 flex items-center gap-1 z-10">
                    <DropdownMenu>
                      <DropdownMenuTrigger as-child>
                        <Button variant="ghost" size="icon"
                          class="h-7 w-7 sm:h-8 sm:w-8 p-1.5 rounded-md hover:bg-gray-100 transition-all">
                          <MoreVertical class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-gray-500" />
                        </Button>
                      </DropdownMenuTrigger>

                      <DropdownMenuContent align="end" class="w-[200px] sm:w-[240px]">
                        <!-- TO YOU (no cancel/resubmit here) -->
                        <template v-if="requestView === 'To You'">
                          <template v-if="req.status === 'Pending'">
                            <DropdownMenuItem @click.stop="openRequestDetails(req)">
                              <Info class="w-3.5 h-3.5 sm:w-4 sm:h-4 mr-2" /> View Request Info
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem class="text-red-600" @click.stop="openRemoveDialogForRequest(req)">
                              <Trash2Icon class="w-3.5 h-3.5 sm:w-4 sm:h-4 mr-2" /> Remove
                            </DropdownMenuItem>
                          </template>

                          <template v-else-if="req.status === 'Approved'">
                            <DropdownMenuItem @click.stop="openRequestDetails(req)">
                              <Info class="w-3.5 h-3.5 sm:w-4 sm:h-4 mr-2" /> View Document Info
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem class="text-red-600" @click.stop="openRemoveDialogForRequest(req)">
                              <Trash2Icon class="w-3.5 h-3.5 sm:w-4 sm:h-4 mr-2" /> Remove
                            </DropdownMenuItem>
                          </template>

                          <template v-else-if="req.status === 'Rejected'">
                            <DropdownMenuItem @click.stop="openRequestDetails(req)">
                              <Info class="w-3.5 h-3.5 sm:w-4 sm:h-4 mr-2" /> View Request Info
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem class="text-red-600" @click.stop="openRemoveDialogForRequest(req)">
                              <Trash2Icon class="w-3.5 h-3.5 sm:w-4 sm:h-4 mr-2" /> Remove
                            </DropdownMenuItem>
                          </template>
                        </template>

                        <!-- BY YOU (cancel/resubmit live here) -->
                        <template v-else>
                          <template v-if="req.status === 'Pending'">
                            <DropdownMenuItem class="text-amber-700" @click.stop="openRemoveDialogForRequest(req)">
                              <Trash2Icon class="w-3.5 h-3.5 sm:w-4 sm:h-4 mr-2" /> Cancel Request
                            </DropdownMenuItem>
                            <DropdownMenuItem @click.stop="openRequestDetails(req)">
                              <Info class="w-3.5 h-3.5 sm:w-4 sm:h-4 mr-2" /> View Request Info
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem class="text-red-600" @click.stop="openRemoveDialogForRequest(req)">
                              <Trash2Icon class="w-3.5 h-3.5 sm:w-4 sm:h-4 mr-2" /> Remove
                            </DropdownMenuItem>
                          </template>

                          <template v-else-if="req.status === 'Approved'">
                            <DropdownMenuItem @click.stop="openRequestDetails(req)">
                              <Info class="w-3.5 h-3.5 sm:w-4 sm:h-4 mr-2" /> View Document Info
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem class="text-red-600" @click.stop="openRemoveDialogForRequest(req)">
                              <Trash2Icon class="w-3.5 h-3.5 sm:w-4 sm:h-4 mr-2" /> Remove
                            </DropdownMenuItem>
                          </template>

                          <template v-else-if="req.status === 'Rejected'">
                            <DropdownMenuItem class="text-emerald-700"
                              @click.stop="updateRequestStatus(req.id, 'Pending')">
                              <RefreshCw class="w-3.5 h-3.5 sm:w-4 sm:h-4 mr-2" /> Resubmit Request
                            </DropdownMenuItem>
                            <DropdownMenuItem @click.stop="openRequestDetails(req)">
                              <Info class="w-3.5 h-3.5 sm:w-4 sm:h-4 mr-2" /> View Request Info
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem class="text-red-600" @click.stop="openRemoveDialogForRequest(req)">
                              <Trash2Icon class="w-3.5 h-3.5 sm:w-4 sm:h-4 mr-2" /> Remove
                            </DropdownMenuItem>
                          </template>
                        </template>
                      </DropdownMenuContent>
                    </DropdownMenu>
                  </div>

                  <!-- Card body -->
                  <div>
                    <div
                      :class="['mx-auto w-16 h-16 sm:w-20 sm:h-20 flex items-center justify-center rounded-lg sm:rounded-xl text-white font-bold text-xs sm:text-sm shadow-md mb-3 sm:mb-4', typeColor(inferTypeFromName(req.name))]">
                      {{ inferTypeFromName(req.name) === 'Word' ? 'WORD' : inferTypeFromName(req.name) }}
                    </div>

                    <h3 class="text-xs sm:text-sm font-semibold text-gray-900 text-center truncate" :title="req.name">
                      {{ req.name }}
                    </h3>

                    <div
                      class="flex items-center justify-center gap-1.5 sm:gap-2 text-[10px] sm:text-xs text-gray-600 mt-2">
                      <span class="whitespace-nowrap">{{ req.department }}</span>
                      <span class="w-0.5 h-0.5 sm:w-1 sm:h-1 rounded-full bg-gray-300"></span>
                      <Badge
                        :class="`${statusColor(req.status)} text-[9px] sm:text-[11px] font-semibold px-2 sm:px-2.5 py-0.5`">
                        {{ req.status }}
                      </Badge>
                    </div>
                  </div>
                </div>
              </div>

              <div v-else class="text-center text-gray-500 py-8 sm:py-10 text-sm sm:text-base">
                No matches for '{{ search }}'. Try removing filters.
              </div>

              <!-- Pagination -->
              <div v-if="totalPages > 1" class="flex justify-center mt-6 sm:mt-8 pb-4 sm:pb-6">
                <nav
                  class="inline-flex items-center bg-white border border-gray-200 rounded-full shadow-md overflow-hidden">
                  <Button variant="ghost" size="icon" @click="prevPage" :disabled="currentPage === 1"
                    class="h-8 w-8 sm:h-9 sm:w-9 disabled:opacity-50 hover:bg-blue-50 transition-all">
                    <ChevronLeft class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-gray-600" />
                  </Button>
                  <div class="flex items-center gap-0.5 sm:gap-1 px-1 sm:px-2 py-1 sm:py-2">
                    <button v-for="n in totalPages" :key="n" @click="goToPage(n)" :class="[
                      'w-7 h-7 sm:w-8 sm:h-8 flex items-center justify-center font-semibold text-xs sm:text-sm rounded-lg transition-all',
                      currentPage === n
                        ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-md'
                        : 'text-gray-600 hover:bg-blue-50'
                    ]">
                      {{ n }}
                    </button>
                  </div>
                  <Button variant="ghost" size="icon" @click="nextPage" :disabled="currentPage === totalPages"
                    class="h-8 w-8 sm:h-9 sm:w-9 disabled:opacity-50 hover:bg-blue-50 transition-all">
                    <ChevronRight class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-gray-600" />
                  </Button>
                </nav>
              </div>
            </div>
            </CardContent>
          </Card>
        </TabsContent>

        <!-- Trash Tab -->
        <TabsContent value="Trash">
          <Card>
            <CardHeader>
              <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div>
                  <CardTitle>Recycle Bin</CardTitle>
                  <CardDescription>Files here will be permanently deleted after 30 days</CardDescription>
                  </div>
                <div class="flex gap-2">
                  <Button variant="secondary" @click="restoreAll">
                    Restore All
                  </Button>
                  <Button variant="destructive" @click="deleteAll">
                    Delete All
                  </Button>
                </div>
              </div>
            </CardHeader>
            <CardContent>
              <div v-if="trashFiles.length"
                class="grid gap-4 sm:gap-5 grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                <div v-for="t in trashFiles" :key="t.id"
                  class="relative bg-white border border-gray-200 rounded-xl sm:rounded-2xl p-4 sm:p-5 shadow-sm hover:shadow-lg transform hover:scale-[1.02] transition-all">
                  <div class="absolute right-2 sm:right-3 top-2 sm:top-3">
                    <DropdownMenu>
                      <DropdownMenuTrigger as-child>
                        <Button variant="ghost" size="icon"
                          class="h-7 w-7 sm:h-8 sm:w-8 p-1.5 rounded-md hover:bg-gray-100 transition-all">
                          <MoreVertical class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-gray-500" />
                        </Button>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent align="end" class="w-[200px] sm:w-[220px]">
                        <DropdownMenuItem class="text-emerald-600" @click="restoreFromTrash(t)">
                          Restore
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuLabel>Details</DropdownMenuLabel>
                        <DropdownMenuItem class="flex-col items-start">
                          <span class="text-[10px] sm:text-xs text-gray-500">Deleted by</span>
                          <span class="text-xs sm:text-sm text-gray-800">{{ t.deletedBy || '—' }}</span>
                        </DropdownMenuItem>
                        <DropdownMenuItem class="flex-col items-start">
                          <span class="text-[10px] sm:text-xs text-gray-500">Deleted at</span>
                          <span class="text-xs sm:text-sm text-gray-800">{{ new Date(t.deletedAt).toLocaleString()
                          }}</span>
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem class="text-rose-600" @click="deletePermanently(t)">
                          Delete Permanently
                        </DropdownMenuItem>
                      </DropdownMenuContent>
                    </DropdownMenu>
                  </div>

                  <div
                    :class="['mx-auto w-16 h-16 sm:w-20 sm:h-20 flex items-center justify-center rounded-lg sm:rounded-xl text-white font-bold text-xs sm:text-sm shadow-md', typeColor(t.type)]">
                    {{ t.type }}
                  </div>

                  <div class="text-center mt-3 space-y-1">
                    <div class="font-semibold text-gray-800 truncate text-xs sm:text-sm" :title="t.name">
                      <span class="inline-flex items-center gap-1">
                        {{ t.name }}
                        <span v-if="lastDeleted && lastDeleted.id === t.id"
                          class="inline-flex items-center text-[9px] sm:text-[11px] text-blue-600">
                          <Clock class="w-3 h-3 sm:w-3.5 sm:h-3.5 mr-0.5" /> Undo
                        </span>
                      </span>
                    </div>

                    <div class="text-[10px] sm:text-xs text-gray-500">Deleted {{ new
                      Date(t.deletedAt).toLocaleDateString() }}</div>
                    <div class="text-[10px] sm:text-xs font-semibold text-rose-600 mt-2">
                      {{ daysUntilPermanent(t.deletedAt) }} day{{ daysUntilPermanent(t.deletedAt) !== 1 ? 's' : '' }}
                      left
                    </div>
                  </div>
                </div>
              </div>

              <div v-else class="text-center text-gray-500 py-10 sm:py-12">
                <Trash2Icon class="w-12 h-12 sm:w-16 sm:h-16 mx-auto text-gray-300 mb-2 sm:mb-3" />
                <p class="text-base sm:text-lg">Trash is empty.</p>
              </div>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
        </div>

        <!-- ========================================================================
  AI DIALOG 
======================================================================= -->
        <Dialog v-model:open="aiDialogOpen">
          <DialogContent class="max-w-[98vw] sm:max-w-4xl h-[95vh] sm:h-[90vh] flex flex-col p-0 gap-0 overflow-hidden">
            <DialogHeader class="sticky top-0 z-10 px-5 sm:px-6 pt-5 sm:pt-6 pb-4 sm:pb-5 border-b bg-gradient-to-r from-blue-50 via-indigo-50 to-purple-50">
              <div class="flex justify-between items-center">
                <DialogTitle class="text-lg sm:text-xl font-bold text-gray-900 flex items-center gap-2.5">
                  <div class="p-2 bg-gradient-to-br from-blue-500 to-purple-600 rounded-lg shadow-md">
                    <Cpu class="w-5 h-5 sm:w-6 sm:h-6 text-white" />
                  </div>
                  <span class="bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 bg-clip-text text-transparent">
                    Smart AI Assistant
                  </span>
                </DialogTitle>
              </div>
            </DialogHeader>

            <div class="flex-1 overflow-y-auto bg-gradient-to-b from-gray-50 to-white">
              <div class="flex-1 flex flex-col min-w-0 px-5 sm:px-6 py-4 sm:py-6">
                <div class="space-y-4 sm:space-y-5">
                  <div v-for="(msg, i) in aiMessages" :key="i" :class="[
                    'max-w-[85%] px-4 sm:px-5 py-3 sm:py-3.5 rounded-2xl text-sm sm:text-base leading-relaxed transition-all duration-300',
                    msg.sender === 'user'
                      ? 'ml-auto bg-gradient-to-br from-blue-500 to-blue-600 text-white rounded-br-md shadow-lg'
                      : 'mr-auto bg-white text-gray-800 border-2 border-gray-200 rounded-bl-md shadow-md'
                  ]">
                    {{ msg.text }}
                  </div>

                  <div v-if="aiLoading"
                    class="mr-auto flex items-center gap-3 bg-gradient-to-r from-blue-50 to-indigo-50 text-blue-700 px-4 sm:px-5 py-3 rounded-2xl rounded-bl-md border-2 border-blue-200 shadow-md">
                    <Loader2 class="w-4 h-4 sm:w-5 sm:h-5 animate-spin" />
                    <span class="text-sm sm:text-base font-medium">AI is analyzing your query...</span>
                  </div>

                  <div v-if="aiSearchResults.length > 0 && !aiLoading" class="space-y-5 mt-6">
                    <div class="text-base sm:text-lg font-bold text-gray-800 flex items-center gap-2">
                      <div class="w-1 h-6 bg-gradient-to-b from-blue-500 to-purple-600 rounded-full"></div>
                      Found {{ aiSearchResults.length }} relevant files
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5">
                      <div v-for="result in aiSearchResults" :key="result.id || result.name"
                        class="bg-white rounded-2xl border-2 border-gray-200 p-5 text-center hover:shadow-2xl hover:border-blue-300 transition-all duration-300 flex flex-col group">
                        <div class="flex-grow">
                          <div :class="[
                            'mx-auto w-20 h-20 sm:w-24 sm:h-24 flex items-center justify-center rounded-2xl text-white font-bold text-sm sm:text-base shadow-lg mb-4 group-hover:scale-110 transition-transform duration-300',
                            typeColor(result.type),
                          ]">
                            {{ result.type === 'Word' ? 'WORD' : result.type }}
                          </div>

                          <h3 class="text-sm sm:text-base font-bold text-gray-900 text-center truncate px-2"
                            :title="result.name">
                            {{ result.name }}
                          </h3>

                          <div class="flex items-center justify-center gap-2 text-xs sm:text-sm text-gray-600 mt-3">
                            <span class="font-medium">{{ result.department }}</span>
                            <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                            <Badge :class="accessChipColor(result.access) + ' text-[10px] sm:text-xs font-bold px-2.5 py-1 flex items-center gap-1'">
                              <component :is="accessIconComponent(result.access)" class="w-3 h-3" />
                              {{ result.access }}
                            </Badge>
                          </div>
                        </div>

                        <div class="mt-4 pt-4 border-t-2 border-gray-100">
                          <div class="text-xs sm:text-sm text-gray-600 mb-2">
                            Confidence: <span class="font-bold text-gray-900">{{ result.confidence }}%</span>
                          </div>
                          <div class="w-full bg-gray-200 rounded-full h-2 overflow-hidden">
                            <div :class="[
                              'h-2 rounded-full transition-all duration-500',
                              result.confidence >= 80 ? 'bg-gradient-to-r from-green-400 to-green-600' :
                                result.confidence >= 60 ? 'bg-gradient-to-r from-yellow-400 to-yellow-600' :
                                  'bg-gradient-to-r from-orange-400 to-orange-600'
                            ]" :style="{ width: result.confidence + '%' }"></div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="sticky bottom-0 z-10 bg-white border-t-2 shadow-2xl">
              <div v-if="recentSearches.length > 0" class="bg-gradient-to-r from-gray-50 to-blue-50 px-5 sm:px-6 py-3 border-b">
                <div class="text-xs sm:text-sm font-bold text-gray-700 mb-2">Recent Searches</div>
                <div class="flex flex-wrap gap-2">
                  <div v-for="(search, index) in recentSearches" :key="index" @click="aiPrompt = search; sendToAI()"
                    class="text-xs sm:text-sm text-blue-600 hover:text-blue-800 cursor-pointer px-3 py-1.5 bg-white rounded-full border-2 border-blue-200 hover:bg-blue-50 hover:border-blue-400 transition-all duration-200 shadow-sm hover:shadow-md font-medium">
                    {{ search }}
                  </div>
                </div>
              </div>

              <div class="flex flex-col md:flex-row items-stretch md:items-center gap-3 px-5 sm:px-6 py-4 sm:py-5 bg-white">
                <div class="flex-1 flex items-center bg-white rounded-xl border border-input focus-within:ring-2 focus-within:ring-ring focus-within:ring-offset-2 transition-all duration-200 min-w-0 relative overflow-hidden">
                  <Input v-model="aiPrompt" @keyup.enter="sendToAI"
                    :placeholder="searchMode === 'keywords' ? 'Enter keywords to search...' : 'Describe what you\'re looking for...'"
                    class="flex-1 h-12 border-0 bg-white text-sm sm:text-base text-gray-900 px-4 focus-visible:ring-0 focus-visible:ring-offset-0 pr-14 placeholder:text-gray-500 font-medium" />
                  <Button @click="sendToAI" :disabled="aiLoading" size="icon"
                    class="absolute right-2 h-9 w-9 bg-primary hover:bg-primary/90 rounded-lg">
                    <Loader2 v-if="aiLoading" class="w-4 h-4 animate-spin" />
                    <SendHorizontal v-else class="w-4 h-4" />
                  </Button>
                </div>
                <Tabs v-model="searchMode" class="flex-shrink-0">
                  <TabsList class="grid w-full grid-cols-2">
                    <TabsTrigger value="keywords" class="text-xs sm:text-sm font-medium">
                      Keywords
                    </TabsTrigger>
                    <TabsTrigger value="context" class="text-xs sm:text-sm font-medium">
                      Context
                    </TabsTrigger>
                  </TabsList>
                </Tabs>
              </div>
            </div>
          </DialogContent>
        </Dialog>


        <!-- ========================================================================
          UPLOAD DIALOG 
        ======================================================================= -->
        <Dialog v-model:open="uploadDialogOpen">
          <DialogContent class="max-w-[95vw] sm:max-w-lg max-h-[90vh] overflow-y-auto">
            <DialogHeader class="border-b pb-3 sm:pb-4 text-left">
              <DialogTitle class="text-lg sm:text-lg text-left">Upload File</DialogTitle>
              <DialogDescription class="text-xs sm:text-sm text-left">
                Upload a new document to the repository. Maximum file size is 10MB.
              </DialogDescription>
            </DialogHeader>

            <div class="space-y-4 sm:space-y-5 py-3 sm:py-4">
              <div>
                <Label for="upload-file" class="text-xs sm:text-sm font-medium block mb-1.5 sm:mb-2">
                  File <span class="text-red-500">*</span>
                </Label>
                <div
                  class="w-full border rounded-lg bg-white px-3 sm:px-4 py-2 sm:py-3 flex items-center justify-between cursor-pointer hover:border-blue-300 transition-all">
                  <input id="upload-file" type="file"
                    class="w-full text-xs sm:text-sm text-gray-700 file:mr-2 sm:file:mr-4 file:py-1 sm:file:py-2 file:px-2 sm:file:px-4 file:rounded-md file:border-0 file:text-xs sm:file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                    @change="onFileChange" />
                </div>
                <div v-if="uploadFile && uploadFile.size > 10 * 1024 * 1024"
                  class="text-[10px] sm:text-xs text-red-600 mt-1">
                  File exceeds 10MB limit.
                </div>
              </div>

              <div>
                <Label for="upload-desc" class="text-xs sm:text-sm font-medium block mb-1.5 sm:mb-2">
                  Description
                </Label>
                <Textarea id="upload-desc" v-model="uploadDescription"
                  placeholder="Enter a short description of the document..." class="w-full text-xs sm:text-sm"
                  rows="3" />
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                <div>
                  <Label class="text-xs sm:text-sm font-medium block mb-1.5 sm:mb-2">
                    Department <span class="text-red-500">*</span>
                  </Label>
                  <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                      <Button variant="outline"
                        class="w-full flex items-center justify-between gap-4 px-3 sm:px-8 py-2 sm:py-2.5 rounded-lg border bg-white text-xs sm:text-sm hover:border-blue-300 transition-all">
                        <span class="truncate">{{ uploadDepartment }}</span>
                        <ChevronDown class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-gray-400 flex-shrink-0" />
                      </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent class="w-full">
                      <DropdownMenuItem v-for="d in departments.filter(d => d !== 'All')" :key="d"
                        @click="uploadDepartment = d">
                        {{ d }}
                      </DropdownMenuItem>
                    </DropdownMenuContent>
                  </DropdownMenu>
                </div>

                <div>
                  <Label class="text-xs sm:text-sm font-medium block mb-1.5 sm:mb-2">
                    Access Level <span class="text-red-500">*</span>
                  </Label>
                  <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                      <Button variant="outline"
                        class="w-full flex items-center justify-between gap-4 px-3 sm:px-8 py-2 sm:py-2.5 rounded-lg border bg-white text-xs sm:text-sm hover:border-blue-300 transition-all">
                        <span class="truncate">{{ uploadAccess }}</span>
                        <ChevronDown class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-gray-400 flex-shrink-0" />
                      </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent class="w-full">
                      <DropdownMenuItem v-for="a in accesses.filter(a => a !== 'All')" :key="a"
                        @click="uploadAccess = a as any">
                        {{ a }}
                      </DropdownMenuItem>
                    </DropdownMenuContent>
                  </DropdownMenu>
                </div>
              </div>

              <!-- Tags (input disabled, keep Pick + Add) -->
              <div>
                <Label class="text-xs sm:text-sm font-medium block mb-1.5 sm:mb-2">Tags</Label>
                <div class="flex flex-wrap gap-1.5 sm:gap-2 mb-2 sm:mb-3">
                  <Badge v-for="(tag, idx) in uploadTags" :key="idx"
                    class="bg-gray-100 text-gray-700 text-[10px] sm:text-xs flex items-center gap-1 px-1.5 sm:px-2 py-0.5">
                    {{ tag }}
                    <button @click="removeUploadTag(tag)" class="ml-1 hover:text-red-600">
                      <X class="w-2.5 h-2.5 sm:w-3 sm:h-3" />
                    </button>
                  </Badge>
                  <p v-if="uploadTags.length === 0" class="text-[10px] sm:text-xs text-gray-500">No tags added</p>
                </div>
                <div class="flex gap-1.5 sm:gap-2 items-center">
                  <Input v-model="uploadNewTag" @keyup.enter="addUploadTag" placeholder="Add a tag..."
                    class="flex-1 opacity-60 pointer-events-none text-xs sm:text-sm" disabled />
                  <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                      <Button variant="outline" size="sm"
                        class="px-2 sm:px-3 py-1.5 sm:py-2 rounded-lg border bg-white text-xs sm:text-sm hover:border-blue-300 inline-flex items-center gap-1.5 sm:gap-2">
                        <Tag class="w-3.5 h-3.5 sm:w-4 sm:h-4" /> Pick
                      </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent class="max-h-48 sm:max-h-64 overflow-auto w-40 sm:w-44">
                      <DropdownMenuLabel class="text-xs sm:text-sm">Tag Options</DropdownMenuLabel>
                      <DropdownMenuSeparator />
                      <DropdownMenuItem v-for="opt in tagOptions" :key="opt" @click="uploadNewTag = opt"
                        class="text-xs sm:text-sm">
                        <div class="inline-flex items-center gap-1.5 sm:gap-2">
                          <span class="w-2 h-2 sm:w-2.5 sm:h-2.5 rounded-full bg-gray-300"></span>
                          <span>{{ opt }}</span>
                        </div>
                      </DropdownMenuItem>
                    </DropdownMenuContent>
                  </DropdownMenu>
                  <Button @click="addUploadTag" size="sm" type="button" class="text-xs sm:text-sm">Add</Button>
                </div>
              </div>
            </div>

            <!-- Admin TAGS panel (Upload) -->
            <div v-if="showTagsPanelUpload"
              class="mt-2 sm:mt-3 rounded-lg border w-full max-w-[280px] sm:max-w-[320px] mx-auto">
              <div
                class="sticky top-0 z-10 bg-white grid grid-cols-[1fr_64px] items-center px-2 sm:px-3 py-1.5 sm:py-2 border-b">
                <h4 class="text-xs sm:text-sm font-semibold pl-1">TAGS</h4>
                <div class="flex items-center justify-end">
                  <Button variant="ghost" size="icon" class="h-7 w-7 sm:h-8 sm:w-8" @click="openAdminTagAdd()"
                    aria-label="Add tag">
                    <Plus class="w-3.5 h-3.5 sm:w-4 sm:h-4" />
                  </Button>
                </div>
              </div>

              <ScrollArea class="h-36 sm:h-44 w-full">
                <div class="px-2 sm:px-3">
                  <div v-for="(opt, i) in tagOptions" :key="opt"
                    class="grid grid-cols-[1fr_64px] items-center py-1.5 sm:py-2" :class="i !== 0 ? 'border-t' : ''">
                    <div class="min-w-0 inline-flex items-center gap-1.5 sm:gap-2 pl-1">
                      <span class="w-2 h-2 sm:w-2.5 sm:h-2.5 rounded-full bg-gray-300"></span>
                      <span class="text-xs sm:text-sm font-medium truncate">{{ opt }}</span>
                    </div>
                    <div class="inline-flex items-center gap-1 justify-end">
                      <Button size="icon" variant="ghost" class="h-7 w-7 sm:h-8 sm:w-8" @click="openAdminTagEdit(opt)"
                        aria-label="Edit tag">
                        <Pencil class="w-3.5 h-3.5 sm:w-4 sm:h-4" />
                      </Button>
                      <Button size="icon" variant="ghost" class="h-7 w-7 sm:h-8 sm:w-8" @click="openAdminTagDelete(opt)"
                        aria-label="Delete tag">
                        <Trash2 class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-rose-600" />
                      </Button>
                    </div>
                  </div>
                </div>
              </ScrollArea>
            </div>

            <DialogFooter class="border-t pt-3 sm:pt-4 mt-3 sm:mt-4 gap-2 flex-col sm:flex-row">
              <Button variant="secondary" size="sm" class="w-full sm:w-auto"
                @click="showTagsPanelUpload = !showTagsPanelUpload">
                <Tag class="w-3.5 h-3.5 sm:w-4 sm:h-4 mr-1.5 sm:mr-2" /> TAGS
              </Button>
              <div class="flex gap-2 w-full sm:w-auto">
                <Button variant="secondary" size="sm" class="flex-1 sm:flex-none" @click="uploadDialogOpen = false">
                  Cancel
                </Button>
                <Button size="sm" class="flex-1 sm:flex-none" @click="handleUploadSubmit" :disabled="!uploadFile">
                  Upload
                </Button>
              </div>
            </DialogFooter>
          </DialogContent>
        </Dialog>

        <!-- ========================================================================
          FILE DETAILS & ACTIVITY MODAL
        ======================================================================= -->
        <Dialog v-model:open="detailModalOpen">
          <DialogContent class="max-w-[95vw] sm:max-w-lg max-h-[90vh] overflow-y-auto">
            <DialogHeader class="border-b pb-3 sm:pb-4 text-left">
              <DialogTitle class="text-lg sm:text-lg text-left">File Details</DialogTitle>
              <DialogDescription class="text-xs sm:text-sm text-left">
                Information and recent activity for this file.
              </DialogDescription>
            </DialogHeader>

            <div v-if="selectedFile" class="py-3 sm:py-4 space-y-3 sm:space-y-4 text-xs sm:text-sm">
              <div class="flex items-start justify-between gap-2">
                <div class="min-w-0 flex-1">
                  <div class="text-[10px] sm:text-xs text-gray-400">Name</div>
                  <div class="font-medium text-white truncate text-xs sm:text-sm">{{ selectedFile.name }}</div>
                </div>
                <div
                  :class="['px-2 sm:px-4 py-1 rounded-lg text-white text-[10px] sm:text-xs font-semibold shrink-0', typeColor(selectedFile.type)]">
                  {{ selectedFile.type }}
                </div>
              </div>

              <div class="grid grid-cols-2 gap-3 sm:gap-5">
                <div>
                  <div class="text-[10px] sm:text-xs text-gray-400">Uploader</div>
                  <div class="text-xs sm:text-sm text-gray-100">{{ selectedFile.uploader }}</div>
                </div>
                <div>
                  <div class="text-[10px] sm:text-xs text-gray-400">Position</div>
                  <div class="text-xs sm:text-sm text-gray-100">
                    {{ getUserMeta(selectedFile.uploader, selectedFile.department).position }}
                  </div>
                </div>
                <div>
                  <div class="text-[10px] sm:text-xs text-gray-400">Department</div>
                  <div class="text-xs sm:text-sm text-gray-100">{{ selectedFile.department || '—' }}</div>
                </div>
                <div>
                  <div class="text-[10px] sm:text-xs text-gray-400">Access</div>
                  <div class="flex items-center gap-1 sm:gap-1.5 text-xs sm:text-sm text-gray-100">
                    <component :is="accessIconComponent(selectedFile.access)" class="w-3 h-3 sm:w-3.5 sm:h-3.5"
                      :class="accessIconColor(selectedFile.access)" />
                    <span>{{ selectedFile.access }}</span>
                  </div>
                </div>
                <div>
                  <div class="text-[10px] sm:text-xs text-gray-400">Created</div>
                  <div class="text-xs sm:text-sm text-gray-100">{{ selectedFile.created || '—' }}</div>
                </div>
                <div>
                  <div class="text-[10px] sm:text-xs text-gray-400">Size</div>
                  <div class="text-xs sm:text-sm text-gray-100">{{ selectedFile.size || '—' }}</div>
                </div>
                <div v-if="selectedFile.updated">
                  <div class="text-[10px] sm:text-xs text-gray-400">Updated</div>
                  <div class="text-xs sm:text-sm text-gray-100">{{ selectedFile.updated }}</div>
                </div>
              </div>

              <Separator />

              <div>
                <div class="text-[10px] sm:text-xs text-gray-400 mb-1">Description</div>
                <p class="text-xs sm:text-sm text-gray-100 whitespace-pre-wrap">{{ selectedFile.description || '—' }}
                </p>
              </div>
            </div>
          </DialogContent>
        </Dialog>

        <!-- ========================================================================
          REQUEST DETAILS & ACTIVITY MODAL 
        ======================================================================= -->
        <Dialog v-model:open="requestDetailModalOpen">
          <DialogContent class="max-w-[95vw] sm:max-w-lg max-h-[90vh] overflow-y-auto">
            <DialogHeader class="border-b pb-3 sm:pb-4 text-left">
              <DialogTitle class="text-lg sm:text-lg text-left">Request Details</DialogTitle>
              <DialogDescription class="text-xs sm:text-sm text-left">
                Information and activity for this request.
              </DialogDescription>
            </DialogHeader>

            <div v-if="selectedRequest" class="py-3 sm:py-4 space-y-3 sm:space-y-4 text-xs sm:text-sm">
              <div class="flex items-start justify-between gap-2">
                <div class="min-w-0 flex-1">
                  <div class="text-[10px] sm:text-xs text-gray-400">Document</div>
                  <div class="font-medium text-white truncate text-xs sm:text-sm">{{ selectedRequest.name }}</div>
                </div>
                <div
                  :class="['px-2 sm:px-4 py-1 rounded-lg text-white text-[10px] sm:text-xs font-semibold shrink-0', typeColor(inferTypeFromName(selectedRequest.name))]">
                  {{ inferTypeFromName(selectedRequest.name) === 'Word' ? 'WORD' :
                    inferTypeFromName(selectedRequest.name) }}
                </div>
              </div>

              <div class="grid grid-cols-2 gap-3 sm:gap-5">
                <div>
                  <div class="text-[10px] sm:text-xs text-gray-400">Requester</div>
                  <div class="text-xs sm:text-sm text-gray-100">{{ selectedRequest.requester }}</div>
                </div>
                <div>
                  <div class="text-[10px] sm:text-xs text-gray-400">Position</div>
                  <div class="text-xs sm:text-sm text-gray-100">
                    {{ getUserMeta(selectedRequest.requester, selectedRequest.department).position }}
                  </div>
                </div>
                <div>
                  <div class="text-[10px] sm:text-xs text-gray-400">Department</div>
                  <div class="text-xs sm:text-sm text-gray-100">{{ selectedRequest.department }}</div>
                </div>
                <div>
                  <div class="text-[10px] sm:text-xs text-gray-400">Access</div>
                  <div class="flex items-center gap-1 sm:gap-1.5 text-xs sm:text-sm text-gray-100">
                    <component :is="accessIconComponent(selectedRequest.access)" class="w-3 h-3 sm:w-3.5 sm:h-3.5"
                      :class="accessIconColor(selectedRequest.access)" />
                    <span>{{ selectedRequest.access }}</span>
                  </div>
                </div>
                <div>
                  <div class="text-[10px] sm:text-xs text-gray-400">Requested At</div>
                  <div class="text-xs sm:text-sm text-gray-100">
                    {{ new Date(selectedRequest.requestedAt).toLocaleDateString() }}
                  </div>
                </div>
              </div>

              <!-- Status Section with Approver Info -->
              <div class="col-span-2">
                <div class="text-[10px] sm:text-xs text-gray-400 mb-1.5 sm:mb-2">Status</div>
                <div class="space-y-2">
                  <!-- Status Badge -->
                  <div class="flex items-center gap-2 flex-wrap">
                    <Badge
                      :class="`${statusColor(selectedRequest.status)} text-[10px] sm:text-xs font-semibold px-2 sm:px-2.5 py-0.5`">
                      {{ selectedRequest.status }}
                    </Badge>
                  </div>

                  <!-- Approver Information (if approved or rejected) -->
                  <div
                    v-if="selectedRequest.approvedBy && (selectedRequest.status === 'Approved' || selectedRequest.status === 'Rejected')"
                    class="pt-1">

                    <div class="text-xs sm:text-sm text-gray-300">
                      <span v-if="selectedRequest.status === 'Approved'">
                        Approved by {{ selectedRequest.approvedBy }} | {{ getUserMeta(selectedRequest.approvedBy,
                          selectedRequest.department).position }} | {{ getUserMeta(selectedRequest.approvedBy,
                          selectedRequest.department).department }}
                      </span>
                      <span v-else-if="selectedRequest.status === 'Rejected'">
                        Reviewed by {{ selectedRequest.approvedBy }} | {{ getUserMeta(selectedRequest.approvedBy,
                          selectedRequest.department).position }} | {{ getUserMeta(selectedRequest.approvedBy,
                          selectedRequest.department).department }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Approve/Reject buttons - "To You" view for Pending requests -->
            <div v-if="requestView === 'To You' && selectedRequest.status === 'Pending'"
              class="flex flex-col sm:flex-row gap-2 pt-2 border-t mt-2">
              <Button @click="updateRequestStatus(selectedRequest.id, 'Approved')" class="flex-1 text-xs sm:text-sm">
                Approve
              </Button>
              <Button variant="secondary" @click="updateRequestStatus(selectedRequest.id, 'Rejected')"
                class="flex-1 text-xs sm:text-sm">
                Reject
              </Button>
            </div>
          </DialogContent>
        </Dialog>
        <!-- ========================================================================
          EDIT DETAILS MODAL 
        ======================================================================= -->
        <Dialog v-model:open="editDialogOpen">
          <DialogContent class="max-w-[95vw] sm:max-w-xl max-h-[90vh] overflow-y-auto">
            <DialogHeader class="border-b pb-3 sm:pb-4 text-left">
              <DialogTitle class="text-lg sm:text-lg text-left">Edit Details</DialogTitle>
              <DialogDescription class="text-xs sm:text-sm text-left">
                Update description, tags, access, and department.
              </DialogDescription>
            </DialogHeader>

            <div class="py-3 sm:py-4 space-y-4 sm:space-y-6" v-if="dialogFile">
              <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2">
                <div class="text-xs sm:text-sm text-gray-900 font-semibold truncate flex-1">{{ dialogFile.name }}</div>
                <div class="text-[10px] sm:text-xs text-gray-500 whitespace-nowrap">
                  Dept: {{ dialogFile.department }} | Access: {{ dialogFile.access }}
                </div>
              </div>

              <div>
                <Label class="text-xs sm:text-sm font-medium block mb-1.5 sm:mb-2">Description</Label>
                <Textarea v-model="dialogFile.description" rows="3" placeholder="Enter description..."
                  class="w-full text-xs sm:text-sm" />
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                <div>
                  <Label class="text-xs sm:text-sm font-medium block mb-1.5 sm:mb-2">Department</Label>
                  <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                      <Button variant="outline"
                        class="w-full flex items-center justify-between gap-4 px-3 sm:px-8 py-2 sm:py-2.5 rounded-lg border bg-white text-xs sm:text-sm hover:border-blue-300 transition-all">
                        <span class="truncate">{{ dialogFile.department }}</span>
                        <ChevronDown class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-gray-400 flex-shrink-0" />
                      </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent class="w-full">
                      <DropdownMenuItem v-for="d in departments.filter(d => d !== 'All')" :key="d"
                        @click="dialogFile.department = d" class="text-xs sm:text-sm">
                        {{ d }}
                      </DropdownMenuItem>
                    </DropdownMenuContent>
                  </DropdownMenu>
                </div>

                <div>
                  <Label class="text-xs sm:text-sm font-medium block mb-1.5 sm:mb-2">Access Level</Label>
                  <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                      <Button variant="outline"
                        class="w-full flex items-center justify-between gap-4 px-3 sm:px-8 py-2 sm:py-2.5 rounded-lg border bg-white text-xs sm:text-sm hover:border-blue-300 transition-all">
                        <span class="truncate">{{ dialogFile.access }}</span>
                        <ChevronDown class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-gray-400 flex-shrink-0" />
                      </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent class="w-full">
                      <DropdownMenuItem @click="dialogFile.access = 'Public'" class="text-xs sm:text-sm">
                        Public
                      </DropdownMenuItem>
                      <DropdownMenuItem @click="dialogFile.access = 'Private'" class="text-xs sm:text-sm">
                        Private
                      </DropdownMenuItem>
                      <DropdownMenuItem @click="dialogFile.access = 'Department'" class="text-xs sm:text-sm">
                        Department
                      </DropdownMenuItem>
                    </DropdownMenuContent>
                  </DropdownMenu>
                </div>
              </div>

              <!-- Tags -->
              <div>
                <Label class="text-xs sm:text-sm font-medium block mb-1.5 sm:mb-2">Tags</Label>

                <div class="flex flex-wrap gap-1.5 sm:gap-2 mb-2 sm:mb-3">
                  <Badge v-for="(tag, idx) in tempTags" :key="idx"
                    class="bg-gray-100 text-gray-700 text-[10px] sm:text-xs flex items-center gap-1 px-1.5 sm:px-2 py-0.5">
                    {{ tag }}
                    <button @click="removeTag(tag)" class="ml-1 hover:text-red-600">
                      <X class="w-2.5 h-2.5 sm:w-3 sm:h-3" />
                    </button>
                  </Badge>
                  <p v-if="tempTags.length === 0" class="text-[10px] sm:text-xs text-gray-500">No tags</p>
                </div>

                <div class="flex gap-1.5 sm:gap-2 items-center">
                  <Input v-model="newTag" @keyup.enter="addTag" placeholder="Add a tag..."
                    class="flex-1 opacity-60 pointer-events-none text-xs sm:text-sm" disabled />
                  <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                      <Button variant="outline" size="sm"
                        class="px-2 sm:px-3 py-1.5 sm:py-2 rounded-lg border bg-white text-xs sm:text-sm hover:border-blue-300 inline-flex items-center gap-1.5 sm:gap-2">
                        <Tag class="w-3.5 h-3.5 sm:w-4 sm:h-4" /> Pick
                      </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent class="max-h-48 sm:max-h-64 overflow-auto w-40 sm:w-44">
                      <DropdownMenuLabel class="text-xs sm:text-sm">Tag Options</DropdownMenuLabel>
                      <DropdownMenuSeparator />
                      <DropdownMenuItem v-for="opt in tagOptions" :key="opt" @click="newTag = opt"
                        class="text-xs sm:text-sm">
                        <div class="inline-flex items-center gap-1.5 sm:gap-2">
                          <span class="w-2 h-2 sm:w-2.5 sm:h-2.5 rounded-full bg-gray-300"></span>
                          <span>{{ opt }}</span>
                        </div>
                      </DropdownMenuItem>
                    </DropdownMenuContent>
                  </DropdownMenu>
                  <Button @click="addTag" size="sm" type="button" class="text-xs sm:text-sm">Add</Button>
                </div>
              </div>

              <!-- Admin TAGS panel (EDIT DETAILS) -->
              <div v-if="showTagsPanelEdit"
                class="mt-2 sm:mt-3 rounded-lg border w-full max-w-[280px] sm:max-w-[320px] mx-auto">
                <div
                  class="sticky top-0 z-10 bg-white grid grid-cols-[1fr_64px] items-center px-2 sm:px-3 py-1.5 sm:py-2 border-b">
                  <h4 class="text-xs sm:text-sm font-semibold pl-1">TAGS</h4>
                  <div class="flex items-center justify-end">
                    <Button variant="ghost" size="icon" class="h-7 w-7 sm:h-8 sm:w-8" @click="openAdminTagAdd()"
                      aria-label="Add tag">
                      <Plus class="w-3.5 h-3.5 sm:w-4 sm:h-4" />
                    </Button>
                  </div>
                </div>

                <ScrollArea class="h-36 sm:h-44 w-full">
                  <div class="px-2 sm:px-3">
                    <div v-for="(opt, i) in tagOptions" :key="opt"
                      class="grid grid-cols-[1fr_64px] items-center py-1.5 sm:py-2" :class="i !== 0 ? 'border-t' : ''">
                      <div class="min-w-0 inline-flex items-center gap-1.5 sm:gap-2 pl-1">
                        <span class="w-2 h-2 sm:w-2.5 sm:h-2.5 rounded-full bg-gray-300"></span>
                        <span class="text-xs sm:text-sm font-medium truncate">{{ opt }}</span>
                      </div>
                      <div class="inline-flex items-center gap-1 justify-end">
                        <Button size="icon" variant="ghost" class="h-7 w-7 sm:h-8 sm:w-8" @click="openAdminTagEdit(opt)"
                          aria-label="Edit tag">
                          <Pencil class="w-3.5 h-3.5 sm:w-4 sm:h-4" />
                        </Button>
                        <Button size="icon" variant="ghost" class="h-7 w-7 sm:h-8 sm:w-8"
                          @click="openAdminTagDelete(opt)" aria-label="Delete tag">
                          <Trash2 class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-rose-600" />
                        </Button>
                      </div>
                    </div>
                  </div>
                </ScrollArea>
              </div>
            </div>

            <DialogFooter class="border-t pt-3 sm:pt-4 mt-2 sm:mt-2 gap-2 flex-col sm:flex-row">
              <Button variant="secondary" size="sm" class="w-full sm:w-auto"
                @click="showTagsPanelEdit = !showTagsPanelEdit">
                <Tag class="w-3.5 h-3.5 sm:w-4 sm:h-4 mr-1.5 sm:mr-2" /> TAGS
              </Button>
              <div class="flex gap-2 w-full sm:w-auto">
                <Button variant="secondary" size="sm" class="flex-1 sm:flex-none"
                  @click="editDialogOpen = false; dialogFile = null as any; tempTags = [] as any; newTag = '' as any; showTagsPanelEdit = false">
                  Cancel
                </Button>
                <Button size="sm" class="flex-1 sm:flex-none" @click="saveEditDetails">
                  Save
                </Button>
              </div>
            </DialogFooter>
          </DialogContent>
        </Dialog>

        <!-- ========================================================================
          EDIT TAG MODAL 
        ======================================================================= -->
        <Dialog v-model:open="tagEditModalOpen">
          <DialogContent class="max-w-[95vw] sm:max-w-sm">
            <DialogHeader class="border-b pb-3 sm:pb-3">
              <DialogTitle class="text-lg sm:text-lg">Edit Tag</DialogTitle>
              <DialogDescription class="text-xs sm:text-sm">
                Rename the selected tag.
              </DialogDescription>
            </DialogHeader>
            <div class="py-3 sm:py-4 space-y-2 sm:space-y-3">
              <Label class="text-xs sm:text-sm">Tag Name</Label>
              <Input v-model="tagEditText" placeholder="Enter tag..." class="text-xs sm:text-sm" />
            </div>
            <DialogFooter class="border-t pt-3 sm:pt-4 flex-col sm:flex-row gap-2">
              <Button variant="secondary" size="sm" class="w-full sm:w-auto" @click="tagEditModalOpen = false">
                Cancel
              </Button>
              <Button size="sm" class="w-full sm:w-auto" @click="confirmAdminTagEdit">
                Save
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>

        <!-- ========================================================================
          DELETE TAG MODAL 
        ======================================================================= -->
        <Dialog v-model:open="tagDeleteModalOpen">
          <DialogContent class="max-w-[95vw] sm:max-w-sm">
            <DialogHeader class="border-b pb-3 sm:pb-3">
              <DialogTitle class="text-lg sm:text-lg">Delete Tag</DialogTitle>
              <DialogDescription class="text-xs sm:text-sm">
                Are you sure you want to delete "{{ tagToDelete }}"?
              </DialogDescription>
            </DialogHeader>
            <DialogFooter class="border-t pt-3 sm:pt-4 flex-col sm:flex-row gap-2">
              <Button variant="secondary" size="sm" class="w-full sm:w-auto" @click="tagDeleteModalOpen = false">
                Cancel
              </Button>
              <Button size="sm" class="w-full sm:w-auto bg-rose-600 hover:bg-rose-700" @click="confirmAdminTagDelete">
                Delete
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>

        <!-- ========================================================================
          ADD TAG MODAL 
        ======================================================================= -->
        <Dialog v-model:open="tagAddModalOpen">
          <DialogContent class="max-w-[95vw] sm:max-w-sm">
            <DialogHeader class="border-b pb-3 sm:pb-3">
              <DialogTitle class="text-lg sm:text-lg">Add Tag</DialogTitle>
              <DialogDescription class="text-xs sm:text-sm">
                Create a new tag option.
              </DialogDescription>
            </DialogHeader>
            <div class="py-3 sm:py-4 space-y-2 sm:space-y-3">
              <Label class="text-xs sm:text-sm">Tag Name</Label>
              <Input v-model="newAdminTag" placeholder="Enter tag..." class="text-xs sm:text-sm" />
            </div>
            <DialogFooter class="border-t pt-3 sm:pt-4 flex-col sm:flex-row gap-2">
              <Button variant="secondary" size="sm" class="w-full sm:w-auto" @click="tagAddModalOpen = false">
                Cancel
              </Button>
              <Button size="sm" class="w-full sm:w-auto" @click="confirmAdminTagAdd">
                Add
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>

        <!-- ========================================================================
          RESTRICTED ACCESS DIALOG 
        ======================================================================= -->
        <Dialog v-model:open="restrictedDialogOpen">
          <DialogContent class="max-w-[95vw] sm:max-w-sm">
            <DialogHeader class="border-b pb-3 sm:pb-3">
              <DialogTitle class="text-lg sm:text-lg">Restricted Document</DialogTitle>
              <DialogDescription class="text-xs sm:text-sm">
                You don't have access to open this document.
              </DialogDescription>
            </DialogHeader>
            <div class="py-3 sm:py-4 text-xs sm:text-sm">
              <p class="text-gray-700">Request access from the owner or admin.</p>
            </div>
            <DialogFooter class="border-t pt-3 sm:pt-4 flex-col sm:flex-row gap-2">
              <Button variant="secondary" size="sm" class="w-full sm:w-auto" @click="restrictedDialogOpen = false">
                Close
              </Button>
              <Button size="sm" class="w-full sm:w-auto" @click="sendAccessRequest">
                Request Access
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>

        <!-- ========================================================================
          RENAME FILE DIALOG 
        ======================================================================= -->
        <Dialog v-model:open="renameDialogOpen">
          <DialogContent class="max-w-[95vw] sm:max-w-sm">
            <DialogHeader class="border-b pb-3 sm:pb-3 text-left">
              <DialogTitle class="text-lg sm:text-lg text-left">Rename File</DialogTitle>
              <DialogDescription class="text-sm sm:text-sm text-left">
                Enter a new name.
              </DialogDescription>
            </DialogHeader>
            <div class="py-4 sm:py-4 space-y-3 sm:space-y-3">
              <Label for="rename" class="text-sm sm:text-sm font-medium">Name</Label>
              <Input id="rename" v-model="tempRename" class="text-sm sm:text-sm h-11 sm:h-10" />
              <div class="flex flex-col sm:flex-row gap-2 sm:gap-2 mt-4 sm:mt-4">
                <Button class="flex-1 text-sm sm:text-sm h-11 sm:h-10 font-medium" @click="handleRenameConfirm">
                  Save
                </Button>
                <Button variant="secondary" class="flex-1 sm:flex-none text-sm sm:text-sm h-11 sm:h-10 font-medium"
                  @click="renameDialogOpen = false">
                  Cancel
                </Button>
              </div>
            </div>
          </DialogContent>
        </Dialog>

        <!-- ========================================================================
          RENAME REQUEST DIALOG 
        ======================================================================= -->
        <Dialog v-model:open="requestRenameDialogOpen">
          <DialogContent class="max-w-[95vw] sm:max-w-sm">
            <DialogHeader class="border-b pb-3 sm:pb-3">
              <DialogTitle class="text-lg sm:text-lg">Rename Request</DialogTitle>
              <DialogDescription class="text-xs sm:text-sm">
                Enter a new document name.
              </DialogDescription>
            </DialogHeader>
            <div class="py-3 sm:py-4 space-y-2 sm:space-y-3">
              <Label for="req-rename" class="text-xs sm:text-sm">Name</Label>
              <Input id="req-rename" v-model="tempRequestRename" class="text-xs sm:text-sm" />
              <div class="flex flex-col sm:flex-row gap-2 mt-3 sm:mt-4">
                <Button size="sm" class="flex-1 text-xs sm:text-sm" @click="handleRequestRenameConfirm">
                  Save
                </Button>
                <Button variant="secondary" size="sm" class="flex-1 sm:flex-none text-xs sm:text-sm"
                  @click="requestRenameDialogOpen = false">
                  Cancel
                </Button>
              </div>
            </div>
          </DialogContent>
        </Dialog>

        <!-- ========================================================================
          DOWNLOAD DIALOG 
        ======================================================================= -->
        <Dialog v-model:open="downloadDialogOpen">
          <DialogContent class="max-w-[95vw] sm:max-w-sm">
            <DialogHeader class="border-b pb-3 sm:pb-3 text-left">
              <DialogTitle class="text-lg sm:text-lg text-left">Download</DialogTitle>
              <DialogDescription class="text-sm sm:text-sm text-left">
                Start download (simulated) for <span class="font-medium">{{ dialogFile?.name }}</span>.
              </DialogDescription>
            </DialogHeader>
            <div class="py-4 sm:py-4 flex flex-col sm:flex-row gap-2 sm:gap-2">
              <Button class="flex-1 text-sm sm:text-sm h-11 sm:h-10 font-medium" @click="handleDownloadConfirm">
                Download
              </Button>
              <Button variant="secondary" class="flex-1 sm:flex-none text-sm sm:text-sm h-11 sm:h-10 font-medium"
                @click="downloadDialogOpen = false">
                Cancel
              </Button>
            </div>
          </DialogContent>
        </Dialog>

        <!-- ========================================================================
          MANAGE TAGS DIALOG 
        ======================================================================= -->
        <Dialog v-model:open="manageTagsDialogOpen">
          <DialogContent class="max-w-[95vw] sm:max-w-md max-h-[90vh] overflow-y-auto">
            <DialogHeader class="border-b pb-3 sm:pb-4">
              <DialogTitle class="text-lg sm:text-lg">Manage Tags</DialogTitle>
              <DialogDescription class="text-xs sm:text-sm">
                Add or remove tags.
              </DialogDescription>
            </DialogHeader>

            <div class="py-3 sm:py-4 space-y-3 sm:space-y-4">
              <div>
                <Label class="text-xs sm:text-sm font-semibold">Current Tags</Label>
                <div class="flex flex-wrap gap-1.5 sm:gap-2 mt-2 sm:mt-3">
                  <Badge v-for="(tag, idx) in tempTags" :key="idx"
                    :class="`${getTagColorByName(tag)} text-[10px] sm:text-xs flex items-center gap-1 px-1.5 sm:px-2 py-0.5`">
                    {{ tag }}
                    <button @click="removeTag(tag)" class="ml-1 hover:text-red-600">
                      <X class="w-2.5 h-2.5 sm:w-3 sm:h-3" />
                    </button>
                  </Badge>
                  <p v-if="tempTags.length === 0" class="text-[10px] sm:text-xs text-gray-500">No tags</p>
                </div>
              </div>

              <div>
                <Label class="text-xs sm:text-sm font-semibold">All Tags</Label>
                <div class="mt-2 sm:mt-3 rounded-lg border">
                  <ScrollArea class="h-40 sm:h-48 w-full p-2">
                    <div v-for="opt in tagOptions" :key="opt"
                      class="flex items-center justify-between px-2 py-1.5 sm:py-2">
                      <div class="inline-flex items-center gap-1.5 sm:gap-2">
                        <span class="w-2 h-2 sm:w-2.5 sm:h-2.5 rounded-full"
                          :class="getTagColorByName(opt).split(' ')[0]"></span>
                        <span class="text-xs sm:text-sm font-medium">{{ opt }}</span>
                      </div>
                      <div class="inline-flex items-center gap-1">
                        <Button size="icon" variant="ghost" class="h-7 w-7 sm:h-8 sm:w-8"
                          @click="addTagFromOptions({ value: tempTags }, opt)">
                          <Plus class="w-3.5 h-3.5 sm:w-4 sm:h-4" />
                        </Button>
                        <Button size="icon" variant="ghost" class="h-7 w-7 sm:h-8 sm:w-8"
                          @click="openAdminTagEdit(opt)">
                          <Pencil class="w-3.5 h-3.5 sm:w-4 sm:h-4" />
                        </Button>
                        <Button size="icon" variant="ghost" class="h-7 w-7 sm:h-8 sm:w-8"
                          @click="openAdminTagDelete(opt)">
                          <Trash2 class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-rose-600" />
                        </Button>
                      </div>
                    </div>
                  </ScrollArea>
                </div>
              </div>
            </div>

            <DialogFooter class="border-t pt-3 sm:pt-4 flex-col sm:flex-row gap-2">
              <Button variant="secondary" size="sm" class="w-full sm:w-auto" @click="manageTagsDialogOpen = false">
                Close
              </Button>
              <Button size="sm" class="w-full sm:w-auto" @click="saveEditDetails">
                Save
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>

        <!-- ========================================================================
          MANAGE ACCESS DIALOG (FILES) 
        ======================================================================= -->
        <Dialog v-model:open="accessDialogOpen">
          <DialogContent class="max-w-[95vw] sm:max-w-sm">
            <DialogHeader class="border-b pb-3 sm:pb-3">
              <DialogTitle class="text-lg sm:text-lg">Manage Access</DialogTitle>
              <DialogDescription class="text-xs sm:text-sm">
                Cycle through Public → Private → Department.
              </DialogDescription>
            </DialogHeader>
            <div class="py-3 sm:py-4 space-y-2">
              <div class="text-xs sm:text-sm text-gray-700">
                Current: <span class="font-medium">{{ dialogFile?.access }}</span>
              </div>
            </div>
            <DialogFooter class="border-t pt-3 sm:pt-4 flex-col sm:flex-row gap-2">
              <Button variant="secondary" size="sm" class="w-full sm:w-auto" @click="accessDialogOpen = false">
                Cancel
              </Button>
              <Button size="sm" class="w-full sm:w-auto" @click="handleManageAccessConfirm">
                Apply
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>

        <!-- ========================================================================
          MANAGE REQUEST ACCESS DIALOG 
        ======================================================================= -->
        <Dialog v-model:open="requestAccessDialogOpen">
          <DialogContent class="max-w-[95vw] sm:max-w-sm">
            <DialogHeader class="border-b pb-3 sm:pb-3">
              <DialogTitle class="text-lg sm:text-lg">Manage Request Access</DialogTitle>
              <DialogDescription class="text-xs sm:text-sm">
                Set accessibility for this requested document.
              </DialogDescription>
            </DialogHeader>
            <div class="py-3 sm:py-4 space-y-3 sm:space-y-4">
              <div>
                <Label class="text-xs sm:text-sm font-medium block mb-1.5 sm:mb-2">Access</Label>
                <DropdownMenu>
                  <DropdownMenuTrigger as-child>
                    <Button variant="outline"
                      class="w-full flex items-center justify-between px-3 sm:px-4 py-2 sm:py-2.5 rounded-lg sm:rounded-xl border border-gray-300 bg-white text-xs sm:text-sm hover:shadow-sm hover:border-blue-300 transition-all">
                      <span class="text-gray-700">{{ requestAccess }}</span>
                      <ChevronDown class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-gray-400" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent class="w-full">
                    <DropdownMenuItem @click="requestAccess = 'Public'" class="text-xs sm:text-sm">
                      Public
                    </DropdownMenuItem>
                    <DropdownMenuItem @click="requestAccess = 'Private'" class="text-xs sm:text-sm">
                      Private
                    </DropdownMenuItem>
                    <DropdownMenuItem @click="requestAccess = 'Department'" class="text-xs sm:text-sm">
                      Department
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              </div>
            </div>
            <DialogFooter class="border-t pt-3 sm:pt-4 flex-col sm:flex-row gap-2">
              <Button variant="secondary" size="sm" class="w-full sm:w-auto" @click="requestAccessDialogOpen = false">
                Cancel
              </Button>
              <Button size="sm" class="w-full sm:w-auto" @click="handleManageAccessRequestConfirm">
                Save
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>

        <!-- ========================================================================
          REMOVE CONFIRMATION DIALOG 
        ======================================================================= -->
        <Dialog v-model:open="removeDialogOpen">
          <DialogContent class="max-w-[95vw] sm:max-w-sm">  
            <DialogHeader class="border-b pb-3 sm:pb-3">
              <DialogTitle class="text-lg sm:text-lg">Confirm Remove</DialogTitle>
              <DialogDescription class="text-xs sm:text-sm">
                <template v-if="removeTarget?.kind === 'file'">
                  This file will be moved to Trash. You can restore it within 30 days.
                </template>
                <template v-else>
                  This request will be removed.
                </template>
              </DialogDescription>
            </DialogHeader>
            <div class="py-3 sm:py-4 space-y-2 text-xs sm:text-sm">
              <div class="text-gray-700">
                Are you sure you want to remove
                <span class="font-medium">
                  {{ removeTarget?.item?.name || 'this item' }}
                </span>?
              </div>
            </div>
            <DialogFooter class="border-t pt-3 sm:pt-4 flex-col sm:flex-row gap-2">
              <Button variant="secondary" size="sm" class="w-full sm:w-auto" @click="removeDialogOpen = false">
                Cancel
              </Button>
              <Button size="sm" class="w-full sm:w-auto bg-rose-600 hover:bg-rose-700" @click="confirmRemove">
                Remove
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>

    <!-- ========================================================================
      TOAST NOTIFICATIONS 
    ======================================================================= -->
    <div
      class="fixed bottom-3 sm:bottom-4 right-3 sm:right-4 z-[100] space-y-2 w-[calc(100%-1.5rem)] sm:w-full max-w-xs">
      <div v-for="t in toasts" :key="t.id" :class="[
        'px-3 sm:px-4 py-2 sm:py-3 rounded-lg sm:rounded-xl shadow-lg border text-xs sm:text-sm',
        t.variant === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' :
          t.variant === 'warning' ? 'bg-amber-50 border-amber-200 text-amber-800' :
            t.variant === 'error' ? 'bg-rose-50 border-rose-200 text-rose-800' :
              'bg-white border-gray-200 text-gray-800'
      ]">
        {{ t.text }}
      </div>
    </div>
  </AppLayout>
</template>