<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref, onMounted, onBeforeUnmount, watch, computed } from 'vue';
import { useSidebar } from '@/components/ui/sidebar';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';

interface ChatItem {
    id: number;
    title: string;
    created_at: string;
    updated_at: string;
}

interface Props {
    currentChatId?: number | null;
    isAuthenticated: boolean;
}

const props = defineProps<Props>();

let chatCache: ChatItem[] = [];
let lastFetchTime = 0;
const CACHE_DURATION = 5000;

const chats = ref<ChatItem[]>(chatCache);
const loading = ref(false);
const editingChatId = ref<number | null>(null);
const editTitle = ref('');
const editInputRef = ref<HTMLInputElement | null>(null);
const lastCurrentChatId = ref<number | null>(undefined as unknown as number | null);
const { state } = useSidebar();

const editForm = useForm({ title: '' });
const createForm = useForm({});

const fetchChats = async (force = false) => {
    if (!props.isAuthenticated) {
        chats.value = [];
        chatCache = [];
        return;
    }
    const now = Date.now();
    if (!force && chatCache.length > 0 && now - lastFetchTime < CACHE_DURATION) {
        chats.value = chatCache;
        return;
    }
    try {
        loading.value = true;
        const response = await fetch(route('api.chats.index'));
        const data = (await response.json()) as ChatItem[];
        chatCache = data;
        lastFetchTime = now;
        chats.value = data;
    } catch {
        // ignore
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchChats();
    const handleTitleUpdate = (event: Event) => {
        const custom = event as CustomEvent<{ chatId: number; newTitle: string }>;
        const { chatId, newTitle } = custom.detail;
        chats.value = chats.value.map((c) => (c.id === chatId ? { ...c, title: newTitle } : c));
        chatCache = chatCache.map((c) => (c.id === chatId ? { ...c, title: newTitle } : c));
    };
    window.addEventListener('chatTitleUpdated', handleTitleUpdate as EventListener);
    onBeforeUnmount(() => {
        window.removeEventListener('chatTitleUpdated', handleTitleUpdate as EventListener);
    });
});

watch(
    () => props.currentChatId,
    (newId) => {
        if (newId && newId !== lastCurrentChatId.value) {
            lastCurrentChatId.value = newId;
            if (!chats.value.some((c) => c.id === newId)) {
                fetchChats(true);
            }
        }
    },
);

const handleNewChat = () => {
    if (!props.isAuthenticated) {
        router.visit('/login');
    } else {
        createForm.post(route('chat.store'));
    }
};

const handleDeleteChat = (chatId: number, event?: MouseEvent) => {
    event?.preventDefault();
    event?.stopPropagation();
    router.delete(route('chat.destroy', chatId), {
        onBefore: () => {
            chats.value = chats.value.filter((c) => c.id !== chatId);
            chatCache = chatCache.filter((c) => c.id !== chatId);
        },
        onError: () => fetchChats(true),
        onFinish: () => setTimeout(() => fetchChats(true), 100),
        preserveScroll: true,
    });
};

const startEditing = (chatId: number, currentTitle: string, event?: MouseEvent) => {
    event?.preventDefault();
    event?.stopPropagation();
    editingChatId.value = chatId;
    editTitle.value = currentTitle || '';
    setTimeout(() => {
        editInputRef.value?.focus();
        editInputRef.value?.select();
    }, 10);
};

const cancelEditing = () => {
    editingChatId.value = null;
    editTitle.value = '';
};

const saveTitle = (chatId: number) => {
    if (!editTitle.value.trim() || editForm.processing) return;
    editForm.title = editTitle.value.trim();
    editForm.patch(route('chat.update', chatId), {
        onSuccess: () => {
            chats.value = chats.value.map((c) => (c.id === chatId ? { ...c, title: editTitle.value.trim() } : c));
            chatCache = chatCache.map((c) => (c.id === chatId ? { ...c, title: editTitle.value.trim() } : c));
            cancelEditing();
        },
        onError: () => fetchChats(true),
        preserveState: true,
        preserveScroll: true,
    });
};

const isCollapsed = computed(() => state.value === 'collapsed');
</script>

<template>
    <div v-if="props.isAuthenticated">
        <div v-if="!isCollapsed" class="px-2 py-2 text-xs font-semibold">Chats</div>
        <div class="space-y-1">
            <div v-if="loading && chats.length === 0" class="px-2 py-1 text-xs" v-show="!isCollapsed">Loading...</div>
            <div v-else-if="chats.length === 0" class="px-2 py-1 text-xs" v-show="!isCollapsed">No chats yet</div>
            <template v-else>
                <template v-for="chat in chats" :key="chat.id">
                    <Link :href="route('chat.show', chat.id)" class="group flex items-center gap-2 rounded px-2 py-1.5 hover:bg-accent">
                        <div class="flex-1 min-w-0">
                            <template v-if="editingChatId === chat.id">
                                <div class="flex items-center gap-1">
                                    <Input ref="editInputRef" v-model="editTitle" class="h-7 text-xs" @keydown.enter.prevent="saveTitle(chat.id)" @keydown.esc.prevent="cancelEditing" />
                                    <Button size="icon" variant="ghost" class="h-7 w-7" :disabled="editForm.processing" @click.stop.prevent="saveTitle(chat.id)">✔</Button>
                                    <Button size="icon" variant="ghost" class="h-7 w-7" @click.stop.prevent="cancelEditing">✕</Button>
                                </div>
                            </template>
                            <template v-else>
                                <div class="truncate text-sm">{{ chat.title || 'Untitled Chat' }}</div>
                                <div class="pointer-events-none absolute inset-y-0 right-0 w-10 bg-gradient-to-l from-background to-transparent" />
                            </template>
                        </div>
                        <div class="ml-auto hidden gap-1 group-hover:flex" v-if="!isCollapsed">
                            <Button size="icon" variant="ghost" class="h-7 w-7" @click.stop.prevent="startEditing(chat.id, chat.title || 'Untitled Chat', $event)">✎</Button>
                            <Button size="icon" variant="ghost" class="h-7 w-7" @click.stop.prevent="handleDeleteChat(chat.id, $event)">🗑</Button>
                        </div>
                    </Link>
                </template>
            </template>
            <div class="px-2 pt-1">
                <Button :disabled="createForm.processing" class="w-full" size="sm" @click="handleNewChat">New chat</Button>
            </div>
        </div>
    </div>
    <div v-else />
</template>
