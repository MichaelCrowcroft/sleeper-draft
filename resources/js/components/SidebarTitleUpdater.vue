<script setup lang="ts">
import { useEventStream } from '@laravel/stream-vue';

interface Props {
    chatId: number;
}

const props = defineProps<Props>();

const { message, close } = useEventStream(`/chat/${props.chatId}/title-stream`, {
    event: 'title-update',
    onMessage: (event: MessageEvent) => {
        try {
            const parsed = JSON.parse(event.data as string) as { title?: string };
            if (parsed.title) {
                window.dispatchEvent(
                    new CustomEvent('chatTitleUpdated', {
                        detail: { chatId: props.chatId, newTitle: parsed.title },
                    }),
                );
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
