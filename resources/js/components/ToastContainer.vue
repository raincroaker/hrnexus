<script setup lang="ts">
import { useToast, type Toast } from '@/composables/useToast';
import { X } from 'lucide-vue-next';

const { toasts, removeToast } = useToast();
</script>

<template>
    <div
        class="fixed bottom-3 sm:bottom-4 right-3 sm:right-4 z-[100] space-y-2 w-[calc(100%-1.5rem)] sm:w-full max-w-xs pointer-events-none"
    >
        <TransitionGroup
            name="toast"
            tag="div"
            class="space-y-2"
        >
            <div
                v-for="toast in toasts"
                :key="toast.id"
                :class="[
                    'px-3 sm:px-4 py-2 sm:py-3 rounded-lg sm:rounded-xl shadow-lg border text-xs sm:text-sm pointer-events-auto',
                    'flex items-start justify-between gap-2',
                    toast.variant === 'success' ? 'bg-emerald-50 dark:bg-emerald-950 border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200' :
                    toast.variant === 'warning' ? 'bg-amber-50 dark:bg-amber-950 border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-200' :
                    toast.variant === 'error' ? 'bg-rose-50 dark:bg-rose-950 border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-200' :
                    'bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-700 text-gray-800 dark:text-gray-200'
                ]"
            >
                <p class="flex-1">{{ toast.text }}</p>
                <button
                    @click="removeToast(toast.id)"
                    class="flex-shrink-0 text-current opacity-70 hover:opacity-100 transition-opacity"
                    aria-label="Close"
                >
                    <X :size="16" />
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
    transition: all 0.3s ease;
}

.toast-enter-from {
    opacity: 0;
    transform: translateX(100%);
}

.toast-leave-to {
    opacity: 0;
    transform: translateX(100%);
}

.toast-move {
    transition: transform 0.3s ease;
}
</style>

