<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Calendar',
        href: 'calendar',
    },
];
</script>

<template>
    <Head title="Chats" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="p-4"
        >
        Test
            <!-- Write Code Here -->
        </div>
    </AppLayout>
</template>
