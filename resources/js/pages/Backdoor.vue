<script setup lang="ts">
import { ref, computed, watch } from 'vue'
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
import { Pencil } from 'lucide-vue-next'
import api from '@/lib/axios'
import { toast } from 'vue-sonner'

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

interface Props {
  documents: Document[]
}

const props = defineProps<Props>()

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
        <DialogContent class="sm:max-w-[600px]">
          <DialogHeader>
            <DialogTitle>Edit Document Content</DialogTitle>
            <DialogDescription>
              Edit the content for document: <strong>{{ editingDocument?.file_name }}</strong>
            </DialogDescription>
          </DialogHeader>
          <div class="py-4 space-y-4">
            <div>
              <Label for="content">Content</Label>
              <Textarea
                id="content"
                v-model="contentValue"
                placeholder="Enter document content..."
                class="min-h-[300px] font-mono text-sm"
              />
              <p class="text-xs text-muted-foreground mt-1">
                This content field is used for semantic search and AI features.
              </p>
            </div>
          </div>
          <DialogFooter>
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
    </div>
  </AppLayout>
</template>

