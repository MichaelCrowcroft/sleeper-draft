<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { computed } from 'vue';
import hljs from 'highlight.js/lib/core';
import json from 'highlight.js/lib/languages/json';
import 'highlight.js/styles/vs2015.css';

hljs.registerLanguage('json', json);

interface McpToken {
    id: string;
    name: string;
    token: string;
    token_preview: string;
}

interface Props {
    hasMcpTokens: boolean;
    mcpTokens: McpToken[];
    firstToken?: McpToken;
}

const props = defineProps<Props>();

// Generate configs with actual token
const claudeConfig = computed(() => {
    if (!props.firstToken) return '';
    return JSON.stringify({
        mcpServers: {
            "fantasy-football-mcp": {
                command: "npx",
                args: [
                    "-y",
                    "supergateway",
                    "--streamableHttp",
                    "http://localhost:8000/mcp",
                    "--oauth2Bearer",
                    props.firstToken.token
                ]
            }
        }
    }, null, 2);
});

const cursorConfig = computed(() => {
    if (!props.firstToken) return '';
    return JSON.stringify({
        mcpServers: {
            "fantasy-football-mcp": {
                transport: {
                    type: "http",
                    url: "http://localhost:8000/mcp",
                    headers: {
                        Authorization: `Bearer ${props.firstToken.token}`
                    }
                }
            }
        }
    }, null, 2);
});

const copyToClipboard = async (text: string) => {
    try {
        await navigator.clipboard.writeText(text);
        // Could add a toast notification here
        console.log('Copied to clipboard');
    } catch (err) {
        console.error('Failed to copy:', err);
    }
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Welcome Section -->
            <div class="space-y-2">
                <h1 class="text-3xl font-bold tracking-tight">Welcome to Fantasy Football MCP</h1>
                <p class="text-muted-foreground">
                    Connect your fantasy football tools and get AI-powered insights for your leagues.
                </p>
            </div>

            <!-- MCP Server Connection Section -->
            <Card class="w-full">
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                MCP Server Connection
                                <Badge :variant="props.hasMcpTokens ? 'default' : 'secondary'">
                                    {{ props.hasMcpTokens ? 'Connected' : 'Not Connected' }}
                                </Badge>
                            </CardTitle>
                            <CardDescription>
                                Connect your MCP client to access fantasy football tools and data
                            </CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="space-y-6">
                    <!-- Token Status -->
                    <div v-if="!props.hasMcpTokens" class="p-4 border border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-950 rounded-lg">
                        <div class="flex items-start gap-3">
                            <div class="w-5 h-5 rounded-full bg-amber-500 flex-shrink-0 mt-0.5"></div>
                            <div class="space-y-2">
                                <h3 class="font-medium text-amber-800 dark:text-amber-200">
                                    No API Token Found
                                </h3>
                                <p class="text-sm text-amber-700 dark:text-amber-300">
                                    You need an API token to connect to the MCP server and access fantasy football tools.
                                </p>
                                <Link href="/settings/api-tokens" class="inline-flex">
                                    <Button size="sm" class="bg-amber-600 hover:bg-amber-700 text-white cursor-pointer">
                                        Create API Token
                                    </Button>
                                </Link>
                            </div>
                        </div>
                    </div>

                    <div v-else class="p-4 border border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950 rounded-lg">
                        <div class="flex items-start gap-3">
                            <div class="w-5 h-5 rounded-full bg-green-500 flex-shrink-0 mt-0.5"></div>
                            <div class="space-y-3">
                                <div>
                                    <h3 class="font-medium text-green-800 dark:text-green-200">
                                        API Token Available
                                    </h3>
                                    <p class="text-sm text-green-700 dark:text-green-300">
                                        Great! You have an API token ready to use with the MCP server.
                                    </p>
                                </div>

                                <!-- Token Details -->
                                <div v-if="props.firstToken" class="space-y-2 p-3 bg-white/50 dark:bg-black/20 rounded border">
                                    <div class="text-xs text-green-700 dark:text-green-300 space-y-1">
                                        <div class="flex items-center justify-between">
                                            <span class="font-medium">Token Name:</span>
                                            <code class="bg-green-100 dark:bg-green-900 px-2 py-1 rounded text-xs">{{ props.firstToken.name }}</code>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <span class="font-medium">Token ID:</span>
                                            <code class="bg-green-100 dark:bg-green-900 px-2 py-1 rounded text-xs">{{ props.firstToken.id }}</code>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <span class="font-medium">Full Token:</span>
                                            <div class="flex items-center gap-2">
                                                <code class="bg-green-100 dark:bg-green-900 px-2 py-1 rounded text-xs max-w-48 truncate">{{ props.firstToken.token }}</code>
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    @click="copyToClipboard(props.firstToken.token)"
                                                    class="h-6 text-xs border-green-300 text-green-800 hover:bg-green-200 dark:border-green-700 dark:text-green-200 dark:hover:bg-green-800 cursor-pointer"
                                                >
                                                    Copy
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <Link href="/settings/api-tokens" class="inline-flex">
                                    <Button variant="outline" size="sm" class="border-green-300 text-green-800 hover:bg-green-200 dark:border-green-700 dark:text-green-200 dark:hover:bg-green-800 cursor-pointer">
                                        Manage Tokens
                                    </Button>
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Connection Instructions -->
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold">How to Connect to the MCP Server</h3>
                            <Link href="/" class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                View Full Documentation →
                            </Link>
                        </div>

                        <div class="space-y-4">
                            <div class="space-y-2">
                                <h4 class="font-medium text-sm uppercase tracking-wide text-muted-foreground">
                                    Step 1: Close Your Client
                                </h4>
                                <p class="text-sm text-muted-foreground">
                                    Close Claude Desktop or Cursor before making configuration changes.
                                </p>
                            </div>

                            <div class="space-y-2">
                                <h4 class="font-medium text-sm uppercase tracking-wide text-muted-foreground">
                                    Step 2: Create/Edit Config File
                                </h4>
                                <div class="space-y-2 text-sm">
                                    <div><strong>Claude Desktop:</strong> <code class="bg-muted px-2 py-1 rounded text-xs">~/Library/Application Support/Claude/claude_desktop_config.json</code></div>
                                    <div><strong>Cursor:</strong> <code class="bg-muted px-2 py-1 rounded text-xs">~/.cursor/mcp.json</code></div>
                                </div>
                                <div class="mt-2 p-2 bg-blue-50 dark:bg-blue-950/20 border border-blue-200 dark:border-blue-800 rounded">
                                    <p class="text-xs text-blue-800 dark:text-blue-200">
                                        💡 <strong>Make sure your server is running:</strong> <code class="bg-blue-100 dark:bg-blue-900 px-1 rounded">php artisan serve</code>
                                    </p>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <h4 class="font-medium text-sm uppercase tracking-wide text-muted-foreground">
                                    Step 3: Configure Your Client
                                </h4>



                                <!-- Authenticated Configuration -->
                                <div class="space-y-3">
                                    <div>
                                        <div class="mb-2 text-sm font-medium">MCP Client Setup (With Authentication)</div>
                                        <div class="space-y-2">
                                            <div>
                                                <div class="flex items-center justify-between mb-2">
                                                    <span class="text-xs text-muted-foreground">Claude Desktop</span>
                                                </div>
                                                <div class="relative overflow-auto rounded-md bg-gray-900 p-3 text-xs">
                                                    <Button
                                                        v-if="claudeConfig"
                                                        variant="outline"
                                                        size="sm"
                                                        @click="copyToClipboard(claudeConfig)"
                                                        class="absolute top-2 right-2 text-xs h-6 bg-gray-800 border-gray-600 text-white hover:bg-gray-700 z-10 cursor-pointer"
                                                    >
                                                        Copy
                                                    </Button>
                                                    <pre v-if="claudeConfig" v-html="hljs.highlight('json', claudeConfig).value" class="hljs"></pre>
                                                    <pre v-else class="text-muted-foreground">Create an API token to see configuration</pre>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="flex items-center justify-between mb-2">
                                                    <span class="text-xs text-muted-foreground">Cursor</span>
                                                </div>
                                                <div class="relative overflow-auto rounded-md bg-gray-900 p-3 text-xs">
                                                    <Button
                                                        v-if="cursorConfig"
                                                        variant="outline"
                                                        size="sm"
                                                        @click="copyToClipboard(cursorConfig)"
                                                        class="absolute top-2 right-2 text-xs h-6 bg-gray-800 border-gray-600 text-white hover:bg-gray-700 z-10 cursor-pointer"
                                                    >
                                                        Copy
                                                    </Button>
                                                    <pre v-if="cursorConfig" v-html="hljs.highlight('json', cursorConfig).value" class="hljs"></pre>
                                                    <pre v-else class="text-muted-foreground">Create an API token to see configuration</pre>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <h4 class="font-medium text-sm uppercase tracking-wide text-muted-foreground">
                                    Step 4: Restart and Use
                                </h4>
                                <p class="text-sm text-muted-foreground">
                                    Restart your client. The MCP server will appear under MCP servers in your client.
                                </p>
                </div>
                </div>

                        <div class="p-4 bg-blue-50 dark:bg-blue-950 border border-blue-200 dark:border-blue-800 rounded-lg">
                            <h4 class="font-medium text-blue-800 dark:text-blue-200 mb-2">
                                💡 Pro Tip
                            </h4>
                            <p class="text-sm text-blue-700 dark:text-blue-300">
                                Make sure to keep your API token secure and never share it publicly.
                                You can manage your tokens in the settings page and revoke them at any time.
                            </p>
                </div>
            </div>
                </CardContent>
            </Card>

            <!-- Quick Actions -->
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">My Leagues</CardTitle>
                        <CardDescription>
                            View and manage your fantasy football leagues
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Link href="/leagues">
                            <Button class="w-full cursor-pointer">
                                View Leagues
                            </Button>
                        </Link>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Settings</CardTitle>
                        <CardDescription>
                            Manage your account and API tokens
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Link href="/settings/profile">
                            <Button variant="outline" class="w-full cursor-pointer">
                                Open Settings
                            </Button>
                        </Link>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">API Documentation</CardTitle>
                        <CardDescription>
                            Learn more about available MCP tools
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Link href="/">
                            <Button variant="outline" class="w-full cursor-pointer">
                                View Documentation
                            </Button>
                        </Link>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
