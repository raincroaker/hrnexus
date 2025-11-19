import { ref } from 'vue';

export type ToastVariant = 'default' | 'success' | 'warning' | 'error';

export interface Toast {
    id: number;
    text: string;
    variant: ToastVariant;
}

// Shared state across all components
const toasts = ref<Toast[]>([]);

/**
 * Display a toast notification
 * @param text - Message to display
 * @param variant - Toast style variant
 * @param duration - Duration in milliseconds (default: 3000)
 */
export const showToast = (text: string, variant: ToastVariant = 'default', duration = 3000) => {
    const id = Date.now() + Math.random();
    toasts.value.push({ id, text, variant });
    
    setTimeout(() => {
        toasts.value = toasts.value.filter(t => t.id !== id);
    }, duration);
};

export const removeToast = (id: number) => {
    toasts.value = toasts.value.filter(t => t.id !== id);
};

/**
 * Composable to access toast state and functions
 */
export const useToast = () => {
    return {
        toasts,
        showToast,
        removeToast,
    };
};

