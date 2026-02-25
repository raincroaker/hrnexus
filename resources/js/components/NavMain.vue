<script setup lang="ts">
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { normalizeUrlPath, toUrl, urlIsActive } from '@/lib/utils';
import { type NavItem } from '@/types';
import { Link, router, usePage, type InertiaLinkProps } from '@inertiajs/vue3';

interface NavGroup {
    label: string;
    items: NavItem[];
}

defineProps<{
    groups: NavGroup[];
}>();

const page = usePage();

function handleNavClick(e: MouseEvent, href: NonNullable<InertiaLinkProps['href']>) {
    const current = normalizeUrlPath(String(page.url ?? ''));
    const target = normalizeUrlPath(toUrl(href));

    if (!target) {
        return;
    }

    if (current === target) {
        e.preventDefault();
        router.reload();
    }
}
</script>

<template>
    <SidebarGroup v-for="group in groups" :key="group.label" class="px-2 py-0">
        <SidebarGroupLabel>{{ group.label }}</SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in group.items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    :is-active="urlIsActive(item.href, page.url)"
                    :tooltip="item.title"
                >
                    <Link
                        :href="item.href"
                        :preserve-state="false"
                        :preserve-scroll="false"
                        :prefetch="false"
                        @click="handleNavClick($event, item.href)"
                    >
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
