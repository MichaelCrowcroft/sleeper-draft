<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Conversation from '@/components/Conversation.vue';
import ChatTitleUpdater from '@/components/ChatTitleUpdater.vue';
import SidebarTitleUpdater from '@/components/SidebarTitleUpdater.vue';
import { Head } from '@inertiajs/vue3';

type Message = {
    id?: number;
    type: 'response' | 'error' | 'prompt';
    content: string;
};

interface ChatModel {
    id: number;
    title: string;
    messages?: Message[];
}

interface PageProps {
    chat: ChatModel | null;
    auth?: { user?: unknown };
}

const props = defineProps<PageProps>();
const isAuthenticated = !!props.auth?.user;
</script>

<template>
    <Head :title="props.chat ? (props.chat.title || 'Chat') : 'New Chat'" />
    <AppLayout>
        <div class="flex">
            <Conversation :initial-messages="props.chat?.messages ?? []" :chat-id="props.chat?.id ?? null" />
        </div>
        <ChatTitleUpdater v-if="props.chat?.id" :chat-id="props.chat.id" :current-title="props.chat?.title || 'Untitled'" />
        <SidebarTitleUpdater v-if="props.chat?.id" :chat-id="props.chat.id" />
    </AppLayout>

</template>
