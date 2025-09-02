@php
$title = 'AI Tools for Fantasy Football';
@endphp

<x-layouts.app.marketing :title="$title">
    <div class="min-h-screen bg-[#FDFDFC] dark:bg-[#0a0a0a] text-[#1b1b18] dark:text-[#EDEDEC] px-4 sm:px-6 py-8 sm:py-12 break-words overflow-x-hidden">
        <div class="mx-auto max-w-6xl w-full">
            @include('partials.marketing-nav')

            <!-- Hero Section -->
            <div class="rounded-2xl border border-black/10 bg-white p-8 sm:p-12 shadow-sm dark:border-white/10 dark:bg-[#141414] mb-8 text-center">
                <div class="flex flex-col items-center gap-6">
                    <x-app-logo-icon class="size-24 sm:size-32 flex-shrink-0" />
                    <div>
                        <h1 class="text-3xl leading-tight font-semibold sm:text-4xl lg:text-5xl mb-4">MCP Tools for Fantasy Football</h1>
                        <p class="text-lg text-[#575654] dark:text-[#A1A09A] max-w-2xl mx-auto mb-8">
                            Connect your AI assistant to Sleeper fantasy football data with our Model Context Protocol (MCP) server.
                        </p>

                        <!-- Primary CTA -->
                        <div class="flex flex-col sm:flex-row justify-center gap-4 mb-8">
                            <a href="https://chatgpt.com/g/g-68b45e513c9881918831ae2a2dc294a5" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center gap-2 rounded-md border border-[#10a37f] bg-[#10a37f] px-8 py-3 text-lg font-medium text-white hover:bg-[#0c8c67] dark:border-[#10a37f] dark:bg-[#10a37f] dark:hover:bg-[#0c8c67]">
                                Try the Custom GPT
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5"><path fill-rule="evenodd" d="M5 10a1 1 0 011-1h5.586L9.293 6.707a1 1 0 111.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 11-1.414-1.414L11.586 11H6a1 1 0 01-1-1z" clip-rule="evenodd" /></svg>
                            </a>
                            <a href="{{ route('mcp') }}" class="inline-flex items-center justify-center gap-2 rounded-md border border-black/10 bg-white px-8 py-3 text-lg font-medium text-black hover:bg-gray-50 dark:border-white/10 dark:bg-zinc-800 dark:text-white dark:hover:bg-zinc-700">
                                View MCP Documentation
                            </a>
                        </div>

                        <!-- Secondary Actions -->
                        <div class="flex flex-col sm:flex-row justify-center gap-3">
                            <a href="https://github.com/MichaelCrowcroft/fantasy-football-mcp" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center gap-2 rounded-md border border-black/20 bg-transparent px-4 py-2 text-sm font-medium text-[#575654] hover:bg-gray-50 dark:border-white/20 dark:text-[#A1A09A] dark:hover:bg-zinc-800">
                                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                                </svg>
                                GitHub
                            </a>
                            <a href="https://donate.stripe.com/3cI7sKcfB6sB0FC33le3e00" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center gap-2 rounded-md border border-[#2563eb]/20 bg-[#eff6ff] px-4 py-2 text-sm font-medium text-[#2563eb] hover:bg-[#dbeafe] dark:border-[#60a5fa]/30 dark:bg-[#172554] dark:text-[#93c5fd] dark:hover:bg-[#1e293b]">
                                Support the project
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Connect -->
            <div class="rounded-xl border border-black/10 bg-[#FCFCFB] p-6 shadow-sm dark:border-white/10 dark:bg-[#0f0f0f] mb-8">
                <div class="text-center mb-6">
                    <h2 class="text-xl font-semibold mb-2">Quick Connect</h2>
                    <p class="text-[#575654] dark:text-[#A1A09A]">Get started with your MCP server in seconds</p>
                </div>

                <div class="grid md:grid-cols-2 gap-4">
                    <div class="rounded-md border border-black/10 bg-white p-4 dark:border-white/10 dark:bg-[#0f0f0f]">
                        <div class="mb-2 text-[12px] tracking-wide text-[#706f6c] uppercase dark:text-[#A1A09A]">Live Endpoint</div>
                        <div class="rounded-md bg-[#FCFCFB] px-3 py-2 text-[13px] break-all shadow-inner select-all dark:bg-[#161615] max-w-full overflow-hidden font-mono">https://www.sleeperdraft.com/mcp</div>
                    </div>
                    <div class="rounded-md border border-black/10 bg-white p-4 dark:border-white/10 dark:bg-[#0f0f0f]">
                        <div class="mb-2 text-[12px] tracking-wide text-[#706f6c] uppercase dark:text-[#A1A09A]">Local Endpoint</div>
                        <div class="rounded-md bg-[#FCFCFB] px-3 py-2 text-[13px] break-all shadow-inner select-all dark:bg-[#161615] max-w-full overflow-hidden font-mono">{{ url('/mcp') }}</div>
                    </div>
                </div>
            </div>

            <!-- Simple Quickstart -->
            <div class="rounded-xl border border-black/10 bg-[#FCFCFB] p-6 shadow-sm dark:border-white/10 dark:bg-[#0f0f0f]">
                <div class="text-center mb-6">
                    <h2 class="text-xl font-semibold mb-2">How to Use</h2>
                    <p class="text-[#575654] dark:text-[#A1A09A] mb-4">Three simple steps to get started</p>
                </div>

                <div class="grid md:grid-cols-3 gap-6">
                    <div class="text-center">
                        <div class="w-12 h-12 bg-[#10a37f] rounded-full flex items-center justify-center text-white font-bold text-lg mx-auto mb-4">1</div>
                        <h3 class="font-semibold mb-2">Choose Your AI</h3>
                        <p class="text-sm text-[#575654] dark:text-[#A1A09A]">Use the Custom GPT above or connect Claude/Cursor with MCP</p>
                    </div>
                    <div class="text-center">
                        <div class="w-12 h-12 bg-[#10a37f] rounded-full flex items-center justify-center text-white font-bold text-lg mx-auto mb-4">2</div>
                        <h3 class="font-semibold mb-2">Connect</h3>
                        <p class="text-sm text-[#575654] dark:text-[#A1A09A]">The AI will automatically connect to Sleeper data through our MCP server</p>
                    </div>
                    <div class="text-center">
                        <div class="w-12 h-12 bg-[#10a37f] rounded-full flex items-center justify-center text-white font-bold text-lg mx-auto mb-4">3</div>
                        <h3 class="font-semibold mb-2">Ask Questions</h3>
                        <p class="text-sm text-[#575654] dark:text-[#A1A09A]">Get fantasy football insights, recommendations, and analysis</p>
                    </div>
                </div>

                <div class="text-center mt-8">
                    <a href="{{ route('mcp') }}" class="inline-flex items-center gap-2 text-[#10a37f] hover:text-[#0c8c67] font-medium">
                        View detailed MCP documentation
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4"><path fill-rule="evenodd" d="M5 10a1 1 0 011-1h5.586L9.293 6.707a1 1 0 111.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 11-1.414-1.414L11.586 11H6a1 1 0 01-1-1z" clip-rule="evenodd" /></svg>
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app.marketing>
