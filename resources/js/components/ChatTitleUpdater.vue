<script setup lang="ts">
import { useEventStream } from '@laravel/stream-vue';

interface Props {
    chatId: number;
    currentTitle: string;
}

const props = defineProps<Props>();

useEventStream(`/chat/${props.chatId}/title-stream`, {
    event: 'title-update',
    onMessage: (event: MessageEvent) => {
        try {
            const parsed = JSON.parse(event.data as string) as { title?: string };
            if (parsed.title) {
                document.title = `${parsed.title} - ${import.meta.env.VITE_APP_NAME || 'Laravel'}`;
            }
        } catch (e) {
            // ignore
        }
    },
});
</script>

<template>
    <div style="display:none" />
</template>
