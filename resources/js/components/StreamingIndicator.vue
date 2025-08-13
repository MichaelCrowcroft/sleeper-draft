<script setup lang="ts">
import { useStream } from '@laravel/stream-vue';

interface Props {
    id: string;
    url: string;
    className?: string;
}

const props = defineProps<Props>();
const { isFetching, isStreaming } = useStream(props.url, { id: props.id });
</script>

<template>
    <div v-if="isStreaming" :class="props.className" class="text-xs text-muted-foreground animate-pulse">Generating…</div>
    <div v-else-if="isFetching" :class="props.className" class="text-xs text-muted-foreground animate-pulse">Connecting…</div>
</template>
