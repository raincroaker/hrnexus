<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Documents',
        href: 'documents',
    },
];
</script>

<template>
    <Head title="Documents" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="border rounded-b-sm h-full m-1">
            Test
            <!-- Write Code Here -->
        </div>
    </AppLayout>
</template>
