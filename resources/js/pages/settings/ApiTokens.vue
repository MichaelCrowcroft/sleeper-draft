<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';

interface ApiToken {
    id: string;
    name: string;
    last_used_at: string | null;
    created_at: string;
    abilities: string[];
    token_preview: string;
}

interface Props {
    tokens: ApiToken[];
    newToken?: { name: string; token: string } | null;
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'API Tokens',
        href: '/settings/api-tokens',
    },
];

const showNewToken = ref(!!props.newToken);
const newTokenData = ref<{ name: string; token: string } | null>(props.newToken || null);
const revealedTokens = ref<Record<string, string>>({});

const form = useForm({
    name: '',
});

const submit = () => {
    form.post(route('api-tokens.store'), {
        preserveScroll: true,
        onSuccess: () => {
            // Form will be reset and page will reload with new token data
            form.reset();
        },
    });
};

const showFullToken = async (tokenId: string) => {
    if (revealedTokens.value[tokenId]) {
        // Hide the token
        delete revealedTokens.value[tokenId];
        return;
    }

    try {
        const response = await fetch(route('api-tokens.show', { tokenId }), {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
        });

        const data = await response.json();

        if (response.ok) {
            revealedTokens.value[tokenId] = data.token;
        }
    } catch {
        alert('Failed to load token');
    }
};

const revokeToken = async (tokenId: string) => {
    if (!confirm('Are you sure you want to revoke this token? This action cannot be undone.')) {
        return;
    }

    try {
        await fetch(route('api-tokens.destroy', { tokenId }), {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
        });

        router.reload({ only: ['tokens'] });
    } catch {
        alert('Failed to revoke token');
    }
};

const revokeAllTokens = async () => {
    if (!confirm('Are you sure you want to revoke ALL tokens? This will break any existing MCP connections.')) {
        return;
    }

    try {
        await fetch(route('api-tokens.destroy-all'), {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
        });

        router.reload({ only: ['tokens'] });
    } catch {
        alert('Failed to revoke tokens');
    }
};

const copyToClipboard = (text: string) => {
    navigator.clipboard.writeText(text);
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const hasTokens = computed(() => props.tokens.length > 0);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="API Tokens" />

        <SettingsLayout>
                        <div class="flex flex-col space-y-6">
                <HeadingSmall
                    title="API Tokens"
                    description="Generate and manage API tokens for MCP server authentication"
                />

                <!-- Token Format Warning -->
                <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg dark:bg-yellow-950/20 dark:border-yellow-800">
                    <h3 class="font-medium text-yellow-800 dark:text-yellow-200 mb-2">
                        ⚠️ Critical: Complete Token Format Required
                    </h3>
                    <p class="text-sm text-yellow-700 dark:text-yellow-300">
                        Laravel Sanctum tokens must include both the token ID and the actual token in the format <code class="bg-yellow-100 dark:bg-yellow-900 px-2 py-1 rounded text-xs">id|token</code>.
                        For example: <code class="bg-yellow-100 dark:bg-yellow-900 px-2 py-1 rounded text-xs">1|abc123...</code><br>
                        <strong>Authentication will fail if you only use the token part without the ID prefix.</strong>
                    </p>
                </div>

                <!-- New Token Success Display -->
                <div v-if="showNewToken && newTokenData" class="p-4 border border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950 rounded-lg">
                    <h3 class="font-medium text-green-800 dark:text-green-200 mb-2">Token Created Successfully</h3>
                    <p class="text-sm text-green-700 dark:text-green-300 mb-4">
                        Please copy your new API token. For security reasons, it won't be shown again.
                    </p>
                    <div class="space-y-3">
                        <div>
                            <Label class="text-green-800 dark:text-green-200">{{ newTokenData.name }}</Label>
                        </div>
                        <div class="flex items-center space-x-2">
                            <Input
                                :value="newTokenData.token"
                                readonly
                                class="font-mono text-sm bg-green-100 dark:bg-green-900 border-green-300 dark:border-green-700"
                            />
                            <Button
                                variant="outline"
                                size="sm"
                                @click="copyToClipboard(newTokenData.token)"
                                class="border-green-300 text-green-800 hover:bg-green-200 dark:border-green-700 dark:text-green-200 dark:hover:bg-green-800"
                            >
                                Copy Complete Token
                            </Button>
                        </div>
                        <div class="text-xs text-green-700 dark:text-green-300 mt-2">
                            <strong>⚠️ Important:</strong> This complete token includes the ID prefix (e.g., "1|abc123...") which is required for authentication.
                        </div>
                        <Button
                            variant="outline"
                            size="sm"
                            @click="showNewToken = false"
                            class="border-green-300 text-green-800 hover:bg-green-200 dark:border-green-700 dark:text-green-200 dark:hover:bg-green-800"
                        >
                            Dismiss
                        </Button>
                    </div>
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    <div class="grid gap-2">
                        <Label for="name">Token Name</Label>
                        <Input
                            id="name"
                            class="mt-1 block w-full"
                            v-model="form.name"
                            type="text"
                            placeholder="e.g., My Desktop Client, Laptop Setup"
                            required
                            :disabled="form.processing"
                        />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <div class="flex items-center gap-4">
                        <Button type="submit" :disabled="form.processing || !form.name.trim()">
                            {{ form.processing ? 'Creating...' : 'Create Token' }}
                        </Button>
                    </div>
                </form>

                <div v-if="hasTokens" class="space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="font-medium">Active Tokens ({{ tokens.length }})</h3>
                        <Button
                            variant="destructive"
                            size="sm"
                            @click="revokeAllTokens"
                            v-if="tokens.length > 1"
                        >
                            Revoke All
                        </Button>
                    </div>

                    <div class="space-y-3">
                        <div
                            v-for="token in tokens"
                            :key="token.id"
                            class="p-4 border rounded-lg space-y-3"
                        >
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <h4 class="font-medium">{{ token.name }}</h4>
                                    <div class="text-sm text-muted-foreground">
                                        Created {{ formatDate(token.created_at) }}
                                        <span v-if="token.last_used_at">
                                            • Last used {{ formatDate(token.last_used_at) }}
                                        </span>
                                        <span v-else>
                                            • Never used
                                        </span>
                                    </div>
                                </div>
                                <Button
                                    variant="destructive"
                                    size="sm"
                                    @click="revokeToken(token.id)"
                                >
                                    Revoke
                                </Button>
                            </div>

                            <div class="flex items-center space-x-2">
                                <Input
                                    :value="revealedTokens[token.id] || token.token_preview"
                                    readonly
                                    class="font-mono text-sm"
                                />
                                <Button
                                    variant="outline"
                                    size="sm"
                                    @click="showFullToken(token.id)"
                                >
                                    {{ revealedTokens[token.id] ? 'Hide' : 'Show' }}
                                </Button>
                                <Button
                                    v-if="revealedTokens[token.id]"
                                    variant="outline"
                                    size="sm"
                                    @click="copyToClipboard(revealedTokens[token.id])"
                                >
                                    Copy Complete Token
                                </Button>
                            </div>
                            <div v-if="revealedTokens[token.id]" class="text-xs text-amber-700 dark:text-amber-300 mt-1">
                                <strong>⚠️ Important:</strong> Use the complete token with ID prefix for authentication (e.g., "1|abc123...")
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="text-center py-8">
                    <p class="text-muted-foreground">No API tokens created yet.</p>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
