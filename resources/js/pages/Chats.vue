<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import ChatList from '@/components/ChatList.vue';
import { Head } from '@inertiajs/vue3';

interface PageProps {
    auth?: { user?: unknown };
}

const props = defineProps<PageProps>();
const isAuthenticated = !!props.auth?.user;
</script>

<template>
    <Head title="Chats" />
    <AppLayout>
        <div class="flex flex-col gap-4 p-4 w-full max-w-3xl mx-auto">
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold">Chats</h1>
            </div>
            <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border p-2">
                <ChatList :is-authenticated="isAuthenticated" />
            </div>
        </div>
    </AppLayout>
</template>
