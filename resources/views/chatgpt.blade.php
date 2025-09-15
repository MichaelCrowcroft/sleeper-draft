@php
$title = 'Sleeper Draft Fantasy Football ChatGPT - AI Assistant with Real-Time Analytics';
$description = 'Experience fantasy football like never before with our Sleeper Draft AI Assistant. Get comprehensive player data, ADP rankings, trade evaluations, and detailed analytics powered by real-time data from the Sleeper platform.';
@endphp

<x-layouts.app.marketing :title="$title" :description="$description">

            <!-- Hero Section -->
            <div class="relative pt-16 pb-16 sm:pt-20 sm:pb-20">
                <div class="relative rounded-3xl border border-zinc-200/60 bg-white/60 backdrop-blur-sm p-8 sm:p-12 shadow-2xl shadow-zinc-900/5 dark:border-zinc-800/60 dark:bg-zinc-900/60 dark:shadow-zinc-100/5">
                    <div class="grid lg:grid-cols-2 gap-12 items-center">
                        <div>
                            <div class="inline-flex items-center gap-2 rounded-full bg-emerald-100 px-4 py-2 text-sm font-medium text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300 mb-6">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                </svg>
                                Custom GPT
                            </div>

                            <h1 class="text-3xl sm:text-4xl lg:text-5xl font-bold tracking-tight mb-6">
                                <span class="bg-gradient-to-br from-zinc-900 via-zinc-800 to-zinc-700 dark:from-zinc-100 dark:via-zinc-200 dark:to-zinc-300 bg-clip-text text-transparent">Sleeper Draft</span>
                                <br>
                                <span class="bg-gradient-to-r from-emerald-500 to-teal-600 bg-clip-text text-transparent">Fantasy Football AI</span>
                            </h1>

                            <p class="text-xl text-zinc-600 dark:text-zinc-400 mb-8 leading-relaxed">
                                Experience fantasy football like never before with our Sleeper Draft AI Assistant. Seamlessly integrated with the Sleeper platform, it provides comprehensive player analytics, trade evaluations, league management, and real-time data through our advanced MCP (Model Context Protocol) tools.
                            </p>

                            <div class="flex flex-col sm:flex-row gap-4">
                                <a href="https://chatgpt.com/g/g-68b45e513c9881918831ae2a2dc294a5" target="_blank" rel="noopener noreferrer"
                                   class="group relative inline-flex items-center justify-center gap-3 rounded-2xl bg-gradient-to-r from-emerald-500 to-teal-600 px-8 py-4 text-lg font-semibold text-white shadow-xl shadow-emerald-500/25 transition-all duration-300 hover:shadow-2xl hover:shadow-emerald-500/40 hover:scale-105">
                                    <span class="relative z-10">Try the Custom GPT</span>
                                    <svg class="relative z-10 h-5 w-5 transition-transform group-hover:translate-x-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M5 10a1 1 0 011-1h5.586L9.293 6.707a1 1 0 111.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 11-1.414-1.414L11.586 11H6a1 1 0 01-1-1z" clip-rule="evenodd" />
                                    </svg>
                                </a>

                                <a href="{{ route('mcp') }}"
                                   class="group inline-flex items-center justify-center gap-3 rounded-2xl border-2 border-zinc-200 bg-white/80 backdrop-blur-sm px-8 py-4 text-lg font-semibold text-zinc-900 shadow-lg transition-all duration-300 hover:border-zinc-300 hover:shadow-xl hover:scale-105 dark:border-zinc-700 dark:bg-zinc-800/80 dark:text-zinc-100 dark:hover:border-zinc-600">
                                    <span>View MCP Docs</span>
                                    <svg class="h-5 w-5 transition-transform group-hover:translate-x-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M5 10a1 1 0 011-1h5.586L9.293 6.707a1 1 0 111.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 11-1.414-1.414L11.586 11H6a1 1 0 01-1-1z" clip-rule="evenodd" />
                                    </svg>
                            </a>
                        </div>
                    </div>

                        <div class="relative">
                            <div class="rounded-2xl border border-zinc-200 bg-gradient-to-br from-white to-zinc-50 p-6 shadow-lg dark:border-zinc-700 dark:from-zinc-800 dark:to-zinc-900">
                                <div class="mb-4 text-xs font-semibold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">Quick Start Guide</div>
                                <ol class="space-y-4 text-sm text-zinc-600 dark:text-zinc-400">
                                    <li class="flex items-start gap-3">
                                        <div class="flex-shrink-0 w-6 h-6 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300 text-xs font-bold flex items-center justify-center mt-0.5">1</div>
                                        <span>Click "Try the Custom GPT" to access our specialized assistant</span>
                                    </li>
                                    <li class="flex items-start gap-3">
                                        <div class="flex-shrink-0 w-6 h-6 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300 text-xs font-bold flex items-center justify-center mt-0.5">2</div>
                                        <span>The GPT has built-in access to all MCP fantasy football tools</span>
                                    </li>
                                    <li class="flex items-start gap-3">
                                        <div class="flex-shrink-0 w-6 h-6 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300 text-xs font-bold flex items-center justify-center mt-0.5">3</div>
                                        <div>
                                            <span>Ask natural language questions like:</span>
                                            <ul class="mt-2 space-y-1 text-xs ml-4">
                                                <li class="text-zinc-500 dark:text-zinc-500">• "What's the current ADP for Christian McCaffrey?"</li>
                                                <li class="text-zinc-500 dark:text-zinc-500">• "Who are the top trending players this week?"</li>
                                                <li class="text-zinc-500 dark:text-zinc-500">• "Show me all QB stats for the 2024 season"</li>
                                    </ul>
                                        </div>
                                </li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

                <!-- Background decoration -->
                <div class="absolute inset-0 -z-10">
                    <div class="absolute top-10 right-10 w-72 h-72 bg-gradient-to-br from-emerald-400/20 to-teal-600/20 rounded-full blur-3xl animate-pulse"></div>
                    <div class="absolute bottom-10 left-10 w-96 h-96 bg-gradient-to-br from-emerald-400/10 to-teal-600/10 rounded-full blur-3xl animate-pulse delay-1000"></div>
                </div>
            </div>

            <!-- Features Section -->
            <div class="pb-16 sm:pb-24">
                <div class="grid lg:grid-cols-2 gap-8">
                    <!-- Features -->
                    <div class="relative rounded-3xl border border-zinc-200/60 bg-white/60 backdrop-blur-sm p-8 shadow-2xl shadow-zinc-900/5 dark:border-zinc-800/60 dark:bg-zinc-900/60 dark:shadow-zinc-100/5">
                        <div class="mb-8">
                            <div class="inline-flex items-center gap-2 rounded-full bg-emerald-100 px-4 py-2 text-sm font-medium text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300 mb-4">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M13 2.05v2.02c4.39.54 7.5 4.53 6.96 8.92A8 8 0 0 1 12 20c-4.41 0-8-3.59-8-8 0-1.5.37-2.91 1.02-4.12l1.49 1.49A5.5 5.5 0 0 0 6.5 12 5.5 5.5 0 0 0 12 17.5a5.5 5.5 0 0 0 5.5-5.5c0-.55-.1-1.07-.27-1.55l1.49-1.49c.5.84.78 1.82.78 2.89 0 3.87-3.13 7-7 7s-7-3.13-7-7c0-1.91.76-3.64 2-4.93V2.05z"/>
                                </svg>
                                Features
                            </div>
                            <h2 class="text-2xl sm:text-3xl font-bold tracking-tight mb-4">Comprehensive Data Access</h2>
                            <p class="text-zinc-600 dark:text-zinc-400 mb-6">Access live fantasy football data through our sophisticated MCP integration</p>
                        </div>

                        <div class="grid sm:grid-cols-2 gap-6">
                            <div class="space-y-4">
                                <h3 class="font-semibold text-lg text-zinc-900 dark:text-zinc-100">Player Analytics & Data</h3>
                                <ul class="space-y-2 text-sm text-zinc-600 dark:text-zinc-400">
                                    <li class="flex items-center gap-2">
                                        <div class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></div>
                                        Live player statistics and projections from Sleeper
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <div class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></div>
                                        Current ADP rankings and draft positioning
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <div class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></div>
                                        Trending players with 24-hour add/drop data
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <div class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></div>
                                        Comprehensive season statistics and performance
                                    </li>
                        </ul>
                    </div>

                            <div class="space-y-4">
                                <h3 class="font-semibold text-lg text-zinc-900 dark:text-zinc-100">League Management & AI Tools</h3>
                                <ul class="space-y-2 text-sm text-zinc-600 dark:text-zinc-400">
                                    <li class="flex items-center gap-2">
                                        <div class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></div>
                                        AI-powered trade evaluation and analysis
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <div class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></div>
                                        Weekly matchup data and scoring analysis
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <div class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></div>
                                        Roster management and free agent tracking
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <div class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></div>
                                        User league discovery and management
                                    </li>
                        </ul>
                    </div>
                </div>
            </div>

                    <!-- Example Conversations -->
                    <div class="relative rounded-3xl border border-zinc-200/60 bg-white/60 backdrop-blur-sm p-8 shadow-2xl shadow-zinc-900/5 dark:border-zinc-800/60 dark:bg-zinc-900/60 dark:shadow-zinc-100/5">
                        <div class="mb-8">
                            <div class="inline-flex items-center gap-2 rounded-full bg-emerald-100 px-4 py-2 text-sm font-medium text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300 mb-4">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M20 2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h4l4 4 4-4h4c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-2 12H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z"/>
                                </svg>
                                Examples
                            </div>
                            <h2 class="text-2xl sm:text-3xl font-bold tracking-tight mb-4">Example Conversations</h2>
                            <p class="text-zinc-600 dark:text-zinc-400 mb-6">See how natural language queries unlock powerful insights</p>
                        </div>

                        <div class="space-y-6">
                            <div class="group relative rounded-xl border border-emerald-200/50 bg-gradient-to-r from-emerald-50/50 to-teal-50/50 p-4 transition-all hover:border-emerald-300/50 dark:border-emerald-800/50 dark:from-emerald-900/10 dark:to-teal-900/10 dark:hover:border-emerald-700/50">
                                <div class="flex items-start gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-emerald-100 flex items-center justify-center flex-shrink-0 dark:bg-emerald-900/30">
                                        <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="font-semibold text-zinc-900 dark:text-zinc-100 mb-1">Trade Evaluation</div>
                                        <div class="text-sm text-zinc-600 dark:text-zinc-400 italic">"Should I trade Patrick Mahomes for Josh Allen and a draft pick?"</div>
                                    </div>
                                </div>
                            </div>

                            <div class="group relative rounded-xl border border-emerald-200/50 bg-gradient-to-r from-emerald-50/50 to-teal-50/50 p-4 transition-all hover:border-emerald-300/50 dark:border-emerald-800/50 dark:from-emerald-900/10 dark:to-teal-900/10 dark:hover:border-emerald-700/50">
                                <div class="flex items-start gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-emerald-100 flex items-center justify-center flex-shrink-0 dark:bg-emerald-900/30">
                                        <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="font-semibold text-zinc-900 dark:text-zinc-100 mb-1">Trending Players</div>
                                        <div class="text-sm text-zinc-600 dark:text-zinc-400 italic">"Who are the most added players in the last 24 hours?"</div>
                                    </div>
                                </div>
                            </div>

                            <div class="group relative rounded-xl border border-emerald-200/50 bg-gradient-to-r from-emerald-50/50 to-teal-50/50 p-4 transition-all hover:border-emerald-300/50 dark:border-emerald-800/50 dark:from-emerald-900/10 dark:to-teal-900/10 dark:hover:border-emerald-700/50">
                                <div class="flex items-start gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-emerald-100 flex items-center justify-center flex-shrink-0 dark:bg-emerald-900/30">
                                        <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="font-semibold text-zinc-900 dark:text-zinc-100 mb-1">League Matchups</div>
                                        <div class="text-sm text-zinc-600 dark:text-zinc-400 italic">"Show me my matchup for this week in league 123456789"</div>
                                    </div>
                                </div>
                            </div>

                            <div class="group relative rounded-xl border border-emerald-200/50 bg-gradient-to-r from-emerald-50/50 to-teal-50/50 p-4 transition-all hover:border-emerald-300/50 dark:border-emerald-800/50 dark:from-emerald-900/10 dark:to-teal-900/10 dark:hover:border-emerald-700/50">
                                <div class="flex items-start gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-emerald-100 flex items-center justify-center flex-shrink-0 dark:bg-emerald-900/30">
                                        <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="font-semibold text-zinc-900 dark:text-zinc-100 mb-1">Player Research</div>
                                        <div class="text-sm text-zinc-600 dark:text-zinc-400 italic">"Compare Christian McCaffrey and Bijan Robinson for my RB2 spot"</div>
                                    </div>
                    </div>
                    </div>
                    </div>
                    </div>
                </div>
            </div>
</x-layouts.app.marketing>
