<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { IdCard, ClipboardCheck, LayoutGrid, MessageSquareText, CalendarDays, FolderClosed } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

interface NavGroup {
    label: string;
    items: NavItem[];
}

const navGroups: NavGroup[] = [
    {
        label: 'Overview',
        items: [
            {
                title: 'Dashboard',
                href: dashboard(),
                icon: LayoutGrid,
            },
            {
                title: 'Attendance',
                href: '/attendance',
                icon: ClipboardCheck,
            },
            {
                title: 'Calendar',
                href: '/calendar',
                icon: CalendarDays,
            },
            {
                title: 'Chats',
                href: '/chats',
                icon: MessageSquareText,
            },
        ],
    },
    {
        label: 'Management',
        items: [
            {
                title: 'Employees',
                href: '/employees',
                icon: IdCard,
            },
            {
                title: 'Documents',
                href: '/documents',
                icon: FolderClosed,
            },
        ],
    },
];


</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :groups="navGroups" />
        </SidebarContent>

        <SidebarFooter>
            <!-- <NavFooter :items="footerNavItems" /> -->
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
