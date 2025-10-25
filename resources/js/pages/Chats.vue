<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { InputGroupButton } from '@/components/ui/input-group';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import {
    Paperclip,
    Pin,
    Search,
    SendHorizontal,
    Settings,
    SquarePen,
    Users,
} from 'lucide-vue-next';
import { ref } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Chats', href: 'chats' }];

const textareaRef = ref<HTMLTextAreaElement>();

const autoResize = () => {
    if (textareaRef.value) {
        textareaRef.value.style.height = 'auto';
        textareaRef.value.style.height = `${Math.min(textareaRef.value.scrollHeight, 128)}px`;
    }
};
</script>

<template>
    <Head title="Chats" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="m-1 flex flex-1 rounded-b-sm border">
            <!-- Sidebar -->
            <div
                class="hidden h-[calc(100vh-5rem)] w-[340px] flex-col border-r p-4 md:flex"
            >
                <!-- Header -->
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-2xl font-semibold">Chats</h2>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="h-8 w-8 text-muted-foreground hover:text-foreground"
                    >
                        <SquarePen class="size-5" />
                    </Button>
                </div>

                <!-- Search -->
                <div class="relative mb-4">
                    <span
                        class="absolute inset-y-0 left-0 flex items-center pl-2"
                    >
                        <Search class="size-4 text-muted-foreground" />
                    </span>
                    <Input
                        type="text"
                        placeholder="Search chats..."
                        class="pl-8"
                    />
                </div>

                <!-- Scrollable Chat List -->
                <ScrollArea class="flex-1">
                    <div class="space-y-4">
                        <!-- Pinned Section -->
                        <div class="text-sm font-medium text-muted-foreground">
                            Pinned
                        </div>

                        <div class="space-y-2">
                            <div
                                class="w-full cursor-pointer rounded-md border px-4 py-2 transition hover:bg-accent hover:text-accent-foreground"
                            >
                                <div
                                    class="flex items-center justify-between text-sm font-medium"
                                >
                                    <span class="truncate text-[15px]"
                                        >Group Chat 1</span
                                    >
                                    <span class="text-xs text-muted-foreground"
                                        >10/25</span
                                    >
                                </div>

                                <div class="flex items-center justify-between">
                                    <p
                                        class="flex-1 truncate text-sm text-muted-foreground"
                                    >
                                        John: Hello how are you people I miss
                                        you blablabla?
                                    </p>
                                </div>
                            </div>
                        </div>

                        <Separator class="my-4" />

                        <!-- Recent Section -->
                        <div class="text-sm font-medium text-muted-foreground">
                            Recent
                        </div>

                        <div class="space-y-2">
                            <div
                                v-for="n in 3"
                                :key="n"
                                class="w-full cursor-pointer rounded-md border px-4 py-2 transition hover:bg-accent hover:text-accent-foreground"
                            >
                                <div
                                    class="flex items-center justify-between text-sm font-medium"
                                >
                                    <span class="truncate text-[15px]"
                                        >Group Chat {{ n }}</span
                                    >
                                    <span class="text-xs text-muted-foreground"
                                        >11/25</span
                                    >
                                </div>

                                <div class="flex items-center justify-between">
                                    <p
                                        class="flex-1 truncate text-sm text-muted-foreground"
                                    >
                                        Jane: Hello how are you people I miss
                                        you blablabla?
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </ScrollArea>
            </div>

            <!-- Main Chat Area -->
            <div class="flex flex-1 flex-col">
                <!-- Header -->
                <div
                    class="flex items-center border-b px-6 py-4 pr-2"
                >
                    <div class="w-0 flex-1 overflow-hidden">
                        <h2 class="truncate text-2xl font-semibold">
                            Group Chat 1
                        </h2>
                    </div>

                    <div
                        class="ml-4 flex items-center gap-2 text-muted-foreground flex-shrink-0"
                    >
                        <Button
                            variant="ghost"
                            size="icon"
                            class="h-10 w-10 text-muted-foreground hover:text-foreground"
                        >
                            <Users class="size-6" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="h-10 w-10 text-muted-foreground hover:text-foreground"
                        >
                            <Pin class="size-6" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="h-10 w-10 text-muted-foreground hover:text-foreground"
                        >
                            <Search class="size-6" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="h-10 w-10 text-muted-foreground hover:text-foreground"
                        >
                            <Paperclip class="size-6" />
                        </Button>
                        <Separator orientation="vertical" class="!h-4" />
                        <Button
                            variant="ghost"
                            size="icon"
                            class="h-10 w-10 text-muted-foreground hover:text-foreground"
                        >
                            <Settings class="size-6" />
                        </Button>
                    </div>
                </div>

                <!-- Chat Area -->
                <div class="flex flex-1 flex-col">
                    <div class="flex flex-1 flex-col">
                        <!-- Messages Area -->
                        <div class="flex-1 p-6 text-sm text-muted-foreground">
                            <div>
                                <!-- Messages will go here -->
                            </div>
                        </div>

                        <!-- Fixed Input Area at Bottom -->
                        <div class="border-t bg-background p-4">
                            <div
                                class="flex w-full flex-col rounded-md border bg-background shadow-sm transition-all focus-within:mb-1 focus-within:box-border focus-within:border-b-4 focus-within:border-b-primary"
                            >
                                <textarea
                                    ref="textareaRef"
                                    class="min-h-16 max-h-60 w-full resize-none rounded-md bg-transparent px-3 py-2.5 text-base transition-[color,box-shadow] outline-none focus-visible:ring-0 md:text-sm"
                                    placeholder="Type a message..."
                                    rows="1"
                                    @input="autoResize"
                                ></textarea>
                                <div
                                    class="flex items-center justify-end gap-2 border-t px-2 py-1"
                                >
                                    <!-- <InputGroupButton
                                        variant="ghost"
                                        size="xs"
                                        class="h-8 w-8 text-muted-foreground hover:text-foreground"
                                    >
                                        <Baseline class="size-5" />
                                    </InputGroupButton>
                                    <InputGroupButton
                                        variant="ghost"
                                        size="xs"
                                        class="h-8 w-8 text-muted-foreground hover:text-foreground"
                                    >
                                        <Smile class="size-5" />
                                    </InputGroupButton> -->
                                    <InputGroupButton
                                        variant="ghost"
                                        size="xs"
                                        class="h-10 w-10 text-muted-foreground hover:text-foreground"
                                    >
                                        <Paperclip class="size-6" />
                                    </InputGroupButton>
                                    <Separator
                                        orientation="vertical"
                                        class="!h-4"
                                    />
                                    <InputGroupButton
                                        size="sm"
                                        variant="ghost"
                                        class="h-10 w-10 rounded-sm"
                                    >
                                        <SendHorizontal class="size-6" />
                                    </InputGroupButton>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
