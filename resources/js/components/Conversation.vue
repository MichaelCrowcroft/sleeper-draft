<script setup lang="ts">
import { ref, computed, nextTick, onMounted, watch, onUpdated } from 'vue';
import Icon from '@/components/Icon.vue';
import StreamingIndicator from '@/components/StreamingIndicator.vue';
import { useStream } from '@laravel/stream-vue';

type Message = {
    id?: number;
    // legacy type for compatibility
    type?: 'response' | 'error' | 'prompt';
    // agentic fields
    role?: 'user' | 'assistant' | 'tool' | 'system' | 'thinking';
    name?: string | null;
    call_id?: string | null;
    content: string;
    content_json?: Record<string, any> | null;
    saved?: boolean;
};

interface Props {
    initialMessages?: Message[];
    streamId?: string;
    chatId?: number | null;
}

const props = withDefaults(defineProps<Props>(), {
    initialMessages: () => [],
    streamId: undefined,
    chatId: null,
});

const messages = ref<Message[]>([...props.initialMessages]);
const input = ref('');
const streamingData = ref('');
const toolExpanded = ref<Record<string, boolean>>({});
const thinkingExpanded = ref<Record<string, boolean>>({});
const alwaysShowThinking = ref(false);
const defaultExpandTools = ref(false);
const hideAgentSections = ref(false);
function generateStreamId(): string {
    try {
        const globalCrypto: any = (globalThis as any)?.crypto;
        if (globalCrypto?.randomUUID) {
            return globalCrypto.randomUUID();
        }
        if (globalCrypto?.getRandomValues) {
            const bytes: Uint8Array = globalCrypto.getRandomValues(new Uint8Array(16));
            bytes[6] = (bytes[6] & 0x0f) | 0x40; // version 4
            bytes[8] = (bytes[8] & 0x3f) | 0x80; // variant
            const toHex = (n: number) => n.toString(16).padStart(2, '0');
            const hex = Array.from(bytes, toHex).join('');
            return (
                hex.substring(0, 8) +
                '-' +
                hex.substring(8, 12) +
                '-' +
                hex.substring(12, 16) +
                '-' +
                hex.substring(16, 20) +
                '-' +
                hex.substring(20)
            );
        }
    } catch {}
    return `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`;
}
const localStreamId = ref(props.streamId || generateStreamId());

const streamUrl = computed(() => (props.chatId ? route('chat.show.stream', props.chatId) : route('chat.stream')));

const { isFetching, isStreaming, send } = useStream(streamUrl.value, {
    id: localStreamId.value,
    initialInput: {},
    json: true,
    credentials: 'same-origin',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    csrfToken: (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content,
    onData: (chunk: string) => {
        streamingData.value += chunk;
    },
    onFinish: () => {
        if (streamingData.value) {
            messages.value.push({ role: 'assistant', type: 'response', content: streamingData.value, saved: false });
            streamingData.value = '';
            nextTick(() => window.scrollTo({ top: document.body.scrollHeight }));
        }
    },
});

const canSend = computed(() => input.value.trim().length > 0 && !isFetching.value && !isStreaming.value);

const toolResultsByCallId = computed<Record<string, Message>>(() => {
    const map: Record<string, Message> = {};
    for (const m of messages.value) {
        if (m.role === 'tool' && m.call_id) {
            map[m.call_id] = m;
        }
    }
    return map;
});

const toolResultIdsToHide = computed<Set<number>>(() => {
    const set = new Set<number>();
    for (const m of messages.value) {
        if (m.role === 'assistant' && m.call_id && toolResultsByCallId.value[m.call_id]) {
            const res = toolResultsByCallId.value[m.call_id];
            if (res.id) set.add(res.id);
        }
    }
    return set;
});

function formatContent(message: Message): string {
    if (message.content_json) {
        try {
            return JSON.stringify(message.content_json, null, 2);
        } catch {}
    }
    // Fall back to raw content
    return message.content;
}

const postMessages = async () => {
    if (!canSend.value) return;
    const prompt = input.value.trim();
    input.value = '';
    const newMsg: Message = { role: 'user', type: 'prompt', content: prompt };
    const payload: Message[] = [
        ...messages.value,
        newMsg,
    ];
    messages.value.push({ ...newMsg, saved: false });

    await send({ messages: payload });
};

function highlightAll(container?: HTMLElement | null) {
    try {
        const root = container || (document.querySelector('#app') as HTMLElement | null) || document.body;
        const blocks = root?.querySelectorAll('pre code.language-json');
        const hl = (window as any)?.hljs;
        if (!blocks || !hl) return;
        blocks.forEach((el) => hl.highlightElement(el as HTMLElement));
    } catch {}
}

onMounted(() => highlightAll());
onUpdated(() => highlightAll());

// Preferences persistence (per chat)
function prefKey(key: string) {
    const id = props.chatId ? `chat:${props.chatId}` : 'chat:local';
    return `ff:${id}:${key}`;
}

function loadPreferences() {
    try {
        const show = localStorage.getItem(prefKey('showThinking'));
        if (show !== null) alwaysShowThinking.value = show === '1';
        const expand = localStorage.getItem(prefKey('expandTools'));
        if (expand !== null) defaultExpandTools.value = expand === '1';
        const compact = localStorage.getItem(prefKey('compact'));
        if (compact !== null) hideAgentSections.value = compact === '1';
        const toolMap = localStorage.getItem(prefKey('toolExpanded'));
        if (toolMap) toolExpanded.value = JSON.parse(toolMap);
        const thinkMap = localStorage.getItem(prefKey('thinkingExpanded'));
        if (thinkMap) thinkingExpanded.value = JSON.parse(thinkMap);
    } catch {}
}

onMounted(() => {
    loadPreferences();
});

watch(alwaysShowThinking, (val) => {
    try {
        localStorage.setItem(prefKey('showThinking'), val ? '1' : '0');
    } catch {}
});

watch(defaultExpandTools, (val) => {
    try {
        localStorage.setItem(prefKey('expandTools'), val ? '1' : '0');
        if (val) {
            // expand all known tool calls
            for (const m of messages.value) {
                if (m.role === 'assistant' && m.call_id) toolExpanded.value[m.call_id] = true;
            }
        }
    } catch {}
});

watch(hideAgentSections, (val) => {
    try {
        localStorage.setItem(prefKey('compact'), val ? '1' : '0');
    } catch {}
});

watch(toolExpanded, (val) => {
    try {
        localStorage.setItem(prefKey('toolExpanded'), JSON.stringify(val));
    } catch {}
}, { deep: true });

watch(thinkingExpanded, (val) => {
    try {
        localStorage.setItem(prefKey('thinkingExpanded'), JSON.stringify(val));
    } catch {}
}, { deep: true });
</script>

<template>
    <div class="mx-auto w-full max-w-3xl px-4">
        <div class="space-y-4 py-6">
            <div class="flex items-center gap-4 text-xs text-muted-foreground">
                <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                    <input type="checkbox" v-model="alwaysShowThinking" class="h-3 w-3" />
                    <span class="inline-flex items-center gap-1"><Icon name="brain" class="h-3 w-3" /> Always show thinking</span>
                </label>
                <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                    <input type="checkbox" v-model="defaultExpandTools" class="h-3 w-3" />
                    <span class="inline-flex items-center gap-1"><Icon name="wrench" class="h-3 w-3" /> Expand all tools</span>
                </label>
                <label class="inline-flex items-center gap-2 cursor-pointer select-none ml-auto">
                    <input type="checkbox" v-model="hideAgentSections" class="h-3 w-3" />
                    <span class="inline-flex items-center gap-1"><Icon name="eye-off" class="h-3 w-3" /> Compact mode</span>
                </label>
            </div>
            <template v-for="(message, index) in messages" :key="message.id ? `db-${message.id}` : `local-${index}-${message.content.substring(0, 10)}`">
                <!-- Thinking message (collapsed by default) -->
                <div v-if="!hideAgentSections && message.role === 'thinking'" class="rounded-lg border p-4 bg-muted/30">
                    <div class="flex items-center justify-between text-xs text-muted-foreground mb-1">
                        <span class="inline-flex items-center gap-1"><Icon name="brain" /> Assistant (thinking)</span>
                        <template v-if="!alwaysShowThinking">
                            <button class="text-muted-foreground hover:underline" @click="thinkingExpanded[`${message.id ?? index}`] = !thinkingExpanded[`${message.id ?? index}`]">
                                {{ thinkingExpanded[`${message.id ?? index}`] ? 'Hide' : 'Show' }}
                            </button>
                        </template>
                    </div>
                    <div v-if="alwaysShowThinking || thinkingExpanded[`${message.id ?? index}`]" class="whitespace-pre-wrap text-xs text-muted-foreground/90">
                        {{ message.content }}
                    </div>
                </div>

                <!-- Tool call with paired result -->
                <div v-else-if="!hideAgentSections && message.role === 'assistant' && message.call_id" class="rounded-lg border p-4 bg-accent/10">
                    <div class="flex items-center justify-between text-xs text-muted-foreground mb-1">
                        <span class="inline-flex items-center gap-1"><Icon name="wrench" /> Tool call: {{ message.name || 'Unknown tool' }}</span>
                        <button class="text-muted-foreground hover:underline" @click="toolExpanded[message.call_id!] = !toolExpanded[message.call_id!]">
                            {{ toolExpanded[message.call_id!] ? 'Hide' : 'Show' }}
                        </button>
                    </div>
                    <div v-if="toolExpanded[message.call_id!] || defaultExpandTools" class="mt-1">
                        <div class="text-xs text-muted-foreground">Arguments</div>
                        <pre class="mt-1 text-xs"><code class="language-json">{{ formatContent(message) }}</code></pre>
                        <template v-if="toolResultsByCallId[message.call_id!]">
                            <div class="mt-3 text-xs text-muted-foreground inline-flex items-center gap-1"><Icon name="check-circle" /> Result</div>
                            <pre class="mt-1 text-xs"><code class="language-json">{{ formatContent(toolResultsByCallId[message.call_id!]) }}</code></pre>
                        </template>
                    </div>
                </div>

                <!-- Tool result standalone (render only if no paired call rendered) -->
                <div v-else-if="!hideAgentSections && message.role === 'tool' && !(message.id && toolResultIdsToHide.has(message.id))" class="rounded-lg border p-4 bg-accent/5">
                    <div class="text-xs text-muted-foreground mb-1 inline-flex items-center gap-1"><Icon name="database" /> Tool result: {{ message.name || 'Tool' }}</div>
                    <pre class="text-xs"><code class="language-json">{{ formatContent(message) }}</code></pre>
                </div>

                <!-- Default message (user/assistant/error) -->
                <div v-else class="rounded-lg border p-4">
                    <div class="text-xs text-muted-foreground mb-1">{{ message.role === 'user' || message.type === 'prompt' ? 'You' : message.role === 'assistant' || message.type === 'response' ? (message.name ? message.name : 'Assistant') : message.type === 'error' ? 'Error' : 'Message' }}</div>
                    <div class="whitespace-pre-wrap">{{ message.content }}</div>
                    <StreamingIndicator v-if="((message.role === 'user' || message.type === 'prompt')) && (index === messages.length - 1 || index === messages.length - 2)" :id="localStreamId" :url="streamUrl" className="mt-2" />
                </div>
            </template>
            <div v-if="streamingData" class="rounded-lg border p-4">
                <div class="text-xs text-muted-foreground mb-1">Assistant</div>
                <div class="whitespace-pre-wrap">{{ streamingData }}</div>
            </div>
        </div>

        <div class="sticky bottom-0 left-0 right-0 bg-background/80 backdrop-blur supports-[backdrop-filter]:bg-background/60">
            <div class="mx-auto max-w-3xl border-t p-4">
                <form @submit.prevent="postMessages" class="flex gap-2">
                    <textarea v-model="input" placeholder="Send a message..." rows="1" class="min-h-[44px] w-full resize-none rounded-md border bg-background px-3 py-2 text-sm shadow-sm focus-visible:outline-none" />
                    <button type="submit" class="rounded-md bg-primary px-3 text-sm text-primary-foreground disabled:opacity-50" :disabled="!canSend">Send</button>
                </form>
            </div>
        </div>
    </div>
</template>
