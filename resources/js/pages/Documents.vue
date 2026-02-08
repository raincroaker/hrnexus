<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import { type BreadcrumbItem } from '@/types'
import api from '@/lib/axios'
import { toast } from 'vue-sonner'

interface Props {
  documents?: any[]
  trashedDocuments?: any[]
  tags?: any[]
  departments?: any[]
  accessRequests?: any[]
  currentUser?: {
    id: number
    name: string
    first_name: string
    last_name: string
    employee_code: string
    department: string
    department_id: number | null
    position: string
    position_id: number | null
    role: 'employee' | 'department_manager' | 'admin'
    email: string
    contact_number: string | null
    birth_date: string | null
    avatar: string | null
  } | null
}

const props = withDefaults(defineProps<Props>(), {
  documents: () => [],
  trashedDocuments: () => [],
  tags: () => [],
  departments: () => [],
  accessRequests: () => [],
  currentUser: null,
})

import {
  Plus, Search, FolderIcon, SendIcon, Trash2 as Trash2Icon, Edit3,
  ChevronDown, X, Loader2, Eye, Building2, Tag, Download, Info, Clock,
  Pencil, Trash2, RefreshCw, Inbox, Forward,
  FileText, File,
  CheckCircle, XCircle, Files, Check, AlertCircle,
} from 'lucide-vue-next'
import {
  DropdownMenu,
  DropdownMenuTrigger,
  DropdownMenuContent,
  DropdownMenuLabel,
  DropdownMenuItem,
  DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import {
  Pagination,
  PaginationContent,
  PaginationEllipsis,
  PaginationItem,
  PaginationNext,
  PaginationPrevious,
} from '@/components/ui/pagination'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'

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
// TOAST NOTIFICATIONS (using Sonner)
// ============================================================================
// Toast notifications are now handled by Sonner via the toast() function from 'vue-sonner'

// ============================================================================
// CURRENT USER
// ============================================================================
const currentUser = ref<{
  id: number
  name: string
  first_name: string
  last_name: string
  employee_code: string
  department: string
  department_id: number | null
  position: string
  position_id: number | null
  role: 'employee' | 'department_manager' | 'admin'
  email: string
  contact_number: string | null
  birth_date: string | null
  avatar: string | null
} | null>(props.currentUser || null)

const normalizedRole = computed(() => {
  if (!currentUser.value) return 'employee'
  return currentUser.value.role.toLowerCase()
})

const isAdminUser = computed(() => normalizedRole.value === 'admin')
const isDepartmentManager = computed(() => normalizedRole.value === 'department_manager')

// ============================================================================
// FILE VIEWER
// ============================================================================
const fileViewerOpen = ref(false)
const viewerFileUrl = ref<string | null>(null)
const viewerFileName = ref<string | null>(null)
const viewerFileType = ref<string | null>(null)

/**
 * Check if file is PDF
 */
const isViewerPdf = computed(() => {
  if (viewerFileType.value === 'application/pdf') return true
  if (viewerFileType.value?.toLowerCase() === 'pdf') return true
  const ext = viewerFileName.value?.split('.').pop()?.toLowerCase()
  return ext === 'pdf'
})

/**
 * Open file viewer
 */
// Document details dialog state
const documentDetailsOpen = ref(false)
const selectedDocumentForDetails = ref<any>(null)
const downloadHistoryOpen = ref(false)

// Preview and Download confirmation dialogs
const previewConfirmOpen = ref(false)
const downloadConfirmOpen = ref(false)
const decisionDialogOpen = ref(false)
const decisionAction = ref<'approve' | 'reject' | null>(null)
const decisionMessage = ref('')
const requestDecisionDialogOpen = ref(false)
const requestDecisionAction = ref<'approve' | 'reject' | null>(null)
const requestDecisionMessage = ref('')
const requestDecisionTarget = ref<{ kind: 'upload' | 'permission'; record: any } | null>(null)
const editAccessDialogOpen = ref(false)
const editAccessTarget = ref<any | null>(null)
const editAccessMessage = ref('')
const requestPreviewConfirmOpen = ref(false)
const requestDownloadConfirmOpen = ref(false)
const requestPreviewTarget = ref<any | null>(null)
const requestDownloadTarget = ref<any | null>(null)

// Scrollbar auto-hide timers
const scrollbarHideTimers = new Map<HTMLElement, ReturnType<typeof setTimeout>>()

const showScrollbar = (event: MouseEvent) => {
  const target = event.currentTarget as HTMLElement
  target.classList.add('scrollbar-visible')
  target.classList.remove('scrollbar-hidden')
  
  // Clear any existing hide timer
  const timer = scrollbarHideTimers.get(target)
  if (timer) {
    clearTimeout(timer)
    scrollbarHideTimers.delete(target)
  }
}

const hideScrollbar = (event: MouseEvent) => {
  const target = event.currentTarget as HTMLElement
  const timer = setTimeout(() => {
    target.classList.remove('scrollbar-visible')
    target.classList.add('scrollbar-hidden')
    scrollbarHideTimers.delete(target)
  }, 1000) // Hide after 1 second of no hover
  scrollbarHideTimers.set(target, timer)
}

const onScroll = (event: Event) => {
  const target = event.currentTarget as HTMLElement
  target.classList.add('scrollbar-visible')
  target.classList.remove('scrollbar-hidden')
  
  // Clear any existing hide timer
  const timer = scrollbarHideTimers.get(target)
  if (timer) {
    clearTimeout(timer)
  }
  
  // Set new timer to hide after scrolling stops
  const newTimer = setTimeout(() => {
    if (!target.matches(':hover')) {
      target.classList.remove('scrollbar-visible')
      target.classList.add('scrollbar-hidden')
    }
    scrollbarHideTimers.delete(target)
  }, 1500) // Hide after 1.5 seconds of no scrolling
  scrollbarHideTimers.set(target, newTimer)
}

const isPendingDetails = computed(() => selectedDocumentForDetails.value?.status === 'Pending')
const isRejectedDetails = computed(() => selectedDocumentForDetails.value?.status === 'Rejected')
const isTrashDetails = computed(() => selectedDocumentForDetails.value?.deletedAt || selectedDocumentForDetails.value?.deletedBy)
const isMyDepartmentTab = computed(() => activeTab.value === 'My Department')
const isEmployeeOwnFile = computed(() => {
  if (!currentUser.value || !selectedDocumentForDetails.value) return false
  return normalizedRole.value === 'employee' && selectedDocumentForDetails.value.uploader === currentUser.value.name
})

watch(decisionDialogOpen, (isOpen) => {
  if (!isOpen) {
    decisionAction.value = null
    decisionMessage.value = ''
  }
})

watch(requestDecisionDialogOpen, (isOpen) => {
  if (!isOpen) {
    requestDecisionAction.value = null
    requestDecisionMessage.value = ''
    requestDecisionTarget.value = null
  }
})

watch(requestPreviewConfirmOpen, (isOpen) => {
  if (!isOpen) {
    requestPreviewTarget.value = null
  }
})

watch(requestDownloadConfirmOpen, (isOpen) => {
  if (!isOpen) {
    requestDownloadTarget.value = null
  }
})

const openFileViewer = (file: any) => {
  // Don't open deleted/non-existent docs (e.g. stale search results)
  const docIds = new Set((props.documents || []).map((d: any) => d.id))
  if (file?.id != null && !docIds.has(file.id)) {
    smartSearchResults.value = smartSearchResults.value.filter((r: any) => r.id !== file.id)
    toast.error('This document no longer exists and was removed from search results.')
    return
  }
  selectedDocumentForDetails.value = file
  documentDetailsOpen.value = true
}

/**
 * Close file viewer
 */
const closeFileViewer = () => {
  fileViewerOpen.value = false
  viewerFileUrl.value = null
  viewerFileName.value = null
  viewerFileType.value = null
}

const startDecisionDialog = (action: 'approve' | 'reject') => {
  if (!selectedDocumentForDetails.value) {
    toast.warning('Select a document to continue')
    return
  }

  decisionAction.value = action
  decisionMessage.value = ''
  decisionDialogOpen.value = true
}

const confirmDecision = async () => {
  if (!selectedDocumentForDetails.value || !decisionAction.value) {
    decisionDialogOpen.value = false
    return
  }

  const documentId = selectedDocumentForDetails.value.id
  const action = decisionAction.value
  const endpoint = action === 'approve' 
    ? `/documents/${documentId}/approve`
    : `/documents/${documentId}/reject`

  try {
    await api.post(endpoint, {
      review_message: decisionMessage.value.trim() || undefined,
    })

    // Close dialog
    decisionDialogOpen.value = false
    decisionMessage.value = ''
    decisionAction.value = null

    // Close document details dialog
    documentDetailsOpen.value = false

    // Show success message
    if (action === 'approve') {
      toast.success('Document approved successfully')
    } else {
      toast.success('Document rejected successfully')
    }

    // Refetch documents to update the list and move to correct tab
    await refetchDocuments()
  } catch (error: any) {
    console.error('Decision error:', error)
    
    if (error.response?.status === 403) {
      toast.error('You do not have permission to perform this action')
    } else if (error.response?.status === 422) {
      // Validation errors
      const errors = error.response.data.errors || {}
      const firstError = Object.values(errors).flat()[0] as string
      toast.error(firstError || 'Validation error')
    } else {
      toast.error(error.response?.data?.message || `Failed to ${action} document`)
    }
  }
}

const startRequestDecision = (
  kind: 'upload' | 'permission',
  record: any,
  action: 'approve' | 'reject',
) => {
  if (!record) {
    toast.warning('Select a request to continue')
    return
  }

  requestDecisionTarget.value = { kind, record }
  requestDecisionAction.value = action
  requestDecisionMessage.value = ''
  requestDecisionDialogOpen.value = true
}

/**
 * Confirm request decision (approve/reject access request)
 */
const confirmRequestDecision = async () => {
  if (!requestDecisionTarget.value || !requestDecisionAction.value) {
    requestDecisionDialogOpen.value = false
    return
  }

  const { record } = requestDecisionTarget.value
  const action = requestDecisionAction.value
  const accessRequestId = record.id || record._original?.id

  if (!accessRequestId) {
    toast.error('Access request ID not found')
    return
  }

  try {
    const endpoint = action === 'approve'
      ? `/document-access-requests/${accessRequestId}/approve`
      : `/document-access-requests/${accessRequestId}/reject`

    const response = await api.post(endpoint, {
      review_message: requestDecisionMessage.value.trim() || null,
    })

    toast.success(response.data.message || `Request ${action}d successfully`)

    // Refetch documents and access requests
    await refetchDocuments()

    // Close dialog and reset
    requestDecisionDialogOpen.value = false
    requestDecisionAction.value = null
    requestDecisionMessage.value = ''
    requestDecisionTarget.value = null
  } catch (error: any) {
    console.error('Error processing request decision:', error)

    // Handle validation errors
    if (error.response?.status === 422) {
      const errors = error.response.data.errors || {}
      const firstError = Object.values(errors).flat()[0]
      toast.error(firstError || 'Validation error occurred')
    } else if (error.response?.status === 403) {
      toast.error('You do not have permission to perform this action.')
    } else if (error.response?.data?.message) {
      toast.error(error.response.data.message)
    } else {
      toast.error(`Failed to ${action} request. Please try again.`)
    }
  }
}

/**
 * Open Edit Access dialog
 */
const openEditAccess = (request: any) => {
  if (!request) {
    toast.warning('Select a request to continue')
    return
  }

  editAccessTarget.value = request
  editAccessMessage.value = ''
  editAccessDialogOpen.value = true
}

/**
 * Confirm Edit Access (change approved to rejected or vice versa)
 */
const confirmEditAccess = async () => {
  if (!editAccessTarget.value) {
    editAccessDialogOpen.value = false
    return
  }

  const request = editAccessTarget.value
  const accessRequestId = request.id || request._original?.id

  if (!accessRequestId) {
    toast.error('Access request ID not found')
    return
  }

  // Determine new status (opposite of current)
  const currentStatus = request.status || request._original?.status || ''
  const newStatus = currentStatus.toLowerCase() === 'approved' ? 'rejected' : 'approved'

  try {
    const response = await api.put(`/document-access-requests/${accessRequestId}`, {
      status: newStatus,
      review_message: editAccessMessage.value.trim() || null,
    })

    toast.success(response.data.message || 'Access request updated successfully')

    // Refetch documents and access requests
    await refetchDocuments()

    // Close dialog and reset
    editAccessDialogOpen.value = false
    editAccessMessage.value = ''
    editAccessTarget.value = null
  } catch (error: any) {
    console.error('Error updating access request:', error)

    // Handle validation errors
    if (error.response?.status === 422) {
      const errors = error.response.data.errors || {}
      const firstError = Object.values(errors).flat()[0]
      toast.error(firstError || 'Validation error occurred')
    } else if (error.response?.status === 403) {
      toast.error('You do not have permission to perform this action.')
    } else if (error.response?.data?.message) {
      toast.error(error.response.data.message)
    } else {
      toast.error('Failed to update access request. Please try again.')
    }
  }
}

const openRequestPreviewConfirm = (record: any) => {
  if (!record) {
    toast.warning('Select a request first')
    return
  }
  requestPreviewTarget.value = record
  requestPreviewConfirmOpen.value = true
}

const confirmRequestPreview = () => {
  if (requestPreviewTarget.value) {
    previewRequestDocument(requestPreviewTarget.value)
  }
  requestPreviewConfirmOpen.value = false
}

const openRequestDownloadConfirm = (record: any) => {
  if (!record) {
    toast.warning('Select a request first')
    return
  }
  requestDownloadTarget.value = record
  requestDownloadConfirmOpen.value = true
}

const confirmRequestDownload = () => {
  if (requestDownloadTarget.value) {
    downloadRequestDocument(requestDownloadTarget.value)
  }
  requestDownloadConfirmOpen.value = false
}

/**
 * Download file
 */
const downloadViewerFile = () => {
  if (!viewerFileUrl.value || !viewerFileName.value) return
  const link = document.createElement('a')
  link.href = viewerFileUrl.value
  link.download = viewerFileName.value
  link.target = '_blank'
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  toast.success('Download started')
}

/**
 * Open file in new tab
 */
const openInNewTab = () => {
  if (!viewerFileUrl.value) return
  window.open(viewerFileUrl.value, '_blank')
  toast.success('Opening in new tab')
}

// ============================================================================
// SMART SEARCH
// ============================================================================
const smartSearchDialogOpen = ref(false)
const smartSearchQuery = ref('')
const smartSearchLoading = ref(false)
const smartSearchResults = ref<any[]>([])
// Only show search results that still exist in the current document list (avoids showing deleted/non-existent docs)
const visibleSearchResults = computed(() => {
  const docIds = new Set((props.documents || []).map((d: any) => d.id))
  return smartSearchResults.value.filter((r: any) => docIds.has(r.id))
})
// UI only: allow user to pick between Keyword vs Context search modes.
// Both modes currently use the same underlying keyword search logic.
const smartSearchMode = ref<'keywords' | 'context'>('keywords')

watch(smartSearchDialogOpen, (isOpen) => {
  if (!isOpen) {
    // Reset search when dialog closes (optional - you can keep results if preferred)
    // smartSearchQuery.value = ''
    // smartSearchResults.value = []
  }
})


/**
 * Perform smart search
 */
const performSmartSearch = async () => {
  if (!smartSearchQuery.value.trim()) {
    toast.warning('Please enter a search query')
    return
  }

  const query = smartSearchQuery.value.trim()
  smartSearchLoading.value = true
  smartSearchResults.value = []

  try {
    // NOTE: Context search is not yet implemented. Both modes currently
    // delegate to the same keyword-based search API for now.
    if (smartSearchMode.value === 'keywords') {
      await performKeywordSearch(query)
    } else {
      await performKeywordSearch(query)
    }
  } finally {
    smartSearchLoading.value = false
  }
}

/**
 * Perform keyword search using Meilisearch
 */
const performKeywordSearch = async (query: string) => {
  try {
    const response = await api.get('/documents/search', {
      params: {
        q: query,
        mode: 'keywords',
      },
    })

    const results = response.data.results || []
    // Exclude any deleted documents (backend also filters; this is a safety net)
    const nonDeleted = results.filter((doc: any) => !doc.deleted_at)
    // Transform results to match All Files tab format using transformDocument
    const transformedResults = nonDeleted.map((doc: any) => transformDocument(doc))

    smartSearchResults.value = transformedResults

    if (transformedResults.length === 0) {
      toast.info('No files found matching your search. Try different keywords or switch to context search.')
    } else {
      toast.success(`Found ${transformedResults.length} relevant file${transformedResults.length > 1 ? 's' : ''}`)
    }
  } catch (error: any) {
    console.error('Keyword search error:', error)
    toast.error(error.response?.data?.message || 'Failed to perform search. Please try again.')
    smartSearchResults.value = []
  }
}

// Contextual search (vector) is currently disabled; smart search always uses keyword search.



// ============================================================================
// FILTERS & DROPDOWNS
// ============================================================================
const search = ref('')
const selectedType = ref('All')
const selectedDept = ref('All')
const selectedAccess = ref<'All' | 'Public' | 'Private' | 'Department'>('All')
const selectedTags = ref<string[]>([])
const fileTypes = ['All', 'PDF', 'Word', 'Excel', 'PPT']
const departments = computed(() => {
  const deptCodes = props.departments.map((d: any) => d.code)
  return ['All', ...deptCodes]
})
const accesses = ['All', 'Public', 'Private', 'Department']

// ============================================================================
// REQUEST FILTERS
// ============================================================================
const selectedReqType = ref('All')
const selectedReqDept = ref('All')
const selectedReqAccess = ref<'All' | 'Public' | 'Private' | 'Department'>('All')
const selectedReqTags = ref<string[]>([])
const selectedReqStatus = ref<'All' | 'Pending' | 'Approved' | 'Rejected'>('All')
const requestTypes = ['All', 'PDF', 'Word', 'Excel', 'PPT']
const requestStatuses = ['All', 'Pending', 'Approved', 'Rejected']

/**
 * Infer file type from mime type (prioritized) or filename extension
 */
const inferTypeFromName = (name: string, mimeType?: string) => {
  // Prioritize mime type if available (more reliable)
  if (mimeType) {
    if (mimeType === 'application/pdf') return 'PDF'
    if (mimeType === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' || 
        mimeType === 'application/msword') return 'Word'
    if (mimeType === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' ||
        mimeType === 'application/vnd.ms-excel') return 'Excel'
    if (mimeType === 'application/vnd.openxmlformats-officedocument.presentationml.presentation' ||
        mimeType === 'application/vnd.ms-powerpoint') return 'PPT'
  }
  
  // Fall back to file extension if mime type not available
  const ext = (name.split('.').pop() || '').toLowerCase()
  if (['doc', 'docx'].includes(ext)) return 'Word'
  if (ext === 'pdf') return 'PDF'
  if (['xls', 'xlsx'].includes(ext)) return 'Excel'
  if (['ppt', 'pptx'].includes(ext)) return 'PPT'
  return (ext || 'OTHER').toUpperCase()
}

const isPdfTypeLabel = (type?: string) => (type || '').toUpperCase() === 'PDF'

const resolveRequestType = (record: any): string => {
  if (!record) return ''
  return record.type || inferTypeFromName(
    record.fullName || record.name || '',
    record._original?.document?.mime_type,
  )
}

const canPreviewRequestDocument = (record: any): boolean => {
  if (!record) return false
  return isPdfTypeLabel(resolveRequestType(record))
}

const getRequestDocumentId = (record: any): number | null => {
  if (!record) return null
  if (record.documentId) return record.documentId
  if (record._original?.document_id) return record._original.document_id
  if (record._original?.document?.id) return record._original.document.id
  return null
}

const previewRequestDocument = (record: any) => {
  if (!record) return
  if (!canPreviewRequestDocument(record)) {
    toast.warning('Preview is available for PDF files only')
    return
  }

  const documentId = getRequestDocumentId(record)
  if (!documentId) {
    toast.error('Document preview is unavailable')
    return
  }

  window.open(`/documents/${documentId}/preview`, '_blank')
  toast.success('Opening document preview')
}

const downloadRequestDocument = (record: any) => {
  if (!record) return
  const documentId = getRequestDocumentId(record)
  if (!documentId) {
    toast.error('Document download is unavailable')
    return
  }

  const link = document.createElement('a')
  link.href = `/documents/${documentId}/download`
  link.target = '_blank'
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  toast.success('Download started')
}

/**
 * Format file size from bytes to readable format
 */
const formatFileSize = (bytes: number): string => {
  if (bytes === 0) return '0 B'
  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return Math.round(bytes / Math.pow(k, i) * 100) / 100 + sizes[i]
}

/**
 * Truncate file name to prevent overflow (horizontal truncation with ellipsis)
 */
const truncateFileName = (fileName: string, maxLength: number = 30): string => {
  if (!fileName || fileName.length <= maxLength) return fileName
  
  // Check if file has extension
  const lastDotIndex = fileName.lastIndexOf('.')
  if (lastDotIndex === -1 || lastDotIndex === 0) {
    // No extension or starts with dot, just truncate
    return fileName.substring(0, maxLength - 3) + '...'
  }
  
  const extension = fileName.substring(lastDotIndex + 1)
  const nameWithoutExt = fileName.substring(0, lastDotIndex)
  
  // Calculate available space for name (maxLength - extension - 3 for "...")
  const availableLength = maxLength - extension.length - 3
  if (availableLength <= 0) {
    // Extension is too long, just show extension
    return '...' + extension
  }
  
  const truncatedName = nameWithoutExt.substring(0, availableLength)
  return `${truncatedName}...${extension}`
}

/**
 * Transform backend document to frontend format
 */
const transformDocument = (doc: any) => {
  const type = inferTypeFromName(doc.file_name, doc.mime_type)
  const reviewedAt = doc.reviewed_at ? new Date(doc.reviewed_at) : null
  
  return {
    id: doc.id,
    name: truncateFileName(doc.file_name),
    fullName: doc.file_name, // Keep full name for tooltips/details
    uploader: doc.user?.name || 'Unknown',
    type,
    department: doc.department?.code || '—',
    access: doc.accessibility === 'public' ? 'Public' : doc.accessibility === 'private' ? 'Private' : 'Department',
    created: doc.created_at ? new Date(doc.created_at).toISOString().split('T')[0] : '',
    size: formatFileSize(doc.size || 0),
    tags: doc.tags?.map((t: any) => t.name) || [],
    description: doc.description || '',
    approvedBy: doc.reviewed_by && doc.status === 'approved' ? (doc.reviewer?.name || '') : '',
    approvedAt: doc.status === 'approved' && reviewedAt ? reviewedAt.toLocaleString('en-US', { 
      year: 'numeric', 
      month: '2-digit', 
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit'
    }) : null,
    rejectedBy: doc.reviewed_by && doc.status === 'rejected' ? (doc.reviewer?.name || '') : '',
    rejectedAt: doc.status === 'rejected' && reviewedAt ? reviewedAt.toLocaleString('en-US', { 
      year: 'numeric', 
      month: '2-digit', 
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit'
    }) : null,
    status: doc.status === 'approved' ? 'Approved' : doc.status === 'rejected' ? 'Rejected' : 'Pending',
    reviewedAt: reviewedAt,
    reviewMessage: doc.review_message || null,
    reviewNotes: doc.review_message || null, // Alias for reviewMessage
    // Backend fields for reference
    _original: doc,
  }
}

/**
 * Transform backend access request to frontend format
 */
const transformAccessRequest = (req: any) => {
  const doc = req.document
  const type = inferTypeFromName(doc?.file_name || '', doc?.mime_type)
  const ownerName = doc?.user?.name || 'Unknown'
  const ownerEmail = doc?.user?.email || ''
  const documentId = doc?.id ?? req.document_id ?? null
  const departmentCode = doc?.department?.code || '—'
  const departmentName = doc?.department?.name || '—'
  const size = typeof doc?.size === 'number' ? formatFileSize(doc.size) : '—'
  const requestedDate = req.requested_at ? new Date(req.requested_at) : null
  const requestedDateString = requestedDate ? requestedDate.toISOString().split('T')[0] : ''
  
  return {
    id: req.id,
    documentId,
    name: truncateFileName(doc?.file_name || 'Unknown'),
    fullName: doc?.file_name || 'Unknown', // Keep full name for tooltips/details
    type, // Include type in transformed request
    requester: req.requester?.name || 'Unknown',
    requestedAt: requestedDateString,
    uploadedAt: requestedDateString,
    status: req.status === 'approved' ? 'Approved' : req.status === 'rejected' ? 'Rejected' : 'Pending',
    department: departmentCode,
    departmentName,
    approvedBy: req.reviewer?.name || '',
    decisionAt: req.reviewed_at ? new Date(req.reviewed_at).toISOString().split('T')[0] : '',
    access: doc?.accessibility === 'public' ? 'Public' : doc?.accessibility === 'private' ? 'Private' : 'Department',
    requestMessage: req.request_message || null,
    reviewMessage: req.review_message || null,
    uploader: ownerName,
    ownerEmail,
    size,
    tags: Array.isArray(doc?.tags) ? doc.tags.map((t: any) => t.name) : [],
    // Backend fields for reference
    _original: req,
  }
}

// ============================================================================
// TABS & NAVIGATION
// ============================================================================
const navLinks = [
  { title: 'All Files', icon: Files },
  { title: 'My Department', icon: Building2 },
  { title: 'Request', icon: SendIcon },
  { title: 'Trash', icon: Trash2Icon },
]

/**
 * Filter navigation links based on user role
 * Employees should not see the Trash tab
 */
const visibleNavLinks = computed(() => {
  if (normalizedRole.value === 'employee') {
    return navLinks.filter(link => link.title !== 'Trash')
  }
  return navLinks
})

const activeTab = ref('All Files')
const allFilesInnerTab = ref<'Approved' | 'Pending' | 'Rejected'>('Approved')
const departmentInnerTab = ref<'Approved' | 'Pending' | 'Rejected'>('Approved')
const requestInternalTab = ref<'Upload' | 'Permission'>('Upload')

/**
 * Determine which inner tabs should be visible in All Files tab based on user role
 */
const visibleAllFilesTabs = computed(() => {
  if (isAdminUser.value) {
    return ['Approved', 'Pending', 'Rejected']
  }
  // Department Manager and Employee: only show Approved
  return ['Approved']
})

/**
 * Watch allFilesInnerTab to ensure non-admin users can only access Approved tab
 */
watch(allFilesInnerTab, (newTab) => {
  if (!isAdminUser.value && newTab !== 'Approved') {
    allFilesInnerTab.value = 'Approved'
  }
})

/**
 * Watch requestInternalTab to ensure proper tab access based on role
 */
watch(requestInternalTab, (newTab) => {
  // Employees can only access Outgoing tab
  if (normalizedRole.value === 'employee' && newTab === 'Upload') {
    requestInternalTab.value = 'Permission'
  }
  // Admin can only access Incoming tab
  if (isAdminUser.value && newTab === 'Permission') {
    requestInternalTab.value = 'Upload'
  }
})

/**
 * Watch activeTab to ensure proper default tab when switching to Request tab
 */
watch(activeTab, (newTab) => {
  if (newTab === 'Request') {
    // Employees default to Outgoing
    if (normalizedRole.value === 'employee') {
      requestInternalTab.value = 'Permission'
    }
    // Admin defaults to Incoming
    else if (isAdminUser.value) {
      requestInternalTab.value = 'Upload'
    }
  }
  // Redirect employees away from Trash tab if they somehow access it
  if (newTab === 'Trash' && normalizedRole.value === 'employee') {
    activeTab.value = 'All Files'
  }
})

// ============================================================================
// DATA STORES - Transform backend data to frontend format
// ============================================================================
// Use ref instead of computed so we can mutate for approval/rejection actions
const files = ref<any[]>([])

// Transform and populate files when props change
// Use a flag to prevent race conditions during reloads
const isReloading = ref(false)
let reloadTimeout: ReturnType<typeof setTimeout> | null = null

watch(() => props.documents, (newDocs) => {
  if (!newDocs || !Array.isArray(newDocs)) {
    console.warn('[Documents] props.documents is not an array:', newDocs)
    if (!isReloading.value) {
      files.value = []
    }
    return
  }
  
  // Skip watch update if we're in the middle of a reload (onSuccess will handle it)
  if (isReloading.value) {
    console.log('[Documents] Skipping watch update during reload, count:', newDocs.length)
    // Safety: If reload flag is stuck, reset it after 5 seconds
    if (reloadTimeout) {
      clearTimeout(reloadTimeout)
    }
    reloadTimeout = setTimeout(() => {
      if (isReloading.value) {
        console.warn('[Documents] Reload flag stuck, resetting it')
        isReloading.value = false
      }
    }, 5000)
    return
  }
  
  console.log('[Documents] Updating files from props, count:', newDocs.length)
  console.log('[Documents] First 5 document IDs:', newDocs.slice(0, 5).map((d: any) => ({ id: d.id, name: d.file_name, status: d.status })))
  files.value = newDocs.map(transformDocument)
  console.log('[Documents] Files updated, new count:', files.value.length)
  console.log('[Documents] First 5 transformed files:', files.value.slice(0, 5).map((f: any) => ({ id: f.id, name: f.name, status: f.status })))
}, { immediate: true, deep: true })

/**
 * Requests data - Transform from backend access requests
 */
const requests = ref<any[]>([])
const uploads = ref<any[]>([])
const permissions = ref<any[]>([])

watch(() => props.accessRequests, (newReqs) => {
  const transformed = newReqs.map(transformAccessRequest)
  requests.value = transformed
  uploads.value = transformed.map((req: any) => ({ ...req }))
  permissions.value = transformed.map((req: any) => ({ ...req }))
}, { immediate: true })

const isIncomingRequestForUser = (request: any): boolean => {
  if (!request || !currentUser.value) return false
  // Outgoing requests (where user is the requester) should not appear in Incoming
  if ((request.requester || '') === currentUser.value.name) {
    return false
  }
  // Admin: see all incoming requests
  if (isAdminUser.value) {
    return true
  }
  // Department Manager: see requests where document belongs to their department
  if (isDepartmentManager.value) {
    // Get request's document department code
    const requestDeptCode = request.department || request._original?.document?.department?.code || ''
    // Get user's department code
    const userDeptCode = getUserDepartmentCode()
    return requestDeptCode === userDeptCode
  }
  // Employee: cannot see incoming requests (cannot approve/reject)
  return false
}

const isOutgoingRequestForUser = (request: any): boolean => {
  if (!request || !currentUser.value) return false
  // Admin: see all outgoing requests
  if (isAdminUser.value) {
    return true
  }
  // Department Manager and Employee: only see their own outgoing requests
  return (request.requester || '') === currentUser.value.name
}

const canReviewRequest = (request: any): boolean => {
  return !!request && request.status === 'Pending' && isIncomingRequestForUser(request)
}


/**
 * Trash files - Soft deleted documents
 */
const trashFiles = ref<any[]>([])

// Transform and populate trash files when props change
watch(() => props.trashedDocuments, (newTrashed) => {
  trashFiles.value = newTrashed.map((doc: any) => {
    const transformed = transformDocument(doc)
    return {
      ...transformed,
      deletedAt: doc.deleted_at ? new Date(doc.deleted_at).toISOString() : null,
      deletedBy: doc.deleted_by_user?.name || 'System',
    }
  })
}, { immediate: true })

// ============================================================================
// PAGINATION
// ============================================================================
const itemsPerPage = ref(12)
const currentPage = ref(1)
const departmentPage = ref(1)

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
 * Get visible tags that fit in the card (max 3 tags visible)
 */
const getVisibleTags = (tags: string[]): string[] => {
  if (!tags || tags.length === 0) return []
  return tags.slice(0, 3)
}

/**
 * Get count of hidden tags (tags beyond the visible limit)
 */
const getHiddenTagsCount = (tags: string[]): number => {
  if (!tags || tags.length === 0) return 0
  return Math.max(0, tags.length - 3)
}

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

const statusIconComponent = (status: string) => {
  switch (status) {
    case 'Approved': return CheckCircle
    case 'Rejected': return XCircle
    default: return Clock
  }
}

const statusIconClass = (status: string) => {
  switch (status) {
    case 'Approved': return 'text-emerald-500'
    case 'Rejected': return 'text-red-500'
    default: return 'text-amber-500'
  }
}



/**
 * Get text color for access level (for text-only display)
 */
const getAccessTextColor = (access: string) => {
  switch (access) {
    case 'Public': return 'text-emerald-600 dark:text-emerald-400'
    case 'Private': return 'text-amber-600 dark:text-amber-400'
    case 'Department': return 'text-indigo-600 dark:text-indigo-400'
    default: return 'text-gray-400 dark:text-neutral-500'
  }
}

/**
 * Get border color class for access level
 */
const getAccessBorderClass = (access: string) => {
  switch (access) {
    case 'Public':
      return 'border-emerald-500/60 dark:border-emerald-400/70'
    case 'Private':
      return 'border-amber-500/60 dark:border-amber-400/70'
    case 'Department':
      return 'border-indigo-500/60 dark:border-indigo-400/70'
    default:
      return 'border-gray-200 dark:border-neutral-700'
  }
}

// ============================================================================
// USER METADATA (from backend when available; fallback for display only)
// ============================================================================
/**
 * Get user metadata (name, position, department) for display.
 * Uses fallback values when backend does not provide full user details.
 */
const getUserMeta = (name: string, fallbackDept?: string) => {
  const department = fallbackDept || '—'
  return { name: name || '—', position: 'Staff', department }
}

const getRequesterDepartmentCode = (request: any, fallbackCode?: string) => {
  if (!request) return fallbackCode || '—'
  const fallback = fallbackCode || request.department || '—'
  const requesterName = request.requester || ''
  const userMeta = requesterName ? getUserMeta(requesterName, fallback) : null
  const deptNameOrCode = userMeta?.department || fallback

  const matchedDept = props.departments.find((dept: any) => {
    const target = (deptNameOrCode || '').toString().toLowerCase()
    return (
      dept.code?.toLowerCase() === target ||
      dept.name?.toLowerCase() === target
    )
  })

  return matchedDept?.code || deptNameOrCode || fallback || '—'
}

// ============================================================================
// TAG MANAGEMENT
// ============================================================================
const tagOptions = ref<string[]>([])

watch(() => props.tags, (newTags) => {
  tagOptions.value = newTags.map((t: any) => t.name)
}, { immediate: true })

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
    toast.success('Tag updated')
  }
}

/**
 * Delete a tag option
 */
const deleteOptionTag = (name: string) => {
  tagOptions.value = tagOptions.value.filter(t => t !== name)
  toast.warning('Tag removed')
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
 * Confirm tag add
 */
const confirmAdminTagAdd = () => {
  const t = newAdminTag.value.trim()
  if (!t || tagOptions.value.includes(t)) {
    tagAddModalOpen.value = false
    return
  }
  tagOptions.value = [t, ...tagOptions.value]
  toast.success('Tag added')
  tagAddModalOpen.value = false
}

/**
 * Toggle tag selection for multi-select filter
 */
const toggleTagSelection = (tag: string) => {
  const index = selectedTags.value.indexOf(tag)
  if (index > -1) {
    selectedTags.value.splice(index, 1)
  } else {
    selectedTags.value.push(tag)
  }
}

/**
 * Toggle tag selection for request filters
 */
const toggleTagSelectionForRequest = (tag: string) => {
  const index = selectedReqTags.value.indexOf(tag)
  if (index > -1) {
    selectedReqTags.value.splice(index, 1)
  } else {
    selectedReqTags.value.push(tag)
  }
}

/**
 * Handle tag select for request filters (for Select component compatibility)
 */
const handleTagSelectForRequest = () => {
  // This is handled by toggleTagSelectionForRequest, but kept for Select component compatibility
}

/**
 * Handle tag select (for Select component compatibility)
 */
const handleTagSelect = () => {
  // This is handled by toggleTagSelection, but kept for Select component compatibility
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
  return list.filter(f => 
    (f.name || '').toLowerCase().includes(q) ||
    (f.uploader || '').toLowerCase().includes(q)
  )
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
 * Apply tag filter (multi-select)
 */
const applyTagFilter = (list: any[]) => {
  if (selectedTags.value.length === 0) return list
  return list.filter(f => {
    if (!f.tags || f.tags.length === 0) return false
    return selectedTags.value.some(tag => f.tags.includes(tag))
  })
}

// ============================================================================
// FILE VISIBILITY & ACCESS CONTROL
// ============================================================================

/**
 * Check if current user has an approved access request for the document
 */
const hasApprovedAccessRequest = (file: any): boolean => {
  if (!currentUser.value || !file) return false

  const documentId = file.id || file._original?.id || file.documentId

  if (!documentId) return false

  // Check all access requests (requests, uploads, permissions are all access requests)
  const allRequests = [...requests.value, ...uploads.value, ...permissions.value]

  const approvedRequest = allRequests.find((req: any) => {
    const reqDocumentId = req.documentId || req._original?.document_id || req._original?.document?.id
    const requesterName = req.requester || req._original?.requester?.name || req._original?.user?.name
    const isApproved = req.status === 'Approved' || req._original?.status === 'approved'

    return (
      reqDocumentId === documentId &&
      requesterName === currentUser.value?.name &&
      isApproved
    )
  })

  return !!approvedRequest
}

/**
 * Check if current user can view file
 */
const canViewFile = (file: any) => {
  if (!currentUser.value) {
    console.log('[canViewFile] No current user, denying access to file:', file.id)
    return false
  }
  
  // Public files: everyone can see
  if (file.access === 'Public') {
    return true
  }
  
  // Private files: uploader, admin, department_manager, and employee can see
  if (file.access === 'Private') {
    const canView = file.uploader === currentUser.value.name || 
           isAdminUser.value || 
           isDepartmentManager.value || 
           normalizedRole.value === 'employee'
    if (!canView) {
      console.log('[canViewFile] Private file access denied:', file.id, 'uploader:', file.uploader, 'currentUser:', currentUser.value.name)
    }
    return canView
  }
  
  // Department files: users from the same department, admin, department_manager, and employee can see
  if (file.access === 'Department') {
    return file.department === currentUser.value.department || 
           isAdminUser.value || 
           isDepartmentManager.value || 
           normalizedRole.value === 'employee'
  }
  
  return false
}

/**
 * Check if current user can preview document
 * For non-admin: only if file is Public OR (Department AND user's dept matches) OR (Private AND same department for department_manager) AND file is PDF
 * For admin: always if PDF
 */
const canPreviewDocument = (file: any): boolean => {
  if (!currentUser.value || !file) return false
  
  // Must be PDF
  const isPdf = file.type === 'PDF' || 
                (file.name || '').toLowerCase().endsWith('.pdf') ||
                (file._original?.mime_type || '').includes('pdf')
  
  if (!isPdf) return false
  
  // Admin can always preview PDFs
  if (isAdminUser.value) return true
  
  // Get department codes for comparison
  const fileDeptCode = file.department || file._original?.department?.code || ''
  let userDeptCode = ''
  const user = currentUser.value
  if (user) {
    if (user.department_id) {
      const userDept = props.departments.find((d: any) => d.id === user.department_id)
      userDeptCode = userDept?.code || ''
    } else {
      // Fallback: try to match by name or use as-is if it's already a code
      userDeptCode = user.department || ''
    }
  }
  
  // Check if user has approved access request for this document
  if (hasApprovedAccessRequest(file)) {
    return true
  }

  // Check if user has approved access request for this document
  if (hasApprovedAccessRequest(file)) {
    return true
  }

  // Non-admin: can preview if Public OR (Department AND same department) OR (Private AND same department for department_manager) OR (Private AND user is the owner)
  if (file.access === 'Public') return true
  if (file.access === 'Department' && fileDeptCode === userDeptCode) return true
  if (file.access === 'Private' && isDepartmentManager.value && fileDeptCode === userDeptCode) return true
  if (file.access === 'Private' && file.uploader === currentUser.value.name) return true
  
  return false
}

/**
 * Check if current user can download document
 * For non-admin: only if file is Public OR (Department AND user's dept matches) OR (Private AND same department for department_manager)
 * For admin: always
 */
const canDownloadDocument = (file: any): boolean => {
  if (!currentUser.value || !file) return false
  
  // Admin can always download
  if (isAdminUser.value) return true
  
  // Get department codes for comparison
  const fileDeptCode = file.department || file._original?.department?.code || ''
  let userDeptCode = ''
  const user = currentUser.value
  if (user) {
    if (user.department_id) {
      const userDept = props.departments.find((d: any) => d.id === user.department_id)
      userDeptCode = userDept?.code || ''
    } else {
      // Fallback: try to match by name or use as-is if it's already a code
      userDeptCode = user.department || ''
    }
  }
  
  // Check if user has approved access request for this document
  if (hasApprovedAccessRequest(file)) {
    return true
  }

  // Non-admin: can download if Public OR (Department AND same department) OR (Private AND same department for department_manager) OR (Private AND user is the owner)
  if (file.access === 'Public') return true
  if (file.access === 'Department' && fileDeptCode === userDeptCode) return true
  if (file.access === 'Private' && isDepartmentManager.value && fileDeptCode === userDeptCode) return true
  if (file.access === 'Private' && file.uploader === currentUser.value.name) return true
  
  return false
}

/**
 * Filtered files based on active tab and filters
 */
const filteredFiles = computed(() => {
  if (activeTab.value === 'Trash' || activeTab.value === 'Request') return []
  let list = [...files.value]
  
  console.log('[filteredFiles] Starting with files.value count:', files.value.length)
  console.log('[filteredFiles] Active tab:', activeTab.value, 'Inner tab:', allFilesInnerTab.value)
  
  // IMPORTANT: Filter by user permissions first!
  list = list.filter(f => canViewFile(f))
  console.log('[filteredFiles] After canViewFile filter:', list.length)
  
  if (activeTab.value === 'My Department' && currentUser.value) {
    // Get user's department code for comparison
    // File.department is the department CODE (from transformDocument)
    const userDeptCode = getUserDepartmentCode()
    list = list.filter(f => f.department === userDeptCode)
    console.log('[filteredFiles] After department filter:', list.length)
  }
  
  // Filter by approval status for All Files tab
  if (activeTab.value === 'All Files') {
    if (allFilesInnerTab.value === 'Approved') {
      const beforeFilter = list.length
      list = list.filter(f => f.status === 'Approved')
      console.log('[filteredFiles] After Approved filter:', beforeFilter, '->', list.length, 'files')
      console.log('[filteredFiles] Approved files:', list.map(f => ({ id: f.id, name: f.name, status: f.status })))
    } else if (allFilesInnerTab.value === 'Pending') {
      list = list.filter(f => f.status === 'Pending')
    } else if (allFilesInnerTab.value === 'Rejected') {
      list = list.filter(f => f.status === 'Rejected')
    }
  }
  
  list = applyTypeFilter(list)
  list = applyDeptFilter(list)
  list = applyAccessFilter(list)
  list = applyTagFilter(list)
  list = baseFilesFilteredBySearch(list)
  console.log('[filteredFiles] Final filtered count:', list.length)
  return list
})

/**
 * Count of approved files
 */
const approvedFilesCount = computed(() => {
  let list = [...files.value]
  if (activeTab.value === 'My Department' && currentUser.value) {
    // Get user's department code for comparison
    // File.department is the department CODE (from transformDocument)
    const userDeptCode = getUserDepartmentCode()
    list = list.filter(f => f.department === userDeptCode)
  }
  list = applyTypeFilter(list)
  list = applyDeptFilter(list)
  list = applyAccessFilter(list)
  list = baseFilesFilteredBySearch(list)
  return list.filter(f => f.status === 'Approved').length
})

/**
 * Count of pending files
 */
const pendingFilesCount = computed(() => {
  let list = [...files.value]
  if (activeTab.value === 'My Department' && currentUser.value) {
    // Get user's department code for comparison
    // File.department is the department CODE (from transformDocument)
    const userDeptCode = getUserDepartmentCode()
    list = list.filter(f => f.department === userDeptCode)
  }
  list = applyTypeFilter(list)
  list = applyDeptFilter(list)
  list = applyAccessFilter(list)
  list = baseFilesFilteredBySearch(list)
  return list.filter(f => f.status === 'Pending').length
})

/**
 * Count of rejected files
 */
const rejectedFilesCount = computed(() => {
  let list = [...files.value]
  if (activeTab.value === 'My Department' && currentUser.value) {
    // Get user's department code for comparison
    // File.department is the department CODE (from transformDocument)
    const userDeptCode = getUserDepartmentCode()
    list = list.filter(f => f.department === userDeptCode)
  }
  list = applyTypeFilter(list)
  list = applyDeptFilter(list)
  list = applyAccessFilter(list)
  list = baseFilesFilteredBySearch(list)
  return list.filter(f => f.status === 'Rejected').length
})

/**
 * Base filtered uploads (before pagination)
 */
const filteredUploadsBase = computed(() => {
  let list = uploads.value.filter(isIncomingRequestForUser)
  // Apply filters
  if (selectedReqType.value !== 'All') list = list.filter(u => (u.type || inferTypeFromName(u.name)) === selectedReqType.value)
  if (selectedReqDept.value !== 'All') list = list.filter(u => (u.department || '') === selectedReqDept.value)
  if (selectedReqAccess.value !== 'All') list = list.filter(u => u.access === selectedReqAccess.value)
  if (selectedReqTags.value.length > 0) {
    list = list.filter(u => {
      if (!u.tags || u.tags.length === 0) return false
      return selectedReqTags.value.some(tag => u.tags.includes(tag))
    })
  }
  if (selectedReqStatus.value !== 'All') list = list.filter(u => (u.status || '') === selectedReqStatus.value)
  // Apply search
  const q = search.value.trim().toLowerCase()
  if (q) {
    list = list.filter(u =>
      (u.name || '').toLowerCase().includes(q) ||
      (u.uploader || '').toLowerCase().includes(q) ||
      (u.requester || '').toLowerCase().includes(q)
    )
  }
  return list
})

/**
 * Base filtered permissions (before pagination)
 */
const filteredPermissionsBase = computed(() => {
  let list = permissions.value.filter(isOutgoingRequestForUser)
  // Apply filters
  if (selectedReqType.value !== 'All') list = list.filter(p => (p.type || inferTypeFromName(p.name)) === selectedReqType.value)
  if (selectedReqDept.value !== 'All') list = list.filter(p => (p.department || '') === selectedReqDept.value)
  if (selectedReqAccess.value !== 'All') list = list.filter(p => p.access === selectedReqAccess.value)
  if (selectedReqTags.value.length > 0) {
    list = list.filter(p => {
      if (!p.tags || p.tags.length === 0) return false
      return selectedReqTags.value.some(tag => p.tags.includes(tag))
    })
  }
  if (selectedReqStatus.value !== 'All') list = list.filter(p => (p.status || '') === selectedReqStatus.value)
  // Apply search
  const q = search.value.trim().toLowerCase()
  if (q) {
    list = list.filter(p =>
      (p.name || '').toLowerCase().includes(q) ||
      (p.requester || '').toLowerCase().includes(q) ||
      (p.uploader || '').toLowerCase().includes(q)
    )
  }
  return list
})


/**
 * Filtered uploads for current tab
 */
const filteredUploads = computed(() => {
  if (activeTab.value !== 'Request' || requestInternalTab.value !== 'Upload') return []
  return filteredUploadsBase.value
})

/**
 * Filtered permissions for current tab
 */
const filteredPermissions = computed(() => {
  if (activeTab.value !== 'Request' || requestInternalTab.value !== 'Permission') return []
  return filteredPermissionsBase.value
})

/**
 * Get current user's department code for comparison
 */
const getUserDepartmentCode = (): string => {
  const user = currentUser.value
  if (!user) return ''
  if (user.department_id) {
    const userDept = props.departments.find((d: any) => d.id === user.department_id)
    return userDept?.code || ''
  }
  // Fallback: try to find by name
  const userDeptName = user.department
  const userDept = props.departments.find((d: any) => d.name === userDeptName)
  return userDept?.code || ''
}

const departmentBaseFiles = computed(() => {
  if (!currentUser.value) return []
  
  // Get user's department code for comparison
  // File.department is the department CODE (from transformDocument)
  const userDeptCode = getUserDepartmentCode()
  return files.value.filter(
    (file: any) => canViewFile(file) && file.department === userDeptCode,
  )
})

const departmentCountsBase = computed(() => {
  let list = [...departmentBaseFiles.value]
  list = applyTypeFilter(list)
  list = applyDeptFilter(list)
  list = applyAccessFilter(list)
  list = applyTagFilter(list)
  return baseFilesFilteredBySearch(list)
})

const departmentFilteredFiles = computed(() => {
  let list = [...departmentCountsBase.value]
  
  // For employees: filter Pending and Rejected to show only their own uploads
  const user = currentUser.value
  if (normalizedRole.value === 'employee' && user) {
    if (departmentInnerTab.value === 'Pending') {
      list = list.filter((file) => file.status === 'Pending' && file.uploader === user.name)
    } else if (departmentInnerTab.value === 'Rejected') {
      list = list.filter((file) => file.status === 'Rejected' && file.uploader === user.name)
    }
  }
  
  // Filter by status
  if (departmentInnerTab.value === 'Approved') {
    return list.filter((file) => file.status === 'Approved')
  }
  if (departmentInnerTab.value === 'Pending') {
    return list.filter((file) => file.status === 'Pending')
  }
  if (departmentInnerTab.value === 'Rejected') {
    return list.filter((file) => file.status === 'Rejected')
  }
  return list
})

const departmentPaginatedFiles = computed(() => {
  const start = (departmentPage.value - 1) * itemsPerPage.value
  return departmentFilteredFiles.value.slice(start, start + itemsPerPage.value)
})

const departmentApprovedCount = computed(() =>
  departmentCountsBase.value.filter((file) => file.status === 'Approved').length,
)
const departmentPendingCount = computed(() => {
  let list = departmentCountsBase.value.filter((file) => file.status === 'Pending')
  // For employees: count only their own pending files
  const user = currentUser.value
  if (normalizedRole.value === 'employee' && user) {
    list = list.filter((file) => file.uploader === user.name)
  }
  return list.length
})
const departmentRejectedCount = computed(() => {
  let list = departmentCountsBase.value.filter((file) => file.status === 'Rejected')
  // For employees: count only their own rejected files
  const user = currentUser.value
  if (normalizedRole.value === 'employee' && user) {
    list = list.filter((file) => file.uploader === user.name)
  }
  return list.length
})


/**
 * Paginated files
 */
const paginatedFiles = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage.value
  return filteredFiles.value.slice(start, start + itemsPerPage.value)
})


/**
 * Paginated uploads
 */
const paginatedUploads = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage.value
  return filteredUploads.value.slice(start, start + itemsPerPage.value)
})

/**
 * Paginated permissions
 */
const paginatedPermissions = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage.value
  return filteredPermissions.value.slice(start, start + itemsPerPage.value)
})

/**
 * Reset to page 1 when filters change
 */
watch([
  filteredFiles,
  () => activeTab.value,
  () => allFilesInnerTab.value,
  () => requestInternalTab.value,
  selectedReqType,
  selectedReqDept,
  selectedReqStatus,
  search,
], () => {
  currentPage.value = 1
})

watch(
  [
    departmentFilteredFiles,
    () => activeTab.value,
    () => departmentInnerTab.value,
    selectedType,
    selectedDept,
    selectedAccess,
    selectedTags,
    search,
  ],
  () => {
    if (activeTab.value === 'My Department') {
      departmentPage.value = 1
    }
  },
)


// ============================================================================
// REQUEST SUMMARY COUNTS
// ============================================================================

/**
 * Upload summary counts
 */
const uploadCounts = computed(() => {
  const list = filteredUploadsBase.value
  const approved = list.filter(u => u.status === 'Approved').length
  const pending = list.filter(u => u.status === 'Pending').length
  const rejected = list.filter(u => u.status === 'Rejected').length
  return { approved, pending, rejected, total: list.length }
})

/**
 * Permission summary counts
 */
const permissionCounts = computed(() => {
  const list = filteredPermissionsBase.value
  const approved = list.filter(p => p.status === 'Approved').length
  const pending = list.filter(p => p.status === 'Pending').length
  const rejected = list.filter(p => p.status === 'Rejected').length
  return { approved, pending, rejected, total: list.length }
})


// ============================================================================
// TAB COUNTS
// ============================================================================

// ============================================================================
// MODAL STATE MANAGEMENT
// ============================================================================
const selectedRequest = ref<any | null>(null)
const requestDetailModalOpen = ref(false)
const selectedUpload = ref<any | null>(null)
const uploadDetailModalOpen = ref(false)
const selectedPermission = ref<any | null>(null)
const permissionDetailModalOpen = ref(false)
const permissionHistoryModalOpen = ref(false)

const determineRequestKind = (record: any): 'upload' | 'permission' => {
  if (!record) return 'upload'
  return isIncomingRequestForUser(record) ? 'upload' : 'permission'
}



// Dialog states
const accessDialogOpen = ref(false)
const renameDialogOpen = ref(false)
const requestRenameDialogOpen = ref(false)
const manageTagsDialogOpen = ref(false)
const uploadDialogOpen = ref(false)
const requestAccessDialogOpen = ref(false)
const deleteConfirmDialogOpen = ref(false)
const deletePassword = ref('')
const deleteCountdown = ref(2)
const deleteCountdownInterval = ref<ReturnType<typeof setInterval> | null>(null)
const deleteTargetFile = ref<any | null>(null)

// Restore single document confirmation
const restoreConfirmDialogOpen = ref(false)
const restorePassword = ref('')
const restoreCountdown = ref(2)
const restoreCountdownInterval = ref<ReturnType<typeof setInterval> | null>(null)
const restoreTargetFile = ref<any | null>(null)

// Permanent delete single document confirmation
const permanentDeleteConfirmDialogOpen = ref(false)
const permanentDeletePassword = ref('')
const permanentDeleteCountdown = ref(3)
const permanentDeleteCountdownInterval = ref<ReturnType<typeof setInterval> | null>(null)
const permanentDeleteTargetFile = ref<any | null>(null)

// Restore all confirmation
const restoreAllConfirmDialogOpen = ref(false)
const restoreAllPassword = ref('')
const restoreAllCountdown = ref(3)
const restoreAllCountdownInterval = ref<ReturnType<typeof setInterval> | null>(null)

// Delete all confirmation
const deleteAllConfirmDialogOpen = ref(false)
const deleteAllPassword = ref('')
const deleteAllCountdown = ref(3)
const deleteAllCountdownInterval = ref<ReturnType<typeof setInterval> | null>(null)
const restrictedDialogOpen = ref(false)
const restrictedFor = ref<any | null>(null)
const documentRequestAccessDialogOpen = ref(false)
const documentRequestMessage = ref('')

// ============================================================================
// FILE ACTIONS
// ============================================================================
/**
 * Open upload details modal
 */
const openUploadDetails = (upload: any) => {
  selectedUpload.value = upload
  selectedRequest.value = upload
  requestDetailModalOpen.value = false
  uploadDetailModalOpen.value = true
}

/**
 * Open permission details modal
 */
const openPermissionDetails = (permission: any) => {
  selectedPermission.value = permission
  selectedRequest.value = permission
  requestDetailModalOpen.value = false
  permissionDetailModalOpen.value = true
}


// ============================================================================
// EDIT DETAILS
// ============================================================================
const editDialogOpen = ref(false)
const dialogFile = ref<any | null>(null)
const editDepartmentId = ref<number | null>(null)
const editAccess = ref<'Public' | 'Private' | 'Department'>('Department')
const editDescription = ref('')
const editTags = ref<number[]>([])
const editNewTag = ref('')
const isEditingFromMyDepartment = ref(false)

// Tags for manage tags dialog (separate from edit)
const tempTags = ref<string[]>([])

/**
 * Open edit details modal
 */
const openEditDetails = (file: any) => {
  dialogFile.value = { ...file }
  
  // Check if editing from My Department tab
  isEditingFromMyDepartment.value = isMyDepartmentTab.value
  
  // Set department ID from file
  if (file._original?.department?.id) {
    editDepartmentId.value = file._original.department.id
  } else if (file.department_id) {
    editDepartmentId.value = file.department_id
  } else {
    // Try to find department by name/code
    const dept = props.departments.find((d: any) => 
      d.name === file.department || d.code === file.department
    )
    editDepartmentId.value = dept?.id || null
  }
  
  // If editing from My Department tab, ensure department is set to user's department
  if (isEditingFromMyDepartment.value && currentUser.value?.department_id) {
    editDepartmentId.value = currentUser.value.department_id
  }
  
  // Set access level
  editAccess.value = file.access || 'Department'
  
  // Set description
  editDescription.value = file.description || ''
  
  // Set tags from file
  if (file._original?.tags && Array.isArray(file._original.tags)) {
    editTags.value = file._original.tags.map((t: any) => t.id || t).filter((id: any) => typeof id === 'number')
  } else if (file.tags && Array.isArray(file.tags)) {
    // If tags are strings, find their IDs
    editTags.value = file.tags
      .map((tagName: string) => {
        const tag = props.tags.find((t: any) => t.name === tagName)
        return tag?.id
      })
      .filter((id: any) => typeof id === 'number')
  } else {
    editTags.value = []
  }
  
  editNewTag.value = ''
  editDialogOpen.value = true
}

/**
 * Add tag to edit form
 */
const addEditTag = () => {
  const t = editNewTag.value.trim()
  if (!t) return
  
  // Find tag by name
  const existingTag = props.tags.find((tag: any) => tag.name.toLowerCase() === t.toLowerCase())
  if (existingTag && !editTags.value.includes(existingTag.id)) {
    editTags.value = [...editTags.value, existingTag.id]
  }
  editNewTag.value = ''
}

/**
 * Remove tag from edit form
 */
const removeEditTag = (tagId: number) => {
  editTags.value = editTags.value.filter(id => id !== tagId)
}

/**
 * Remove tag from manage tags list
 */
const removeTag = (tag: string) => {
  tempTags.value = tempTags.value.filter(t => t !== tag)
}

/**
 * Save edited details
 */
const saveEditDetails = async () => {
  if (!dialogFile.value || !editDepartmentId.value) return
  
  try {
    await api.put(`/documents/${dialogFile.value.id}`, {
      description: editDescription.value || '',
      department_id: editDepartmentId.value,
      accessibility: editAccess.value.toLowerCase(),
      tags: editTags.value,
    })
    
    toast.success('Document updated successfully')
    
    // Close dialog and reset form
    editDialogOpen.value = false
    dialogFile.value = null
    editDepartmentId.value = null
    editAccess.value = 'Department'
    editDescription.value = ''
    editTags.value = []
    editNewTag.value = ''
    isEditingFromMyDepartment.value = false
    
    // Reload to get updated documents list from server (use Inertia to keep props in sync)
    // Clear any existing timeout
    if (reloadTimeout) {
      clearTimeout(reloadTimeout)
      reloadTimeout = null
    }
    
    // Prevent multiple concurrent reloads
    if (isReloading.value) {
      console.warn('[Documents] Reload already in progress, skipping edit reload')
      return
    }
    
    isReloading.value = true
    try {
      await router.reload({
        only: ['documents', 'trashedDocuments', 'accessRequests'],
        onSuccess: async () => {
          // Wait for Vue to process the prop updates
          await nextTick()
          console.log('[Documents] Edit reload successful, documents count:', props.documents?.length || 0)
          // Force update files from props
          if (props.documents && Array.isArray(props.documents)) {
            files.value = props.documents.map(transformDocument)
            console.log('[Documents] Files manually updated after edit, count:', files.value.length)
          } else {
            console.warn('[Documents] props.documents is not an array after edit reload:', props.documents)
          }
          // Reset reload flag and reset pagination to first page
          await nextTick()
          currentPage.value = 1
          isReloading.value = false
          if (reloadTimeout) {
            clearTimeout(reloadTimeout)
            reloadTimeout = null
          }
        },
        onError: (errors) => {
          console.error('[Documents] Edit reload error:', errors)
          isReloading.value = false
          if (reloadTimeout) {
            clearTimeout(reloadTimeout)
            reloadTimeout = null
          }
        },
      })
    } catch (error) {
      console.error('[Documents] Edit reload exception:', error)
      isReloading.value = false
      if (reloadTimeout) {
        clearTimeout(reloadTimeout)
        reloadTimeout = null
      }
    }
  } catch (error: any) {
    console.error('Update error:', error)
    
    if (error.response?.status === 403) {
      toast.error('You do not have permission to edit documents')
    } else if (error.response?.status === 422) {
      // Validation errors
      const errors = error.response.data.errors || {}
      const firstError = Object.values(errors).flat()[0] as string
      toast.error(firstError || 'Validation error')
    } else {
      toast.error(error.response?.data?.message || 'Failed to update document')
    }
  }
}

// ============================================================================
// CLIPBOARD UTILITY
// ============================================================================


// ============================================================================
// DIALOG OPENERS
// ============================================================================



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
  toast.success('Access updated')
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
  toast.success('File renamed')
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
  toast.success('Request updated')
  requestRenameDialogOpen.value = false
}

// ============================================================================
// REQUEST STATUS MANAGEMENT
// ============================================================================


// ============================================================================
// REQUEST ACCESS MANAGEMENT
// ============================================================================
const requestAccess = ref<'Public' | 'Private' | 'Department'>('Department')


/**
 * Confirm request access change
 */
const handleManageAccessRequestConfirm = () => {
  if (!selectedRequest.value) return
  requests.value = requests.value.map(r =>
    r.id === selectedRequest.value!.id ? { ...r, access: requestAccess.value } : r
  )
  toast.success('Request access updated')
  requestAccessDialogOpen.value = false
}

// ============================================================================
// REQUEST STATUS UPDATE (FIXED LOGIC)
// ============================================================================





// ============================================================================
// UPLOAD FUNCTIONALITY
// ============================================================================
const uploadDescription = ref('')
const uploadKeywords = ref<string[]>([])
const newKeyword = ref('')
const uploadDepartment = ref<number | null>(null)
const uploadAccess = ref<'Public' | 'Private' | 'Department'>('Department')
const uploadTags = ref<number[]>([])
const uploadNewTag = ref('')
const uploadFile = ref<File | null>(null)
const isUploading = ref(false)

/**
 * Check if uploaded file requires manual summary
 */
// Excel, Word, and PowerPoint files require manual summary (OpenAI Chat API only supports PDF)
const manualSummaryMimeTypes = [
    // Excel files
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx
    'application/vnd.ms-excel', // .xls
    // Word files
    'application/msword', // .doc
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
    // PowerPoint files
    'application/vnd.ms-powerpoint', // .ppt
    'application/vnd.openxmlformats-officedocument.presentationml.presentation', // .pptx
  ]

const requiresManualSummary = computed(() => {
  if (!uploadFile.value) return false
  if (manualSummaryMimeTypes.includes(uploadFile.value.type)) {
    return true
  }

  const name = uploadFile.value.name.toLowerCase()
  const manualExtensions = ['.xlsx', '.xls', '.doc', '.docx', '.ppt', '.pptx']
  return manualExtensions.some((ext) => name.endsWith(ext))
})

const summaryMinChars = 20
const keywordsMinCount = 5

const summaryIsValid = computed(() => {
  if (!requiresManualSummary.value) {
    return true
  }

  return uploadDescription.value.trim().length >= summaryMinChars
})

const keywordsAreValid = computed(() => {
  if (!requiresManualSummary.value) {
    return true
  }

  return uploadKeywords.value.length >= keywordsMinCount
})

const canSubmitUpload = computed(() => {
  if (!uploadFile.value) {
    return false
  }

  return summaryIsValid.value && keywordsAreValid.value
})

/**
 * Initialize upload department based on user role
 */
onMounted(() => {
  if (currentUser.value && !isAdminUser.value) {
    // Non-admin users: auto-set their department
    uploadDepartment.value = currentUser.value.department_id
  } else if (props.departments.length > 0) {
    // Admin: default to first department
    uploadDepartment.value = props.departments[0]?.id || null
  }

  // Subscribe to PDF extraction notifications (admin only)
  if (isAdminUser.value) {
    const echoInstance = getEchoInstance()
    if (echoInstance) {
      try {
        pdfExtractionChannel = echoInstance.private('admin.pdf-extraction')
        
        pdfExtractionChannel.listen('.PdfExtractionCompleted', (data: any) => {
          console.log('[Documents] PDF extraction completed:', data)
          
          if (data.message) {
            toast.success(data.message, {
              duration: 6000,
            })
          }
          
          // Reload documents to get updated data
          // Clear any existing timeout
          if (reloadTimeout) {
            clearTimeout(reloadTimeout)
            reloadTimeout = null
          }
          
          // Prevent multiple concurrent reloads
          if (isReloading.value) {
            console.warn('[Documents] Reload already in progress, skipping PDF extraction reload')
            return
          }
          
          isReloading.value = true
          router.reload({
            only: ['documents', 'trashedDocuments', 'accessRequests'],
            onSuccess: async () => {
              // Wait for Vue to process the prop updates
              await nextTick()
              console.log('[Documents] PDF extraction reload successful, documents count:', props.documents?.length || 0)
              // Force update files from props
              if (props.documents && Array.isArray(props.documents)) {
                files.value = props.documents.map(transformDocument)
                console.log('[Documents] Files manually updated after extraction, count:', files.value.length)
              } else {
                console.warn('[Documents] props.documents is not an array after extraction reload:', props.documents)
              }
              // Reset reload flag and reset pagination to first page
              await nextTick()
              currentPage.value = 1
              isReloading.value = false
              if (reloadTimeout) {
                clearTimeout(reloadTimeout)
                reloadTimeout = null
              }
            },
            onError: (errors) => {
              console.error('[Documents] PDF extraction reload error:', errors)
              isReloading.value = false
              if (reloadTimeout) {
                clearTimeout(reloadTimeout)
                reloadTimeout = null
              }
            },
          })
        })

        // Listen for conversion failures
        pdfExtractionChannel.listen('.DocumentConversionFailed', (data: any) => {
          console.log('[Documents] Document conversion failed:', data)
          
          if (data.message) {
            toast.error(data.message, {
              duration: 8000,
            })
          } else {
            toast.error(`Conversion failed for ${data.file_name}. Please use manual summary instead.`, {
              duration: 8000,
            })
          }
          
          // Reload documents to get updated data
          // Clear any existing timeout
          if (reloadTimeout) {
            clearTimeout(reloadTimeout)
            reloadTimeout = null
          }
          
          // Prevent multiple concurrent reloads
          if (isReloading.value) {
            console.warn('[Documents] Reload already in progress, skipping conversion failure reload')
            return
          }
          
          isReloading.value = true
          router.reload({
            only: ['documents', 'trashedDocuments', 'accessRequests'],
            onSuccess: async () => {
              // Wait for Vue to process the prop updates
              await nextTick()
              console.log('[Documents] Conversion failed reload successful, documents count:', props.documents?.length || 0)
              // Force update files from props
              if (props.documents && Array.isArray(props.documents)) {
                files.value = props.documents.map(transformDocument)
                console.log('[Documents] Files manually updated after conversion failure, count:', files.value.length)
              } else {
                console.warn('[Documents] props.documents is not an array after conversion failure reload:', props.documents)
              }
              // Reset reload flag and reset pagination to first page
              await nextTick()
              currentPage.value = 1
              isReloading.value = false
              if (reloadTimeout) {
                clearTimeout(reloadTimeout)
                reloadTimeout = null
              }
            },
            onError: (errors) => {
              console.error('[Documents] Conversion failure reload error:', errors)
              isReloading.value = false
              if (reloadTimeout) {
                clearTimeout(reloadTimeout)
                reloadTimeout = null
              }
            },
          })
        })
        
        console.log('[Documents] Subscribed to admin.pdf-extraction channel for PDF extraction notifications')
      } catch (error) {
        console.error('[Documents] Error subscribing to PDF extraction channel:', error)
      }
    } else {
      console.warn('[Documents] Echo instance not available for PDF extraction notifications')
    }
  }
})

onUnmounted(() => {
  // Unsubscribe from PDF extraction channel
  if (pdfExtractionChannel) {
    try {
      pdfExtractionChannel.stopListening('.PdfExtractionCompleted')
      pdfExtractionChannel.stopListening('.DocumentConversionFailed')
      console.log('[Documents] Unsubscribed from PDF extraction channel')
    } catch (error) {
      console.error('[Documents] Error unsubscribing from PDF extraction channel:', error)
    }
  }
})

watch(() => uploadFile.value, () => {
  uploadDescription.value = ''
  uploadKeywords.value = []
  newKeyword.value = ''
})

const addKeywordChip = () => {
  const value = newKeyword.value.trim()
  if (!value) {
    return
  }

  if (!uploadKeywords.value.includes(value)) {
    uploadKeywords.value = [...uploadKeywords.value, value]
  }

  newKeyword.value = ''
}

const removeKeywordChip = (keyword: string) => {
  uploadKeywords.value = uploadKeywords.value.filter((k) => k !== keyword)
}

/**
 * Handle file input change
 */
const onFileChange = (e: Event) => {
  const target = e.target as HTMLInputElement
  uploadFile.value = target.files?.[0] || null
}

/**
 * Add tag to upload form (for display only - actual tags are selected from existing tags)
 */
const addUploadTag = () => {
  const t = uploadNewTag.value.trim()
  if (!t) return
  
  // Find tag by name
  const existingTag = props.tags.find((tag: any) => tag.name.toLowerCase() === t.toLowerCase())
  if (existingTag && !uploadTags.value.includes(existingTag.id)) {
    uploadTags.value = [...uploadTags.value, existingTag.id]
  }
  uploadNewTag.value = ''
}

/**
 * Remove tag from upload form
 */
const removeUploadTag = (tagId: number) => {
  uploadTags.value = uploadTags.value.filter(id => id !== tagId)
}

/**
 * Refetch documents from API
 */
const refetchDocuments = async () => {
  try {
    const response = await api.get('/documents')
    const data = response.data

    // Update documents
    if (data.documents) {
      files.value = data.documents.map(transformDocument)
    }

    // Update trashed documents
    if (data.trashedDocuments) {
      trashFiles.value = data.trashedDocuments.map((doc: any) => {
        const transformed = transformDocument(doc)
    return {
          ...transformed,
          deletedAt: doc.deleted_at ? new Date(doc.deleted_at).toISOString() : null,
          deletedBy: doc.deleted_by_user?.name || 'System',
        }
      })
    }

    // Update access requests
    if (data.accessRequests) {
      const transformed = data.accessRequests.map(transformAccessRequest)
      requests.value = transformed
      uploads.value = transformed.map((req: any) => ({ ...req }))
      permissions.value = transformed.map((req: any) => ({ ...req }))
    }
  } catch (error: any) {
    console.error('Error refetching documents:', error)
    toast.error('Failed to refresh documents')
  }
}

/**
 * Handle upload submission
 */
const handleUploadSubmit = async () => {
  if (!uploadFile.value) {
    toast.error('Please select a file to upload')
    return
  }

  if (uploadFile.value.size > 10 * 1024 * 1024) {
    toast.error('File size exceeds 10MB limit')
    return
  }

  // Validate mime type
  const allowedMimeTypes = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
  ]

  if (!allowedMimeTypes.includes(uploadFile.value.type)) {
    toast.error('File must be a PDF, Word, Excel, or PowerPoint document')
    return
  }

  // Validate department (required for admin)
  if (isAdminUser.value && !uploadDepartment.value) {
    toast.error('Please select a department')
    return
  }

  // Non-admin users should have department auto-set
  if (!isAdminUser.value && !currentUser.value?.department_id) {
    toast.error('Department information not found')
    return
  }

  if (requiresManualSummary.value) {
    if (!summaryIsValid.value) {
      toast.error(`Please provide a short summary (at least ${summaryMinChars} characters).`)
      return
    }

    if (!keywordsAreValid.value) {
      toast.error(`Please add at least ${keywordsMinCount} keywords before uploading.`)
      return
    }
  }

  isUploading.value = true

  try {
    const formData = new FormData()
    formData.append('file', uploadFile.value)
    formData.append('description', uploadDescription.value || '')
    formData.append('accessibility', uploadAccess.value.toLowerCase())
    
    if (requiresManualSummary.value) {
      uploadKeywords.value.forEach((keyword) => {
        formData.append('manual_keywords[]', keyword)
      })
    }
    
    // Only send department_id if admin
    if (isAdminUser.value && uploadDepartment.value) {
      formData.append('department_id', uploadDepartment.value.toString())
    }

    // Add tags
    uploadTags.value.forEach((tagId) => {
      formData.append('tags[]', tagId.toString())
    })

    const response = await api.post('/documents', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    })

    toast.success(response.data?.message || 'File uploaded successfully')
    
    // Reset form
    uploadDialogOpen.value = false
    uploadDescription.value = ''
    uploadAccess.value = 'Department'
    uploadTags.value = []
    uploadNewTag.value = ''
    uploadFile.value = null
    uploadKeywords.value = []
    newKeyword.value = ''
    
    // Reset file input
    const fileInput = document.getElementById('upload-file') as HTMLInputElement
    if (fileInput) {
      fileInput.value = ''
    }

    // Reload to get updated documents list from server
    // Clear any existing timeout
    if (reloadTimeout) {
      clearTimeout(reloadTimeout)
      reloadTimeout = null
    }
    
    // Prevent multiple concurrent reloads
    if (isReloading.value) {
      console.warn('[Documents] Reload already in progress, skipping duplicate reload')
      return
    }
    
    isReloading.value = true
    try {
      await router.reload({
        only: ['documents', 'trashedDocuments', 'accessRequests'],
        onSuccess: async () => {
          // Wait for Vue to process the prop updates
          await nextTick()
          console.log('[Documents] Upload reload successful, documents count:', props.documents?.length || 0)
          // Force update files from props
          if (props.documents && Array.isArray(props.documents)) {
            files.value = props.documents.map(transformDocument)
            console.log('[Documents] Files manually updated after upload, count:', files.value.length)
          } else {
            console.warn('[Documents] props.documents is not an array after upload reload:', props.documents)
          }
          // Reset reload flag and reset pagination to first page
          await nextTick()
          currentPage.value = 1
          isReloading.value = false
          if (reloadTimeout) {
            clearTimeout(reloadTimeout)
            reloadTimeout = null
          }
        },
        onError: (errors) => {
          console.error('[Documents] Upload reload error:', errors)
          isReloading.value = false
          if (reloadTimeout) {
            clearTimeout(reloadTimeout)
            reloadTimeout = null
          }
        },
      })
    } catch (error) {
      console.error('[Documents] Upload reload exception:', error)
      isReloading.value = false
      if (reloadTimeout) {
        clearTimeout(reloadTimeout)
        reloadTimeout = null
      }
    }

  } catch (error: any) {
    console.error('Upload error:', error)
    
    if (error.response?.status === 422) {
      // Validation errors
      const errors = error.response.data.errors || {}
      const firstError = Object.values(errors).flat()[0] as string
      toast.error(firstError || 'Validation error')
    } else {
      toast.error(error.response?.data?.message || 'Failed to upload file')
    }
  } finally {
    isUploading.value = false
  }
}

/**
 * Get Echo instance for Reverb broadcasting
 */
const getEchoInstance = (): any => {
  if (typeof window !== 'undefined' && (window as any).Echo) {
    return (window as any).Echo
  }
  return null
}

// Store channel reference for cleanup
let pdfExtractionChannel: any = null

// ============================================================================
// TRASH ACTIONS
// ============================================================================

/**
 * Open restore confirmation dialog
 */
const openRestoreConfirmDialog = (t: any) => {
  restoreTargetFile.value = t
  restoreCountdown.value = 2
  restoreConfirmDialogOpen.value = true
  
  // Start countdown
  if (restoreCountdownInterval.value) {
    clearInterval(restoreCountdownInterval.value)
  }
  
  restoreCountdownInterval.value = setInterval(() => {
    restoreCountdown.value--
    if (restoreCountdown.value <= 0) {
      if (restoreCountdownInterval.value) {
        clearInterval(restoreCountdownInterval.value)
        restoreCountdownInterval.value = null
      }
    }
  }, 1000)
}

/**
 * Confirm restore action with password
 */
const confirmRestore = async () => {
  if (!restoreTargetFile.value) return
  
  if (restorePassword.value.trim() === '') {
    toast.error('Please enter your password to confirm restore')
    return
  }
  
  try {
    const response = await api.post(`/documents/${restoreTargetFile.value.id}/restore`, {
      password: restorePassword.value,
    })
    
    toast.success(response.data?.message || 'File restored successfully')
    
    // Reload to get updated documents list from server
    await router.reload({
      only: ['documents', 'trashedDocuments', 'accessRequests'],
    })
  } catch (error: any) {
    console.error('Error restoring document:', error)
    if (error.response?.status === 403) {
      toast.error('You do not have permission to restore this document.')
    } else if (error.response?.status === 404) {
      toast.error('Document not found.')
    } else {
      toast.error(error.response?.data?.message || 'Failed to restore document. Please try again.')
    }
    throw error
  }
  
  // Close dialog and reset
  restoreConfirmDialogOpen.value = false
  restorePassword.value = ''
  restoreCountdown.value = 2
  restoreTargetFile.value = null
  await router.reload({
    only: ['documents', 'trashedDocuments', 'accessRequests'],
  })
}

/**
 * Restore file from trash (opens confirmation dialog)
 */
const restoreFromTrash = (t: any) => {
  openRestoreConfirmDialog(t)
}

/**
 * Open permanent delete confirmation dialog
 */
const openPermanentDeleteConfirmDialog = (t: any) => {
  permanentDeleteTargetFile.value = t
  permanentDeleteCountdown.value = 3
  permanentDeleteConfirmDialogOpen.value = true
  
  // Start countdown
  if (permanentDeleteCountdownInterval.value) {
    clearInterval(permanentDeleteCountdownInterval.value)
  }
  
  permanentDeleteCountdownInterval.value = setInterval(() => {
    permanentDeleteCountdown.value--
    if (permanentDeleteCountdown.value <= 0) {
      if (permanentDeleteCountdownInterval.value) {
        clearInterval(permanentDeleteCountdownInterval.value)
        permanentDeleteCountdownInterval.value = null
      }
    }
  }, 1000)
}

/**
 * Confirm permanent delete action with password
 */
const confirmPermanentDelete = async () => {
  if (!permanentDeleteTargetFile.value) return
  
  if (permanentDeletePassword.value.trim() === '') {
    toast.error('Please enter your password to confirm permanent deletion')
    return
  }
  
  try {
    await api.delete(`/documents/${permanentDeleteTargetFile.value.id}/force-delete`, {
      data: {
        password: permanentDeletePassword.value,
      },
    })
    
    toast.success('File permanently deleted')
    
    // Close dialog and reset
    permanentDeleteConfirmDialogOpen.value = false
    permanentDeletePassword.value = ''
    permanentDeleteCountdown.value = 3
    permanentDeleteTargetFile.value = null
    
    // Reload to get updated documents list from server
    await router.reload({
      only: ['documents', 'trashedDocuments', 'accessRequests'],
    })
  } catch (error: any) {
    console.error('Error permanently deleting document:', error)
    if (error.response?.status === 403) {
      toast.error('You do not have permission to permanently delete this document.')
    } else if (error.response?.status === 404) {
      toast.error('Document not found.')
    } else {
      toast.error(error.response?.data?.message || 'Failed to permanently delete document. Please try again.')
    }
  }
}

/**
 * Permanently delete file (opens confirmation dialog)
 */
const deletePermanently = (t: any) => {
  openPermanentDeleteConfirmDialog(t)
}

/**
 * Open restore all confirmation dialog
 */
const openRestoreAllConfirmDialog = () => {
  if (!trashFiles.value.length) return
  
  restoreAllCountdown.value = 3
  restoreAllConfirmDialogOpen.value = true
  
  // Start countdown
  if (restoreAllCountdownInterval.value) {
    clearInterval(restoreAllCountdownInterval.value)
  }
  
  restoreAllCountdownInterval.value = setInterval(() => {
    restoreAllCountdown.value--
    if (restoreAllCountdown.value <= 0) {
      if (restoreAllCountdownInterval.value) {
        clearInterval(restoreAllCountdownInterval.value)
        restoreAllCountdownInterval.value = null
      }
    }
  }, 1000)
}

/**
 * Confirm restore all action with password
 */
const confirmRestoreAll = async () => {
  if (restoreAllPassword.value.trim() === '') {
    toast.error('Please enter your password to confirm restore all')
    return
  }
  
  try {
    const response = await api.post('/documents/restore-all', {
      password: restoreAllPassword.value,
    })
    
    const restoredCount = response.data.restored_count || 0
    if (restoredCount > 0) {
      toast.success(`Successfully restored ${restoredCount} file${restoredCount > 1 ? 's' : ''}`)
    } else {
      toast.info('No files were restored.')
    }
    
    // Close dialog and reset
    restoreAllConfirmDialogOpen.value = false
    restoreAllPassword.value = ''
    restoreAllCountdown.value = 3
    
    // Reload to get updated documents list from server
    await router.reload({
      only: ['documents', 'trashedDocuments', 'accessRequests'],
    })
  } catch (error: any) {
    console.error('Error restoring all documents:', error)
    if (error.response?.status === 403) {
      toast.error('You do not have permission to restore documents.')
    } else {
      toast.error(error.response?.data?.message || 'Failed to restore documents. Please try again.')
    }
  }
}

/**
 * Restore all files from trash (opens confirmation dialog)
 */
const restoreAll = () => {
  openRestoreAllConfirmDialog()
}

/**
 * Open delete all confirmation dialog
 */
const openDeleteAllConfirmDialog = () => {
  if (!trashFiles.value.length) return
  
  deleteAllCountdown.value = 3
  deleteAllConfirmDialogOpen.value = true
  
  // Start countdown
  if (deleteAllCountdownInterval.value) {
    clearInterval(deleteAllCountdownInterval.value)
  }
  
  deleteAllCountdownInterval.value = setInterval(() => {
    deleteAllCountdown.value--
    if (deleteAllCountdown.value <= 0) {
      if (deleteAllCountdownInterval.value) {
        clearInterval(deleteAllCountdownInterval.value)
        deleteAllCountdownInterval.value = null
      }
    }
  }, 1000)
}

/**
 * Confirm delete all action with password
 */
const confirmDeleteAll = async () => {
  if (deleteAllPassword.value.trim() === '') {
    toast.error('Please enter your password to confirm permanent deletion')
    return
  }
  
  try {
    const response = await api.delete('/documents/force-delete-all', {
      data: {
        password: deleteAllPassword.value,
      },
    })
    
    const deletedCount = response.data.deleted_count || 0
    if (deletedCount > 0) {
      toast.success(`Successfully deleted ${deletedCount} file${deletedCount > 1 ? 's' : ''} permanently`)
    } else {
      toast.info('No files were deleted.')
    }
    
    // Close dialog and reset
    deleteAllConfirmDialogOpen.value = false
    deleteAllPassword.value = ''
    deleteAllCountdown.value = 3
    
    // Reload to get updated documents list from server
    await router.reload({
      only: ['documents', 'trashedDocuments', 'accessRequests'],
    })
  } catch (error: any) {
    console.error('Error permanently deleting all documents:', error)
    if (error.response?.status === 403) {
      toast.error('You do not have permission to permanently delete documents.')
    } else {
      toast.error(error.response?.data?.message || 'Failed to permanently delete documents. Please try again.')
    }
  }
}

/**
 * Delete all files from trash (opens confirmation dialog)
 */
const deleteAll = () => {
  openDeleteAllConfirmDialog()
}

/**
 * Calculate days until permanent deletion
 */


/**
 * Send access request for restricted file
 */
const sendAccessRequest = () => {
  if (!restrictedFor.value || !currentUser.value) return
  const userName = currentUser.value.name
  const newReq = {
    id: Date.now(),
    name: restrictedFor.value.name,
    requester: userName,
    requestedAt: new Date().toISOString().split('T')[0],
    status: 'Pending',
    department: restrictedFor.value.department,
    approvedBy: '',
    access: restrictedFor.value.access,
  }
  requests.value = [newReq, ...requests.value]
  uploads.value = [newReq, ...uploads.value]
  permissions.value = [newReq, ...permissions.value]
  restrictedDialogOpen.value = false
  toast.success('Access request sent!')
}

// ============================================================================
// REMOVE DIALOG HANDLERS
// ============================================================================

/**
 * Open remove dialog for file
 */
const openRemoveDialogForFile = (file: any) => {
  deleteTargetFile.value = file
  deletePassword.value = ''
  deleteCountdown.value = 2
  deleteConfirmDialogOpen.value = true
  
  // Start countdown
  if (deleteCountdownInterval.value) {
    clearInterval(deleteCountdownInterval.value)
  }
  
  deleteCountdownInterval.value = setInterval(() => {
    deleteCountdown.value--
    if (deleteCountdown.value <= 0) {
      if (deleteCountdownInterval.value) {
        clearInterval(deleteCountdownInterval.value)
        deleteCountdownInterval.value = null
      }
    }
  }, 1000)
}

// Cleanup countdown on dialog close
watch(() => deleteConfirmDialogOpen.value, (isOpen) => {
  if (!isOpen) {
    if (deleteCountdownInterval.value) {
      clearInterval(deleteCountdownInterval.value)
      deleteCountdownInterval.value = null
    }
    deletePassword.value = ''
    deleteCountdown.value = 2
  }
})

// Cleanup restore countdown on dialog close
watch(() => restoreConfirmDialogOpen.value, (isOpen) => {
  if (!isOpen) {
    if (restoreCountdownInterval.value) {
      clearInterval(restoreCountdownInterval.value)
      restoreCountdownInterval.value = null
    }
    restorePassword.value = ''
    restoreCountdown.value = 2
  }
})

// Cleanup permanent delete countdown on dialog close
watch(() => permanentDeleteConfirmDialogOpen.value, (isOpen) => {
  if (!isOpen) {
    if (permanentDeleteCountdownInterval.value) {
      clearInterval(permanentDeleteCountdownInterval.value)
      permanentDeleteCountdownInterval.value = null
    }
    permanentDeletePassword.value = ''
    permanentDeleteCountdown.value = 3
  }
})

// Cleanup restore all countdown on dialog close
watch(() => restoreAllConfirmDialogOpen.value, (isOpen) => {
  if (!isOpen) {
    if (restoreAllCountdownInterval.value) {
      clearInterval(restoreAllCountdownInterval.value)
      restoreAllCountdownInterval.value = null
    }
    restoreAllPassword.value = ''
    restoreAllCountdown.value = 3
  }
})

// Cleanup delete all countdown on dialog close
watch(() => deleteAllConfirmDialogOpen.value, (isOpen) => {
  if (!isOpen) {
    if (deleteAllCountdownInterval.value) {
      clearInterval(deleteAllCountdownInterval.value)
      deleteAllCountdownInterval.value = null
    }
    deleteAllPassword.value = ''
    deleteAllCountdown.value = 3
  }
})


/**
 * Confirm delete action with password
 */
const confirmDelete = async () => {
  if (!deleteTargetFile.value) return
  
  // TODO: Verify password with backend
  // For now, just require password to be entered
  if (deletePassword.value.trim() === '') {
    toast.error('Please enter your password to confirm deletion')
    return
  }
  
  try {
    await api.delete(`/documents/${deleteTargetFile.value.id}`, {
      data: {
        password: deletePassword.value, // Will be used for password verification later
      },
    })
    
    toast.success('Document moved to trash')
    
    // Close dialog and reset
    deleteConfirmDialogOpen.value = false
    deletePassword.value = ''
    deleteCountdown.value = 2
    deleteTargetFile.value = null
    
    // Reload to get updated documents list from server
    await router.reload({
      only: ['documents', 'trashedDocuments', 'accessRequests'],
    })
    
  } catch (error: any) {
    console.error('Delete error:', error)
    
    if (error.response?.status === 403) {
      toast.error('You do not have permission to delete documents')
    } else {
      toast.error(error.response?.data?.message || 'Failed to delete document')
    }
  }
}

// ============================================================================
// DOCUMENT DETAILS DIALOG ACTIONS
// ============================================================================
/**
 * Handle preview button click - show confirmation dialog
 */
const handlePreviewFromDetails = () => {
  if (!selectedDocumentForDetails.value) return
  previewConfirmOpen.value = true
}

/**
 * Confirm preview - open in new tab
 */
const confirmPreview = () => {
  if (!selectedDocumentForDetails.value) return
  const doc = selectedDocumentForDetails.value
  const docId = doc.id || doc._original?.id
  
  if (!docId) {
    toast.error('Document ID not found')
    previewConfirmOpen.value = false
    return
  }
  
  // Open PDF in new tab
  const previewUrl = `/documents/${docId}/preview`
  window.open(previewUrl, '_blank')
  previewConfirmOpen.value = false
  toast.success('Opening file in new tab')
}

/**
 * Handle download button click - show confirmation dialog
 */
const handleDownloadFromDetails = () => {
  if (!selectedDocumentForDetails.value) return
  downloadConfirmOpen.value = true
}

/**
 * Confirm download - download the file
 */
const confirmDownload = () => {
  if (!selectedDocumentForDetails.value) return
  
  const doc = selectedDocumentForDetails.value
  const docId = doc.id || doc._original?.id
  
  if (!docId) {
    toast.error('Document ID not found')
    downloadConfirmOpen.value = false
    return
  }
  
  // Download file
  const downloadUrl = `/documents/${docId}/download`
  const link = document.createElement('a')
  link.href = downloadUrl
  link.download = doc.fullName || doc.name || 'document'
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  
  downloadConfirmOpen.value = false
  toast.success('Download started')
}

/**
 * Handle cancel request for employee's pending upload
 */
const handleCancelPendingRequest = () => {
  if (!selectedDocumentForDetails.value) return
  
  // Remove from files list (UI only, no backend yet)
  files.value = files.value.filter(f => f.id !== selectedDocumentForDetails.value.id)
  toast.success('Upload request cancelled')
  documentDetailsOpen.value = false
}

/**
 * Handle cancel outgoing request
 */
const handleCancelOutgoingRequest = () => {
  if (!selectedPermission.value) return
  
  // Remove from requests, uploads, and permissions lists (UI only, no backend yet)
  const requestId = selectedPermission.value.id
  requests.value = requests.value.filter(r => r.id !== requestId)
  uploads.value = uploads.value.filter(u => u.id !== requestId)
  permissions.value = permissions.value.filter(p => p.id !== requestId)
  
  toast.success('Request cancelled')
  permissionDetailModalOpen.value = false
  selectedPermission.value = null
}

/**
 * Handle edit from document details dialog
 */
const handleEditFromDetails = () => {
  if (selectedDocumentForDetails.value) {
    openEditDetails(selectedDocumentForDetails.value)
    documentDetailsOpen.value = false
  }
}

/**
 * Handle delete from document details dialog
 */
const handleDeleteFromDetails = () => {
  if (selectedDocumentForDetails.value) {
    openRemoveDialogForFile(selectedDocumentForDetails.value)
    documentDetailsOpen.value = false
  }
}

/**
 * Handle document request access
 */
/**
 * Handle document request access
 */
const handleDocumentRequestAccess = async () => {
  if (!selectedDocumentForDetails.value) {
    toast.error('No document selected')
    return
  }

  try {
    const response = await api.post(
      `/documents/${selectedDocumentForDetails.value.id}/request-access`,
      {
        request_message: documentRequestMessage.value || null,
      }
    )

    toast.success(response.data.message || 'Access request sent successfully')
    
    // Refetch documents and access requests
    await refetchDocuments()
    
    // Close dialog and reset message
    documentRequestAccessDialogOpen.value = false
    documentRequestMessage.value = ''
  } catch (error: any) {
    console.error('Error requesting access:', error)
    
    // Handle validation errors
    if (error.response?.status === 422) {
      const errors = error.response.data.errors || {}
      const firstError = Object.values(errors).flat()[0]
      toast.error(firstError || 'Validation error occurred')
    } else if (error.response?.data?.message) {
      toast.error(error.response.data.message)
  } else {
      toast.error('Failed to send access request. Please try again.')
    }
  }
}
</script>

<template>

  <Head title="Documents" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div
      class="relative min-h-[100vh] p-3 sm:p-6 flex-1 border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border">

      <Tabs :model-value="activeTab" @update:model-value="(val: string | number) => activeTab = String(val)"
        class="w-full">
        <TabsList :class="['grid w-full mb-6', visibleNavLinks.length === 4 ? 'grid-cols-4' : 'grid-cols-3']">
          <TabsTrigger v-for="item in visibleNavLinks" :key="item.title" :value="item.title" class="px-1 sm:px-4 py-2 sm:py-2.5">
            <div class="flex flex-col sm:flex-row items-center gap-1 sm:gap-2">
              <component :is="item.icon" :size="18" class="shrink-0" />
              <span class="hidden sm:inline">{{ item.title }}</span>
            </div>
          </TabsTrigger>
        </TabsList>

        <!-- All Files Tab -->
        <TabsContent value="All Files">
          <Card class="mb-6 dark:bg-neutral-900 dark:border-neutral-700">
            <CardHeader class="dark:border-neutral-700">
              <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div>
                  <CardTitle class="dark:text-neutral-100">All Files</CardTitle>
                  <CardDescription class="dark:text-neutral-400">Browse and manage all documents</CardDescription>
                </div>
                <div class="flex gap-2 flex-wrap">
                  <Button 
                    @click="smartSearchDialogOpen = true" 
                    class="relative bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 hover:from-blue-700 hover:via-indigo-700 hover:to-purple-700 text-white font-semibold shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-105 border-2 border-blue-400/30 hover:border-blue-300/50 overflow-hidden group"
                  >
                    <!-- Animated background gradient -->
                    <div class="absolute inset-0 bg-gradient-to-r from-blue-400/20 via-indigo-400/20 to-purple-400/20 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                    
                    <!-- Sparkle/Shine effect -->
                    <div class="absolute inset-0 -translate-x-full group-hover:translate-x-full transition-transform duration-700 bg-gradient-to-r from-transparent via-white/20 to-transparent"></div>
                    
                    <div class="relative flex items-center gap-2">
                      <div class="relative">
                        <Search :size="16" class="sm:mr-1" />
                      </div>
                      <span class="hidden sm:inline bg-gradient-to-r from-white to-blue-100 bg-clip-text text-transparent font-bold">
                        Smart Search
                      </span>
                      <span class="sm:hidden ml-1 text-xs font-bold">Search</span>
                      <span class="hidden sm:inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full bg-white/20 text-xs font-bold border border-white/30">
                        AI
                      </span>
                    </div>
                  </Button>
                  <Button @click="uploadDialogOpen = true"
                    class="bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white">
                    <Plus :size="16" class="sm:mr-2" />
                    <span class="hidden sm:inline">Upload</span>
                    <span class="sm:hidden text-xs">Upload</span>
                  </Button>
                </div>
              </div>
            </CardHeader>
            <CardContent class="dark:bg-neutral-900">
              <!-- Inner Tabs for Approved/Pending/Rejected -->
              <Tabs :model-value="allFilesInnerTab" @update:model-value="(val: string | number) => allFilesInnerTab = String(val) as 'Approved' | 'Pending' | 'Rejected'" class="w-full">
                <TabsList :class="['grid w-full mb-6', visibleAllFilesTabs.length === 3 ? 'grid-cols-3' : 'grid-cols-1']">
                  <TabsTrigger v-if="visibleAllFilesTabs.includes('Approved')" value="Approved">
                    <div class="flex items-center gap-1 sm:gap-2">
                      <CheckCircle :size="16" class="text-green-600 shrink-0" />
                      <span class="text-xs sm:text-sm">Approved</span>
                      <Badge variant="secondary" class="ml-1 text-xs">
                        {{ approvedFilesCount }}
                      </Badge>
                    </div>
                  </TabsTrigger>
                  <TabsTrigger v-if="visibleAllFilesTabs.includes('Pending')" value="Pending">
                    <div class="flex items-center gap-1 sm:gap-2">
                      <Clock :size="16" class="text-amber-600 shrink-0" />
                      <span class="text-xs sm:text-sm">Pending</span>
                      <Badge variant="secondary" class="ml-1 text-xs">
                        {{ pendingFilesCount }}
                      </Badge>
                    </div>
                  </TabsTrigger>
                  <TabsTrigger v-if="visibleAllFilesTabs.includes('Rejected')" value="Rejected">
                    <div class="flex items-center gap-1 sm:gap-2">
                      <XCircle :size="16" class="text-red-600 shrink-0" />
                      <span class="text-xs sm:text-sm">Rejected</span>
                      <Badge variant="secondary" class="ml-1 text-xs">
                        {{ rejectedFilesCount }}
                      </Badge>
                    </div>
                  </TabsTrigger>
                </TabsList>

                <!-- Approved Files Content -->
                <TabsContent value="Approved">
              <!-- Search and Filters Section -->
              <div class="flex flex-col gap-3 mb-6">
                <!-- Search Bar -->
                <div class="relative w-full">
                  <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" />
                  <Input v-model="search" placeholder="Search File Name or Uploader Name" class="pl-10 h-10" />
                </div>

                <!-- Filters Row -->
                <div class="flex gap-2 flex-wrap">
                  <!-- Type Filter -->
                  <Select v-model="selectedType">
                    <SelectTrigger class="h-9 w-[140px] text-xs">
                      <SelectValue placeholder="Type" />
                    </SelectTrigger>
                    <SelectContent class="dark:bg-neutral-800 dark:border-neutral-700">
                      <SelectItem value="All" class="text-xs dark:text-neutral-100">All Types</SelectItem>
                      <SelectItem 
                        v-for="t in fileTypes.filter(t => t !== 'All')" 
        :key="t"
                        :value="t"
                        class="text-xs dark:text-neutral-100"
      >
        {{ t }}
                      </SelectItem>
                    </SelectContent>
                  </Select>

                  <!-- Department Filter -->
                  <Select v-model="selectedDept">
                    <SelectTrigger class="h-9 w-[160px] text-xs">
                      <SelectValue placeholder="Department" />
                    </SelectTrigger>
                    <SelectContent class="dark:bg-neutral-800 dark:border-neutral-700">
                      <SelectItem value="All" class="text-xs dark:text-neutral-100">All Departments</SelectItem>
                      <SelectItem 
                        v-for="d in departments.filter(d => d !== 'All')" 
              :key="d"
                        :value="d"
                        class="text-xs dark:text-neutral-100"
             >
                {{ d }}
                      </SelectItem>
                    </SelectContent>
                  </Select>

                  <!-- Accessibility Filter -->
                  <Select v-model="selectedAccess">
                    <SelectTrigger class="h-9 w-[150px] text-xs">
                      <SelectValue placeholder="Access" />
                    </SelectTrigger>
                    <SelectContent class="dark:bg-neutral-800 dark:border-neutral-700">
                      <SelectItem value="All" class="text-xs dark:text-neutral-100">All Access</SelectItem>
                      <SelectItem value="Public" class="text-xs dark:text-neutral-100">Public</SelectItem>
                      <SelectItem value="Private" class="text-xs dark:text-neutral-100">Private</SelectItem>
                      <SelectItem value="Department" class="text-xs dark:text-neutral-100">Department</SelectItem>
                    </SelectContent>
                  </Select>

                  <!-- Tag Filter (Multi-select) -->
                  <div class="relative">
                    <Select 
                      :model-value="selectedTags.length > 0 ? selectedTags.join(', ') : undefined"
                      @update:model-value="handleTagSelect"
                    >
                      <SelectTrigger class="h-9 w-[140px] text-xs">
                        <SelectValue :placeholder="selectedTags.length > 0 ? `${selectedTags.length} selected` : 'Tags'" />
                      </SelectTrigger>
                      <SelectContent class="dark:bg-neutral-800 dark:border-neutral-700 max-h-[200px]">
                        <div class="p-2">
                          <div class="space-y-1">
                            <div
                              v-for="tag in tagOptions"
                              :key="tag"
                              @click.stop="toggleTagSelection(tag)"
                              :class="[
                                'px-2 py-1.5 rounded-md cursor-pointer text-xs transition-colors',
                                selectedTags.includes(tag)
                                  ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'
                                  : 'hover:bg-gray-100 dark:hover:bg-neutral-700 dark:text-neutral-100'
                              ]"
                            >
                              <div class="flex items-center gap-2">
                                <div 
                                  :class="[
                                    'w-4 h-4 rounded border flex items-center justify-center',
                                    selectedTags.includes(tag)
                                      ? 'bg-blue-600 border-blue-600'
                                      : 'border-gray-300 dark:border-neutral-600'
                                  ]"
                                >
                                  <Check 
                                    v-if="selectedTags.includes(tag)"
                                    class="w-3 h-3 text-white"
                                  />
                                </div>
                                <span>{{ tag }}</span>
                              </div>
                            </div>
                          </div>
                        </div>
                      </SelectContent>
                    </Select>
                  </div>

                  <!-- Clear Filters Button -->
                  <Button 
                    v-if="selectedType !== 'All' || selectedDept !== 'All' || selectedAccess !== 'All' || selectedTags.length > 0"
                    variant="ghost" 
                    size="sm" 
                    @click="selectedType = 'All'; selectedDept = 'All'; selectedAccess = 'All'; selectedTags = []"
                    class="text-xs text-gray-500 hover:text-gray-700 dark:text-neutral-400 dark:hover:text-neutral-200"
                  >
                    <X class="w-3 h-3 mr-1" />
                    Clear filters
                  </Button>
                </div>
              </div>

              <!-- File Grid -->
              <div v-if="paginatedFiles.length"
                class="grid gap-4 grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 mt-5">
                <div v-for="file in paginatedFiles" :key="file.id"
                  @click="openFileViewer(file)"
                  class="relative bg-white border border-gray-200 rounded-lg p-4 hover:shadow-lg cursor-pointer dark:bg-neutral-800 dark:border-neutral-700 dark:hover:bg-neutral-750">
                  <!-- Card body -->
                  <div class="flex flex-col h-full">
                    <div class="flex items-start gap-3 mb-3">
                    <div
                        :class="['w-16 h-16 flex items-center justify-center rounded-lg text-white font-bold text-sm shadow-sm shrink-0', typeColor(file.type)]">
                      {{ file.type }}
                    </div>
                      <div class="flex-1 min-w-0">
                        <h3 class="text-sm font-semibold text-gray-900 truncate w-full dark:text-neutral-100">
                            {{ file.name }}
                          </h3>
                        <div class="flex items-center gap-2 mt-1 text-xs text-gray-500 dark:text-neutral-400">
                          <span>{{ file.size }}</span>
                          <span>•</span>
                      <span>{{ file.department }}</span>
                        </div>
                        <div :class="['text-xs mt-1', getAccessTextColor(file.access)]">
                          {{ file.access }}
                        </div>
                      </div>
                    </div>
                    
                    <!-- Tags Section (at bottom) -->
                    <div class="mt-auto pt-2 border-t border-gray-100 dark:border-neutral-700">
                      <div v-if="!file.tags || file.tags.length === 0" class="flex items-center gap-1.5 text-xs text-gray-400 dark:text-neutral-500">
                        <Tag :size="12" class="shrink-0" />
                        <span>No Tag</span>
                      </div>
                      <div v-else class="relative flex items-center gap-1.5 overflow-hidden flex-nowrap pr-8">
                        <Tag :size="12" class="shrink-0 text-gray-500 dark:text-neutral-400" />
                        <template v-for="(tag, index) in getVisibleTags(file.tags)" :key="index">
                          <Badge 
                            class="text-xs px-1.5 py-0.5 shrink-0 bg-gray-100 text-gray-700 dark:bg-neutral-700 dark:text-neutral-300 border-0"
                          >
                            {{ tag }}
                          </Badge>
                        </template>
                        <span
                          v-if="getHiddenTagsCount(file.tags) > 0"
                          class="absolute right-0 inline-flex items-center justify-center text-xs px-1.5 py-0.5 rounded bg-gray-100 text-gray-700 dark:bg-neutral-700 dark:text-neutral-300 border-0"
                        >
                          +{{ getHiddenTagsCount(file.tags) }}
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
              <div class="mt-6">
                <Pagination v-model:page="currentPage" :items-per-page="itemsPerPage" :total="filteredFiles.length" class="justify-end">
                  <PaginationContent v-slot="{ items }">
                    <PaginationPrevious />
                    <template v-for="(item, index) in items" :key="index">
                      <PaginationItem v-if="item.type === 'page'" :value="item.value">
                        {{ item.value }}
                      </PaginationItem>
                      <PaginationEllipsis v-else-if="item.type === 'ellipsis'" :index="index" />
                    </template>
                    <PaginationNext />
                  </PaginationContent>
                </Pagination>
              </div>
                </TabsContent>

                <!-- Pending Files Content -->
                <TabsContent v-if="visibleAllFilesTabs.includes('Pending')" value="Pending">

                  <!-- Search and Filters Section -->
                  <div class="flex flex-col gap-3 mb-6">
                    <!-- Search Bar -->
                    <div class="relative w-full">
                      <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" />
                      <Input v-model="search" placeholder="Search File Name or Uploader Name" class="pl-10 h-10" />
                    </div>

                    <!-- Filters Row -->
                    <div class="flex gap-2 flex-wrap">
                      <!-- Type Filter -->
                      <Select v-model="selectedType">
                        <SelectTrigger class="h-9 w-[140px] text-xs">
                          <SelectValue placeholder="Type" />
                        </SelectTrigger>
                        <SelectContent class="dark:bg-neutral-800 dark:border-neutral-700">
                          <SelectItem value="All" class="text-xs dark:text-neutral-100">All Types</SelectItem>
                          <SelectItem 
                            v-for="t in fileTypes.filter(t => t !== 'All')" 
                            :key="t"
                            :value="t"
                            class="text-xs dark:text-neutral-100"
                          >
                            {{ t }}
                          </SelectItem>
                        </SelectContent>
                      </Select>

                      <!-- Department Filter -->
                      <Select v-model="selectedDept">
                        <SelectTrigger class="h-9 w-[160px] text-xs">
                          <SelectValue placeholder="Department" />
                        </SelectTrigger>
                        <SelectContent class="dark:bg-neutral-800 dark:border-neutral-700">
                          <SelectItem value="All" class="text-xs dark:text-neutral-100">All Departments</SelectItem>
                          <SelectItem 
                            v-for="d in departments.filter(d => d !== 'All')" 
                            :key="d"
                            :value="d"
                            class="text-xs dark:text-neutral-100"
                          >
                            {{ d }}
                          </SelectItem>
                        </SelectContent>
                      </Select>

                      <!-- Accessibility Filter -->
                      <Select v-model="selectedAccess">
                        <SelectTrigger class="h-9 w-[150px] text-xs">
                          <SelectValue placeholder="Access" />
                        </SelectTrigger>
                        <SelectContent class="dark:bg-neutral-800 dark:border-neutral-700">
                          <SelectItem value="All" class="text-xs dark:text-neutral-100">All Access</SelectItem>
                          <SelectItem value="Public" class="text-xs dark:text-neutral-100">Public</SelectItem>
                          <SelectItem value="Private" class="text-xs dark:text-neutral-100">Private</SelectItem>
                          <SelectItem value="Department" class="text-xs dark:text-neutral-100">Department</SelectItem>
                        </SelectContent>
                      </Select>

                      <!-- Tag Filter (Multi-select) -->
                      <div class="relative">
                        <Select 
                          :model-value="selectedTags.length > 0 ? selectedTags.join(', ') : undefined"
                          @update:model-value="handleTagSelect"
                        >
                          <SelectTrigger class="h-9 w-[140px] text-xs">
                            <SelectValue :placeholder="selectedTags.length > 0 ? `${selectedTags.length} selected` : 'Tags'" />
                          </SelectTrigger>
                          <SelectContent class="dark:bg-neutral-800 dark:border-neutral-700 max-h-[200px]">
                            <div class="p-2">
                              <div class="space-y-1">
                                <div
                                  v-for="tag in tagOptions"
                                  :key="tag"
                                  @click.stop="toggleTagSelection(tag)"
                                  :class="[
                                    'px-2 py-1.5 rounded-md cursor-pointer text-xs transition-colors',
                                    selectedTags.includes(tag)
                                      ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'
                                      : 'hover:bg-gray-100 dark:hover:bg-neutral-700 dark:text-neutral-100'
                                  ]"
                                >
                                  <div class="flex items-center gap-2">
                                    <div 
                                      :class="[
                                        'w-4 h-4 rounded border flex items-center justify-center',
                                        selectedTags.includes(tag)
                                          ? 'bg-blue-600 border-blue-600'
                                          : 'border-gray-300 dark:border-neutral-600'
                                      ]"
                                    >
                                      <Check 
                                        v-if="selectedTags.includes(tag)"
                                        class="w-3 h-3 text-white"
                                      />
                                    </div>
                                    <span>{{ tag }}</span>
                                  </div>
                                </div>
                              </div>
                            </div>
                          </SelectContent>
                        </Select>
                      </div>

                      <!-- Clear Filters Button -->
                      <Button 
                        v-if="selectedType !== 'All' || selectedDept !== 'All' || selectedAccess !== 'All' || selectedTags.length > 0"
                        variant="ghost" 
                        size="sm" 
                        @click="selectedType = 'All'; selectedDept = 'All'; selectedAccess = 'All'; selectedTags = []"
                        class="text-xs text-gray-500 hover:text-gray-700 dark:text-neutral-400 dark:hover:text-neutral-200"
                      >
                        <X class="w-3 h-3 mr-1" />
                        Clear filters
                      </Button>
                    </div>
                  </div>

                  <!-- File Grid - Pending Files -->
                  <div v-if="paginatedFiles.length"
                    class="grid gap-4 grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 mt-5">
                    <div v-for="file in paginatedFiles" :key="file.id"
                      @click="openFileViewer(file)"
                      class="relative bg-white border border-gray-200 rounded-lg p-4 hover:shadow-lg cursor-pointer dark:bg-neutral-800 dark:border-neutral-700 dark:hover:bg-neutral-750">
                      <div class="flex flex-col h-full">
                        <div class="flex items-start gap-3 mb-3">
                          <div
                            :class="['w-16 h-16 flex items-center justify-center rounded-lg text-white font-bold text-sm shadow-sm shrink-0', typeColor(file.type)]">
                          {{ file.type }}
                        </div>
                          <div class="flex-1 min-w-0">
                            <h3 class="text-sm font-semibold text-gray-900 truncate w-full dark:text-neutral-100">
                                {{ file.name }}
                              </h3>
                            <div class="flex items-center gap-2 mt-1 text-xs text-gray-500 dark:text-neutral-400">
                              <span>{{ file.size }}</span>
                              <span>•</span>
                          <span>{{ file.department }}</span>
                            </div>
                            <div :class="['text-xs mt-1', getAccessTextColor(file.access)]">
                              {{ file.access }}
                            </div>
                          </div>
                        </div>

                        <div class="mt-auto pt-2 border-t border-gray-100 dark:border-neutral-700">
                          <div v-if="!file.tags || file.tags.length === 0" class="flex items-center gap-1.5 text-xs text-gray-400 dark:text-neutral-500">
                            <Tag :size="12" class="shrink-0" />
                            <span>No Tag</span>
                          </div>
                        <div v-else class="relative flex items-center gap-1.5 overflow-hidden flex-nowrap pr-8">
                            <Tag :size="12" class="shrink-0 text-gray-500 dark:text-neutral-400" />
                            <template v-for="(tag, index) in getVisibleTags(file.tags)" :key="index">
                              <Badge 
                                class="text-xs px-1.5 py-0.5 shrink-0 bg-gray-100 text-gray-700 dark:bg-neutral-700 dark:text-neutral-300 border-0"
                              >
                                {{ tag }}
                              </Badge>
                            </template>
                          <span
                            v-if="getHiddenTagsCount(file.tags) > 0"
                            class="absolute right-0 inline-flex items-center justify-center text-xs px-1.5 py-0.5 rounded bg-gray-100 text-gray-700 dark:bg-neutral-700 dark:text-neutral-300 border-0"
                          >
                            +{{ getHiddenTagsCount(file.tags) }}
                          </span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Empty State -->
                  <div v-else
                    class="text-center py-12 sm:py-16 text-gray-500 dark:text-neutral-400 text-xs sm:text-sm">
                    No pending files found.
                  </div>

                  <!-- Pagination -->
                  <div class="mt-6">
                    <Pagination v-model:page="currentPage" :items-per-page="itemsPerPage" :total="filteredFiles.length" class="justify-end">
                      <PaginationContent v-slot="{ items }">
                        <PaginationPrevious />
                        <template v-for="(item, index) in items" :key="index">
                          <PaginationItem v-if="item.type === 'page'" :value="item.value">
                            {{ item.value }}
                          </PaginationItem>
                          <PaginationEllipsis v-else-if="item.type === 'ellipsis'" :index="index" />
                        </template>
                        <PaginationNext />
                      </PaginationContent>
                    </Pagination>
                  </div>
                </TabsContent>

                <!-- Rejected Content -->
                <TabsContent v-if="visibleAllFilesTabs.includes('Rejected')" value="Rejected">

                  <!-- Search and Filters Section -->
                  <div class="flex flex-col gap-3 mb-6">
                    <!-- Search Bar -->
                    <div class="relative w-full">
                      <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" />
                      <Input v-model="search" placeholder="Search File Name or Uploader Name" class="pl-10 h-10" />
                    </div>

                    <!-- Filters Row -->
                    <div class="flex gap-2 flex-wrap">
                      <Select v-model="selectedType">
                        <SelectTrigger class="h-9 w-[140px] text-xs">
                          <SelectValue placeholder="Type" />
                        </SelectTrigger>
                        <SelectContent class="dark:bg-neutral-800 dark:border-neutral-700">
                          <SelectItem value="All" class="text-xs dark:text-neutral-100">All Types</SelectItem>
                          <SelectItem 
                            v-for="t in fileTypes.filter(t => t !== 'All')" 
                            :key="t" 
                            :value="t"
                            class="text-xs dark:text-neutral-100"
                          >
                            {{ t }}
                          </SelectItem>
                        </SelectContent>
                      </Select>

                      <Select v-model="selectedDept">
                        <SelectTrigger class="h-9 w-[160px] text-xs">
                          <SelectValue placeholder="Department" />
                        </SelectTrigger>
                        <SelectContent class="dark:bg-neutral-800 dark:border-neutral-700">
                          <SelectItem value="All" class="text-xs dark:text-neutral-100">All Departments</SelectItem>
                          <SelectItem 
                            v-for="d in departments.filter(d => d !== 'All')" 
                            :key="d" 
                            :value="d"
                            class="text-xs dark:text-neutral-100"
                          >
                            {{ d }}
                          </SelectItem>
                        </SelectContent>
                      </Select>

                      <Select v-model="selectedAccess">
                        <SelectTrigger class="h-9 w-[150px] text-xs">
                          <SelectValue placeholder="Access" />
                        </SelectTrigger>
                        <SelectContent class="dark:bg-neutral-800 dark:border-neutral-700">
                          <SelectItem value="All" class="text-xs dark:text-neutral-100">All Access</SelectItem>
                          <SelectItem value="Public" class="text-xs dark:text-neutral-100">Public</SelectItem>
                          <SelectItem value="Private" class="text-xs dark:text-neutral-100">Private</SelectItem>
                          <SelectItem value="Department" class="text-xs dark:text-neutral-100">Department</SelectItem>
                        </SelectContent>
                      </Select>

                      <div class="relative">
                        <Select 
                          :model-value="selectedTags.length > 0 ? selectedTags.join(', ') : undefined"
                          @update:model-value="handleTagSelect"
                        >
                          <SelectTrigger class="h-9 w-[140px] text-xs">
                            <SelectValue :placeholder="selectedTags.length > 0 ? `${selectedTags.length} selected` : 'Tags'" />
                          </SelectTrigger>
                          <SelectContent class="dark:bg-neutral-800 dark:border-neutral-700 max-h-[200px]">
                            <div class="p-2">
                              <div class="space-y-1">
                                <div
                                  v-for="tag in tagOptions"
                                  :key="tag"
                                  @click.stop="toggleTagSelection(tag)"
                                  :class="[
                                    'px-2 py-1.5 rounded-md cursor-pointer text-xs transition-colors',
                                    selectedTags.includes(tag)
                                      ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'
                                      : 'hover:bg-gray-100 dark:hover:bg-neutral-700 dark:text-neutral-100'
                                  ]"
                                >
                                  <div class="flex items-center gap-2">
                                    <div 
                                      :class="[
                                        'w-4 h-4 rounded border flex items-center justify-center',
                                        selectedTags.includes(tag)
                                          ? 'bg-blue-600 border-blue-600'
                                          : 'border-gray-300 dark:border-neutral-600'
                                      ]"
                                    >
                                      <Check 
                                        v-if="selectedTags.includes(tag)"
                                        class="w-3 h-3 text-white"
                                      />
                                    </div>
                                    <span>{{ tag }}</span>
                                  </div>
                                </div>
                              </div>
                            </div>
                          </SelectContent>
                        </Select>
                      </div>

                        <Button 
                        v-if="selectedType !== 'All' || selectedDept !== 'All' || selectedAccess !== 'All' || selectedTags.length > 0"
                        variant="ghost" 
                          size="sm"
                        @click="selectedType = 'All'; selectedDept = 'All'; selectedAccess = 'All'; selectedTags = []"
                        class="text-xs text-gray-500 hover:text-gray-700 dark:text-neutral-400 dark:hover:text-neutral-200"
                        >
                        <X class="w-3 h-3 mr-1" />
                        Clear filters
                        </Button>
                    </div>
                      </div>

                  <!-- File Grid -->
                  <div v-if="paginatedFiles.length"
                    class="grid gap-4 grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 mt-5">
                    <div v-for="file in paginatedFiles" :key="file.id"
                      @click="openFileViewer(file)"
                      class="relative bg-white border border-gray-200 rounded-lg p-4 hover:shadow-lg cursor-pointer dark:bg-neutral-800 dark:border-neutral-700 dark:hover:bg-neutral-750">
                      <div class="flex flex-col h-full">
                        <div class="flex items-start gap-3 mb-3">
                          <div
                            :class="['w-16 h-16 flex items-center justify-center rounded-lg text-white font-bold text-sm shadow-sm shrink-0', typeColor(file.type)]">
                          {{ file.type }}
                        </div>
                          <div class="flex-1 min-w-0">
                            <h3 class="text-sm font-semibold text-gray-900 truncate w-full dark:text-neutral-100">
                              {{ file.name }}
                            </h3>
                            <div class="flex items-center gap-2 mt-1 text-xs text-gray-500 dark:text-neutral-400">
                              <span>{{ file.size }}</span>
                              <span>•</span>
                          <span>{{ file.department }}</span>
                            </div>
                            <div :class="['text-xs mt-1', getAccessTextColor(file.access)]">
                              {{ file.access }}
                            </div>
                          </div>
                        </div>

                        <div class="mt-auto pt-2 border-t border-gray-100 dark:border-neutral-700">
                          <div v-if="!file.tags || file.tags.length === 0" class="flex items-center gap-1.5 text-xs text-gray-400 dark:text-neutral-500">
                            <Tag :size="12" class="shrink-0" />
                            <span>No Tag</span>
                          </div>
                        <div v-else class="relative flex items-center gap-1.5 overflow-hidden flex-nowrap pr-8">
                            <Tag :size="12" class="shrink-0 text-gray-500 dark:text-neutral-400" />
                            <template v-for="(tag, index) in getVisibleTags(file.tags)" :key="index">
                              <Badge 
                                class="text-xs px-1.5 py-0.5 shrink-0 bg-gray-100 text-gray-700 dark:bg-neutral-700 dark:text-neutral-300 border-0"
                              >
                                {{ tag }}
                              </Badge>
                            </template>
                          <span
                            v-if="getHiddenTagsCount(file.tags) > 0"
                            class="absolute right-0 inline-flex items-center justify-center text-xs px-1.5 py-0.5 rounded bg-gray-100 text-gray-700 dark:bg-neutral-700 dark:text-neutral-300 border-0"
                          >
                            +{{ getHiddenTagsCount(file.tags) }}
                          </span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Empty State -->
                  <div v-else
                    class="text-center py-12 sm:py-16 text-gray-500 dark:text-neutral-400 text-xs sm:text-sm">
                    No rejected files found.
                  </div>

                  <!-- Pagination -->
                  <div class="mt-6">
                    <Pagination v-model:page="currentPage" :items-per-page="itemsPerPage" :total="filteredFiles.length" class="justify-end">
                      <PaginationContent v-slot="{ items }">
                        <PaginationPrevious />
                        <template v-for="(item, index) in items" :key="index">
                          <PaginationItem v-if="item.type === 'page'" :value="item.value">
                            {{ item.value }}
                          </PaginationItem>
                          <PaginationEllipsis v-else-if="item.type === 'ellipsis'" :index="index" />
                        </template>
                        <PaginationNext />
                      </PaginationContent>
                    </Pagination>
                  </div>
                </TabsContent>
              </Tabs>
            </CardContent>
          </Card>
        </TabsContent>

        <!-- My Department Tab -->
        <TabsContent value="My Department">
          <Card class="dark:bg-neutral-900 dark:border-neutral-700">
            <CardHeader class="dark:border-neutral-700">
              <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div>
                  <CardTitle class="dark:text-neutral-100">My Department</CardTitle>
                  <CardDescription class="dark:text-neutral-400">Documents shared within your department</CardDescription>
                </div>
                <Button @click="uploadDialogOpen = true"
                  class="bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white">
                  <Plus :size="16" />
                  Upload
                </Button>
              </div>
            </CardHeader>
            <CardContent class="dark:bg-neutral-900">
              <Tabs :model-value="departmentInnerTab"
                @update:model-value="(val: string | number) => departmentInnerTab = String(val) as 'Approved' | 'Pending' | 'Rejected'"
                class="w-full">
                <TabsList class="grid w-full grid-cols-3 mb-6">
                  <TabsTrigger value="Approved">
                    <div class="flex items-center gap-1 sm:gap-2">
                      <CheckCircle :size="16" class="text-green-600 shrink-0" />
                      <span class="text-xs sm:text-sm">Approved</span>
                      <Badge variant="secondary" class="ml-1 text-xs">
                        {{ departmentApprovedCount }}
                      </Badge>
                    </div>
                  </TabsTrigger>
                  <TabsTrigger value="Pending">
                    <div class="flex items-center gap-1 sm:gap-2">
                      <Clock :size="16" class="text-amber-600 shrink-0" />
                      <span class="text-xs sm:text-sm">Pending</span>
                      <Badge variant="secondary" class="ml-1 text-xs">
                        {{ departmentPendingCount }}
                      </Badge>
                    </div>
                  </TabsTrigger>
                  <TabsTrigger value="Rejected">
                    <div class="flex items-center gap-1 sm:gap-2">
                      <XCircle :size="16" class="text-red-600 shrink-0" />
                      <span class="text-xs sm:text-sm">Rejected</span>
                      <Badge variant="secondary" class="ml-1 text-xs">
                        {{ departmentRejectedCount }}
                      </Badge>
                    </div>
                  </TabsTrigger>
                </TabsList>

                <!-- Approved Department Files -->
                <TabsContent value="Approved">
              <div class="flex flex-col gap-3 mb-6">
                <div class="relative w-full">
                  <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" />
                  <Input v-model="search" placeholder="Search File Name or Uploader Name" class="pl-10 h-10" />
                </div>

                <div class="flex gap-2 flex-wrap">
                      <Select v-model="selectedType">
                        <SelectTrigger class="h-9 w-[140px] text-xs">
                          <SelectValue placeholder="Type" />
                        </SelectTrigger>
                        <SelectContent class="dark:bg-neutral-800 dark:border-neutral-700">
                          <SelectItem value="All" class="text-xs dark:text-neutral-100">All Types</SelectItem>
                          <SelectItem 
                            v-for="t in fileTypes.filter(t => t !== 'All')" 
                            :key="t" 
                            :value="t"
                            class="text-xs dark:text-neutral-100"
                          >
                            {{ t }}
                          </SelectItem>
                        </SelectContent>
                      </Select>

                      <Select v-model="selectedDept">
                        <SelectTrigger class="h-9 w-[160px] text-xs">
                          <SelectValue placeholder="Department" />
                        </SelectTrigger>
                        <SelectContent class="dark:bg-neutral-800 dark:border-neutral-700">
                          <SelectItem value="All" class="text-xs dark:text-neutral-100">All Departments</SelectItem>
                          <SelectItem 
                            v-for="d in departments.filter(d => d !== 'All')" 
                            :key="d" 
                            :value="d"
                            class="text-xs dark:text-neutral-100"
                          >
                            {{ d }}
                          </SelectItem>
                        </SelectContent>
                      </Select>

                      <Select v-model="selectedAccess">
                        <SelectTrigger class="h-9 w-[150px] text-xs">
                          <SelectValue placeholder="Access" />
                        </SelectTrigger>
                        <SelectContent class="dark:bg-neutral-800 dark:border-neutral-700">
                          <SelectItem value="All" class="text-xs dark:text-neutral-100">All Access</SelectItem>
                          <SelectItem value="Public" class="text-xs dark:text-neutral-100">Public</SelectItem>
                          <SelectItem value="Private" class="text-xs dark:text-neutral-100">Private</SelectItem>
                          <SelectItem value="Department" class="text-xs dark:text-neutral-100">Department</SelectItem>
                        </SelectContent>
                      </Select>

                      <div class="relative">
                        <Select 
                          :model-value="selectedTags.length > 0 ? selectedTags.join(', ') : undefined"
                          @update:model-value="handleTagSelect"
                        >
                          <SelectTrigger class="h-9 w-[140px] text-xs">
                            <SelectValue :placeholder="selectedTags.length > 0 ? `${selectedTags.length} selected` : 'Tags'" />
                          </SelectTrigger>
                          <SelectContent class="dark:bg-neutral-800 dark:border-neutral-700 max-h-[200px]">
                            <div class="p-2">
                              <div class="space-y-1">
                                <div
                                  v-for="tag in tagOptions"
                                  :key="tag"
                                  @click.stop="toggleTagSelection(tag)"
                                  :class="[
                                    'px-2 py-1.5 rounded-md cursor-pointer text-xs transition-colors',
                                    selectedTags.includes(tag)
                                      ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'
                                      : 'hover:bg-gray-100 dark:hover:bg-neutral-700 dark:text-neutral-100'
                                  ]"
                                >
                                  <div class="flex items-center gap-2">
                                    <div 
                                      :class="[
                                        'w-4 h-4 rounded border flex items-center justify-center',
                                        selectedTags.includes(tag)
                                          ? 'bg-blue-600 border-blue-600'
                                          : 'border-gray-300 dark:border-neutral-600'
                                      ]"
                                    >
                                      <Check 
                                        v-if="selectedTags.includes(tag)"
                                        class="w-3 h-3 text-white"
                                      />
                                    </div>
                                    <span>{{ tag }}</span>
                                  </div>
                                </div>
                              </div>
                            </div>
                          </SelectContent>
                        </Select>
                      </div>

                      <Button 
                        v-if="selectedType !== 'All' || selectedDept !== 'All' || selectedAccess !== 'All' || selectedTags.length > 0"
                        variant="ghost" 
                        size="sm" 
                        @click="selectedType = 'All'; selectedDept = 'All'; selectedAccess = 'All'; selectedTags = []"
                        class="text-xs text-gray-500 hover:text-gray-700 dark:text-neutral-400 dark:hover:text-neutral-200"
                      >
                        <X class="w-3 h-3 mr-1" />
                        Clear filters
    </Button>
                    </div>
                  </div>

                  <div v-if="departmentPaginatedFiles.length"
                    class="grid gap-4 grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 mt-5">
                    <div v-for="file in departmentPaginatedFiles" :key="file.id"
                      @click="openFileViewer(file)"
                      class="relative bg-white border border-gray-200 rounded-lg p-4 hover:shadow-lg cursor-pointer dark:bg-neutral-800 dark:border-neutral-700 dark:hover:bg-neutral-750">
                      <div class="flex flex-col h-full">
                        <div class="flex items-start gap-3 mb-3">
                          <div
                            :class="['w-16 h-16 flex items-center justify-center rounded-lg text-white font-bold text-sm shadow-sm shrink-0', typeColor(file.type)]">
                            {{ file.type }}
                          </div>
                          <div class="flex-1 min-w-0">
                            <h3 class="text-sm font-semibold text-gray-900 truncate w-full dark:text-neutral-100">
                              {{ file.name }}
                            </h3>
                            <div class="flex items-center gap-2 mt-1 text-xs text-gray-500 dark:text-neutral-400">
                              <span>{{ file.size }}</span>
                              <span>•</span>
                              <span>{{ file.department }}</span>
                            </div>
                            <div :class="['text-xs mt-1', getAccessTextColor(file.access)]">
                              {{ file.access }}
                            </div>
                          </div>
                        </div>

                        <div class="mt-auto pt-2 border-t border-gray-100 dark:border-neutral-700">
                          <div v-if="!file.tags || file.tags.length === 0" class="flex items-center gap-1.5 text-xs text-gray-400 dark:text-neutral-500">
                            <Tag :size="12" class="shrink-0" />
                            <span>No Tag</span>
                          </div>
                          <div v-else class="relative flex items-center gap-1.5 overflow-hidden flex-nowrap pr-8">
                            <Tag :size="12" class="shrink-0 text-gray-500 dark:text-neutral-400" />
                            <template v-for="(tag, index) in getVisibleTags(file.tags)" :key="index">
                              <Badge 
                                class="text-xs px-1.5 py-0.5 shrink-0 bg-gray-100 text-gray-700 dark:bg-neutral-700 dark:text-neutral-300 border-0"
                              >
                                {{ tag }}
                              </Badge>
                            </template>
                            <span
                              v-if="getHiddenTagsCount(file.tags) > 0"
                              class="absolute right-0 inline-flex items-center justify-center text-xs px-1.5 py-0.5 rounded bg-gray-100 text-gray-700 dark:bg-neutral-700 dark:text-neutral-300 border-0"
                            >
                              +{{ getHiddenTagsCount(file.tags) }}
                            </span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div v-else class="text-center text-gray-500 py-8 sm:py-10 text-sm sm:text-base">
                    No files found. Try adjusting filters or uploading a new document.
                  </div>

                  <div class="mt-6">
                    <Pagination v-model:page="departmentPage" :items-per-page="itemsPerPage" :total="departmentFilteredFiles.length" class="justify-end">
                      <PaginationContent v-slot="{ items }">
                        <PaginationPrevious />
                        <template v-for="(item, index) in items" :key="index">
                          <PaginationItem v-if="item.type === 'page'" :value="item.value">
                            {{ item.value }}
                          </PaginationItem>
                          <PaginationEllipsis v-else-if="item.type === 'ellipsis'" :index="index" />
                        </template>
                        <PaginationNext />
                      </PaginationContent>
                    </Pagination>
                  </div>
                </TabsContent>

                <!-- Pending Department Files -->
                <TabsContent value="Pending">
                  <div class="flex flex-col gap-3 mb-6">
                    <div class="relative w-full">
                      <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" />
                      <Input v-model="search" placeholder="Search File Name or Uploader Name" class="pl-10 h-10" />
                    </div>

                    <div class="flex gap-2 flex-wrap">
                      <Select v-model="selectedType">
                        <SelectTrigger class="h-9 w-[140px] text-xs">
                          <SelectValue placeholder="Type" />
                        </SelectTrigger>
                        <SelectContent class="dark:bg-neutral-800 dark:border-neutral-700">
                          <SelectItem value="All" class="text-xs dark:text-neutral-100">All Types</SelectItem>
                          <SelectItem 
                            v-for="t in fileTypes.filter(t => t !== 'All')" 
                            :key="t" 
                            :value="t"
                            class="text-xs dark:text-neutral-100"
                          >
                            {{ t }}
                          </SelectItem>
                        </SelectContent>
                      </Select>

                      <Select v-model="selectedDept">
                        <SelectTrigger class="h-9 w-[160px] text-xs">
                          <SelectValue placeholder="Department" />
                        </SelectTrigger>
                        <SelectContent class="dark:bg-neutral-800 dark:border-neutral-700">
                          <SelectItem value="All" class="text-xs dark:text-neutral-100">All Departments</SelectItem>
                          <SelectItem 
                            v-for="d in departments.filter(d => d !== 'All')" 
              :key="d"
                            :value="d"
                            class="text-xs dark:text-neutral-100"
             >
                {{ d }}
                          </SelectItem>
                        </SelectContent>
                      </Select>

                      <Select v-model="selectedAccess">
                        <SelectTrigger class="h-9 w-[150px] text-xs">
                          <SelectValue placeholder="Access" />
                        </SelectTrigger>
                        <SelectContent class="dark:bg-neutral-800 dark:border-neutral-700">
                          <SelectItem value="All" class="text-xs dark:text-neutral-100">All Access</SelectItem>
                          <SelectItem value="Public" class="text-xs dark:text-neutral-100">Public</SelectItem>
                          <SelectItem value="Private" class="text-xs dark:text-neutral-100">Private</SelectItem>
                          <SelectItem value="Department" class="text-xs dark:text-neutral-100">Department</SelectItem>
                        </SelectContent>
                      </Select>

                      <div class="relative">
                        <Select 
                          :model-value="selectedTags.length > 0 ? selectedTags.join(', ') : undefined"
                          @update:model-value="handleTagSelect"
                        >
                          <SelectTrigger class="h-9 w-[140px] text-xs">
                            <SelectValue :placeholder="selectedTags.length > 0 ? `${selectedTags.length} selected` : 'Tags'" />
                          </SelectTrigger>
                          <SelectContent class="dark:bg-neutral-800 dark:border-neutral-700 max-h-[200px]">
                            <div class="p-2">
                              <div class="space-y-1">
                                <div
                                  v-for="tag in tagOptions"
                                  :key="tag"
                                  @click.stop="toggleTagSelection(tag)"
                                  :class="[
                                    'px-2 py-1.5 rounded-md cursor-pointer text-xs transition-colors',
                                    selectedTags.includes(tag)
                                      ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'
                                      : 'hover:bg-gray-100 dark:hover:bg-neutral-700 dark:text-neutral-100'
                                  ]"
                                >
                                  <div class="flex items-center gap-2">
                                    <div 
                                      :class="[
                                        'w-4 h-4 rounded border flex items-center justify-center',
                                        selectedTags.includes(tag)
                                          ? 'bg-blue-600 border-blue-600'
                                          : 'border-gray-300 dark:border-neutral-600'
                                      ]"
                                    >
                                      <Check 
                                        v-if="selectedTags.includes(tag)"
                                        class="w-3 h-3 text-white"
                                      />
                                    </div>
                                    <span>{{ tag }}</span>
                                  </div>
                                </div>
                              </div>
                            </div>
                          </SelectContent>
                        </Select>
                      </div>

                  <Button 
                        v-if="selectedType !== 'All' || selectedDept !== 'All' || selectedAccess !== 'All' || selectedTags.length > 0"
                    variant="ghost" 
                    size="sm" 
                        @click="selectedType = 'All'; selectedDept = 'All'; selectedAccess = 'All'; selectedTags = []"
                    class="text-xs text-gray-500 hover:text-gray-700 dark:text-neutral-400 dark:hover:text-neutral-200"
                  >
                    <X class="w-3 h-3 mr-1" />
                    Clear filters
                  </Button>
                </div>
              </div>

                  <div v-if="departmentPaginatedFiles.length"
                    class="grid gap-4 grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 mt-5">
                    <div v-for="file in departmentPaginatedFiles" :key="file.id"
                  @click="openFileViewer(file)"
                      class="relative bg-white border border-gray-200 rounded-lg p-4 hover:shadow-lg cursor-pointer dark:bg-neutral-800 dark:border-neutral-700 dark:hover:bg-neutral-750">

                      <div class="flex flex-col h-full">
                        <div class="flex items-start gap-3 mb-3">
                          <div
                            :class="['w-16 h-16 flex items-center justify-center rounded-lg text-white font-bold text-sm shadow-sm shrink-0', typeColor(file.type)]">
                            {{ file.type }}
                          </div>
                          <div class="flex-1 min-w-0">
                            <h3 class="text-sm font-semibold text-gray-900 truncate w-full dark:text-neutral-100">
                              {{ file.name }}
                            </h3>
                            <div class="flex items-center gap-2 mt-1 text-xs text-gray-500 dark:text-neutral-400">
                              <span>{{ file.size }}</span>
                              <span>•</span>
                              <span>{{ file.department }}</span>
                            </div>
                            <div :class="['text-xs mt-1', getAccessTextColor(file.access)]">
                              {{ file.access }}
                            </div>
                          </div>
                        </div>

                        <div class="mt-auto pt-2 border-t border-gray-100 dark:border-neutral-700">
                          <div v-if="!file.tags || file.tags.length === 0" class="flex items-center gap-1.5 text-xs text-gray-400 dark:text-neutral-500">
                            <Tag :size="12" class="shrink-0" />
                            <span>No Tag</span>
                          </div>
                          <div v-else class="relative flex items-center gap-1.5 overflow-hidden flex-nowrap pr-8">
                            <Tag :size="12" class="shrink-0 text-gray-500 dark:text-neutral-400" />
                            <template v-for="(tag, index) in getVisibleTags(file.tags)" :key="index">
                              <Badge 
                                class="text-xs px-1.5 py-0.5 shrink-0 bg-gray-100 text-gray-700 dark:bg-neutral-700 dark:text-neutral-300 border-0"
                              >
                                {{ tag }}
                              </Badge>
                            </template>
                            <span
                              v-if="getHiddenTagsCount(file.tags) > 0"
                              class="absolute right-0 inline-flex items-center justify-center text-xs px-1.5 py-0.5 rounded bg-gray-100 text-gray-700 dark:bg-neutral-700 dark:text-neutral-300 border-0"
                            >
                              +{{ getHiddenTagsCount(file.tags) }}
                            </span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div v-else class="text-center py-12 sm:py-16 text-gray-500 dark:text-neutral-400 text-xs sm:text-sm">
                    No pending files found.
                  </div>

                  <div class="mt-6">
                    <Pagination v-model:page="departmentPage" :items-per-page="itemsPerPage" :total="departmentFilteredFiles.length" class="justify-end">
                      <PaginationContent v-slot="{ items }">
                        <PaginationPrevious />
                        <template v-for="(item, index) in items" :key="index">
                          <PaginationItem v-if="item.type === 'page'" :value="item.value">
                            {{ item.value }}
                          </PaginationItem>
                          <PaginationEllipsis v-else-if="item.type === 'ellipsis'" :index="index" />
                        </template>
                        <PaginationNext />
                      </PaginationContent>
                    </Pagination>
                  </div>
                </TabsContent>

                <!-- Rejected Department Files -->
                <TabsContent value="Rejected">
                  <div class="flex flex-col gap-3 mb-6">
                    <div class="relative w-full">
                      <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" />
                      <Input v-model="search" placeholder="Search File Name or Uploader Name" class="pl-10 h-10" />
                    </div>

                    <div class="flex gap-2 flex-wrap">
                      <Select v-model="selectedType">
                        <SelectTrigger class="h-9 w-[140px] text-xs">
                          <SelectValue placeholder="Type" />
                        </SelectTrigger>
                        <SelectContent class="dark:bg-neutral-800 dark:border-neutral-700">
                          <SelectItem value="All" class="text-xs dark:text-neutral-100">All Types</SelectItem>
                          <SelectItem 
                            v-for="t in fileTypes.filter(t => t !== 'All')" 
                            :key="t" 
                            :value="t"
                            class="text-xs dark:text-neutral-100"
                          >
                            {{ t }}
                          </SelectItem>
                        </SelectContent>
                      </Select>

                      <Select v-model="selectedDept">
                        <SelectTrigger class="h-9 w-[160px] text-xs">
                          <SelectValue placeholder="Department" />
                        </SelectTrigger>
                        <SelectContent class="dark:bg-neutral-800 dark:border-neutral-700">
                          <SelectItem value="All" class="text-xs dark:text-neutral-100">All Departments</SelectItem>
                          <SelectItem 
                            v-for="d in departments.filter(d => d !== 'All')" 
                            :key="d" 
                            :value="d"
                            class="text-xs dark:text-neutral-100"
                          >
                            {{ d }}
                          </SelectItem>
                        </SelectContent>
                      </Select>

                      <Select v-model="selectedAccess">
                        <SelectTrigger class="h-9 w-[150px] text-xs">
                          <SelectValue placeholder="Access" />
                        </SelectTrigger>
                        <SelectContent class="dark:bg-neutral-800 dark:border-neutral-700">
                          <SelectItem value="All" class="text-xs dark:text-neutral-100">All Access</SelectItem>
                          <SelectItem value="Public" class="text-xs dark:text-neutral-100">Public</SelectItem>
                          <SelectItem value="Private" class="text-xs dark:text-neutral-100">Private</SelectItem>
                          <SelectItem value="Department" class="text-xs dark:text-neutral-100">Department</SelectItem>
                        </SelectContent>
                      </Select>

                      <div class="relative">
                        <Select 
                          :model-value="selectedTags.length > 0 ? selectedTags.join(', ') : undefined"
                          @update:model-value="handleTagSelect"
                        >
                          <SelectTrigger class="h-9 w-[140px] text-xs">
                            <SelectValue :placeholder="selectedTags.length > 0 ? `${selectedTags.length} selected` : 'Tags'" />
                          </SelectTrigger>
                          <SelectContent class="dark:bg-neutral-800 dark:border-neutral-700 max-h-[200px]">
                            <div class="p-2">
                              <div class="space-y-1">
                                <div
                                  v-for="tag in tagOptions"
                                  :key="tag"
                                  @click.stop="toggleTagSelection(tag)"
                                  :class="[
                                    'px-2 py-1.5 rounded-md cursor-pointer text-xs transition-colors',
                                    selectedTags.includes(tag)
                                      ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'
                                      : 'hover:bg-gray-100 dark:hover:bg-neutral-700 dark:text-neutral-100'
                                  ]"
                                >
                                  <div class="flex items-center gap-2">
                                    <div 
                                      :class="[
                                        'w-4 h-4 rounded border flex items-center justify-center',
                                        selectedTags.includes(tag)
                                          ? 'bg-blue-600 border-blue-600'
                                          : 'border-gray-300 dark:border-neutral-600'
                                      ]"
                                    >
                                      <Check 
                                        v-if="selectedTags.includes(tag)"
                                        class="w-3 h-3 text-white"
                                      />
                                    </div>
                                    <span>{{ tag }}</span>
                                  </div>
                                </div>
                              </div>
                            </div>
                          </SelectContent>
                        </Select>
                      </div>

                      <Button 
                        v-if="selectedType !== 'All' || selectedDept !== 'All' || selectedAccess !== 'All' || selectedTags.length > 0"
                        variant="ghost" 
                        size="sm" 
                        @click="selectedType = 'All'; selectedDept = 'All'; selectedAccess = 'All'; selectedTags = []"
                        class="text-xs text-gray-500 hover:text-gray-700 dark:text-neutral-400 dark:hover:text-neutral-200"
                      >
                        <X class="w-3 h-3 mr-1" />
                        Clear filters
                        </Button>
                    </div>
                  </div>

                  <div v-if="departmentPaginatedFiles.length"
                    class="grid gap-4 grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 mt-5">
                    <div v-for="file in departmentPaginatedFiles" :key="file.id"
                      @click="openFileViewer(file)"
                      class="relative bg-white border border-gray-200 rounded-lg p-4 hover:shadow-lg cursor-pointer dark:bg-neutral-800 dark:border-neutral-700 dark:hover:bg-neutral-750">
                      <div class="flex flex-col h-full">
                        <div class="flex items-start gap-3 mb-3">
                          <div
                            :class="['w-16 h-16 flex items-center justify-center rounded-lg text-white font-bold text-sm shadow-sm shrink-0', typeColor(file.type)]">
                      {{ file.type }}
                    </div>
                          <div class="flex-1 min-w-0">
                            <h3 class="text-sm font-semibold text-gray-900 truncate w-full dark:text-neutral-100">
                            {{ file.name }}
                          </h3>
                            <div class="flex items-center gap-2 mt-1 text-xs text-gray-500 dark:text-neutral-400">
                              <span>{{ file.size }}</span>
                              <span>•</span>
                      <span>{{ file.department }}</span>
                    </div>
                            <div :class="['text-xs mt-1', getAccessTextColor(file.access)]">
                              {{ file.access }}
                  </div>
                </div>
              </div>

                        <div class="mt-auto pt-2 border-t border-gray-100 dark:border-neutral-700">
                          <div v-if="!file.tags || file.tags.length === 0" class="flex items-center gap-1.5 text-xs text-gray-400 dark:text-neutral-500">
                            <Tag :size="12" class="shrink-0" />
                            <span>No Tag</span>
              </div>
                          <div v-else class="relative flex items-center gap-1.5 overflow-hidden flex-nowrap pr-8">
                            <Tag :size="12" class="shrink-0 text-gray-500 dark:text-neutral-400" />
                            <template v-for="(tag, index) in getVisibleTags(file.tags)" :key="index">
                              <Badge 
                                class="text-xs px-1.5 py-0.5 shrink-0 bg-gray-100 text-gray-700 dark:bg-neutral-700 dark:text-neutral-300 border-0"
                              >
                                {{ tag }}
                              </Badge>
                            </template>
                            <span
                              v-if="getHiddenTagsCount(file.tags) > 0"
                              class="absolute right-0 inline-flex items-center justify-center text-xs px-1.5 py-0.5 rounded bg-gray-100 text-gray-700 dark:bg-neutral-700 dark:text-neutral-300 border-0"
                            >
                              +{{ getHiddenTagsCount(file.tags) }}
                            </span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div v-else class="text-center py-12 sm:py-16 text-gray-500 dark:text-neutral-400 text-xs sm:text-sm">
                    No rejected files found.
                  </div>

              <div class="mt-6">
                    <Pagination v-model:page="departmentPage" :items-per-page="itemsPerPage" :total="departmentFilteredFiles.length" class="justify-end">
                  <PaginationContent v-slot="{ items }">
                    <PaginationPrevious />
                    <template v-for="(item, index) in items" :key="index">
                      <PaginationItem v-if="item.type === 'page'" :value="item.value">
                        {{ item.value }}
                      </PaginationItem>
                      <PaginationEllipsis v-else-if="item.type === 'ellipsis'" :index="index" />
                    </template>
                    <PaginationNext />
                  </PaginationContent>
                </Pagination>
              </div>
                </TabsContent>
              </Tabs>
            </CardContent>
          </Card>
        </TabsContent>

        <!-- Request Tab -->
        <TabsContent value="Request">
          <Card class="dark:bg-neutral-900 dark:border-neutral-700">
            <CardHeader class="dark:border-neutral-700">
              <CardTitle class="dark:text-neutral-100">Document Requests</CardTitle>
              <CardDescription class="dark:text-neutral-400">Manage document access requests</CardDescription>
            </CardHeader>
            <CardContent class="dark:bg-neutral-900">
              <!-- Internal Tabs: Incoming and Outgoing -->
              <Tabs v-model="requestInternalTab" class="w-full">
                <TabsList :class="['grid w-full mb-6 dark:bg-neutral-800 dark:border-neutral-700', isDepartmentManager ? 'grid-cols-2' : 'grid-cols-1']">
                  <TabsTrigger 
                    v-if="isAdminUser || isDepartmentManager"
                    value="Upload"
                    class="dark:text-neutral-300 dark:data-[state=active]:text-white dark:data-[state=active]:bg-neutral text-xs sm:text-sm">
                    <div class="flex items-center gap-1 sm:gap-2">
                      <Inbox :size="14" class="shrink-0 sm:w-4 sm:h-4" />
                      <span>Incoming</span>
                    </div>
                  </TabsTrigger>
                  <TabsTrigger 
                    v-if="!isAdminUser"
                    value="Permission"
                    class="dark:text-neutral-300 dark:data-[state=active]:text-white dark:data-[state=active]:bg-neutral text-xs sm:text-sm">
                    <div class="flex items-center gap-1 sm:gap-2">
                      <Forward :size="14" class="shrink-0 sm:w-4 sm:h-4" />
                      <span>Outgoing</span>
                    </div>
                  </TabsTrigger>
                </TabsList>


                <!-- Incoming Tab Content -->
                <TabsContent value="Upload" class="space-y-6">
                  <!-- Upload Status with To You / By You buttons -->
                  <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-4">
                    <div class="inline-flex items-center gap-3 rounded-lg border border-gray-200 dark:border-neutral-700 bg-gradient-to-r from-white to-gray-50 dark:from-neutral-900 dark:to-neutral-800 px-3 py-2">
                      <div class="flex items-center gap-2">
                        <Info class="w-3.5 h-3.5 text-blue-500" />
                        <span class="text-xs font-semibold text-gray-900 dark:text-neutral-100">Permission Status:</span>
                      </div>
                      <div class="flex items-center gap-1.5">
                        <div class="w-1.5 h-1.5 rounded-full bg-green-500"></div>
                        <span class="text-xs text-green-700 dark:text-green-400">{{ uploadCounts.approved }} Approved</span>
                      </div>
                      <div class="flex items-center gap-1.5">
                        <div class="w-1.5 h-1.5 rounded-full bg-amber-500"></div>
                        <span class="text-xs text-amber-700 dark:text-amber-400">{{ uploadCounts.pending }} Pending</span>
                      </div>
                      <div class="flex items-center gap-1.5">
                        <div class="w-1.5 h-1.5 rounded-full bg-red-500"></div>
                        <span class="text-xs text-red-700 dark:text-red-400">{{ uploadCounts.rejected }} Rejected</span>
                      </div>
                    </div>
                  </div>

                  <!-- Search and History -->
                  <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 mb-6">
                    <div class="relative flex-1">
                     <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" />
                      <Input v-model="search" placeholder="Search File Name or User Name"
                        class="pl-10 h-10" />
                    </div>
                  </div>

                  <!-- Upload Filters -->
                  <div class="flex gap-2 flex-wrap">
                    <!-- Type Filter -->
                    <Select v-model="selectedReqType">
                      <SelectTrigger class="h-9 w-[140px] text-xs">
                        <SelectValue placeholder="Type" />
                      </SelectTrigger>
                      <SelectContent class="dark:bg-neutral-800 dark:border-neutral-700">
                        <SelectItem value="All" class="text-xs dark:text-neutral-100">All Types</SelectItem>
                        <SelectItem 
                          v-for="t in requestTypes.filter(t => t !== 'All')" 
          :key="t"
                          :value="t"
                          class="text-xs dark:text-neutral-100"
        >
          {{ t }}
                        </SelectItem>
                      </SelectContent>
                    </Select>

                    <!-- Department Filter -->
                    <Select v-model="selectedReqDept">
                      <SelectTrigger class="h-9 w-[160px] text-xs">
                        <SelectValue placeholder="Department" />
                      </SelectTrigger>
                      <SelectContent class="dark:bg-neutral-800 dark:border-neutral-700">
                        <SelectItem value="All" class="text-xs dark:text-neutral-100">All Departments</SelectItem>
                        <SelectItem 
                          v-for="d in departments.filter(d => d !== 'All')" 
                :key="d"
                          :value="d"
                          class="text-xs dark:text-neutral-100"
              >
                  {{ d }}
                        </SelectItem>
                      </SelectContent>
                    </Select>

                    <!-- Accessibility Filter -->
                    <Select v-model="selectedReqAccess">
                      <SelectTrigger class="h-9 w-[150px] text-xs">
                        <SelectValue placeholder="Access" />
                      </SelectTrigger>
                      <SelectContent class="dark:bg-neutral-800 dark:border-neutral-700">
                        <SelectItem value="All" class="text-xs dark:text-neutral-100">All Access</SelectItem>
                        <SelectItem value="Public" class="text-xs dark:text-neutral-100">Public</SelectItem>
                        <SelectItem value="Private" class="text-xs dark:text-neutral-100">Private</SelectItem>
                        <SelectItem value="Department" class="text-xs dark:text-neutral-100">Department</SelectItem>
                      </SelectContent>
                    </Select>

                    <!-- Tag Filter (Multi-select) -->
                    <div class="relative">
                      <Select 
                        :model-value="selectedReqTags.length > 0 ? selectedReqTags.join(', ') : undefined"
                        @update:model-value="handleTagSelectForRequest"
                      >
                        <SelectTrigger class="h-9 w-[140px] text-xs">
                          <SelectValue :placeholder="selectedReqTags.length > 0 ? `${selectedReqTags.length} selected` : 'Tags'" />
                        </SelectTrigger>
                        <SelectContent class="dark:bg-neutral-800 dark:border-neutral-700 max-h-[200px]">
                          <div class="p-2">
                            <div class="space-y-1">
                              <div
                                v-for="tag in tagOptions"
                                :key="tag"
                                @click.stop="toggleTagSelectionForRequest(tag)"
                                :class="[
                                  'px-2 py-1.5 rounded-md cursor-pointer text-xs transition-colors',
                                  selectedReqTags.includes(tag)
                                    ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'
                                    : 'hover:bg-gray-100 dark:hover:bg-neutral-700 dark:text-neutral-100'
                                ]"
                              >
                                <div class="flex items-center gap-2">
                                  <div 
                                    :class="[
                                      'w-4 h-4 rounded border flex items-center justify-center',
                                      selectedReqTags.includes(tag)
                                        ? 'bg-blue-600 border-blue-600'
                                        : 'border-gray-300 dark:border-neutral-600'
                                    ]"
                                  >
                                    <Check 
                                      v-if="selectedReqTags.includes(tag)"
                                      class="w-3 h-3 text-white"
                                    />
                                  </div>
                                  <span>{{ tag }}</span>
                                </div>
                              </div>
                            </div>
                          </div>
                        </SelectContent>
                      </Select>
                    </div>

                    <!-- Status Filter -->
                    <Select v-model="selectedReqStatus">
                      <SelectTrigger class="h-9 w-[140px] text-xs">
                        <SelectValue placeholder="Status" />
                      </SelectTrigger>
                      <SelectContent class="dark:bg-neutral-800 dark:border-neutral-700">
                        <SelectItem value="All" class="text-xs dark:text-neutral-100">All Status</SelectItem>
                        <SelectItem 
                          v-for="s in requestStatuses.filter(s => s !== 'All')" 
                                    :key="s"
                          :value="s"
                          class="text-xs dark:text-neutral-100"
                                                >
                                              {{ s }}
                        </SelectItem>
                      </SelectContent>
                    </Select>

                    <!-- Clear Filters Button -->
                    <Button 
                      v-if="selectedReqType !== 'All' || selectedReqDept !== 'All' || selectedReqAccess !== 'All' || selectedReqTags.length > 0 || selectedReqStatus !== 'All'"
                      variant="ghost" 
                      size="sm" 
                      @click="selectedReqType = 'All'; selectedReqDept = 'All'; selectedReqAccess = 'All'; selectedReqTags = []; selectedReqStatus = 'All'"
                      class="text-xs text-gray-500 hover:text-gray-700 dark:text-neutral-400 dark:hover:text-neutral-200"
                    >
                      <X class="w-3 h-3 mr-1" />
                      Clear filters
                    </Button>
                        </div>

                  <!-- Upload Grid -->
                  <div>
                    <div v-if="paginatedUploads.length"
                      class="grid gap-4 grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 mt-5">

                      <!-- Upload Cards -->
                      <div
                        v-for="upload in paginatedUploads"
                        :key="upload.id"
                        @click="openUploadDetails(upload)"
                        class="relative bg-white border border-gray-200 rounded-lg p-4 hover:shadow-lg transition-all dark:bg-neutral-800 dark:border-neutral-700 dark:hover:bg-neutral-750 cursor-pointer"
                      >
                        <!-- Card body -->
                        <div class="flex flex-col h-full">
                          <div class="flex items-start gap-3 mb-3">
                            <div
                              :class="[
                                'w-14 h-14 flex items-center justify-center rounded-lg text-white font-bold text-xs shadow-sm shrink-0',
                                typeColor(upload.type || inferTypeFromName(upload.name)),
                              ]"
                            >
                              {{
                                (upload.type || inferTypeFromName(upload.name)) === 'Word'
                                  ? 'WORD'
                                  : (upload.type || inferTypeFromName(upload.name))
                              }}
                          </div>
                            <div class="flex-1 min-w-0">
                              <div class="flex items-start justify-between gap-2">
                          <h3
                                  class="text-sm font-semibold text-gray-900 truncate w-full dark:text-neutral-100"
                                  :title="upload.fullName || upload.name"
                                >
                            {{ upload.name }}
                          </h3>
                                <component
                                  :is="statusIconComponent(upload.status)"
                                  class="w-4 h-4 shrink-0"
                                  :class="statusIconClass(upload.status)"
                                />
                              </div>
                              <p class="text-xs text-gray-600 dark:text-neutral-300 mt-1 truncate">
                                {{ upload.requester || '—' }} | {{ getRequesterDepartmentCode(upload) }}
                              </p>
                              <p class="text-xs text-gray-500 dark:text-neutral-400 mt-0.5 truncate">
                                <span :class="getAccessTextColor(upload.access)">{{ upload.access }}</span>
                                <span class="text-gray-500 dark:text-neutral-400"> • {{ getRequesterDepartmentCode(upload) }}</span>
                              </p>
                            </div>
                          </div>

                          <div class="mt-auto pt-2 border-t border-gray-100 dark:border-neutral-700">
                            <div
                              v-if="!upload.tags || upload.tags.length === 0"
                              class="flex items-center gap-1.5 text-xs text-gray-400 dark:text-neutral-500"
                            >
                              <Tag :size="12" class="shrink-0" />
                              <span>No Tag</span>
                            </div>
                            <div v-else class="relative flex items-center gap-1.5 overflow-hidden flex-nowrap pr-8">
                              <Tag :size="12" class="shrink-0 text-gray-500 dark:text-neutral-400" />
                              <template v-for="(tag, index) in getVisibleTags(upload.tags)" :key="index">
                            <Badge
                                  class="text-xs px-1.5 py-0.5 shrink-0 bg-gray-100 text-gray-700 dark:bg-neutral-700 dark:text-neutral-300 border-0"
                                >
                                  {{ tag }}
                            </Badge>
                              </template>
                              <span
                                v-if="getHiddenTagsCount(upload.tags) > 0"
                                class="absolute right-0 inline-flex items-center justify-center text-xs px-1.5 py-0.5 rounded bg-gray-100 text-gray-700 dark:bg-neutral-700 dark:text-neutral-300 border-0"
                              >
                                +{{ getHiddenTagsCount(upload.tags) }}
                              </span>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>

                    <div v-else
                      class="text-center text-gray-500 dark:text-neutral-400 py-8 sm:py-10 text-sm sm:text-base">
                      No matches for '{{ search }}'. Try removing filters.
                    </div>

                    <!-- Pagination -->
                    <div class="mt-6">
                      <Pagination v-model:page="currentPage" :items-per-page="itemsPerPage" :total="filteredUploadsBase.length" class="justify-end">
                        <PaginationContent v-slot="{ items }">
                          <PaginationPrevious />
                          <template v-for="(item, index) in items" :key="index">
                            <PaginationItem v-if="item.type === 'page'" :value="item.value">
                              {{ item.value }}
                            </PaginationItem>
                            <PaginationEllipsis v-else-if="item.type === 'ellipsis'" :index="index" />
                          </template>
                          <PaginationNext />
                        </PaginationContent>
                      </Pagination>
                    </div>
                  </div>
                </TabsContent>

                <!-- Outgoing Tab Content -->
                <TabsContent value="Permission" class="space-y-6">
                  <!-- Permission Status -->
                  <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-4">
                    <div class="inline-flex items-center gap-3 rounded-lg border border-gray-200 dark:border-neutral-700 bg-gradient-to-r from-white to-gray-50 dark:from-neutral-900 dark:to-neutral-800 px-3 py-2">
                      <div class="flex items-center gap-2">
                        <Info class="w-3.5 h-3.5 text-blue-500" />
                        <span class="text-xs font-semibold text-gray-900 dark:text-neutral-100">Permission Status:</span>
                      </div>
                      <div class="flex items-center gap-1.5">
                        <div class="w-1.5 h-1.5 rounded-full bg-green-500"></div>
                        <span class="text-xs text-green-700 dark:text-green-400">{{ permissionCounts.approved }} Approved</span>
                      </div>
                      <div class="flex items-center gap-1.5">
                        <div class="w-1.5 h-1.5 rounded-full bg-amber-500"></div>
                        <span class="text-xs text-amber-700 dark:text-amber-400">{{ permissionCounts.pending }} Pending</span>
                      </div>
                      <div class="flex items-center gap-1.5">
                        <div class="w-1.5 h-1.5 rounded-full bg-red-500"></div>
                        <span class="text-xs text-red-700 dark:text-red-400">{{ permissionCounts.rejected }} Rejected</span>
                      </div>
                    </div>
                  </div>

                  <!-- Search -->
                  <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 mb-6">
                    <div class="relative flex-1">
                      <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" />
                      <Input v-model="search" placeholder="Search File Name or User Name" class="pl-10 h-10" />
                    </div>
                  </div>

                  <!-- Permission Filters -->  
                  <div class="flex gap-2 flex-wrap">
                    <!-- Type Filter -->
                    <Select v-model="selectedReqType">
                      <SelectTrigger class="h-9 w-[140px] text-xs">
                        <SelectValue placeholder="Type" />
                      </SelectTrigger>
                      <SelectContent class="dark:bg-neutral-800 dark:border-neutral-700">
                        <SelectItem value="All" class="text-xs dark:text-neutral-100">All Types</SelectItem>
                        <SelectItem 
                          v-for="t in requestTypes.filter(t => t !== 'All')" 
        :key="t"
                          :value="t"
                          class="text-xs dark:text-neutral-100"
      >
        {{ t }}
                        </SelectItem>
                      </SelectContent>
                    </Select>

                  <!-- Department Filter -->
                    <Select v-model="selectedReqDept">
                      <SelectTrigger class="h-9 w-[160px] text-xs">
                        <SelectValue placeholder="Department" />
                      </SelectTrigger>
                      <SelectContent class="dark:bg-neutral-800 dark:border-neutral-700">
                        <SelectItem value="All" class="text-xs dark:text-neutral-100">All Departments</SelectItem>
                        <SelectItem 
                          v-for="d in departments.filter(d => d !== 'All')" 
              :key="d"
                          :value="d"
                          class="text-xs dark:text-neutral-100"
             >
                {{ d }}
                        </SelectItem>
                      </SelectContent>
                    </Select>

                    <!-- Accessibility Filter -->
                    <Select v-model="selectedReqAccess">
                      <SelectTrigger class="h-9 w-[150px] text-xs">
                        <SelectValue placeholder="Access" />
                      </SelectTrigger>
                      <SelectContent class="dark:bg-neutral-800 dark:border-neutral-700">
                        <SelectItem value="All" class="text-xs dark:text-neutral-100">All Access</SelectItem>
                        <SelectItem value="Public" class="text-xs dark:text-neutral-100">Public</SelectItem>
                        <SelectItem value="Private" class="text-xs dark:text-neutral-100">Private</SelectItem>
                        <SelectItem value="Department" class="text-xs dark:text-neutral-100">Department</SelectItem>
                      </SelectContent>
                    </Select>

                    <!-- Tag Filter (Multi-select) -->
                    <div class="relative">
                      <Select 
                        :model-value="selectedReqTags.length > 0 ? selectedReqTags.join(', ') : undefined"
                        @update:model-value="handleTagSelectForRequest"
                      >
                        <SelectTrigger class="h-9 w-[140px] text-xs">
                          <SelectValue :placeholder="selectedReqTags.length > 0 ? `${selectedReqTags.length} selected` : 'Tags'" />
                        </SelectTrigger>
                        <SelectContent class="dark:bg-neutral-800 dark:border-neutral-700 max-h-[200px]">
                          <div class="p-2">
                            <div class="space-y-1">
                              <div
                                v-for="tag in tagOptions"
                                :key="tag"
                                @click.stop="toggleTagSelectionForRequest(tag)"
                                :class="[
                                  'px-2 py-1.5 rounded-md cursor-pointer text-xs transition-colors',
                                  selectedReqTags.includes(tag)
                                    ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'
                                    : 'hover:bg-gray-100 dark:hover:bg-neutral-700 dark:text-neutral-100'
                                ]"
                              >
                                <div class="flex items-center gap-2">
                                  <div 
                                    :class="[
                                      'w-4 h-4 rounded border flex items-center justify-center',
                                      selectedReqTags.includes(tag)
                                        ? 'bg-blue-600 border-blue-600'
                                        : 'border-gray-300 dark:border-neutral-600'
                                    ]"
                                  >
                                    <Check 
                                      v-if="selectedReqTags.includes(tag)"
                                      class="w-3 h-3 text-white"
                                    />
                                  </div>
                                  <span>{{ tag }}</span>
                                </div>
                              </div>
                            </div>
                          </div>
                        </SelectContent>
                      </Select>
                    </div>

                    <!-- Status Filter -->
                    <Select v-model="selectedReqStatus">
                      <SelectTrigger class="h-9 w-[140px] text-xs">
                        <SelectValue placeholder="Status" />
                      </SelectTrigger>
                      <SelectContent class="dark:bg-neutral-800 dark:border-neutral-700">
                        <SelectItem value="All" class="text-xs dark:text-neutral-100">All Status</SelectItem>
                        <SelectItem 
                          v-for="s in requestStatuses.filter(s => s !== 'All')" 
                                  :key="s"
                          :value="s"
                          class="text-xs dark:text-neutral-100"
                                              >
                                            {{ s }}
                        </SelectItem>
                      </SelectContent>
                    </Select>

                    <!-- Clear Filters Button -->
                    <Button 
                      v-if="selectedReqType !== 'All' || selectedReqDept !== 'All' || selectedReqAccess !== 'All' || selectedReqTags.length > 0 || selectedReqStatus !== 'All'"
                      variant="ghost" 
                      size="sm" 
                      @click="selectedReqType = 'All'; selectedReqDept = 'All'; selectedReqAccess = 'All'; selectedReqTags = []; selectedReqStatus = 'All'"
                      class="text-xs text-gray-500 hover:text-gray-700 dark:text-neutral-400 dark:hover:text-neutral-200"
                    >
                      <X class="w-3 h-3 mr-1" />
                      Clear filters
                    </Button>
                  </div>

                  <!-- Permission Grid -->
                  <div>
                    <div v-if="paginatedPermissions.length"
                      class="grid gap-4 grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 mt-5">

                      <!-- Permission Cards -->
                      <div
                        v-for="permission in paginatedPermissions"
                        :key="permission.id"
                        @click="openPermissionDetails(permission)"
                        class="relative bg-white border border-gray-200 rounded-lg p-4 hover:shadow-lg transition-all dark:bg-neutral-800 dark:border-neutral-700 dark:hover:bg-neutral-750 cursor-pointer"
                      >
                        <!-- Card body -->
                        <div class="flex flex-col h-full">
                          <div class="flex items-start gap-3 mb-3">
                            <div
                              :class="[
                                'w-14 h-14 flex items-center justify-center rounded-lg text-white font-bold text-xs shadow-sm shrink-0',
                                typeColor(permission.type || inferTypeFromName(permission.name)),
                              ]"
                            >
                              {{
                                (permission.type || inferTypeFromName(permission.name)) === 'Word'
                                  ? 'WORD'
                                  : (permission.type || inferTypeFromName(permission.name))
                              }}
                          </div>
                            <div class="flex-1 min-w-0">
                              <div class="flex items-start justify-between gap-2">
                          <h3
                                  class="text-sm font-semibold text-gray-900 truncate w-full dark:text-neutral-100"
                                  :title="permission.fullName || permission.name"
                                >
                            {{ permission.name }}
                          </h3>
                                <component
                                  :is="statusIconComponent(permission.status)"
                                  class="w-4 h-4 shrink-0"
                                  :class="statusIconClass(permission.status)"
                                />
                              </div>
                              <p class="text-xs text-gray-600 dark:text-neutral-300 mt-1 truncate">
                                {{ permission.requester || '—' }} | {{ getRequesterDepartmentCode(permission) }}
                              </p>
                              <p class="text-xs text-gray-500 dark:text-neutral-400 mt-0.5 truncate">
                                <span :class="getAccessTextColor(permission.access)">{{ permission.access }}</span>
                                <span class="text-gray-500 dark:text-neutral-400"> • {{ getRequesterDepartmentCode(permission) }}</span>
                              </p>
                            </div>
                          </div>

                          <div class="mt-auto pt-2 border-t border-gray-100 dark:border-neutral-700">
                            <div
                              v-if="!permission.tags || permission.tags.length === 0"
                              class="flex items-center gap-1.5 text-xs text-gray-400 dark:text-neutral-500"
                            >
                              <Tag :size="12" class="shrink-0" />
                              <span>No Tag</span>
                            </div>
                            <div v-else class="relative flex items-center gap-1.5 overflow-hidden flex-nowrap pr-8">
                              <Tag :size="12" class="shrink-0 text-gray-500 dark:text-neutral-400" />
                              <template v-for="(tag, index) in getVisibleTags(permission.tags)" :key="index">
                            <Badge
                                  class="text-xs px-1.5 py-0.5 shrink-0 bg-gray-100 text-gray-700 dark:bg-neutral-700 dark:text-neutral-300 border-0"
                                >
                                  {{ tag }}
                            </Badge>
                              </template>
                              <span
                                v-if="getHiddenTagsCount(permission.tags) > 0"
                                class="absolute right-0 inline-flex items-center justify-center text-xs px-1.5 py-0.5 rounded bg-gray-100 text-gray-700 dark:bg-neutral-700 dark:text-neutral-300 border-0"
                              >
                                +{{ getHiddenTagsCount(permission.tags) }}
                              </span>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>

                    <div v-else
                      class="text-center text-gray-500 dark:text-neutral-400 py-8 sm:py-10 text-sm sm:text-base">
                      No matches for '{{ search }}'. Try removing filters.
                    </div>

                    <!-- Pagination -->
                    <div class="mt-6">
                      <Pagination v-model:page="currentPage" :items-per-page="itemsPerPage" :total="filteredPermissionsBase.length" class="justify-end">
                        <PaginationContent v-slot="{ items }">
                          <PaginationPrevious />
                          <template v-for="(item, index) in items" :key="index">
                            <PaginationItem v-if="item.type === 'page'" :value="item.value">
                              {{ item.value }}
                            </PaginationItem>
                            <PaginationEllipsis v-else-if="item.type === 'ellipsis'" :index="index" />
                          </template>
                          <PaginationNext />
                        </PaginationContent>
                      </Pagination>
                    </div>
                  </div>
                </TabsContent>
              </Tabs>
            </CardContent>
          </Card>
        </TabsContent>

        <!-- Trash Tab -->
        <TabsContent value="Trash">
          <Card class="dark:bg-neutral-900 dark:border-neutral-700">
            <CardHeader class="dark:border-neutral-700">
              <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div>
                  <CardTitle class="dark:text-neutral-100">Recycle Bin</CardTitle>
                  <CardDescription class="dark:text-neutral-400">Files here will be permanently deleted after 30 days
                  </CardDescription>
                </div>
                <div class="flex gap-2">
                  <Button variant="secondary" @click="restoreAll"
                    class="dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-100">
                    Restore All
                  </Button>
                  <Button variant="destructive" @click="deleteAll"
                    class="dark:bg-red-900/30 dark:text-red-400 dark:border-red-800">
                    Delete All
                  </Button>
                </div>
              </div>
            </CardHeader>
            <CardContent class="bg-red-50/50 dark:bg-red-950/20">
              <div v-if="trashFiles.length"
                class="grid gap-4 grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                <div v-for="t in trashFiles" :key="t.id"
                  @click="openFileViewer(t)"
                  :class="[
                    'relative bg-white border rounded-lg p-4 hover:shadow-lg cursor-pointer dark:bg-neutral-800 dark:hover:bg-neutral-750',
                    getAccessBorderClass(t.access),
                  ]">
                  <!-- Card body -->
                  <div class="flex flex-col h-full">
                    <div class="flex items-start gap-3 mb-3">
                    <div
                        :class="['w-16 h-16 flex items-center justify-center rounded-lg text-white font-bold text-sm shadow-sm shrink-0', typeColor(t.type)]">
                      {{ t.type }}
                    </div>
                      <div class="flex-1 min-w-0">
                        <div class="flex items-start justify-between gap-2">
                          <h3 class="text-sm font-semibold text-gray-900 truncate w-full dark:text-neutral-100">
                            {{ t.name }}
                          </h3>
                          <Trash2Icon class="w-4 h-4 shrink-0 text-red-500 dark:text-red-400" />
                        </div>
                        <div class="flex items-center gap-2 mt-1 text-xs text-gray-500 dark:text-neutral-400">
                          <span>{{ t.size }}</span>
                          <span>•</span>
                          <span>{{ t.department }}</span>
                        </div>
                        <div :class="['text-xs mt-1', getAccessTextColor(t.access)]">
                          {{ t.access }}
                        </div>
                      </div>
                    </div>
                    
                    <!-- Tags Section (at bottom) -->
                    <div class="mt-auto pt-2 border-t border-gray-100 dark:border-neutral-700">
                      <div v-if="!t.tags || t.tags.length === 0" class="flex items-center gap-1.5 text-xs text-gray-400 dark:text-neutral-500">
                        <Tag :size="12" class="shrink-0" />
                        <span>No Tag</span>
                      </div>
                      <div v-else class="relative flex items-center gap-1.5 overflow-hidden flex-nowrap pr-8">
                        <Tag :size="12" class="shrink-0 text-gray-500 dark:text-neutral-400" />
                        <template v-for="(tag, index) in getVisibleTags(t.tags)" :key="index">
                          <Badge 
                            class="text-xs px-1.5 py-0.5 shrink-0 bg-gray-100 text-gray-700 dark:bg-neutral-700 dark:text-neutral-300 border-0"
                          >
                            {{ tag }}
                          </Badge>
                        </template>
                        <span
                          v-if="getHiddenTagsCount(t.tags) > 0"
                          class="absolute right-0 inline-flex items-center justify-center text-xs px-1.5 py-0.5 rounded bg-gray-100 text-gray-700 dark:bg-neutral-700 dark:text-neutral-300 border-0"
                        >
                          +{{ getHiddenTagsCount(t.tags) }}
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div v-else class="text-center text-gray-500 dark:text-neutral-400 py-10 sm:py-12">
                <Trash2Icon
                  class="w-12 h-12 sm:w-16 sm:h-16 mx-auto text-gray-300 dark:text-neutral-600 mb-2 sm:mb-3" />
                <p class="text-base sm:text-lg dark:text-neutral-100">Trash is empty.</p>
              </div>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>

    <!-- ========================================================================
  SMART SEARCH DIALOG 
======================================================================= -->
    <Dialog v-model:open="smartSearchDialogOpen">
      <DialogContent class="max-w-[95vw] sm:max-w-6xl max-h-[90vh] flex flex-col min-h-0 dark:bg-neutral-900 dark:border-neutral-700">
        <DialogHeader class="shrink-0 border-b pb-4 dark:border-neutral-700">
          <DialogTitle class="text-xl font-semibold dark:text-neutral-100 flex items-center gap-2">
            <Search class="w-5 h-5 text-blue-600 dark:text-blue-400" />
            Smart Search
          </DialogTitle>
          <DialogDescription class="text-sm dark:text-neutral-400">
            Choose between <span class="font-semibold">Keyword</span> and <span class="font-semibold">Context</span> modes.
            For now, both modes use the same keyword search logic under the hood.
          </DialogDescription>
        </DialogHeader>

        <!-- Search Bar Section -->
        <div class="shrink-0 px-6 py-4 border-b dark:border-neutral-700 bg-gray-50 dark:bg-neutral-800/50 space-y-3">
          <!-- Mode Toggle -->
          <div class="flex items-center gap-2">
            <span class="text-xs font-medium text-gray-600 dark:text-neutral-300">Search mode:</span>
            <div class="inline-flex rounded-lg border border-border/70 overflow-hidden dark:border-neutral-700">
              <button
                type="button"
                class="px-3 py-1.5 text-xs sm:text-sm font-medium transition-colors"
                :class="smartSearchMode === 'keywords'
                  ? 'bg-blue-600 text-white'
                  : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-700'"
                @click="smartSearchMode = 'keywords'"
              >
                Keyword
              </button>
              <button
                type="button"
                class="px-3 py-1.5 text-xs sm:text-sm font-medium border-l border-border/70 transition-colors dark:border-neutral-700"
                :class="smartSearchMode === 'context'
                  ? 'bg-blue-600 text-white'
                  : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-700'"
                @click="smartSearchMode = 'context'"
              >
                Context
              </button>
            </div>
          </div>

          <div class="flex flex-col sm:flex-row gap-3">
            <!-- Search Input -->
            <div class="flex-1 relative">
              <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-neutral-500" />
              <Input
                v-model="smartSearchQuery"
                @keyup.enter="performSmartSearch"
                :placeholder="smartSearchMode === 'keywords'
                  ? 'Enter keywords to search (e.g., report, budget, Q4)...'
                  : 'Describe what you are looking for (e.g., policies about remote work)...'"
                class="pl-10 h-11 dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-100"
              />
            </div>

            <!-- Search Button -->
            <Button
              @click="performSmartSearch"
              :disabled="smartSearchLoading || !smartSearchQuery.trim()"
              class="h-11 px-6 bg-blue-600 hover:bg-blue-700 text-white"
            >
              <Loader2 v-if="smartSearchLoading" class="w-4 h-4 mr-2 animate-spin" />
              <Search v-else class="w-4 h-4 mr-2" />
              Search
            </Button>
          </div>
        </div>

        <!-- Results Section -->
        <div 
          class="flex-1 overflow-y-auto min-h-0 scrollbar-auto-hide scrollbar-hidden"
          @mouseenter="showScrollbar"
          @mouseleave="hideScrollbar"
          @scroll="onScroll"
        >
          <div class="p-6">
            <!-- Loading State -->
            <div v-if="smartSearchLoading" class="flex flex-col items-center justify-center py-12">
              <Loader2 class="w-8 h-8 text-blue-600 dark:text-blue-400 animate-spin mb-4" />
              <p class="text-sm text-gray-600 dark:text-neutral-400">Searching documents...</p>
                      </div>

            <!-- Empty State (No Search Yet) -->
            <div v-else-if="!smartSearchQuery.trim() && visibleSearchResults.length === 0" class="flex flex-col items-center justify-center py-12">
              <div class="w-16 h-16 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center mb-4">
                <Search class="w-8 h-8 text-blue-600 dark:text-blue-400" />
                      </div>
              <h3 class="text-lg font-semibold text-gray-900 dark:text-neutral-100 mb-2">Start Your Search</h3>
              <p class="text-sm text-gray-600 dark:text-neutral-400 text-center max-w-md">
                Enter keywords or describe what you're looking for to find relevant documents.
              </p>
                    </div>

            <!-- No Results -->
            <div v-else-if="visibleSearchResults.length === 0" class="flex flex-col items-center justify-center py-12">
              <div class="w-16 h-16 rounded-full bg-gray-100 dark:bg-neutral-800 flex items-center justify-center mb-4">
                <FolderIcon class="w-8 h-8 text-gray-400 dark:text-neutral-500" />
                      </div>
              <h3 class="text-lg font-semibold text-gray-900 dark:text-neutral-100 mb-2">No Results Found</h3>
              <p class="text-sm text-gray-600 dark:text-neutral-400 text-center max-w-md">
                Try different keywords or refine your search terms.
              </p>
                      </div>

            <!-- Results Grid -->
            <div v-else class="space-y-4">
              <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-neutral-100">
                  Found {{ visibleSearchResults.length }} result{{ visibleSearchResults.length > 1 ? 's' : '' }}
                </h3>
                <Button
                  variant="ghost"
                  size="sm"
                  @click="smartSearchQuery = ''; smartSearchResults.value = []"
                  class="text-xs text-gray-600 dark:text-neutral-400"
                >
                  Clear
                </Button>
                    </div>

              <!-- Document Cards Grid -->
              <div class="grid gap-4 grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                <div
                  v-for="result in visibleSearchResults"
                  :key="result.id"
                  @click="openFileViewer(result)"
                  class="relative bg-white border border-gray-200 rounded-lg p-4 hover:shadow-lg cursor-pointer transition-shadow dark:bg-neutral-800 dark:border-neutral-700 dark:hover:bg-neutral-750"
                >
                  <!-- Card body -->
                  <div class="flex flex-col h-full">
                    <div class="flex items-start gap-3 mb-3">
                      <div
                        :class="['w-16 h-16 flex items-center justify-center rounded-lg text-white font-bold text-sm shadow-sm shrink-0', typeColor(result.type)]">
                        {{ result.type }}
                      </div>
                      <div class="flex-1 min-w-0">
                        <h3 class="text-sm font-semibold text-gray-900 truncate w-full dark:text-neutral-100">
                          {{ result.name }}
                        </h3>
                        <div class="flex items-center gap-2 mt-1 text-xs text-gray-500 dark:text-neutral-400">
                          <span>{{ result.size }}</span>
                          <span>•</span>
                          <span>{{ result.department }}</span>
                        </div>
                        <div :class="['text-xs mt-1', getAccessTextColor(result.access)]">
                          {{ result.access }}
                        </div>
                      </div>
                    </div>
                    
                    <!-- Tags Section (at bottom) -->
                    <div class="mt-auto pt-2 border-t border-gray-100 dark:border-neutral-700">
                      <div v-if="!result.tags || result.tags.length === 0" class="flex items-center gap-1.5 text-xs text-gray-400 dark:text-neutral-500">
                        <Tag :size="12" class="shrink-0" />
                        <span>No Tag</span>
                      </div>
                      <div v-else class="relative flex items-center gap-1.5 overflow-hidden flex-nowrap pr-8">
                        <Tag :size="12" class="shrink-0 text-gray-500 dark:text-neutral-400" />
                        <template v-for="(tag, index) in getVisibleTags(result.tags)" :key="index">
                          <Badge 
                            class="text-xs px-1.5 py-0.5 shrink-0 bg-gray-100 text-gray-700 dark:bg-neutral-700 dark:text-neutral-300 border-0"
                          >
                            {{ tag }}
                          </Badge>
                        </template>
                        <span
                          v-if="getHiddenTagsCount(result.tags) > 0"
                          class="absolute right-0 inline-flex items-center justify-center text-xs px-1.5 py-0.5 rounded bg-gray-100 text-gray-700 dark:bg-neutral-700 dark:text-neutral-300 border-0"
                        >
                          +{{ getHiddenTagsCount(result.tags) }}
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <DialogFooter class="shrink-0 border-t pt-4 dark:border-neutral-700">
          <Button variant="outline" @click="smartSearchDialogOpen = false" class="dark:bg-neutral-800 dark:text-neutral-100 dark:hover:bg-neutral-700">
            Close
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>


    <!-- ========================================================================
          UPLOAD DIALOG 
        ======================================================================= -->
    <Dialog v-model:open="uploadDialogOpen">
      <DialogContent
        class="max-w-[95vw] sm:max-w-lg max-h-[90vh] flex flex-col min-h-0 dark:bg-neutral-900 dark:border-neutral-700">
        <DialogHeader class="shrink-0 border-b pb-3 sm:pb-4 text-left dark:border-neutral-700">
          <DialogTitle class="text-lg sm:text-lg text-left dark:text-neutral-100">Upload File</DialogTitle>
          <DialogDescription class="text-xs sm:text-sm text-left dark:text-neutral-400">
            Upload a new document to the repository. Maximum file size is 10MB.
          </DialogDescription>
        </DialogHeader>

        <div 
          class="flex-1 overflow-y-auto max-h-[calc(90vh-200px)] min-h-0 scrollbar-auto-hide scrollbar-hidden"
          @mouseenter="showScrollbar"
          @mouseleave="hideScrollbar"
          @scroll="onScroll"
        >
        <div class="space-y-4 sm:space-y-5 py-3 sm:py-4">
          <div>
            <Label for="upload-file" class="text-xs sm:text-sm font-medium block mb-1.5 sm:mb-2 dark:text-neutral-100">
              File <span class="text-red-500">*</span>
            </Label>
            <div
              class="w-full border-2 border-dashed rounded-lg bg-gray-50 dark:bg-neutral-800 dark:border-neutral-700 px-3 sm:px-4 py-4 sm:py-5 flex flex-col items-center justify-center cursor-pointer hover:border-blue-400 dark:hover:border-blue-600 hover:bg-gray-100 dark:hover:bg-neutral-700 transition-colors">
              <input id="upload-file" type="file"
                accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation"
                class="hidden"
                @change="onFileChange" />
              <label for="upload-file" class="cursor-pointer w-full text-center">
                <div class="flex flex-col items-center gap-2 sm:gap-3">
                  <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                    <FileText class="w-5 h-5 sm:w-6 sm:h-6 text-blue-600 dark:text-blue-400" />
                  </div>
                  <div class="text-center">
                    <p class="text-xs sm:text-sm font-semibold text-gray-700 dark:text-neutral-200 mb-1">
                      <span class="text-blue-600 dark:text-blue-400 hover:underline">Click to upload</span> or drag and drop
                    </p>
                    <p class="text-[10px] sm:text-xs text-gray-500 dark:text-neutral-400">
                      PDF, Word, Excel, or PowerPoint files only (Max 10MB)
                    </p>
                  </div>
                  <div v-if="uploadFile" class="mt-2 px-3 py-1.5 bg-blue-50 dark:bg-blue-900/30 rounded-md border border-blue-200 dark:border-blue-800">
                    <p class="text-xs sm:text-sm font-medium text-blue-700 dark:text-blue-300 truncate max-w-xs">
                      {{ uploadFile.name }}
                    </p>
                    <p class="text-[10px] sm:text-xs text-blue-600 dark:text-blue-400 mt-0.5">
                      {{ formatFileSize(uploadFile.size) }}
                    </p>
                  </div>
                </div>
              </label>
            </div>
            <div v-if="uploadFile && uploadFile.size > 10 * 1024 * 1024"
              class="text-[10px] sm:text-xs text-red-600 dark:text-red-400 mt-1.5 flex items-center gap-1">
              <XCircle class="w-3 h-3" />
              File exceeds 10MB limit. Please choose a smaller file.
            </div>
            <div v-if="uploadFile && uploadFile.size <= 10 * 1024 * 1024"
              class="text-[10px] sm:text-xs text-emerald-600 dark:text-emerald-400 mt-1.5 flex items-center gap-1">
              <CheckCircle class="w-3 h-3" />
              File is ready to upload
            </div>
          </div>

          <div
            v-if="requiresManualSummary"
            class="rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50/80 dark:bg-amber-900/10 p-4 space-y-4">
            <div class="flex items-start gap-3">
              <div
                class="w-10 h-10 rounded-full bg-amber-100 dark:bg-amber-800/40 flex items-center justify-center">
                <AlertCircle class="w-5 h-5 text-amber-600 dark:text-amber-300" />
              </div>
                <div class="flex-1">
                <p class="text-xs font-semibold text-amber-900 dark:text-amber-300 mb-1">
                  We can’t auto-extract Word, Excel, or PowerPoint files.
                  </p>
                <p class="text-[11px] text-amber-800 dark:text-amber-200">
                  Add a brief summary (1–2 sentences) and at least <span class="font-semibold">5
                  keywords</span> so this file becomes searchable. Mention the people involved,
                  departments, policy numbers, dates, or codes that matter.
                  </p>
                </div>
              </div>

            <div class="space-y-2">
              <Label for="upload-desc" class="text-xs sm:text-sm font-medium dark:text-neutral-100 flex items-center gap-2">
                Summary / Description
                <span class="text-red-500">*</span>
                <span class="text-[10px] text-amber-800 dark:text-amber-200">({{ summaryMinChars }}+ characters)</span>
              </Label>
              <Textarea
                id="upload-desc"
                v-model="uploadDescription"
                placeholder="Example: 'Maria Santos submitted the FY25 Marketing Plan for VP review due 11/30.'"
              :class="[
                'w-full text-xs sm:text-sm dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-100',
                  !summaryIsValid ? 'border-amber-400 dark:border-amber-600 focus:border-amber-500 dark:focus:border-amber-500' : ''
              ]"
                rows="3"
              />
              <p v-if="!summaryIsValid" class="text-[10px] text-amber-600 dark:text-amber-400 flex items-center gap-1">
              <AlertCircle class="w-3 h-3" />
                Please add at least {{ summaryMinChars }} characters.
              </p>
            </div>

            <div class="space-y-2">
              <div class="flex items-center justify-between">
                <Label class="text-xs sm:text-sm font-medium dark:text-neutral-100 flex items-center gap-2">
                  Keywords (min 5)
                  <span class="text-red-500">*</span>
                </Label>
                <p class="text-[10px] text-gray-500 dark:text-neutral-400">
                  e.g. "Maria Santos" "Finance Dept" "FY25 Plan" "Q4 Deadline"
                </p>
              </div>

              <div class="flex flex-wrap gap-2 mb-2">
                <Badge
                  v-for="keyword in uploadKeywords"
                  :key="keyword"
                  class="bg-blue-50 text-blue-700 dark:bg-blue-900/20 dark:text-blue-200 text-[10px] sm:text-xs flex items-center gap-1 px-2 py-0.5 rounded-full">
                  {{ keyword }}
                  <button @click="removeKeywordChip(keyword)" class="ml-1 hover:text-red-600">
                    <X class="w-2.5 h-2.5" />
                  </button>
                </Badge>
                <p v-if="uploadKeywords.length === 0" class="text-[10px] text-gray-500 dark:text-neutral-400">
                  No keywords added yet
                </p>
              </div>

              <div class="flex gap-2 flex-col sm:flex-row">
                <Input
                  v-model="newKeyword"
                  placeholder='"Type a name or phrase"'
                  class="flex-1 text-xs sm:text-sm dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-100"
                  @keyup.enter.prevent="addKeywordChip"
                />
                <Button type="button" size="sm" class="text-xs sm:text-sm shrink-0" @click="addKeywordChip">
                  Add Keyword
                </Button>
              </div>

              <p v-if="!keywordsAreValid" class="text-[10px] text-amber-600 dark:text-amber-400 flex items-center gap-1">
                <AlertCircle class="w-3 h-3" />
                Please add at least 5 keywords (names, departments, codes, etc.).
              </p>
            </div>
          </div>

          <div v-else>
            <Label for="upload-desc-simple" class="text-xs sm:text-sm font-medium block mb-1.5 sm:mb-2 dark:text-neutral-100">
              Description
            </Label>
            <Textarea
              id="upload-desc-simple"
              v-model="uploadDescription"
              placeholder="Enter a short description (optional)..."
              class="w-full text-xs sm:text-sm dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-100"
              rows="3"
            />
          </div>

          <div :class="['grid gap-3 sm:gap-4', isAdminUser ? 'grid-cols-1 sm:grid-cols-2' : 'grid-cols-1']">
            <div v-if="isAdminUser">
              <Label class="text-xs sm:text-sm font-medium block mb-1.5 sm:mb-2">
                Department <span class="text-red-500">*</span>
              </Label>
              <DropdownMenu>
                <DropdownMenuTrigger as-child>
                  <Button variant="outline"
                    class="w-full flex items-center justify-between gap-4 px-3 sm:px-8 py-2 sm:py-2.5 rounded-lg border bg-white text-xs sm:text-sm hover:border-blue-300">
                    <span class="truncate">{{ 
                      uploadDepartment 
                        ? (props.departments.find((d: any) => d.id === uploadDepartment)?.name || 'Select Department')
                        : 'Select Department'
                    }}</span>
                    <ChevronDown class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-gray-400 flex-shrink-0" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent class="w-full">
                  <DropdownMenuItem v-for="d in props.departments" :key="d.id"
                    @click="uploadDepartment = d.id">
                    {{ d.name }}
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </div>
            <div v-else>
              <Label class="text-xs sm:text-sm font-medium block mb-1.5 sm:mb-2">
                Department
              </Label>
              <div class="w-full px-3 sm:px-4 py-2 sm:py-2.5 rounded-lg border bg-gray-50 dark:bg-neutral-800 text-xs sm:text-sm text-gray-600 dark:text-neutral-400">
                {{ currentUser?.department || '—' }}
              </div>
            </div>

            <div>
              <Label class="text-xs sm:text-sm font-medium block mb-1.5 sm:mb-2">
                Access Level <span class="text-red-500">*</span>
              </Label>
              <DropdownMenu>
                <DropdownMenuTrigger as-child>
                  <Button variant="outline"
                    class="w-full flex items-center justify-between gap-4 px-3 sm:px-8 py-2 sm:py-2.5 rounded-lg border bg-white text-xs sm:text-sm hover:border-blue-300">
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
              <Badge v-for="tagId in uploadTags" :key="tagId"
                class="bg-gray-100 text-gray-700 text-[10px] sm:text-xs flex items-center gap-1 px-1.5 sm:px-2 py-0.5">
                {{ props.tags.find((t: any) => t.id === tagId)?.name || 'Unknown' }}
                <button @click="removeUploadTag(tagId)" class="ml-1 hover:text-red-600">
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
                  <DropdownMenuItem 
                    v-for="tag in props.tags.filter((t: any) => !uploadTags.includes(t.id))" 
                    :key="tag.id" 
                    @click="uploadTags = [...uploadTags, tag.id]"
                    class="text-xs sm:text-sm">
                    <div class="inline-flex items-center gap-1.5 sm:gap-2">
                      <span class="w-2 h-2 sm:w-2.5 sm:h-2.5 rounded-full bg-gray-300"></span>
                      <span>{{ tag.name }}</span>
                    </div>
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
              <Button @click="addUploadTag" size="sm" type="button" class="text-xs sm:text-sm">Add</Button>
            </div>
          </div>
        </div>
        </div>

        <DialogFooter class="shrink-0 border-t pt-3 sm:pt-4 mt-3 sm:mt-4 gap-2 flex-col sm:flex-row">
          <div class="flex gap-2 w-full sm:w-auto">
            <Button variant="secondary" size="sm" class="flex-1 sm:flex-none" 
              @click="uploadDialogOpen = false"
              :disabled="isUploading">
              Cancel
            </Button>
            <Button size="sm" class="flex-1 sm:flex-none bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white" 
              @click="handleUploadSubmit" 
              :disabled="isUploading || !uploadFile || !canSubmitUpload">
              <Loader2 v-if="isUploading" class="w-4 h-4 mr-2 animate-spin" />
              {{ isUploading ? 'Uploading...' : 'Upload' }}
            </Button>
          </div>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- ========================================================================
REQUEST DETAILS & ACTIVITY MODAL 
        ======================================================================= -->
    <Dialog v-model:open="requestDetailModalOpen">
      <DialogContent class="max-w-2xl max-h-[90vh] flex flex-col dark:bg-neutral-900 dark:border-neutral-700 mx-4 sm:mx-auto">
        <DialogHeader class="shrink-0">
          <DialogTitle class="dark:text-neutral-100">Request Details</DialogTitle>
          <DialogDescription class="dark:text-neutral-400">
            View and manage request information
          </DialogDescription>
        </DialogHeader>

        <div 
          class="flex-1 overflow-y-auto max-h-[calc(90vh-200px)] min-h-0 scrollbar-auto-hide scrollbar-hidden"
          @mouseenter="showScrollbar"
          @mouseleave="hideScrollbar"
          @scroll="onScroll"
        >
          <div v-if="selectedRequest" class="space-y-6 py-4">
            <!-- File Header -->
            <div class="flex items-start gap-4 pb-4 border-b dark:border-neutral-700">
              <div
                :class="['w-20 h-20 flex items-center justify-center rounded-lg text-white font-bold text-lg shadow-sm', typeColor(selectedRequest.type || inferTypeFromName(selectedRequest.name))]">
                {{ (selectedRequest.type || inferTypeFromName(selectedRequest.name)) === 'Word' ? 'WORD' : (selectedRequest.type || inferTypeFromName(selectedRequest.name)) }}
            </div>
              <div class="flex-1 min-w-0">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-neutral-100 mb-1 break-words">
                  {{ selectedRequest.fullName || selectedRequest.name }}
                </h3>
                <div class="flex items-center gap-3 text-sm text-gray-500 dark:text-neutral-400 mb-1">
                  <span>{{ selectedRequest.size || '—' }}</span>
                  <span>•</span>
                  <span>{{ selectedRequest.departmentName || selectedRequest.department || '—' }}</span>
          </div>
                <div class="text-sm" :class="getAccessTextColor(selectedRequest.access)">
                  {{ selectedRequest.access }}
            </div>
              </div>
            </div>

            <!-- Document Information -->
            <div class="space-y-4">
              <!-- Requester Information -->
              <div class="grid grid-cols-2 gap-4">
            <div>
                  <Label class="text-xs font-medium text-gray-500 dark:text-neutral-400 mb-1 block">Requester</Label>
                  <p class="text-sm text-gray-900 dark:text-neutral-100">{{ selectedRequest.requester || '—' }}</p>
            </div>
            <div>
                  <Label class="text-xs font-medium text-gray-500 dark:text-neutral-400 mb-1 block">Requester Department</Label>
                  <p class="text-sm text-gray-900 dark:text-neutral-100">
                    {{ getUserMeta(selectedRequest.requester, selectedRequest.department).department }}
                  </p>
              </div>
            </div>

              <!-- Uploaded By and Requested At -->
              <div class="grid grid-cols-2 gap-4">
            <div>
                  <Label class="text-xs font-medium text-gray-500 dark:text-neutral-400 mb-1 block">Uploaded By</Label>
                  <p class="text-sm text-gray-900 dark:text-neutral-100">{{ selectedRequest.uploader || '—' }}</p>
            </div>
            <div>
                  <Label class="text-xs font-medium text-gray-500 dark:text-neutral-400 mb-1 block">Requested At</Label>
                  <p class="text-sm text-gray-900 dark:text-neutral-100">
                    {{ selectedRequest.requestedAt ? new Date(selectedRequest.requestedAt).toLocaleDateString() : 'N/A' }}
                  </p>
            </div>
            </div>

              <!-- Request Message (Highlighted) -->
              <div class="space-y-2 mt-2">
                <Label class="text-xs font-medium text-gray-500 dark:text-neutral-400 mb-2 block">Request Message</Label>
                <div class="bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800 rounded-lg p-4">
                  <p class="text-sm text-indigo-900 dark:text-indigo-100 whitespace-pre-wrap">
                    {{ selectedRequest.requestMessage || '—' }}
                  </p>
            </div>
          </div>

              <!-- Reviewed By and Reviewed At (if reviewed) -->
              <div v-if="selectedRequest.approvedBy && (selectedRequest.status === 'Approved' || selectedRequest.status === 'Rejected')" class="grid grid-cols-2 gap-4">
          <div>
                  <Label class="text-xs font-medium text-gray-500 dark:text-neutral-400 mb-1 block">Reviewed By</Label>
                  <p class="text-sm text-gray-900 dark:text-neutral-100">
                    {{ selectedRequest.approvedBy || '—' }}
            </p>
          </div>
            <div>
                  <Label class="text-xs font-medium text-gray-500 dark:text-neutral-400 mb-1 block">Reviewed At</Label>
                  <p class="text-sm text-gray-900 dark:text-neutral-100">
                    {{ selectedRequest.decisionAt ? new Date(selectedRequest.decisionAt).toLocaleDateString() : '—' }}
                  </p>
            </div>
          </div>

              <!-- Review Message (if reviewed) -->
              <div v-if="selectedRequest.reviewMessage" class="space-y-2 mt-2">
                <Label class="text-xs font-medium text-gray-500 dark:text-neutral-400 mb-2 block">Review Message</Label>
                <div 
                  :class="[
                    'border rounded-lg p-4',
                    selectedRequest.status === 'Approved' 
                      ? 'bg-emerald-50 dark:bg-emerald-900/20 border-emerald-200 dark:border-emerald-800'
                      : selectedRequest.status === 'Rejected'
                      ? 'bg-rose-50 dark:bg-rose-900/20 border-rose-200 dark:border-rose-800'
                      : 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800'
                  ]"
                >
                  <p 
                    :class="[
                      'text-sm whitespace-pre-wrap',
                      selectedRequest.status === 'Approved'
                        ? 'text-emerald-900 dark:text-emerald-100'
                        : selectedRequest.status === 'Rejected'
                        ? 'text-rose-900 dark:text-rose-100'
                        : 'text-gray-800 dark:text-neutral-200'
                    ]"
                  >
                    {{ selectedRequest.reviewMessage }}
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Action buttons (matching All Files > Pending style) -->
        <template v-if="selectedRequest && canReviewRequest(selectedRequest)">
          <DialogFooter class="gap-2">
            <Button
              v-if="selectedRequest && (selectedRequest.type === 'PDF' || inferTypeFromName(selectedRequest.name) === 'PDF')"
              @click.stop="openRequestPreviewConfirm(selectedRequest)"
              variant="outline"
              size="icon"
              title="Preview"
            >
              <Eye class="w-4 h-4" />
          </Button>
            <Button
              v-if="selectedRequest"
              @click.stop="openRequestDownloadConfirm(selectedRequest)"
              variant="outline"
              size="icon"
              title="Download"
            >
              <Download class="w-4 h-4" />
            </Button>
            <Button
              v-if="selectedRequest"
              @click="startRequestDecision(determineRequestKind(selectedRequest), selectedRequest, 'reject')"
              variant="destructive"
              class="text-xs sm:text-sm w-[120px]"
            >
              <XCircle class="w-4 h-4 mr-2" />
            Reject
          </Button>
            <Button
              v-if="selectedRequest"
              @click="startRequestDecision(determineRequestKind(selectedRequest), selectedRequest, 'approve')"
              class="text-xs sm:text-sm bg-emerald-600 hover:bg-emerald-700 text-white w-[120px]"
            >
              <CheckCircle class="w-4 h-4 mr-2" />
              Approve
            </Button>
          </DialogFooter>
        </template>
        <template v-else-if="selectedRequest">
          <DialogFooter class="gap-2">
            <Button
              v-if="selectedRequest && (selectedRequest.type === 'PDF' || inferTypeFromName(selectedRequest.name) === 'PDF')"
              @click.stop="openRequestPreviewConfirm(selectedRequest)"
              variant="outline"
              size="icon"
              title="Preview"
            >
              <Eye class="w-4 h-4" />
            </Button>
            <Button
              v-if="selectedRequest"
              @click.stop="openRequestDownloadConfirm(selectedRequest)"
              variant="outline"
              size="icon"
              title="Download"
            >
              <Download class="w-4 h-4" />
            </Button>
            <Button
              v-if="selectedRequest && (selectedRequest.status === 'Approved' || selectedRequest.status === 'Rejected') && (isAdminUser || isDepartmentManager)"
              @click="openEditAccess(selectedRequest)"
              variant="outline"
              class="text-xs sm:text-sm w-auto"
            >
              <Edit3 class="w-4 h-4 mr-2" />
              Edit Access
            </Button>
            <Button variant="outline" @click="requestDetailModalOpen = false">
              Close
            </Button>
          </DialogFooter>
        </template>
      </DialogContent>
    </Dialog>

    <!-- ========================================================================
UPLOAD DETAILS & ACTIVITY MODAL 
======================================================================= -->
    <Dialog v-model:open="uploadDetailModalOpen">
      <DialogContent class="max-w-2xl max-h-[90vh] flex flex-col dark:bg-neutral-900 dark:border-neutral-700 mx-4 sm:mx-auto">
        <DialogHeader class="shrink-0">
          <DialogTitle class="dark:text-neutral-100">Request Details</DialogTitle>
          <DialogDescription class="dark:text-neutral-400">
            View and manage request information
          </DialogDescription>
        </DialogHeader>

        <div 
          class="flex-1 overflow-y-auto max-h-[calc(90vh-200px)] min-h-0 scrollbar-auto-hide scrollbar-hidden"
          @mouseenter="showScrollbar"
          @mouseleave="hideScrollbar"
          @scroll="onScroll"
          ref="uploadDetailScrollRef"
        >
          <div v-if="selectedUpload" class="space-y-6 py-4">
            <!-- File Header -->
            <div class="flex items-start gap-4 pb-4 border-b dark:border-neutral-700">
              <div
                :class="['w-20 h-20 flex items-center justify-center rounded-lg text-white font-bold text-lg shadow-sm', typeColor(selectedUpload.type || inferTypeFromName(selectedUpload.name))]">
                {{ (selectedUpload.type || inferTypeFromName(selectedUpload.name)) === 'Word' ? 'WORD' : (selectedUpload.type || inferTypeFromName(selectedUpload.name)) }}
            </div>
              <div class="flex-1 min-w-0">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-neutral-100 mb-1 break-words">
                  {{ selectedUpload.fullName || selectedUpload.name }}
                </h3>
                <div class="flex items-center gap-3 text-sm text-gray-500 dark:text-neutral-400 mb-1">
                  <span>{{ selectedUpload.size || '—' }}</span>
                  <span>•</span>
                  <span>{{ selectedUpload.departmentName || selectedUpload.department || '—' }}</span>
          </div>
                <div class="text-sm" :class="getAccessTextColor(selectedUpload.access)">
                  {{ selectedUpload.access }}
                </div>
              </div>
            </div>

            <!-- Document Information -->
            <div class="space-y-4">
              <!-- Requester Information -->
              <div class="grid grid-cols-2 gap-4">
            <div>
                  <Label class="text-xs font-medium text-gray-500 dark:text-neutral-400 mb-1 block">Requester</Label>
                  <p class="text-sm text-gray-900 dark:text-neutral-100">{{ selectedUpload.requester || '—' }}</p>
            </div>
            <div>
                  <Label class="text-xs font-medium text-gray-500 dark:text-neutral-400 mb-1 block">Requester Department</Label>
                  <p class="text-sm text-gray-900 dark:text-neutral-100">
                    {{ getUserMeta(selectedUpload.requester, selectedUpload.department).department }}
                  </p>
            </div>
              </div>

              <!-- Uploaded By and Requested At -->
              <div class="grid grid-cols-2 gap-4">
            <div>
                  <Label class="text-xs font-medium text-gray-500 dark:text-neutral-400 mb-1 block">Uploaded By</Label>
                  <p class="text-sm text-gray-900 dark:text-neutral-100">{{ selectedUpload.uploader || '—' }}</p>
            </div>
            <div>
                  <Label class="text-xs font-medium text-gray-500 dark:text-neutral-400 mb-1 block">Requested At</Label>
                  <p class="text-sm text-gray-900 dark:text-neutral-100">
                    {{ selectedUpload.requestedAt ? new Date(selectedUpload.requestedAt).toLocaleDateString() : (selectedUpload.uploadedAt ? new Date(selectedUpload.uploadedAt).toLocaleDateString() : 'N/A') }}
                  </p>
              </div>
            </div>

              <!-- Request Message (Highlighted) -->
              <div class="space-y-2 mt-2">
                <Label class="text-xs font-medium text-gray-500 dark:text-neutral-400 mb-2 block">Request Message</Label>
                <div class="bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800 rounded-lg p-4">
                  <p class="text-sm text-indigo-900 dark:text-indigo-100 whitespace-pre-wrap">
                    {{ selectedUpload.requestMessage || '—' }}
                  </p>
                </div>
              </div>

              <!-- Reviewed By and Reviewed At (if reviewed) -->
              <div v-if="selectedUpload.approvedBy && (selectedUpload.status === 'Approved' || selectedUpload.status === 'Rejected')" class="grid grid-cols-2 gap-4">
            <div>
                  <Label class="text-xs font-medium text-gray-500 dark:text-neutral-400 mb-1 block">Reviewed By</Label>
                  <p class="text-sm text-gray-900 dark:text-neutral-100">
                    {{ selectedUpload.approvedBy || '—' }}
                  </p>
            </div>
                <div>
                  <Label class="text-xs font-medium text-gray-500 dark:text-neutral-400 mb-1 block">Reviewed At</Label>
                  <p class="text-sm text-gray-900 dark:text-neutral-100">
                    {{ selectedUpload.decisionAt ? new Date(selectedUpload.decisionAt).toLocaleDateString() : '—' }}
                  </p>
          </div>
              </div>

              <!-- Review Message (if reviewed) -->
              <div v-if="selectedUpload.reviewMessage" class="space-y-2 mt-2">
                <Label class="text-xs font-medium text-gray-500 dark:text-neutral-400 mb-2 block">Review Message</Label>
                <div 
                  :class="[
                    'border rounded-lg p-4',
                    selectedUpload.status === 'Approved' 
                      ? 'bg-emerald-50 dark:bg-emerald-900/20 border-emerald-200 dark:border-emerald-800'
                      : selectedUpload.status === 'Rejected'
                      ? 'bg-rose-50 dark:bg-rose-900/20 border-rose-200 dark:border-rose-800'
                      : 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800'
                  ]"
                >
                  <p 
                    :class="[
                      'text-sm whitespace-pre-wrap',
                      selectedUpload.status === 'Approved'
                        ? 'text-emerald-900 dark:text-emerald-100'
                        : selectedUpload.status === 'Rejected'
                        ? 'text-rose-900 dark:text-rose-100'
                        : 'text-gray-800 dark:text-neutral-200'
                    ]"
                  >
                    {{ selectedUpload.reviewMessage }}
                  </p>
                </div>
                </div>
              </div>
            </div>
          </div>

        <!-- Action buttons (matching All Files > Pending style) -->
        <template v-if="selectedUpload && canReviewRequest(selectedUpload)">
          <DialogFooter class="gap-2">
            <Button
              v-if="selectedUpload && (selectedUpload.type === 'PDF' || inferTypeFromName(selectedUpload.name) === 'PDF')"
              @click.stop="openRequestPreviewConfirm(selectedUpload)"
              variant="outline"
              size="icon"
              title="Preview"
            >
              <Eye class="w-4 h-4" />
            </Button>
            <Button
              v-if="selectedUpload"
              @click.stop="openRequestDownloadConfirm(selectedUpload)"
              variant="outline"
              size="icon"
              title="Download"
            >
              <Download class="w-4 h-4" />
            </Button>
            <Button
              v-if="selectedUpload"
              @click="startRequestDecision('upload', selectedUpload, 'reject')"
              variant="destructive"
              class="text-xs sm:text-sm w-[120px]"
            >
              <XCircle class="w-4 h-4 mr-2" />
              Reject
            </Button>
            <Button
              v-if="selectedUpload"
              @click="startRequestDecision('upload', selectedUpload, 'approve')"
              class="text-xs sm:text-sm bg-emerald-600 hover:bg-emerald-700 text-white w-[120px]"
            >
              <CheckCircle class="w-4 h-4 mr-2" />
              Approve
            </Button>
          </DialogFooter>
        </template>
        <template v-else-if="selectedUpload">
          <DialogFooter class="gap-2">
            <Button
              v-if="selectedUpload && (selectedUpload.type === 'PDF' || inferTypeFromName(selectedUpload.name) === 'PDF')"
              @click.stop="openRequestPreviewConfirm(selectedUpload)"
              variant="outline"
              size="icon"
              title="Preview"
            >
              <Eye class="w-4 h-4" />
            </Button>
            <Button
              v-if="selectedUpload"
              @click.stop="openRequestDownloadConfirm(selectedUpload)"
              variant="outline"
              size="icon"
              title="Download"
            >
              <Download class="w-4 h-4" />
            </Button>
            <Button
              v-if="selectedUpload && (selectedUpload.status === 'Approved' || selectedUpload.status === 'Rejected') && (isAdminUser || isDepartmentManager)"
              @click="openEditAccess(selectedUpload)"
              variant="outline"
              class="text-xs sm:text-sm w-auto"
            >
              <Edit3 class="w-4 h-4 mr-2" />
              Edit Access
            </Button>
            <Button variant="outline" @click="uploadDetailModalOpen = false">
              Close
            </Button>
          </DialogFooter>
        </template>
      </DialogContent>
    </Dialog>


    <!-- ========================================================================
PERMISSION DETAILS & ACTIVITY MODAL 
======================================================================= -->
    <Dialog v-model:open="permissionDetailModalOpen">
      <DialogContent class="max-w-2xl max-h-[90vh] flex flex-col dark:bg-neutral-900 dark:border-neutral-700 mx-4 sm:mx-auto">
        <DialogHeader class="shrink-0">
          <DialogTitle class="dark:text-neutral-100">Request Details</DialogTitle>
          <DialogDescription class="dark:text-neutral-400">
            View and manage request information
          </DialogDescription>
        </DialogHeader>

        <div 
          class="flex-1 overflow-y-auto max-h-[calc(90vh-200px)] min-h-0 scrollbar-auto-hide scrollbar-hidden"
          @mouseenter="showScrollbar"
          @mouseleave="hideScrollbar"
          @scroll="onScroll"
          ref="permissionDetailScrollRef"
        >
          <div v-if="selectedPermission" class="space-y-6 py-4">
            <!-- File Header -->
            <div class="flex items-start gap-4 pb-4 border-b dark:border-neutral-700">
              <div
                :class="['w-20 h-20 flex items-center justify-center rounded-lg text-white font-bold text-lg shadow-sm', typeColor(selectedPermission.type || inferTypeFromName(selectedPermission.name))]">
                {{ (selectedPermission.type || inferTypeFromName(selectedPermission.name)) === 'Word' ? 'WORD' : (selectedPermission.type || inferTypeFromName(selectedPermission.name)) }}
            </div>
              <div class="flex-1 min-w-0">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-neutral-100 mb-1 break-words">
                  {{ selectedPermission.fullName || selectedPermission.name }}
                </h3>
                <div class="flex items-center gap-3 text-sm text-gray-500 dark:text-neutral-400 mb-1">
                  <span>{{ selectedPermission.size || '—' }}</span>
                  <span>•</span>
                  <span>{{ selectedPermission.departmentName || selectedPermission.department || '—' }}</span>
          </div>
                <div class="text-sm" :class="getAccessTextColor(selectedPermission.access)">
                  {{ selectedPermission.access }}
              </div>
            </div>
            </div>

            <!-- Document Information -->
            <div class="space-y-4">
              <!-- Requester Information -->
              <div class="grid grid-cols-2 gap-4">
            <div>
                  <Label class="text-xs font-medium text-gray-500 dark:text-neutral-400 mb-1 block">Requester</Label>
                  <p class="text-sm text-gray-900 dark:text-neutral-100">{{ selectedPermission.requester || '—' }}</p>
            </div>
            <div>
                  <Label class="text-xs font-medium text-gray-500 dark:text-neutral-400 mb-1 block">Requester Department</Label>
                  <p class="text-sm text-gray-900 dark:text-neutral-100">
                    {{ getUserMeta(selectedPermission.requester, selectedPermission.department).department }}
                  </p>
              </div>
            </div>

              <!-- Uploaded By and Requested At -->
              <div class="grid grid-cols-2 gap-4">
            <div>
                  <Label class="text-xs font-medium text-gray-500 dark:text-neutral-400 mb-1 block">Uploaded By</Label>
                  <p class="text-sm text-gray-900 dark:text-neutral-100">{{ selectedPermission.uploader || '—' }}</p>
            </div>
            <div>
                  <Label class="text-xs font-medium text-gray-500 dark:text-neutral-400 mb-1 block">Requested At</Label>
                  <p class="text-sm text-gray-900 dark:text-neutral-100">
                    {{ selectedPermission.requestedAt ? new Date(selectedPermission.requestedAt).toLocaleDateString() : 'N/A' }}
                  </p>
            </div>
          </div>

              <!-- Request Message (Highlighted) -->
              <div class="space-y-2 mt-2">
                <Label class="text-xs font-medium text-gray-500 dark:text-neutral-400 mb-2 block">Request Message</Label>
                <div class="bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800 rounded-lg p-4">
                  <p class="text-sm text-indigo-900 dark:text-indigo-100 whitespace-pre-wrap">
                    {{ selectedPermission.requestMessage || '—' }}
                  </p>
              </div>
                </div>

              <!-- Reviewed By and Reviewed At (if reviewed) -->
              <div v-if="selectedPermission.approvedBy && (selectedPermission.status === 'Approved' || selectedPermission.status === 'Rejected')" class="grid grid-cols-2 gap-4">
                <div>
                  <Label class="text-xs font-medium text-gray-500 dark:text-neutral-400 mb-1 block">Reviewed By</Label>
                  <p class="text-sm text-gray-900 dark:text-neutral-100">
                    {{ selectedPermission.approvedBy || '—' }}
                  </p>
                </div>
                <div>
                  <Label class="text-xs font-medium text-gray-500 dark:text-neutral-400 mb-1 block">Reviewed At</Label>
                  <p class="text-sm text-gray-900 dark:text-neutral-100">
                    {{ selectedPermission.decisionAt ? new Date(selectedPermission.decisionAt).toLocaleDateString() : '—' }}
                  </p>
              </div>
            </div>

              <!-- Review Message (if reviewed) -->
              <div v-if="selectedPermission.reviewMessage" class="space-y-2 mt-2">
                <Label class="text-xs font-medium text-gray-500 dark:text-neutral-400 mb-2 block">Review Message</Label>
                <div 
                  :class="[
                    'border rounded-lg p-4',
                    selectedPermission.status === 'Approved' 
                      ? 'bg-emerald-50 dark:bg-emerald-900/20 border-emerald-200 dark:border-emerald-800'
                      : selectedPermission.status === 'Rejected'
                      ? 'bg-rose-50 dark:bg-rose-900/20 border-rose-200 dark:border-rose-800'
                      : 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800'
                  ]"
                >
                  <p 
                    :class="[
                      'text-sm whitespace-pre-wrap',
                      selectedPermission.status === 'Approved'
                        ? 'text-emerald-900 dark:text-emerald-100'
                        : selectedPermission.status === 'Rejected'
                        ? 'text-rose-900 dark:text-rose-100'
                        : 'text-gray-800 dark:text-neutral-200'
                    ]"
                  >
                    {{ selectedPermission.reviewMessage }}
                  </p>
              </div>
            </div>
          </div>
        </div>
        </div>

        <!-- Action buttons for outgoing requests (no Approve/Reject, no Edit Access) -->
        <template v-if="selectedPermission">
          <DialogFooter class="gap-2">
            <!-- Preview and Download only available if request is Approved -->
            <Button
              v-if="selectedPermission && selectedPermission.status === 'Approved' && (selectedPermission.type === 'PDF' || inferTypeFromName(selectedPermission.name) === 'PDF')"
              @click.stop="openRequestPreviewConfirm(selectedPermission)"
              variant="outline"
              size="icon"
              title="Preview"
            >
              <Eye class="w-4 h-4" />
            </Button>
            <Button
              v-if="selectedPermission && selectedPermission.status === 'Approved'"
              @click.stop="openRequestDownloadConfirm(selectedPermission)"
              variant="outline"
              size="icon"
              title="Download"
            >
              <Download class="w-4 h-4" />
            </Button>
            <!-- Cancel button for outgoing requests (only if pending) -->
            <Button
              v-if="selectedPermission && selectedPermission.status === 'Pending'"
              @click="handleCancelOutgoingRequest"
              variant="destructive"
              class="text-xs sm:text-sm w-[120px]"
            >
              <XCircle class="w-4 h-4 mr-2" />
              Cancel
            </Button>
            <Button variant="outline" @click="permissionDetailModalOpen = false">
              Close
            </Button>
          </DialogFooter>
        </template>
      </DialogContent>
    </Dialog>


    <!-- ========================================================================
          PERMISSION HISTORY MODAL 
        ======================================================================= -->
    <Dialog v-model:open="permissionHistoryModalOpen">
      <DialogContent
        class="max-w-[95vw] sm:max-w-2xl max-h-[90vh] flex flex-col min-h-0 dark:bg-neutral-900 dark:border-neutral-700">
        <DialogHeader class="border-b pb-3 sm:pb-4 text-left dark:border-neutral-700">
          <DialogTitle class="text-lg sm:text-lg text-left dark:text-neutral-100">Permission History</DialogTitle>
          <DialogDescription class="text-xs sm:text-sm text-left dark:text-neutral-400">
            View all permission request history.
          </DialogDescription>
        </DialogHeader>
        <ScrollArea class="flex-1 min-h-0">
        <div class="py-4 space-y-2">
          <div v-for="permission in permissions" :key="permission.id"
            class="p-3 border rounded-lg dark:bg-neutral-800 dark:border-neutral-700">
            <div class="flex items-center justify-between">
              <div>
                <div class="font-medium text-sm dark:text-neutral-100">{{ permission.name }}</div>
                <div class="text-xs text-gray-500 dark:text-neutral-400">Requested by {{ permission.requester }} on {{
                  new
                    Date(permission.requestedAt).toLocaleDateString() }}</div>
              </div>
              <Badge :class="`${statusColor(permission.status)} text-xs`">{{ permission.status }}</Badge>
            </div>
          </div>
        </div>
        </ScrollArea>
      </DialogContent>
    </Dialog>
    <!-- ========================================================================
          EDIT DETAILS MODAL 
        ======================================================================= -->
    <Dialog v-model:open="editDialogOpen">
      <DialogContent
        class="edit-dialog-no-animation max-w-[95vw] sm:max-w-lg max-h-[90vh] flex flex-col min-h-0 dark:bg-neutral-900 dark:border-neutral-700 [&>button]:hidden">
        <DialogHeader class="shrink-0 border-b pb-4 text-left dark:border-neutral-700">
          <DialogTitle class="text-lg font-semibold text-left dark:text-neutral-100">Edit Document</DialogTitle>
          <DialogDescription class="text-sm text-left dark:text-neutral-400 mt-1">
            Update document information including description, department, access level, and tags.
          </DialogDescription>
        </DialogHeader>

        <div 
          class="flex-1 overflow-y-auto max-h-[calc(90vh-220px)] min-h-0 scrollbar-auto-hide scrollbar-hidden"
          @mouseenter="showScrollbar"
          @mouseleave="hideScrollbar"
          @scroll="onScroll"
        >
          <div class="space-y-5 py-4" v-if="dialogFile">
            <!-- Document Info Header -->
            <div class="bg-gray-50 dark:bg-neutral-800/50 rounded-lg p-4 border border-gray-200 dark:border-neutral-700">
              <div class="flex items-start gap-3">
                <div class="flex-1 min-w-0">
                  <h3 class="text-sm font-semibold text-gray-900 dark:text-neutral-100 mb-1 break-words">
                    {{ dialogFile.name || dialogFile.fullName }}
                  </h3>
                  <div class="flex items-center gap-2 text-xs text-gray-600 dark:text-neutral-400">
                    <span>{{ dialogFile.department || '—' }}</span>
                    <span>•</span>
                    <span>{{ dialogFile.access || '—' }}</span>
                  </div>
                </div>
            </div>
          </div>

            <!-- Description -->
          <div>
              <Label for="edit-description" class="text-sm font-medium block mb-2 dark:text-neutral-100">
                Description
              </Label>
              <Textarea 
                id="edit-description"
                v-model="editDescription" 
                rows="4" 
                placeholder="Enter a description for this document..."
                class="w-full text-sm dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-100 resize-none" 
              />
          </div>

            <!-- Department and Access Level -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <Label class="text-sm font-medium block mb-2 dark:text-neutral-100">
                  Department <span class="text-red-500">*</span>
                </Label>
              <!-- If editing from My Department tab, show read-only department -->
              <div v-if="isEditingFromMyDepartment" 
                class="w-full px-3 py-2.5 rounded-lg border bg-gray-50 dark:bg-neutral-800 text-sm text-gray-600 dark:text-neutral-400">
                {{ 
                  editDepartmentId 
                    ? (props.departments.find((d: any) => d.id === editDepartmentId)?.name || currentUser?.department || '—')
                    : currentUser?.department || '—'
                }}
              </div>
              <!-- If editing from All Files tab (admin), show dropdown -->
              <DropdownMenu v-else>
                <DropdownMenuTrigger as-child>
                  <Button variant="outline"
                      class="w-full flex items-center justify-between gap-2 px-3 py-2.5 text-sm hover:border-blue-300 dark:hover:border-blue-600">
                      <span class="truncate">
                        {{ 
                          editDepartmentId 
                            ? (props.departments.find((d: any) => d.id === editDepartmentId)?.name || 'Select Department')
                            : 'Select Department'
                        }}
                      </span>
                      <ChevronDown class="w-4 h-4 text-gray-400 dark:text-neutral-400 flex-shrink-0" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent class="w-full">
                    <DropdownMenuItem 
                      v-for="d in props.departments" 
                      :key="d.id"
                      @click="editDepartmentId = d.id" 
                      class="text-sm">
                      {{ d.name }}
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </div>

            <div>
                <Label class="text-sm font-medium block mb-2 dark:text-neutral-100">
                  Access Level <span class="text-red-500">*</span>
                </Label>
              <DropdownMenu>
                <DropdownMenuTrigger as-child>
                  <Button variant="outline"
                      class="w-full flex items-center justify-between gap-2 px-3 py-2.5 text-sm hover:border-blue-300 dark:hover:border-blue-600">
                      <span class="truncate">{{ editAccess }}</span>
                      <ChevronDown class="w-4 h-4 text-gray-400 dark:text-neutral-400 flex-shrink-0" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent class="w-full">
                    <DropdownMenuItem @click="editAccess = 'Public'" class="text-sm">
                    Public
                  </DropdownMenuItem>
                    <DropdownMenuItem @click="editAccess = 'Private'" class="text-sm">
                    Private
                  </DropdownMenuItem>
                    <DropdownMenuItem @click="editAccess = 'Department'" class="text-sm">
                    Department
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </div>
          </div>

          <!-- Tags -->
          <div>
              <Label class="text-sm font-medium block mb-2 dark:text-neutral-100">Tags</Label>
              <div class="flex flex-wrap gap-2 mb-3 min-h-[2.5rem] items-start">
                <Badge 
                  v-for="tagId in editTags" 
                  :key="tagId"
                  class="bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300 text-xs flex items-center gap-1.5 px-2.5 py-1">
                  {{ props.tags.find((t: any) => t.id === tagId)?.name || 'Unknown' }}
                  <button 
                    @click="removeEditTag(tagId)" 
                    class="ml-1 hover:text-red-600 dark:hover:text-red-400 transition-colors"
                    aria-label="Remove tag">
                    <X class="w-3 h-3" />
                </button>
              </Badge>
                <p v-if="editTags.length === 0" class="text-xs text-gray-500 dark:text-neutral-400 py-1">
                  No tags added
                </p>
            </div>
              <div class="flex gap-2 items-center">
                <Input 
                  v-model="editNewTag" 
                  @keyup.enter="addEditTag" 
                  placeholder="Type tag name and press Enter..."
                  class="flex-1 text-sm dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-100" 
                />
              <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <Button 
                      variant="outline" 
                      size="sm"
                      class="px-3 py-2 text-sm hover:border-blue-300 dark:hover:border-blue-600 inline-flex items-center gap-2">
                      <Tag class="w-4 h-4" /> 
                      Pick
                  </Button>
                </DropdownMenuTrigger>
                  <DropdownMenuContent class="max-h-64 overflow-auto w-48">
                    <DropdownMenuLabel class="text-sm">Select Tag</DropdownMenuLabel>
                  <DropdownMenuSeparator />
                    <DropdownMenuItem 
                      v-for="tag in props.tags.filter((t: any) => !editTags.includes(t.id))" 
                      :key="tag.id" 
                      @click="editTags = [...editTags, tag.id]"
                      class="text-sm">
                      <div class="inline-flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-gray-300 dark:bg-neutral-600"></span>
                        <span>{{ tag.name }}</span>
                    </div>
                  </DropdownMenuItem>
                    <div v-if="props.tags.filter((t: any) => !editTags.includes(t.id)).length === 0" 
                      class="px-2 py-1.5 text-xs text-gray-500 dark:text-neutral-400">
                      No more tags available
                    </div>
                </DropdownMenuContent>
              </DropdownMenu>
                <Button 
                  @click="addEditTag" 
                  size="sm" 
                  type="button" 
                  class="px-3 py-2 text-sm"
                  :disabled="!editNewTag.trim()">
                  Add
                </Button>
              </div>
            </div>
                  </div>
          </div>

        <DialogFooter class="shrink-0 border-t pt-4 mt-4 gap-3 flex-row justify-end">
          <Button 
            variant="secondary" 
            size="sm" 
            class="px-4"
            @click="editDialogOpen = false; dialogFile = null; editDepartmentId = null; editAccess = 'Department'; editDescription = ''; editTags = []; editNewTag = ''; isEditingFromMyDepartment = false">
              Cancel
            </Button>
          <Button 
            size="sm" 
            class="px-4 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white" 
            @click="saveEditDetails"
            :disabled="!editDepartmentId">
            Save Changes
            </Button>
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
          MANAGE TAGS DIALOG 
        ======================================================================= -->
    <Dialog v-model:open="manageTagsDialogOpen">
      <DialogContent class="max-w-[95vw] sm:max-w-md max-h-[90vh] flex flex-col min-h-0">
        <DialogHeader class="border-b pb-3 sm:pb-4">
          <DialogTitle class="text-lg sm:text-lg">Manage Tags</DialogTitle>
          <DialogDescription class="text-xs sm:text-sm">
            Add or remove tags.
          </DialogDescription>
        </DialogHeader>

        <ScrollArea class="flex-1 min-h-0">
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
                <div v-for="opt in tagOptions" :key="opt" class="flex items-center justify-between px-2 py-1.5 sm:py-2">
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
                    <Button size="icon" variant="ghost" class="h-7 w-7 sm:h-8 sm:w-8" @click="openAdminTagEdit(opt)">
                      <Pencil class="w-3.5 h-3.5 sm:w-4 sm:h-4" />
                    </Button>
                    <Button size="icon" variant="ghost" class="h-7 w-7 sm:h-8 sm:w-8" @click="openAdminTagDelete(opt)">
                      <Trash2 class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-rose-600" />
                    </Button>
                  </div>
                </div>
              </ScrollArea>
            </div>
          </div>
        </div>
        </ScrollArea>

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
                  class="w-full flex items-center justify-between px-3 sm:px-4 py-2 sm:py-2.5 rounded-lg sm:rounded-xl border border-gray-300 bg-white text-xs sm:text-sm hover:shadow-sm hover:border-blue-300">
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
    <!-- Delete Confirmation AlertDialog -->
    <AlertDialog v-model:open="deleteConfirmDialogOpen">
      <AlertDialogContent class="max-w-[95vw] sm:max-w-md dark:bg-neutral-900 dark:border-neutral-700 !duration-0 data-[state=open]:!animate-none data-[state=closed]:!animate-none data-[state=open]:!fade-in-0 data-[state=closed]:!fade-out-0 data-[state=open]:!zoom-in-100 data-[state=closed]:!zoom-out-100 data-[state=open]:!translate-x-[-50%] data-[state=open]:!translate-y-[-50%] data-[state=closed]:!translate-x-[-50%] data-[state=closed]:!translate-y-[-50%]">
        <AlertDialogHeader>
          <AlertDialogTitle class="text-lg sm:text-lg dark:text-neutral-100">Confirm Delete</AlertDialogTitle>
          <AlertDialogDescription class="text-xs sm:text-sm dark:text-neutral-400">
            This action cannot be undone. This document will be permanently deleted from the system.
            <span v-if="deleteTargetFile" class="block mt-2 font-medium text-gray-900 dark:text-neutral-100">
              Document: {{ deleteTargetFile.name || deleteTargetFile.fullName }}
            </span>
          </AlertDialogDescription>
        </AlertDialogHeader>
        <div class="py-4 space-y-4">
          <div>
            <Label for="delete-password" class="text-xs sm:text-sm font-medium block mb-1.5 sm:mb-2 dark:text-neutral-100">
              Enter your password to confirm <span class="text-red-500">*</span>
            </Label>
            <Input
              id="delete-password"
              v-model="deletePassword"
              type="password"
              placeholder="Enter your password..."
              class="w-full text-xs sm:text-sm dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-100"
              @keyup.enter="confirmDelete"
            />
          </div>
        </div>
        <AlertDialogFooter class="gap-2 flex-col sm:flex-row">
          <AlertDialogCancel 
            class="w-full sm:w-auto"
            @click="deleteConfirmDialogOpen = false; deletePassword = ''; deleteCountdown = 5">
            Cancel
          </AlertDialogCancel>
          <AlertDialogAction
            as-child
            class="w-full sm:w-auto"
          >
          <Button 
            variant="destructive" 
              size="sm"
              class="w-full sm:w-auto"
              @click="confirmDelete"
              :disabled="deleteCountdown > 0 || !deletePassword.trim()"
            >
              <template v-if="deleteCountdown > 0">
                Delete ({{ deleteCountdown }}s)
            </template>
            <template v-else>
                Delete Permanently
            </template>
          </Button>
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>

    <!-- Restore Single Document Confirmation AlertDialog -->
    <AlertDialog v-model:open="restoreConfirmDialogOpen">
      <AlertDialogContent class="max-w-[95vw] sm:max-w-md dark:bg-neutral-900 dark:border-neutral-700 !duration-0 data-[state=open]:!animate-none data-[state=closed]:!animate-none data-[state=open]:!fade-in-0 data-[state=closed]:!fade-out-0 data-[state=open]:!zoom-in-100 data-[state=closed]:!zoom-out-100 data-[state=open]:!translate-x-[-50%] data-[state=open]:!translate-y-[-50%] data-[state=closed]:!translate-x-[-50%] data-[state=closed]:!translate-y-[-50%]">
        <AlertDialogHeader>
          <AlertDialogTitle class="text-lg sm:text-lg dark:text-neutral-100">Confirm Restore</AlertDialogTitle>
          <AlertDialogDescription class="text-xs sm:text-sm dark:text-neutral-400">
            This will restore the document from trash.
            <span v-if="restoreTargetFile" class="block mt-2 font-medium text-gray-900 dark:text-neutral-100">
              Document: {{ restoreTargetFile.name || restoreTargetFile.fullName }}
            </span>
          </AlertDialogDescription>
        </AlertDialogHeader>
        <div class="py-4 space-y-4">
          <div>
            <Label for="restore-password" class="text-xs sm:text-sm font-medium block mb-1.5 sm:mb-2 dark:text-neutral-100">
              Enter your password to confirm <span class="text-red-500">*</span>
            </Label>
            <Input
              id="restore-password"
              v-model="restorePassword"
              type="password"
              placeholder="Enter your password..."
              class="w-full text-xs sm:text-sm dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-100"
              @keyup.enter="confirmRestore"
            />
          </div>
        </div>
        <AlertDialogFooter class="gap-2 flex-col sm:flex-row">
          <AlertDialogCancel 
            class="w-full sm:w-auto"
            @click="restoreConfirmDialogOpen = false; restorePassword = ''; restoreCountdown = 2">
            Cancel
          </AlertDialogCancel>
          <AlertDialogAction
            as-child
            class="w-full sm:w-auto"
          >
            <Button 
              class="w-full sm:w-auto bg-emerald-600 hover:bg-emerald-700 text-white"
              size="sm"
              @click="confirmRestore"
              :disabled="restoreCountdown > 0 || !restorePassword.trim()"
            >
              <template v-if="restoreCountdown > 0">
                Restore ({{ restoreCountdown }}s)
              </template>
              <template v-else>
                Restore
              </template>
          </Button>
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>

    <!-- Permanent Delete Single Document Confirmation AlertDialog -->
    <AlertDialog v-model:open="permanentDeleteConfirmDialogOpen">
      <AlertDialogContent class="max-w-[95vw] sm:max-w-md dark:bg-neutral-900 dark:border-neutral-700 !duration-0 data-[state=open]:!animate-none data-[state=closed]:!animate-none data-[state=open]:!fade-in-0 data-[state=closed]:!fade-out-0 data-[state=open]:!zoom-in-100 data-[state=closed]:!zoom-out-100 data-[state=open]:!translate-x-[-50%] data-[state=open]:!translate-y-[-50%] data-[state=closed]:!translate-x-[-50%] data-[state=closed]:!translate-y-[-50%]">
        <AlertDialogHeader>
          <AlertDialogTitle class="text-lg sm:text-lg dark:text-neutral-100">Confirm Permanent Delete</AlertDialogTitle>
          <AlertDialogDescription class="text-xs sm:text-sm dark:text-neutral-400">
            This action cannot be undone. This document will be permanently deleted from the system.
            <span v-if="permanentDeleteTargetFile" class="block mt-2 font-medium text-gray-900 dark:text-neutral-100">
              Document: {{ permanentDeleteTargetFile.name || permanentDeleteTargetFile.fullName }}
            </span>
          </AlertDialogDescription>
        </AlertDialogHeader>
        <div class="py-4 space-y-4">
          <div>
            <Label for="permanent-delete-password" class="text-xs sm:text-sm font-medium block mb-1.5 sm:mb-2 dark:text-neutral-100">
              Enter your password to confirm <span class="text-red-500">*</span>
            </Label>
            <Input
              id="permanent-delete-password"
              v-model="permanentDeletePassword"
              type="password"
              placeholder="Enter your password..."
              class="w-full text-xs sm:text-sm dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-100"
              @keyup.enter="confirmPermanentDelete"
            />
              </div>
                </div>
        <AlertDialogFooter class="gap-2 flex-col sm:flex-row">
          <AlertDialogCancel 
            class="w-full sm:w-auto"
            @click="permanentDeleteConfirmDialogOpen = false; permanentDeletePassword = ''; permanentDeleteCountdown = 3">
            Cancel
          </AlertDialogCancel>
          <AlertDialogAction
            as-child
            class="w-full sm:w-auto"
          >
            <Button 
              variant="destructive"
              size="sm"
              class="w-full sm:w-auto"
              @click="confirmPermanentDelete"
              :disabled="permanentDeleteCountdown > 0 || !permanentDeletePassword.trim()"
            >
              <template v-if="permanentDeleteCountdown > 0">
                Delete ({{ permanentDeleteCountdown }}s)
              </template>
              <template v-else>
                Delete Permanently
              </template>
            </Button>
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>

    <!-- Restore All Confirmation AlertDialog -->
    <AlertDialog v-model:open="restoreAllConfirmDialogOpen">
      <AlertDialogContent class="max-w-[95vw] sm:max-w-md dark:bg-neutral-900 dark:border-neutral-700 !duration-0 data-[state=open]:!animate-none data-[state=closed]:!animate-none data-[state=open]:!fade-in-0 data-[state=closed]:!fade-out-0 data-[state=open]:!zoom-in-100 data-[state=closed]:!zoom-out-100 data-[state=open]:!translate-x-[-50%] data-[state=open]:!translate-y-[-50%] data-[state=closed]:!translate-x-[-50%] data-[state=closed]:!translate-y-[-50%]">
        <AlertDialogHeader>
          <AlertDialogTitle class="text-lg sm:text-lg dark:text-neutral-100">Confirm Restore All</AlertDialogTitle>
          <AlertDialogDescription class="text-xs sm:text-sm dark:text-neutral-400">
            This will restore all trashed documents. Are you sure?
          </AlertDialogDescription>
        </AlertDialogHeader>
        <div class="py-4 space-y-4">
          <div>
            <Label for="restore-all-password" class="text-xs sm:text-sm font-medium block mb-1.5 sm:mb-2 dark:text-neutral-100">
              Enter your password to confirm <span class="text-red-500">*</span>
            </Label>
            <Input
              id="restore-all-password"
              v-model="restoreAllPassword"
              type="password"
              placeholder="Enter your password..."
              class="w-full text-xs sm:text-sm dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-100"
              @keyup.enter="confirmRestoreAll"
            />
            </div>
          </div>
        <AlertDialogFooter class="gap-2 flex-col sm:flex-row">
          <AlertDialogCancel 
            class="w-full sm:w-auto"
            @click="restoreAllConfirmDialogOpen = false; restoreAllPassword = ''; restoreAllCountdown = 3">
            Cancel
          </AlertDialogCancel>
          <AlertDialogAction
            as-child
            class="w-full sm:w-auto"
          >
          <Button 
              class="w-full sm:w-auto bg-emerald-600 hover:bg-emerald-700 text-white"
              size="sm"
              @click="confirmRestoreAll"
              :disabled="restoreAllCountdown > 0 || !restoreAllPassword.trim()"
            >
              <template v-if="restoreAllCountdown > 0">
                Restore All ({{ restoreAllCountdown }}s)
              </template>
              <template v-else>
                Restore All
              </template>
          </Button>
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>

    <!-- Delete All Confirmation AlertDialog -->
    <AlertDialog v-model:open="deleteAllConfirmDialogOpen">
      <AlertDialogContent class="max-w-[95vw] sm:max-w-md dark:bg-neutral-900 dark:border-neutral-700 !duration-0 data-[state=open]:!animate-none data-[state=closed]:!animate-none data-[state=open]:!fade-in-0 data-[state=closed]:!fade-out-0 data-[state=open]:!zoom-in-100 data-[state=closed]:!zoom-out-100 data-[state=open]:!translate-x-[-50%] data-[state=open]:!translate-y-[-50%] data-[state=closed]:!translate-x-[-50%] data-[state=closed]:!translate-y-[-50%]">
        <AlertDialogHeader>
          <AlertDialogTitle class="text-lg sm:text-lg dark:text-neutral-100">Confirm Permanent Delete All</AlertDialogTitle>
          <AlertDialogDescription class="text-xs sm:text-sm dark:text-neutral-400">
            This action cannot be undone. All trashed documents will be permanently deleted from the system.
          </AlertDialogDescription>
        </AlertDialogHeader>
        <div class="py-4 space-y-4">
          <div>
            <Label for="delete-all-password" class="text-xs sm:text-sm font-medium block mb-1.5 sm:mb-2 dark:text-neutral-100">
              Enter your password to confirm <span class="text-red-500">*</span>
            </Label>
            <Input
              id="delete-all-password"
              v-model="deleteAllPassword"
              type="password"
              placeholder="Enter your password..."
              class="w-full text-xs sm:text-sm dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-100"
              @keyup.enter="confirmDeleteAll"
            />
          </div>
        </div>
        <AlertDialogFooter class="gap-2 flex-col sm:flex-row">
          <AlertDialogCancel 
            class="w-full sm:w-auto"
            @click="deleteAllConfirmDialogOpen = false; deleteAllPassword = ''; deleteAllCountdown = 3">
            Cancel
          </AlertDialogCancel>
          <AlertDialogAction
            as-child
            class="w-full sm:w-auto"
          >
          <Button 
            variant="destructive" 
              size="sm"
              class="w-full sm:w-auto"
              @click="confirmDeleteAll"
              :disabled="deleteAllCountdown > 0 || !deleteAllPassword.trim()"
            >
              <template v-if="deleteAllCountdown > 0">
                Delete All ({{ deleteAllCountdown }}s)
              </template>
              <template v-else>
                Delete All Permanently
              </template>
          </Button>
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>

    <!-- ========================================================================
      FILE VIEWER MODAL
    ======================================================================= -->
    <Dialog :open="fileViewerOpen" @update:open="closeFileViewer">
      <DialogContent class="max-w-5xl max-h-[90vh] overflow-hidden flex flex-col dark:bg-neutral-900 dark:border-neutral-700">
        <DialogHeader>
          <DialogTitle class="text-xl dark:text-neutral-100">{{ viewerFileName || 'File Viewer' }}</DialogTitle>
        </DialogHeader>

        <div class="flex-1 overflow-auto mt-4">
          <!-- PDF Files: Open in New Tab -->
          <Card v-if="isViewerPdf && viewerFileUrl" class="border-gray-200 dark:border-neutral-700 dark:bg-neutral-800">
            <CardContent class="pt-6">
              <div class="flex flex-col items-center justify-center py-12 text-center">
                <div class="rounded-full bg-red-100 dark:bg-red-900/30 p-4 mb-4">
                  <FileText class="w-12 h-12 text-red-600 dark:text-red-400" />
                </div>
                
                <h3 class="text-lg font-semibold text-gray-900 dark:text-neutral-100 mb-2">
                  {{ viewerFileName }}
                </h3>
                
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-red-100 dark:bg-red-900/30 mb-6">
                  <span class="text-xs font-medium text-red-700 dark:text-red-300">
                    PDF Document
                  </span>
                </div>

                <p class="text-sm text-gray-600 dark:text-neutral-400 mb-6 max-w-md">
                  Click the button below to open this PDF in a new tab with your browser's PDF viewer.
                </p>

                <div class="flex gap-3">
                  <Button @click="openInNewTab" size="lg" class="gap-2 bg-blue-600 hover:bg-blue-700 text-white">
                    <Eye :size="18" />
                    Open in New Tab
                  </Button>
                  <Button @click="downloadViewerFile" size="lg" variant="outline" class="gap-2">
                    <Download :size="18" />
                    Download
                  </Button>
                </div>
              </div>
            </CardContent>
          </Card>

          <!-- Non-PDF Files: Download Only -->
          <Card v-else-if="viewerFileUrl" class="border-gray-200 dark:border-neutral-700 dark:bg-neutral-800"> 
            <CardContent class="pt-6">
              <div class="flex flex-col items-center justify-center py-12 text-center">
                <div class="rounded-full bg-blue-100 dark:bg-blue-900/30 p-4 mb-4">
                  <FolderIcon class="w-12 h-12 text-blue-600 dark:text-blue-400" />
                </div>
                
                <h3 class="text-lg font-semibold text-gray-900 dark:text-neutral-100 mb-2">
                  {{ viewerFileName }}
                </h3>
                
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-gray-100 dark:bg-neutral-700 mb-6">
                  <span class="text-xs font-medium text-gray-700 dark:text-neutral-300">
                    {{ viewerFileName?.split('.').pop()?.toUpperCase() || 'FILE' }}
                  </span>
                </div>

                <p class="text-sm text-gray-600 dark:text-neutral-400 mb-6 max-w-md">
                  Preview is not available for this file type. Click the button below to download and view the file.
                </p>

                <Button @click="downloadViewerFile" size="lg" class="gap-2 bg-blue-600 hover:bg-blue-700 text-white">
                  <Download :size="18" />
                  Download File
                </Button>
              </div>
            </CardContent>
          </Card>
        </div>
      </DialogContent>
    </Dialog>

    <!-- Toast notifications are now handled by Sonner via the Toaster component in AppLayout -->

    <!-- Document Details Dialog -->
    <Dialog v-model:open="documentDetailsOpen">
      <DialogContent class="max-w-2xl max-h-[90vh] flex flex-col dark:bg-neutral-900 dark:border-neutral-700 mx-4 sm:mx-auto">
        <DialogHeader class="shrink-0">
          <DialogTitle class="dark:text-neutral-100">Document Details</DialogTitle>
          <DialogDescription class="dark:text-neutral-400">
            View and manage document information
          </DialogDescription>
        </DialogHeader>

        <div 
          class="flex-1 overflow-y-auto max-h-[calc(90vh-200px)] min-h-0 scrollbar-auto-hide scrollbar-hidden"
          @mouseenter="showScrollbar"
          @mouseleave="hideScrollbar"
          @scroll="onScroll"
          ref="documentDetailScrollRef"
        >
          <div v-if="selectedDocumentForDetails" class="space-y-6 py-4">
          <!-- File Header -->
          <div class="flex items-start gap-4 pb-4 border-b dark:border-neutral-700">
            <div
              :class="['w-20 h-20 flex items-center justify-center rounded-lg text-white font-bold text-lg shadow-sm', typeColor(selectedDocumentForDetails.type)]">
              {{ selectedDocumentForDetails.type }}
      </div>
            <div class="flex-1 min-w-0">
              <h3 class="text-lg font-semibold text-gray-900 dark:text-neutral-100 mb-1 break-words">
                {{ selectedDocumentForDetails.fullName || selectedDocumentForDetails.name }}
              </h3>
              <div class="flex items-center gap-3 text-sm text-gray-500 dark:text-neutral-400 mb-1">
                <span>{{ selectedDocumentForDetails.size }}</span>
                <span>•</span>
                <span>{{ selectedDocumentForDetails._original?.department?.name || selectedDocumentForDetails.department || '—' }}</span>
    </div>
              <div class="text-sm" :class="getAccessTextColor(selectedDocumentForDetails.access)">
                {{ selectedDocumentForDetails.access }}
              </div>
            </div>
          </div>

          <!-- Document Information -->
          <div class="space-y-4">
            <!-- Uploaded By and Uploaded At -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <Label class="text-xs font-medium text-gray-500 dark:text-neutral-400 mb-1 block">Uploaded By</Label>
                <p class="text-sm text-gray-900 dark:text-neutral-100">{{ selectedDocumentForDetails.uploader }}</p>
              </div>
              <div>
                <Label class="text-xs font-medium text-gray-500 dark:text-neutral-400 mb-1 block">Uploaded At</Label>
                <p class="text-sm text-gray-900 dark:text-neutral-100">{{ selectedDocumentForDetails.created || 'N/A' }}</p>
              </div>
            </div>

            <!-- Reviewed By and Reviewed At -->
            <div v-if="selectedDocumentForDetails.approvedBy || selectedDocumentForDetails.rejectedBy || selectedDocumentForDetails.approvedAt || selectedDocumentForDetails.rejectedAt" class="grid grid-cols-2 gap-4">
              <div>
                <Label class="text-xs font-medium text-gray-500 dark:text-neutral-400 mb-1 block">Reviewed By</Label>
                <p class="text-sm text-gray-900 dark:text-neutral-100">
                  {{ selectedDocumentForDetails.approvedBy || selectedDocumentForDetails.rejectedBy || '—' }}
                </p>
              </div>
              <div>
                <Label class="text-xs font-medium text-gray-500 dark:text-neutral-400 mb-1 block">Reviewed At</Label>
                <p class="text-sm text-gray-900 dark:text-neutral-100">
                  {{ selectedDocumentForDetails.approvedAt || selectedDocumentForDetails.rejectedAt || '—' }}
                </p>
              </div>
            </div>

            <!-- Description -->
            <div v-if="selectedDocumentForDetails.description">
              <Label class="text-xs font-medium text-gray-500 dark:text-neutral-400 mb-2 block">Description</Label>
              <p class="text-sm text-gray-700 dark:text-neutral-200 whitespace-pre-wrap">{{ selectedDocumentForDetails.description }}</p>
            </div>

            <!-- Tags -->
            <div v-if="selectedDocumentForDetails.tags && selectedDocumentForDetails.tags.length > 0">
              <Label class="text-xs font-medium text-gray-500 dark:text-neutral-400 mb-2 block">Tags</Label>
              <div class="flex flex-wrap gap-2">
                <Badge
                  v-for="(tag, index) in selectedDocumentForDetails.tags"
                  :key="index"
                  variant="secondary"
                  class="text-xs"
                >
                  {{ tag }}
                </Badge>
              </div>
            </div>

            <!-- Review Notes -->
            <div v-if="selectedDocumentForDetails.reviewMessage || selectedDocumentForDetails.reviewNotes || selectedDocumentForDetails._original?.review_message" class="space-y-2 mt-2">
              <Label class="text-xs font-medium text-gray-500 dark:text-neutral-400 mb-2 block">Review Message</Label>
              <div 
                :class="[
                  'border rounded-lg p-4',
                  isTrashDetails
                    ? 'bg-emerald-50 dark:bg-emerald-900/20 border-emerald-200 dark:border-emerald-800'
                    : selectedDocumentForDetails.status === 'Approved' || selectedDocumentForDetails.approvedBy
                    ? 'bg-emerald-50 dark:bg-emerald-900/20 border-emerald-200 dark:border-emerald-800'
                    : selectedDocumentForDetails.status === 'Rejected' || selectedDocumentForDetails.rejectedBy
                    ? 'bg-rose-50 dark:bg-rose-900/20 border-rose-200 dark:border-rose-800'
                    : 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800'
                ]"
              >
                <p 
                  :class="[
                    'text-sm whitespace-pre-wrap',
                    isTrashDetails
                      ? 'text-emerald-900 dark:text-emerald-100'
                      : selectedDocumentForDetails.status === 'Approved' || selectedDocumentForDetails.approvedBy
                      ? 'text-emerald-900 dark:text-emerald-100'
                      : selectedDocumentForDetails.status === 'Rejected' || selectedDocumentForDetails.rejectedBy
                      ? 'text-rose-900 dark:text-rose-100'
                      : 'text-gray-800 dark:text-neutral-200'
                  ]"
                >
                  {{ selectedDocumentForDetails.reviewMessage || selectedDocumentForDetails.reviewNotes || selectedDocumentForDetails._original?.review_message || '—' }}
                </p>
              </div>
            </div>

            <!-- Deleted By and Deleted At (for trash items) -->
            <div v-if="isTrashDetails" class="grid grid-cols-2 gap-4">
              <div>
                <Label class="text-xs font-medium text-gray-500 dark:text-neutral-400 mb-1 block">Deleted By</Label>
                <p class="text-sm text-gray-900 dark:text-neutral-100">
                  {{ selectedDocumentForDetails.deletedBy || '—' }}
                </p>
              </div>
              <div>
                <Label class="text-xs font-medium text-gray-500 dark:text-neutral-400 mb-1 block">Deleted At</Label>
                <p class="text-sm text-gray-900 dark:text-neutral-100">
                  {{ selectedDocumentForDetails.deletedAt ? new Date(selectedDocumentForDetails.deletedAt).toLocaleString() : '—' }}
                </p>
              </div>
            </div>
          </div>
          </div>
        </div>

        <template v-if="isTrashDetails">
          <DialogFooter class="gap-2">
            <!-- Preview button: Admin always (if PDF), Non-admin only if canPreviewDocument -->
            <Button
              v-if="selectedDocumentForDetails && (isAdminUser ? (selectedDocumentForDetails.type === 'PDF') : canPreviewDocument(selectedDocumentForDetails))"
              @click="handlePreviewFromDetails"
              variant="outline"
              size="icon"
              title="Preview"
            >
              <Eye class="w-4 h-4" />
            </Button>
            <!-- Download button: Admin always, Non-admin only if canDownloadDocument -->
            <Button
              v-if="selectedDocumentForDetails && (isAdminUser || canDownloadDocument(selectedDocumentForDetails))"
              @click="handleDownloadFromDetails"
              variant="outline"
              size="icon"
              title="Download"
            >
              <Download class="w-4 h-4" />
            </Button>
            <!-- Restore and Delete: Admin only -->
            <Button
              v-if="selectedDocumentForDetails && isAdminUser"
              @click="restoreFromTrash(selectedDocumentForDetails); documentDetailsOpen = false"
              class="w-auto text-xs sm:text-sm bg-emerald-600 hover:bg-emerald-700 text-white"
            >
              <RefreshCw class="w-4 h-4 mr-2" />
              Restore
            </Button>
            <Button
              v-if="selectedDocumentForDetails && isAdminUser"
              @click="deletePermanently(selectedDocumentForDetails); documentDetailsOpen = false"
              variant="destructive"
              class="w-auto text-xs sm:text-sm"
            >
              <Trash2Icon class="w-4 h-4 mr-2" />
              Permanent Delete
            </Button>
            <!-- Request Access button for non-admin (only if they don't have access) -->
            <Button
              v-if="!isAdminUser && selectedDocumentForDetails && !canDownloadDocument(selectedDocumentForDetails)"
              @click="documentRequestAccessDialogOpen = true"
              class="text-xs sm:text-sm bg-blue-600 hover:bg-blue-700 text-white w-auto"
            >
              <SendIcon class="w-4 h-4 mr-2" />
              Request Access
            </Button>
            <!-- Cancel/Close button for non-admin -->
            <Button
              v-if="!isAdminUser"
              variant="outline"
              @click="documentDetailsOpen = false"
              class="text-xs sm:text-sm w-auto"
            >
              Cancel
            </Button>
          </DialogFooter>
        </template>
        <template v-else-if="isPendingDetails">
          <DialogFooter class="gap-2">
            <!-- For My Department tab: Admin and Dept Manager have full controls -->
            <template v-if="isMyDepartmentTab && (isAdminUser || isDepartmentManager)">
              <!-- Preview button -->
              <Button
                v-if="selectedDocumentForDetails && selectedDocumentForDetails.type === 'PDF'"
                @click="handlePreviewFromDetails"
                variant="outline"
                size="icon"
                title="Preview"
              >
                <Eye class="w-4 h-4" />
              </Button>
              <!-- Download button -->
              <Button
                v-if="selectedDocumentForDetails"
                @click="handleDownloadFromDetails"
                variant="outline"
                size="icon"
                title="Download"
              >
                <Download class="w-4 h-4" />
              </Button>
              <!-- Approve/Reject buttons -->
              <Button
                v-if="selectedDocumentForDetails"
                @click="startDecisionDialog('reject')"
                variant="destructive"
                class="text-xs sm:text-sm w-[120px]"
              >
                <XCircle class="w-4 h-4 mr-2" />
                Reject
              </Button>
              <Button
                v-if="selectedDocumentForDetails"
                @click="startDecisionDialog('approve')"
                class="text-xs sm:text-sm bg-emerald-600 hover:bg-emerald-700 text-white w-[120px]"
              >
                <CheckCircle class="w-4 h-4 mr-2" />
                Approve
              </Button>
            </template>
            <!-- For My Department tab: Employee can cancel their own pending upload -->
            <template v-else-if="isMyDepartmentTab && normalizedRole === 'employee' && isEmployeeOwnFile">
              <Button
                @click="handleCancelPendingRequest"
                variant="destructive"
                class="text-xs sm:text-sm w-auto"
              >
                <XCircle class="w-4 h-4 mr-2" />
                Cancel Request
              </Button>
            </template>
            <!-- For other tabs (All Files): Original logic -->
            <template v-else>
              <!-- Preview button: Admin always (if PDF), Non-admin only if canPreviewDocument -->
              <Button
                v-if="selectedDocumentForDetails && (isAdminUser ? (selectedDocumentForDetails.type === 'PDF') : canPreviewDocument(selectedDocumentForDetails))"
                @click="handlePreviewFromDetails"
                variant="outline"
                size="icon"
                title="Preview"
              >
                <Eye class="w-4 h-4" />
              </Button>
              <!-- Download button: Admin always, Non-admin only if canDownloadDocument -->
              <Button
                v-if="selectedDocumentForDetails && (isAdminUser || canDownloadDocument(selectedDocumentForDetails))"
                @click="handleDownloadFromDetails"
                variant="outline"
                size="icon"
                title="Download"
              >
                <Download class="w-4 h-4" />
              </Button>
              <!-- Approve/Reject buttons: Admin only -->
              <Button
                v-if="selectedDocumentForDetails && isAdminUser"
                @click="startDecisionDialog('reject')"
                variant="destructive"
                class="text-xs sm:text-sm w-[120px]"
              >
                <XCircle class="w-4 h-4 mr-2" />
                Reject
              </Button>
              <Button
                v-if="selectedDocumentForDetails && isAdminUser"
                @click="startDecisionDialog('approve')"
                class="text-xs sm:text-sm bg-emerald-600 hover:bg-emerald-700 text-white w-[120px]"
              >
                <CheckCircle class="w-4 h-4 mr-2" />
                Approve
              </Button>
              <!-- Cancel button for non-admin -->
              <Button
                v-if="!isAdminUser"
                variant="outline"
                @click="documentDetailsOpen = false"
                class="text-xs sm:text-sm w-[120px]"
              >
                Cancel
              </Button>
            </template>
          </DialogFooter>
        </template>
        <template v-else-if="isRejectedDetails">
          <DialogFooter class="justify-end">
            <Button variant="outline" @click="documentDetailsOpen = false">
              Close
            </Button>
          </DialogFooter>
        </template>
        <template v-else>
          <DialogFooter class="gap-2">
            <!-- For My Department tab: Admin and Dept Manager have full controls -->
            <template v-if="isMyDepartmentTab && (isAdminUser || isDepartmentManager)">
              <!-- Download History -->
              <Button
                v-if="selectedDocumentForDetails"
                @click="downloadHistoryOpen = true"
                variant="outline"
                size="icon"
                title="Download History"
              >
                <Clock class="w-4 h-4" />
              </Button>
              <!-- Preview button -->
              <Button
                v-if="selectedDocumentForDetails && selectedDocumentForDetails.type === 'PDF'"
                @click="handlePreviewFromDetails"
                variant="outline"
                size="icon"
                title="Preview"
              >
                <Eye class="w-4 h-4" />
              </Button>
              <!-- Download button -->
              <Button
                v-if="selectedDocumentForDetails"
                @click="handleDownloadFromDetails"
                variant="outline"
                size="icon"
                title="Download"
              >
                <Download class="w-4 h-4" />
              </Button>
              <!-- Edit button -->
              <Button
                v-if="selectedDocumentForDetails"
                @click="handleEditFromDetails"
                variant="outline"
              >
                <Edit3 class="w-4 h-4 mr-2" />
                Edit
              </Button>
              <!-- Delete button -->
              <Button
                v-if="selectedDocumentForDetails"
                @click="handleDeleteFromDetails"
                variant="destructive"
              >
                <Trash2Icon class="w-4 h-4 mr-2" />
                Delete
              </Button>
            </template>
            <!-- For My Department tab: Employee has limited controls with Request Access for Private files -->
            <template v-else-if="isMyDepartmentTab && normalizedRole === 'employee'">
              <!-- Preview button (if has access) -->
              <Button
                v-if="selectedDocumentForDetails && canPreviewDocument(selectedDocumentForDetails)"
                @click="handlePreviewFromDetails"
                variant="outline"
                size="icon"
                title="Preview"
              >
                <Eye class="w-4 h-4" />
              </Button>
              <!-- Download button (if has access) -->
              <Button
                v-if="selectedDocumentForDetails && canDownloadDocument(selectedDocumentForDetails)"
                @click="handleDownloadFromDetails"
                variant="outline"
                size="icon"
                title="Download"
              >
                <Download class="w-4 h-4" />
              </Button>
              <!-- Request Access button (only if they don't have access) -->
              <Button
                v-if="selectedDocumentForDetails && !canDownloadDocument(selectedDocumentForDetails)"
                @click="documentRequestAccessDialogOpen = true"
                class="text-xs sm:text-sm bg-blue-600 hover:bg-blue-700 text-white w-auto"
              >
                <SendIcon class="w-4 h-4 mr-2" />
                Request Access
              </Button>
              <!-- Cancel button -->
              <Button
                variant="outline"
                @click="documentDetailsOpen = false"
                class="text-xs sm:text-sm w-auto"
              >
                Cancel
              </Button>
            </template>
            <!-- For other tabs (All Files): Original logic -->
            <template v-else>
              <!-- Download History: Admin only -->
              <Button
                v-if="selectedDocumentForDetails && isAdminUser"
                @click="downloadHistoryOpen = true"
                variant="outline"
                size="icon"
                title="Download History"
              >
                <Clock class="w-4 h-4" />
              </Button>
              <!-- Preview button: Admin always (if PDF), Non-admin only if canPreviewDocument -->
              <Button
                v-if="selectedDocumentForDetails && (isAdminUser ? (selectedDocumentForDetails.type === 'PDF') : canPreviewDocument(selectedDocumentForDetails))"
                @click="handlePreviewFromDetails"
                variant="outline"
                size="icon"
                title="Preview"
              >
                <Eye class="w-4 h-4" />
              </Button>
              <!-- Download button: Admin always, Non-admin only if canDownloadDocument -->
              <Button
                v-if="selectedDocumentForDetails && (isAdminUser || canDownloadDocument(selectedDocumentForDetails))"
                @click="handleDownloadFromDetails"
                variant="outline"
                size="icon"
                title="Download"
              >
                <Download class="w-4 h-4" />
              </Button>
              <!-- Edit button: Admin only -->
              <Button
                v-if="selectedDocumentForDetails && isAdminUser"
                @click="handleEditFromDetails"
                variant="outline"
              >
                <Edit3 class="w-4 h-4 mr-2" />
                Edit
              </Button>
              <!-- Delete button: Admin only -->
              <Button
                v-if="selectedDocumentForDetails && isAdminUser"
                @click="handleDeleteFromDetails"
                variant="destructive"
              >
                <Trash2Icon class="w-4 h-4 mr-2" />
                Delete
              </Button>
              <!-- Request Access button for non-admin (only if they don't have access) -->
              <Button
                v-if="!isAdminUser && selectedDocumentForDetails && !canDownloadDocument(selectedDocumentForDetails)"
                @click="documentRequestAccessDialogOpen = true"
                class="text-xs sm:text-sm bg-blue-600 hover:bg-blue-700 text-white w-auto"
              >
                <SendIcon class="w-4 h-4 mr-2" />
                Request Access
              </Button>
              <!-- Cancel button for non-admin -->
              <Button
                v-if="!isAdminUser"
                variant="outline"
                @click="documentDetailsOpen = false"
                class="text-xs sm:text-sm w-auto"
              >
                Cancel
              </Button>
            </template>
          </DialogFooter>
        </template>
      </DialogContent>
    </Dialog>

    <!-- Preview Confirmation Dialog -->
    <AlertDialog v-model:open="previewConfirmOpen">
      <AlertDialogContent class="max-w-md dark:bg-neutral-900 dark:border-neutral-700 !duration-0 data-[state=open]:!animate-none data-[state=closed]:!animate-none data-[state=open]:!fade-in-0 data-[state=closed]:!fade-out-0 data-[state=open]:!zoom-in-100 data-[state=closed]:!zoom-out-100 data-[state=open]:!translate-x-[-50%] data-[state=open]:!translate-y-[-50%] data-[state=closed]:!translate-x-[-50%] data-[state=closed]:!translate-y-[-50%]">
        <AlertDialogHeader>
          <AlertDialogTitle class="dark:text-neutral-100">Open File in New Tab</AlertDialogTitle>
          <AlertDialogDescription class="dark:text-neutral-400">
            Do you want to open this file in a new tab?
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter class="gap-2">
          <AlertDialogCancel class="dark:bg-neutral-800 dark:text-neutral-100 dark:hover:bg-neutral-700">
            Cancel
          </AlertDialogCancel>
          <AlertDialogAction
            @click="confirmPreview"
            class="dark:bg-blue-600 dark:hover:bg-blue-700"
          >
            Open
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>

    <!-- Download Confirmation Dialog -->
    <AlertDialog v-model:open="downloadConfirmOpen">
      <AlertDialogContent class="max-w-md dark:bg-neutral-900 dark:border-neutral-700 !duration-0 data-[state=open]:!animate-none data-[state=closed]:!animate-none data-[state=open]:!fade-in-0 data-[state=closed]:!fade-out-0 data-[state=open]:!zoom-in-100 data-[state=closed]:!zoom-out-100 data-[state=open]:!translate-x-[-50%] data-[state=open]:!translate-y-[-50%] data-[state=closed]:!translate-x-[-50%] data-[state=closed]:!translate-y-[-50%]">
        <AlertDialogHeader>
          <AlertDialogTitle class="dark:text-neutral-100">Download File</AlertDialogTitle>
          <AlertDialogDescription class="dark:text-neutral-400">
            Do you want to download this file?
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter class="gap-2">
          <AlertDialogCancel class="dark:bg-neutral-800 dark:text-neutral-100 dark:hover:bg-neutral-700">
            Cancel
          </AlertDialogCancel>
          <AlertDialogAction
            @click="confirmDownload"
            class="dark:bg-blue-600 dark:hover:bg-blue-700"
          >
            Download
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>

    <!-- Review Decision Dialog -->
    <AlertDialog v-model:open="decisionDialogOpen">
      <AlertDialogContent class="max-w-md dark:bg-neutral-900 dark:border-neutral-700 !duration-0 data-[state=open]:!animate-none data-[state=closed]:!animate-none data-[state=open]:!fade-in-0 data-[state=closed]:!fade-out-0 data-[state=open]:!zoom-in-100 data-[state=closed]:!zoom-out-100 data-[state=open]:!translate-x-[-50%] data-[state=open]:!translate-y-[-50%] data-[state=closed]:!translate-x-[-50%] data-[state=closed]:!translate-y-[-50%]">
        <AlertDialogHeader>
          <AlertDialogTitle class="dark:text-neutral-100">
            {{ decisionAction === 'approve' ? 'Approve Document' : 'Reject Document' }}
          </AlertDialogTitle>
          <AlertDialogDescription class="dark:text-neutral-400">
            Do you want to {{ decisionAction === 'approve' ? 'approve' : 'reject' }}
            "{{ selectedDocumentForDetails?.fullName || selectedDocumentForDetails?.name }}"?
          </AlertDialogDescription>
        </AlertDialogHeader>

        <div class="space-y-2">
          <Label class="text-xs font-medium text-gray-500 dark:text-neutral-400">
            Review Message
          </Label>
          <Textarea
            v-model="decisionMessage"
            rows="4"
            placeholder="Add an optional review message"
            class="text-sm"
          />
        </div>

        <AlertDialogFooter class="gap-2">
          <AlertDialogCancel class="dark:bg-neutral-800 dark:text-neutral-100 dark:hover:bg-neutral-700">
            Cancel
          </AlertDialogCancel>
          <AlertDialogAction
            @click="confirmDecision"
            :class="decisionAction === 'approve'
              ? 'bg-emerald-600 hover:bg-emerald-700 text-white'
              : 'bg-red-600 hover:bg-red-700 text-white'"
          >
            {{ decisionAction === 'approve' ? 'Approve' : 'Reject' }}
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>

    <!-- Request Review Decision Dialog -->
    <AlertDialog v-model:open="requestDecisionDialogOpen">
      <AlertDialogContent
        class="max-w-md dark:bg-neutral-900 dark:border-neutral-700 !duration-0 data-[state=open]:!animate-none data-[state=closed]:!animate-none data-[state=open]:!fade-in-0 data-[state=closed]:!fade-out-0 data-[state=open]:!zoom-in-100 data-[state=closed]:!zoom-out-100 data-[state=open]:!translate-x-[-50%] data-[state=open]:!translate-y-[-50%] data-[state=closed]:!translate-x-[-50%] data-[state=closed]:!translate-y-[-50%]"
      >
        <AlertDialogHeader>
          <AlertDialogTitle class="dark:text-neutral-100">
            {{ requestDecisionAction === 'approve' ? 'Approve Request' : 'Reject Request' }}
          </AlertDialogTitle>
          <AlertDialogDescription class="dark:text-neutral-400">
            Do you want to {{ requestDecisionAction === 'approve' ? 'approve' : 'reject' }}
            "
            {{ requestDecisionTarget?.record?.fullName || requestDecisionTarget?.record?.name }}
            "?
          </AlertDialogDescription>
        </AlertDialogHeader>

        <div class="space-y-2">
          <Label class="text-xs font-medium text-gray-500 dark:text-neutral-400">
            Review Message
          </Label>
          <Textarea
            v-model="requestDecisionMessage"
            rows="4"
            placeholder="Add a review note for this decision"
            class="text-sm"
          />
        </div>

        <AlertDialogFooter class="gap-2">
          <AlertDialogCancel class="dark:bg-neutral-800 dark:text-neutral-100 dark:hover:bg-neutral-700">
            Cancel
          </AlertDialogCancel>
          <AlertDialogAction
            @click="confirmRequestDecision"
            class="dark:bg-blue-600 dark:hover:bg-blue-700"
          >
            Confirm
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>

    <!-- Edit Access Dialog -->
    <Dialog v-model:open="editAccessDialogOpen">
      <DialogContent
        class="max-w-md dark:bg-neutral-900 dark:border-neutral-700 !duration-0 data-[state=open]:!animate-none data-[state=closed]:!animate-none"
      >
        <DialogHeader>
          <DialogTitle class="dark:text-neutral-100">
            Edit Access Request
          </DialogTitle>
          <DialogDescription class="dark:text-neutral-400">
            Change the status of this access request.
          </DialogDescription>
        </DialogHeader>

        <div class="space-y-4 py-4">
          <!-- Current Status Display -->
          <div class="bg-gray-50 dark:bg-neutral-800 rounded-lg p-4 border border-gray-200 dark:border-neutral-700">
            <Label class="text-xs font-medium text-gray-500 dark:text-neutral-400 mb-2 block">
              Current Status
            </Label>
            <div 
              :class="[
                'inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-sm font-medium',
                editAccessTarget?.status === 'Approved'
                  ? 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300'
                  : 'bg-rose-100 dark:bg-rose-900/30 text-rose-700 dark:text-rose-300'
              ]"
            >
              <span>{{ editAccessTarget?.status || '—' }}</span>
            </div>
          </div>

          <!-- Review Message -->
          <div class="space-y-2">
            <Label class="text-xs font-medium text-gray-500 dark:text-neutral-400">
              Review Message
            </Label>
            <Textarea
              v-model="editAccessMessage"
              rows="4"
              placeholder="Add a review note for this change"
              class="text-sm dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-100"
            />
          </div>
        </div>

        <DialogFooter class="gap-2">
          <Button
            variant="outline"
            @click="editAccessDialogOpen = false; editAccessMessage = ''; editAccessTarget = null"
            class="dark:bg-neutral-800 dark:text-neutral-100 dark:hover:bg-neutral-700"
          >
            Cancel
          </Button>
          <Button
            @click="confirmEditAccess"
            :class="[
              'text-white',
              editAccessTarget?.status === 'Approved'
                ? 'bg-rose-600 hover:bg-rose-700'
                : 'bg-emerald-600 hover:bg-emerald-700'
            ]"
          >
            {{ editAccessTarget?.status === 'Approved' ? 'Reject' : 'Approve' }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Request Preview Confirmation Dialog -->
    <AlertDialog v-model:open="requestPreviewConfirmOpen">
      <AlertDialogContent
        class="max-w-md dark:bg-neutral-900 dark:border-neutral-700 !duration-0 data-[state=open]:!animate-none data-[state=closed]:!animate-none data-[state=open]:!fade-in-0 data-[state=closed]:!fade-out-0 data-[state=open]:!zoom-in-100 data-[state=closed]:!zoom-out-100 data-[state=open]:!translate-x-[-50%] data-[state=open]:!translate-y-[-50%] data-[state=closed]:!translate-x-[-50%] data-[state=closed]:!translate-y-[-50%]"
      >
        <AlertDialogHeader>
          <AlertDialogTitle class="dark:text-neutral-100">
            Preview Document
          </AlertDialogTitle>
          <AlertDialogDescription class="dark:text-neutral-400">
            Do you want to open "
            {{ requestPreviewTarget?.fullName || requestPreviewTarget?.name || 'this request' }}
            " in a new tab?
          </AlertDialogDescription>
        </AlertDialogHeader>

        <AlertDialogFooter class="gap-2">
          <AlertDialogCancel class="dark:bg-neutral-800 dark:text-neutral-100 dark:hover:bg-neutral-700">
            Cancel
          </AlertDialogCancel>
          <AlertDialogAction
            @click="confirmRequestPreview"
            class="dark:bg-blue-600 dark:hover:bg-blue-700"
          >
            Preview
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>

    <!-- Request Download Confirmation Dialog -->
    <AlertDialog v-model:open="requestDownloadConfirmOpen">
      <AlertDialogContent
        class="max-w-md dark:bg-neutral-900 dark:border-neutral-700 !duration-0 data-[state=open]:!animate-none data-[state=closed]:!animate-none data-[state=open]:!fade-in-0 data-[state=closed]:!fade-out-0 data-[state=open]:!zoom-in-100 data-[state=closed]:!zoom-out-100 data-[state=open]:!translate-x-[-50%] data-[state=open]:!translate-y-[-50%] data-[state=closed]:!translate-x-[-50%] data-[state=closed]:!translate-y-[-50%]"
      >
        <AlertDialogHeader>
          <AlertDialogTitle class="dark:text-neutral-100">
            Download Document
          </AlertDialogTitle>
          <AlertDialogDescription class="dark:text-neutral-400">
            Do you want to download "
            {{ requestDownloadTarget?.fullName || requestDownloadTarget?.name || 'this request' }}
            "?
          </AlertDialogDescription>
        </AlertDialogHeader>

        <AlertDialogFooter class="gap-2">
          <AlertDialogCancel class="dark:bg-neutral-800 dark:text-neutral-100 dark:hover:bg-neutral-700">
            Cancel
          </AlertDialogCancel>
          <AlertDialogAction
            @click="confirmRequestDownload"
            class="dark:bg-blue-600 dark:hover:bg-blue-700"
          >
            Download
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>

    <!-- Request Access Dialog -->
    <Dialog v-model:open="documentRequestAccessDialogOpen">
      <DialogContent class="max-w-md dark:bg-neutral-900 dark:border-neutral-700">
        <DialogHeader>
          <DialogTitle class="dark:text-neutral-100">Request Access</DialogTitle>
          <DialogDescription class="dark:text-neutral-400">
            Request access to view this document
          </DialogDescription>
        </DialogHeader>
        <div class="space-y-4 py-4">
          <div>
            <Label class="text-sm font-medium text-gray-700 dark:text-neutral-300 mb-2 block">
              Request Message
            </Label>
            <Textarea
              v-model="documentRequestMessage"
              rows="4"
              placeholder="Enter your request message (optional)"
              class="text-sm dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-100"
            />
          </div>
        </div>
        <DialogFooter class="gap-2">
          <Button
            variant="outline"
            @click="documentRequestAccessDialogOpen = false; documentRequestMessage = ''"
            class="flex-1 text-xs sm:text-sm"
          >
            Cancel
          </Button>
          <Button
            @click="handleDocumentRequestAccess"
            class="flex-1 text-xs sm:text-sm bg-blue-600 hover:bg-blue-700 text-white"
          >
            <SendIcon class="w-4 h-4 mr-2" />
            Send Request
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Download History Dialog -->
    <Dialog v-model:open="downloadHistoryOpen">
      <DialogContent class="max-w-2xl max-h-[90vh] flex flex-col min-h-0 dark:bg-neutral-900 dark:border-neutral-700 mx-4 sm:mx-auto">
        <DialogHeader>
          <DialogTitle class="dark:text-neutral-100">Download History</DialogTitle>
          <DialogDescription class="dark:text-neutral-400">
            Users who have downloaded this document
          </DialogDescription>
        </DialogHeader>

        <ScrollArea class="flex-1 min-h-0">
          <div v-if="selectedDocumentForDetails && selectedDocumentForDetails._original?.downloads && selectedDocumentForDetails._original.downloads.length > 0" class="py-4">
            <div class="space-y-3">
              <div
                v-for="(download, index) in selectedDocumentForDetails._original.downloads"
                :key="index"
                class="flex items-center justify-between p-3 border border-gray-200 dark:border-neutral-700 rounded-lg"
              >
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium text-gray-900 dark:text-neutral-100 truncate">
                    {{ download.user?.name || 'Unknown User' }}
                  </p>
                  <p class="text-xs text-gray-500 dark:text-neutral-400 mt-1">
                    {{ download.employee?.department?.name || download.employee?.department?.code || '—' }}
                  </p>
                </div>
                <div class="text-right ml-4">
                  <p class="text-xs text-gray-500 dark:text-neutral-400">
                    {{ download.downloaded_at ? new Date(download.downloaded_at).toLocaleString('en-US', {
                      year: 'numeric',
                      month: '2-digit',
                      day: '2-digit',
                      hour: '2-digit',
                      minute: '2-digit'
                    }) : '—' }}
                  </p>
                </div>
              </div>
            </div>
          </div>
          <div v-else class="py-8 text-center">
            <Download class="w-12 h-12 mx-auto text-gray-300 dark:text-neutral-600 mb-3" />
            <p class="text-sm text-gray-500 dark:text-neutral-400">No download history available</p>
          </div>
        </ScrollArea>

        <DialogFooter>
          <Button @click="downloadHistoryOpen = false" variant="outline">
            Close
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </AppLayout>
</template> 

<style scoped>
/* Scrollbar auto-hide styles */
.scrollbar-auto-hide {
  scrollbar-width: thin;
  scrollbar-color: transparent transparent;
  transition: scrollbar-color 0.3s ease;
}

.scrollbar-auto-hide.scrollbar-hidden {
  scrollbar-color: transparent transparent;
}

.scrollbar-auto-hide.scrollbar-visible {
  scrollbar-color: rgba(156, 163, 175, 0.5) transparent;
}

.scrollbar-auto-hide::-webkit-scrollbar {
  width: 8px;
}

.scrollbar-auto-hide::-webkit-scrollbar-track {
  background: transparent;
}

.scrollbar-auto-hide::-webkit-scrollbar-thumb {
  background-color: transparent;
  border-radius: 4px;
  transition: background-color 0.3s ease;
}

.scrollbar-auto-hide.scrollbar-hidden::-webkit-scrollbar-thumb {
  background-color: transparent;
}

.scrollbar-auto-hide.scrollbar-visible::-webkit-scrollbar-thumb {
  background-color: rgba(156, 163, 175, 0.5);
}

.scrollbar-auto-hide.scrollbar-visible::-webkit-scrollbar-thumb:hover {
  background-color: rgba(156, 163, 175, 0.8);
}

/* Dark mode scrollbar */
:deep(.dark) .scrollbar-auto-hide.scrollbar-visible {
  scrollbar-color: rgba(115, 115, 115, 0.5) transparent;
}

:deep(.dark) .scrollbar-auto-hide.scrollbar-visible::-webkit-scrollbar-thumb {
  background-color: rgba(115, 115, 115, 0.5);
}

:deep(.dark) .scrollbar-auto-hide.scrollbar-visible::-webkit-scrollbar-thumb:hover {
  background-color: rgba(115, 115, 115, 0.8);
}

/* Disable animations for edit dialog */
:deep(.edit-dialog-no-animation) {
  animation: none !important;
  transition: none !important;
}

:deep(.edit-dialog-no-animation[data-state]) {
  animation: none !important;
  transition: none !important;
}

:deep(.edit-dialog-no-animation > *) {
  animation: none !important;
  transition: none !important;
}

</style> 