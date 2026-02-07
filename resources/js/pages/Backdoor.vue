<script setup lang="ts">
import { ref, computed, watch, withDefaults } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Head } from '@inertiajs/vue3'
import { type BreadcrumbItem } from '@/types'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import {
  Pagination,
  PaginationContent,
  PaginationItem,
  PaginationNext,
  PaginationPrevious,
} from '@/components/ui/pagination'
import { Pencil, FileText, Plus, Loader2, X, Tag, ChevronDown, CheckCircle, XCircle } from 'lucide-vue-next'
import { ScrollArea } from '@/components/ui/scroll-area'
import api from '@/lib/axios'
import { toast } from 'vue-sonner'
import { router } from '@inertiajs/vue3'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'

interface Document {
  id: number
  file_name: string
  stored_name: string
  mime_type: string
  size: number
  description: string | null
  accessibility: string
  status: string
  user: {
    id: number
    name: string
    email: string
  } | null
  department: {
    id: number
    name: string
    code: string
  } | null
  tags: Array<{
    id: number
    name: string
  }>
  reviewer: {
    id: number
    name: string
    email: string
  } | null
  reviewed_at: string | null
  review_message: string | null
  deleted_at: string | null
  deleted_by_user: {
    id: number
    name: string
    email: string
  } | null
  restored_at: string | null
  restored_by: number | null
  content: string | null
  created_at: string
  updated_at: string
}

interface Department {
  id: number
  name: string
  code: string
}

interface Tag {
  id: number
  name: string
}

interface Props {
  documents: Document[]
  tags?: Tag[]
  departments?: Department[]
}

const props = withDefaults(defineProps<Props>(), {
  tags: () => [],
  departments: () => [],
})

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Backdoor',
    href: '/backdoor',
  },
]

// Create a local reactive copy of documents
const documents = ref<Document[]>([...props.documents])

// Watch for prop changes and update local copy
watch(() => props.documents, (newDocs) => {
  documents.value = [...newDocs]
}, { deep: true })

const searchQuery = ref('')
const currentPage = ref(1)
const itemsPerPage = 20

const filteredDocuments = computed(() => {
  if (!searchQuery.value.trim()) {
    return documents.value
  }

  const query = searchQuery.value.toLowerCase()
  return documents.value.filter((doc) => {
    return (
      doc.file_name.toLowerCase().includes(query) ||
      doc.description?.toLowerCase().includes(query) ||
      doc.user?.name.toLowerCase().includes(query) ||
      doc.user?.email.toLowerCase().includes(query) ||
      doc.department?.name.toLowerCase().includes(query) ||
      doc.tags.some((tag) => tag.name.toLowerCase().includes(query))
    )
  })
})

const totalPages = computed(() => Math.ceil(filteredDocuments.value.length / itemsPerPage))

const paginatedDocuments = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage
  const end = start + itemsPerPage
  return filteredDocuments.value.slice(start, end)
})

const visiblePages = computed(() => {
  const pages = []
  const total = totalPages.value
  const current = currentPage.value

  if (total <= 3) {
    for (let i = 1; i <= total; i++) {
      pages.push(i)
    }
  } else {
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

// Reset to page 1 when search query changes
watch(searchQuery, () => {
  currentPage.value = 1
})

// Edit content dialog state
const editContentDialogOpen = ref(false)
const editingDocument = ref<Document | null>(null)
const contentValue = ref('')
const isUpdating = ref(false)

// Upload dialog state
const uploadDialogOpen = ref(false)
const uploadFile = ref<File | null>(null)
const uploadDescription = ref('')
const uploadContent = ref('')
const uploadAccess = ref<'Public' | 'Private' | 'Department'>('Department')
const uploadDepartment = ref<number | null>(null)
const uploadTags = ref<number[]>([])
const uploadNewTag = ref('')
const isUploading = ref(false)

const openEditDialog = (doc: Document) => {
  editingDocument.value = doc
  contentValue.value = doc.content || ''
  editContentDialogOpen.value = true
}

const closeEditDialog = () => {
  editContentDialogOpen.value = false
  editingDocument.value = null
  contentValue.value = ''
}

const updateContent = async () => {
  if (!editingDocument.value) return

  isUpdating.value = true
  try {
    await api.put(`/documents/${editingDocument.value.id}/content`, {
      content: contentValue.value,
    })

    // Update the document in the local array
    const docIndex = documents.value.findIndex((d) => d.id === editingDocument.value!.id)
    if (docIndex !== -1) {
      documents.value[docIndex].content = contentValue.value
      documents.value[docIndex].updated_at = new Date().toISOString()
    }

    toast.success('Content updated successfully')
    closeEditDialog()
  } catch (error: any) {
    console.error('Error updating content:', error)
    toast.error(error.response?.data?.message || 'Failed to update content. Please try again.')
  } finally {
    isUpdating.value = false
  }
}

const formatFileSize = (bytes: number): string => {
  if (bytes === 0) return '0 Bytes'
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i]
}

const formatDate = (dateString: string | null): string => {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleString()
}

const getAccessibilityBadge = (accessibility: string) => {
  switch (accessibility) {
    case 'public':
      return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
    case 'departmental':
      return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
    case 'private':
      return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
    default:
      return 'bg-gray-100 text-gray-700 dark:bg-gray-900/30 dark:text-gray-400'
  }
}

const getStatusBadge = (status: string) => {
  switch (status) {
    case 'approved':
      return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
    case 'pending':
      return 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400'
    case 'rejected':
      return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
    default:
      return 'bg-gray-100 text-gray-700 dark:bg-gray-900/30 dark:text-gray-400'
  }
}

// Open upload dialog
const openUploadDialog = () => {
  uploadDialogOpen.value = true
}

// Close upload dialog
const closeUploadDialog = () => {
  uploadDialogOpen.value = false
  uploadFile.value = null
  uploadDescription.value = ''
  uploadContent.value = ''
  uploadAccess.value = 'Department'
  uploadDepartment.value = null
  uploadTags.value = []
  uploadNewTag.value = ''
  
  // Reset file input
  const fileInput = document.getElementById('upload-file') as HTMLInputElement
  if (fileInput) {
    fileInput.value = ''
  }
}

// Handle file input change
const onFileChange = (e: Event) => {
  const target = e.target as HTMLInputElement
  uploadFile.value = target.files?.[0] || null
}

// Add tag to upload form
const addUploadTag = () => {
  const t = uploadNewTag.value.trim()
  if (!t) return
  
  // Find tag by name
  const existingTag = props.tags.find((tag) => tag.name.toLowerCase() === t.toLowerCase())
  if (existingTag && !uploadTags.value.includes(existingTag.id)) {
    uploadTags.value = [...uploadTags.value, existingTag.id]
  }
  uploadNewTag.value = ''
}

// Remove tag from upload form
const removeUploadTag = (tagId: number) => {
  uploadTags.value = uploadTags.value.filter((id) => id !== tagId)
}

// Handle upload submission
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

  if (!uploadDepartment.value) {
    toast.error('Please select a department')
    return
  }

  isUploading.value = true

  try {
    const formData = new FormData()
    formData.append('file', uploadFile.value)
    formData.append('description', uploadDescription.value || '')
    formData.append('content', uploadContent.value || '')
    formData.append('accessibility', uploadAccess.value.toLowerCase())
    formData.append('department_id', uploadDepartment.value.toString())

    // Add tags
    uploadTags.value.forEach((tagId) => {
      formData.append('tags[]', tagId.toString())
    })

    // TODO: Update this endpoint once backend is ready
    const response = await api.post('/backdoor/documents', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    })

    toast.success(response.data?.message || 'File uploaded successfully')
    
    // Reset form
    closeUploadDialog()

    // Reload page to get updated documents
    router.reload({
      only: ['documents'],
      onSuccess: () => {
        // Reset to first page
        currentPage.value = 1
      },
    })
  } catch (error: any) {
    console.error('Upload error:', error)
    
    if (error.response?.status === 422) {
      // Validation errors
      const errors = error.response.data.errors || {}
      const firstError = Object.values(errors).flat()[0] as string
      toast.error(firstError || 'Validation error')
    } else {
      toast.error(error.response?.data?.message || 'Failed to upload file. Please try again.')
    }
  } finally {
    isUploading.value = false
  }
}

// Computed for accessibility options
const accessibilityOptions = ['Public', 'Private', 'Department']
</script>

<template>
  <Head title="Backdoor - All Documents" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4 bg-gray-50/30 dark:bg-black/25">
      <Card class="bg-background border border-sidebar-border/70 dark:border-sidebar-border shadow-sm">
        <CardHeader>
          <div class="flex items-center justify-between">
            <div>
              <CardTitle class="text-2xl font-bold">Backdoor - All Documents</CardTitle>
              <p class="text-sm text-muted-foreground mt-1">
                Complete list of all documents including soft-deleted ones ({{ filteredDocuments.length }} total)
                <span v-if="totalPages > 1" class="ml-2">
                  - Page {{ currentPage }} of {{ totalPages }}
                </span>
              </p>
            </div>
            <Button @click="openUploadDialog" class="flex items-center gap-2">
              <Plus class="h-4 w-4" />
              Upload Document
            </Button>
          </div>
        </CardHeader>
        <CardContent>
          <!-- Search -->
          <div class="mb-4">
            <Input
              v-model="searchQuery"
              type="text"
              placeholder="Search by file name, description, uploader, department, or tags..."
              class="w-full"
            />
          </div>

          <!-- Table -->
          <div class="rounded-lg border border-sidebar-border/50 overflow-hidden">
            <div class="overflow-x-auto">
              <table class="w-full">
                <thead class="bg-muted/50">
                  <tr class="border-b border-sidebar-border/50">
                    <th class="text-left p-3 text-xs font-semibold text-foreground">ID</th>
                    <th class="text-left p-3 text-xs font-semibold text-foreground">File Name</th>
                    <th class="text-left p-3 text-xs font-semibold text-foreground">Uploader</th>
                    <th class="text-left p-3 text-xs font-semibold text-foreground">Department</th>
                    <th class="text-left p-3 text-xs font-semibold text-foreground">Accessibility</th>
                    <th class="text-left p-3 text-xs font-semibold text-foreground">Status</th>
                    <th class="text-left p-3 text-xs font-semibold text-foreground">Size</th>
                    <th class="text-left p-3 text-xs font-semibold text-foreground">Tags</th>
                    <th class="text-left p-3 text-xs font-semibold text-foreground">Deleted</th>
                    <th class="text-left p-3 text-xs font-semibold text-foreground">Created At</th>
                    <th class="text-left p-3 text-xs font-semibold text-foreground">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="doc in paginatedDocuments"
                    :key="doc.id"
                    class="border-b border-sidebar-border/50 last:border-0 hover:bg-muted/30 transition-colors"
                    :class="doc.deleted_at ? 'bg-red-50/50 dark:bg-red-950/10' : ''"
                  >
                    <td class="p-3 text-xs font-medium text-foreground">{{ doc.id }}</td>
                    <td class="p-3 text-xs text-foreground">
                      <div class="flex flex-col gap-1">
                        <span class="font-medium">{{ doc.file_name }}</span>
                        <span v-if="doc.description" class="text-muted-foreground text-[10px] line-clamp-1">
                          {{ doc.description }}
                        </span>
                      </div>
                    </td>
                    <td class="p-3 text-xs text-muted-foreground">
                      <div class="flex flex-col">
                        <span>{{ doc.user?.name || '-' }}</span>
                        <span class="text-[10px]">{{ doc.user?.email || '' }}</span>
                      </div>
                    </td>
                    <td class="p-3 text-xs text-muted-foreground">
                      {{ doc.department?.name || '-' }}
                    </td>
                    <td class="p-3">
                      <Badge :class="getAccessibilityBadge(doc.accessibility)" class="text-[10px] capitalize">
                        {{ doc.accessibility }}
                      </Badge>
                    </td>
                    <td class="p-3">
                      <Badge :class="getStatusBadge(doc.status)" class="text-[10px] capitalize">
                        {{ doc.status }}
                      </Badge>
                    </td>
                    <td class="p-3 text-xs text-muted-foreground">
                      {{ formatFileSize(doc.size) }}
                    </td>
                    <td class="p-3">
                      <div class="flex flex-wrap gap-1">
                        <Badge
                          v-for="tag in doc.tags"
                          :key="tag.id"
                          variant="outline"
                          class="text-[10px]"
                        >
                          {{ tag.name }}
                        </Badge>
                        <span v-if="doc.tags.length === 0" class="text-xs text-muted-foreground">-</span>
                      </div>
                    </td>
                    <td class="p-3 text-xs">
                      <div v-if="doc.deleted_at" class="flex flex-col gap-0.5">
                        <span class="text-red-600 dark:text-red-400 font-medium">Yes</span>
                        <span class="text-[10px] text-muted-foreground">
                          {{ formatDate(doc.deleted_at) }}
                        </span>
                        <span v-if="doc.deleted_by_user" class="text-[10px] text-muted-foreground">
                          by {{ doc.deleted_by_user.name }}
                        </span>
                        <span v-if="doc.restored_at" class="text-[10px] text-emerald-600 dark:text-emerald-400">
                          Restored: {{ formatDate(doc.restored_at) }}
                        </span>
                      </div>
                      <span v-else class="text-muted-foreground">No</span>
                    </td>
                    <td class="p-3 text-xs text-muted-foreground">
                      {{ formatDate(doc.created_at) }}
                    </td>
                    <td class="p-3">
                      <Button
                        @click="openEditDialog(doc)"
                        variant="outline"
                        size="sm"
                        class="h-7 text-xs"
                      >
                        <Pencil class="h-3 w-3 mr-1" />
                        Edit Content
                      </Button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Pagination -->
          <div v-if="totalPages > 1" class="mt-4 flex items-center justify-between">
            <p class="text-xs text-muted-foreground">
              Showing {{ ((currentPage - 1) * itemsPerPage) + 1 }} to
              {{ Math.min(currentPage * itemsPerPage, filteredDocuments.length) }} of
              {{ filteredDocuments.length }} documents
            </p>
            <div class="flex justify-end">
              <Pagination
                :total="totalPages"
                :items-per-page="itemsPerPage"
                :sibling-count="1"
                show-edges
                :default-page="1"
                v-model:page="currentPage"
              >
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

          <!-- Empty State -->
          <div v-if="filteredDocuments.length === 0" class="flex flex-col items-center justify-center py-12 text-center">
            <p class="text-sm font-medium text-foreground mb-1">No documents found</p>
            <p class="text-xs text-muted-foreground">
              {{ searchQuery ? 'Try adjusting your search query' : 'No documents in the system' }}
            </p>
          </div>
        </CardContent>
      </Card>

      <!-- Edit Content Dialog -->
      <Dialog v-model:open="editContentDialogOpen">
        <DialogContent class="sm:max-w-[600px] max-h-[90vh] flex flex-col min-h-0 overflow-hidden">
          <DialogHeader class="shrink-0">
            <DialogTitle>Edit Document Content</DialogTitle>
            <DialogDescription>
              Edit the content for document: <strong>{{ editingDocument?.file_name }}</strong>
            </DialogDescription>
          </DialogHeader>
          <ScrollArea class="flex-1 min-h-0 max-h-[calc(90vh-180px)]">
            <div class="pr-4 py-4">
              <div class="space-y-4">
                <div>
                  <Label for="content">Content</Label>
                  <Textarea
                    id="content"
                    v-model="contentValue"
                    placeholder="Enter document content..."
                    class="font-mono text-sm max-h-[400px] overflow-y-auto resize-none"
                    rows="12"
                  />
                  <p class="text-xs text-muted-foreground mt-1">
                    This content field is used for semantic search and AI features.
                  </p>
                </div>
              </div>
            </div>
          </ScrollArea>
          <DialogFooter class="shrink-0 border-t pt-4">
            <Button variant="outline" @click="closeEditDialog" :disabled="isUpdating">
              Cancel
            </Button>
            <Button @click="updateContent" :disabled="isUpdating">
              <span v-if="isUpdating">Updating...</span>
              <span v-else>Update Content</span>
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <!-- Upload Dialog -->
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
            class="flex-1 overflow-y-auto max-h-[calc(90vh-200px)] min-h-0 scrollbar-auto-hide scrollbar-hidden">
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

              <!-- Description -->
              <div>
                <Label for="upload-desc" class="text-xs sm:text-sm font-medium block mb-1.5 sm:mb-2 dark:text-neutral-100">
                  Description
                </Label>
                <Textarea
                  id="upload-desc"
                  v-model="uploadDescription"
                  placeholder="Enter a short description (optional)..."
                  class="w-full text-xs sm:text-sm dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-100"
                  rows="3"
                />
              </div>

              <!-- Content Textarea -->
              <div>
                <Label for="upload-content" class="text-xs sm:text-sm font-medium block mb-1.5 sm:mb-2 dark:text-neutral-100">
                  Content <span class="text-xs text-muted-foreground">(for semantic search)</span>
                </Label>
                <Textarea
                  id="upload-content"
                  v-model="uploadContent"
                  placeholder="Enter document content for semantic search and AI features..."
                  class="w-full text-xs sm:text-sm font-mono max-h-[300px] overflow-y-auto resize-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-100"
                  rows="10"
                />
                <p class="text-xs text-muted-foreground mt-1">
                  This content field is used for semantic search and AI features. You can paste extracted text here.
                </p>
              </div>

              <div :class="['grid gap-3 sm:gap-4', 'grid-cols-1 sm:grid-cols-2']">
                <div>
                  <Label class="text-xs sm:text-sm font-medium block mb-1.5 sm:mb-2">
                    Department <span class="text-red-500">*</span>
                  </Label>
                  <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                      <Button variant="outline"
                        class="w-full flex items-center justify-between gap-4 px-3 sm:px-8 py-2 sm:py-2.5 rounded-lg border bg-white text-xs sm:text-sm hover:border-blue-300">
                        <span class="truncate">{{ 
                          uploadDepartment 
                            ? (props.departments.find((d) => d.id === uploadDepartment)?.name || 'Select Department')
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
                      <DropdownMenuItem v-for="a in accessibilityOptions" :key="a"
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
                    {{ props.tags.find((t) => t.id === tagId)?.name || 'Unknown' }}
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
                        v-for="tag in props.tags.filter((t) => !uploadTags.includes(t.id))" 
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
                @click="closeUploadDialog"
                :disabled="isUploading">
                Cancel
              </Button>
              <Button size="sm" class="flex-1 sm:flex-none bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white" 
                @click="handleUploadSubmit" 
                :disabled="isUploading || !uploadFile || !uploadDepartment">
                <Loader2 v-if="isUploading" class="w-4 h-4 mr-2 animate-spin" />
                {{ isUploading ? 'Uploading...' : 'Upload' }}
              </Button>
            </div>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  </AppLayout>
</template>

