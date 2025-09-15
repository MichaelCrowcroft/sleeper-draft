@php
$title = 'Fantasy Football AI - AI-Powered Trade Analysis & Matchup Predictions';
$description = 'Advanced AI-powered fantasy football analytics with intelligent trade evaluation, matchup predictions, and real-time player insights. Get AI-driven recommendations for trades, waiver wire pickups, and lineup optimization with comprehensive Sleeper integration.';
$keywords = 'fantasy football AI, trade analysis, matchup predictions, player analytics, waiver wire, lineup optimization, Sleeper integration, AI assistant, fantasy football tools, trade evaluation';
@endphp

@section('meta')
<meta name="description" content="{{ $description }}">
<meta name="keywords" content="{{ $keywords }}">
<meta name="robots" content="index, follow">
<meta property="og:title" content="{{ $title }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:type" content="website">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $title }}">
<meta name="twitter:description" content="{{ $description }}">
@endsection

<x-layouts.app.marketing :title="$title">

            <!-- Hero Section -->
            <div class="relative pt-16 pb-24 sm:pt-20 sm:pb-32">
                <div class="relative z-10 text-center">
                    <div class="flex justify-center mb-8">
                        <div class="relative">
                            <div class="absolute inset-0 bg-gradient-to-br from-emerald-400 to-teal-600 rounded-3xl blur-2xl opacity-20 scale-110"></div>
                            <x-app-logo-icon class="relative size-28 sm:size-36 flex-shrink-0" />
                        </div>
                    </div>

                    <h1 class="text-4xl sm:text-5xl lg:text-6xl xl:text-7xl font-bold tracking-tight bg-gradient-to-br from-zinc-900 via-zinc-800 to-zinc-700 dark:from-zinc-100 dark:via-zinc-200 dark:to-zinc-300 bg-clip-text text-transparent mb-8">
                        Fantasy Football AI
                        <span class="bg-gradient-to-r from-emerald-500 to-teal-600 bg-clip-text text-transparent">Trade Analysis & Predictions</span>
                    </h1>

                    <p class="text-xl sm:text-2xl text-zinc-600 dark:text-zinc-400 max-w-4xl mx-auto mb-12 leading-relaxed">
                        Get AI-powered trade recommendations, matchup predictions, and waiver wire insights.
                        <span class="font-semibold text-emerald-600 dark:text-emerald-400">Make data-driven fantasy football decisions</span> with intelligent analysis and real-time player data.
                    </p>

                    <!-- Primary CTAs -->
                    <div class="flex flex-col sm:flex-row justify-center gap-4 mb-12">
                        <a href="https://chatgpt.com/g/g-68b45e513c9881918831ae2a2dc294a5" target="_blank" rel="noopener noreferrer"
                           class="group relative inline-flex items-center justify-center gap-3 rounded-2xl bg-gradient-to-r from-emerald-500 to-teal-600 px-8 py-4 text-lg font-semibold text-white shadow-xl shadow-emerald-500/25 transition-all duration-300 hover:shadow-2xl hover:shadow-emerald-500/40 hover:scale-105">
                            <span class="relative z-10">Try the Custom GPT</span>
                            <svg class="relative z-10 h-5 w-5 transition-transform group-hover:translate-x-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5 10a1 1 0 011-1h5.586L9.293 6.707a1 1 0 111.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 11-1.414-1.414L11.586 11H6a1 1 0 01-1-1z" clip-rule="evenodd" />
                            </svg>
                        </a>

                        <a href="{{ route('mcp') }}"
                           class="group inline-flex items-center justify-center gap-3 rounded-2xl border-2 border-zinc-200 bg-white/80 backdrop-blur-sm px-8 py-4 text-lg font-semibold text-zinc-900 shadow-lg transition-all duration-300 hover:border-zinc-300 hover:shadow-xl hover:scale-105 dark:border-zinc-700 dark:bg-zinc-800/80 dark:text-zinc-100 dark:hover:border-zinc-600">
                            <span>View Documentation</span>
                            <svg class="h-5 w-5 transition-transform group-hover:translate-x-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5 10a1 1 0 011-1h5.586L9.293 6.707a1 1 0 111.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 11-1.414-1.414L11.586 11H6a1 1 0 01-1-1z" clip-rule="evenodd" />
                            </svg>
                        </a>
                    </div>

                    <!-- Secondary Actions -->
                    <div class="flex justify-center gap-6">
                        <a href="https://github.com/MichaelCrowcroft/fantasy-football-mcp" target="_blank" rel="noopener noreferrer"
                           class="group inline-flex items-center gap-2 text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100 transition-colors">
                            <svg class="h-5 w-5 transition-transform group-hover:scale-110" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                            </svg>
                            <span class="font-medium">GitHub</span>
                        </a>

                        <a href="https://donate.stripe.com/3cI7sKcfB6sB0FC33le3e00" target="_blank" rel="noopener noreferrer"
                           class="group inline-flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-medium text-emerald-700 transition-all hover:bg-emerald-100 dark:border-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-400 dark:hover:bg-emerald-900/40">
                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2L13.09 8.26L16 2L17.91 8.26L21 3L22.09 8.26L24 4L22.09 15.74L21 12L17.91 15.74L16 10L13.09 15.74L12 10L10.91 15.74L8 10L4.09 15.74L3 12L1.91 15.74L0 4L1.91 8.26L3 3L6.09 8.26L8 2L10.91 8.26Z"/>
                            </svg>
                            <span>Support</span>
                        </a>
                    </div>
                </div>

                <!-- Background decoration -->
                <div class="absolute inset-0 -z-10">
                    <div class="absolute top-10 left-10 w-72 h-72 bg-gradient-to-br from-emerald-400/20 to-teal-600/20 rounded-full blur-3xl animate-pulse"></div>
                    <div class="absolute top-40 right-10 w-96 h-96 bg-gradient-to-br from-emerald-400/10 to-teal-600/10 rounded-full blur-3xl animate-pulse delay-1000"></div>
                </div>
            </div>

            <!-- Quick Connect Section -->
            <div class="pb-16 sm:pb-24">
                <div class="relative rounded-3xl border border-zinc-200/60 bg-white/60 backdrop-blur-sm p-8 shadow-2xl shadow-zinc-900/5 dark:border-zinc-800/60 dark:bg-zinc-900/60 dark:shadow-zinc-100/5 mb-16">
                    <div class="text-center mb-10">
                        <div class="inline-flex items-center gap-2 rounded-full bg-emerald-100 px-4 py-2 text-sm font-medium text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300 mb-4">
                            <div class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></div>
                            Quick Connect
                        </div>
                        <h2 class="text-3xl sm:text-4xl font-bold tracking-tight mb-4">Get started in seconds</h2>
                        <p class="text-lg text-zinc-600 dark:text-zinc-400 max-w-2xl mx-auto">
                            Copy the endpoint URL and connect your AI assistant to fantasy football data
                        </p>
                    </div>

                    <div class="grid lg:grid-cols-2 gap-8 max-w-4xl mx-auto">
                        <div class="group relative rounded-2xl border border-zinc-200 bg-gradient-to-br from-white to-zinc-50 p-6 shadow-lg transition-all hover:shadow-xl dark:border-zinc-700 dark:from-zinc-800 dark:to-zinc-900">
                            <div class="absolute inset-x-0 -top-px h-px bg-gradient-to-r from-transparent via-emerald-500 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
                            <div class="mb-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Live Endpoint</div>
                            <div class="relative rounded-lg bg-zinc-100 p-4 dark:bg-zinc-800">
                                <code class="text-sm font-mono text-zinc-800 dark:text-zinc-200 break-all select-all">https://www.sleeperdraft.com/mcp</code>
                                <div class="absolute top-2 right-2">
                                    <button class="rounded-md p-1.5 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 transition-colors" onclick="navigator.clipboard.writeText('https://www.sleeperdraft.com/mcp')">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="group relative rounded-2xl border border-zinc-200 bg-gradient-to-br from-white to-zinc-50 p-6 shadow-lg transition-all hover:shadow-xl dark:border-zinc-700 dark:from-zinc-800 dark:to-zinc-900">
                            <div class="absolute inset-x-0 -top-px h-px bg-gradient-to-r from-transparent via-emerald-500 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
                            <div class="mb-4 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Local Development</div>
                            <div class="relative rounded-lg bg-zinc-100 p-4 dark:bg-zinc-800">
                                <code class="text-sm font-mono text-zinc-800 dark:text-zinc-200 break-all select-all">{{ url('/mcp') }}</code>
                                <div class="absolute top-2 right-2">
                                    <button class="rounded-md p-1.5 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 transition-colors" data-copy="{{ url('/mcp') }}" onclick="navigator.clipboard.writeText(this.dataset.copy)">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- How it Works -->
                <div class="relative rounded-3xl border border-zinc-200/60 bg-white/60 backdrop-blur-sm p-8 shadow-2xl shadow-zinc-900/5 dark:border-zinc-800/60 dark:bg-zinc-900/60 dark:shadow-zinc-100/5">
                    <div class="text-center mb-12">
                        <h2 class="text-3xl sm:text-4xl font-bold tracking-tight mb-4">AI-Powered Fantasy Football Analysis</h2>
                        <p class="text-lg text-zinc-600 dark:text-zinc-400 max-w-2xl mx-auto">
                            Three simple steps to unlock intelligent trade analysis and matchup predictions
                        </p>
                    </div>

                    <div class="grid md:grid-cols-3 gap-8 max-w-5xl mx-auto">
                        <div class="relative text-center group">
                            <div class="relative inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 shadow-xl shadow-emerald-500/25 text-white font-bold text-xl mb-6 group-hover:scale-110 transition-transform">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/>
                                </svg>
                                <div class="absolute inset-0 rounded-2xl bg-gradient-to-br from-emerald-400 to-teal-500 opacity-0 group-hover:opacity-100 transition-opacity blur-xl"></div>
                            </div>
                            <h3 class="text-xl font-bold mb-3">Ask About Trades</h3>
                            <p class="text-zinc-600 dark:text-zinc-400 leading-relaxed">
                                "Should I trade Christian McCaffrey for Austin Ekeler?" Get AI-powered analysis with value comparisons and risk assessment
                            </p>
                        </div>

                        <div class="relative text-center group">
                            <div class="relative inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 shadow-xl shadow-emerald-500/25 text-white font-bold text-xl mb-6 group-hover:scale-110 transition-transform">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                </svg>
                                <div class="absolute inset-0 rounded-2xl bg-gradient-to-br from-emerald-400 to-teal-500 opacity-0 group-hover:opacity-100 transition-opacity blur-xl"></div>
                            </div>
                            <h3 class="text-xl font-bold mb-3">Matchup Analysis</h3>
                            <p class="text-zinc-600 dark:text-zinc-400 leading-relaxed">
                                "What's the matchup outlook for Week 8?" Receive win probabilities, player projections, and matchup insights
                            </p>
                        </div>

                        <div class="relative text-center group">
                            <div class="relative inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-emerald-600 to-green-700 shadow-xl shadow-emerald-600/25 text-white font-bold text-xl mb-6 group-hover:scale-110 transition-transform">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                                </svg>
                                <div class="absolute inset-0 rounded-2xl bg-gradient-to-br from-emerald-500 to-green-600 opacity-0 group-hover:opacity-100 transition-opacity blur-xl"></div>
                            </div>
                            <h3 class="text-xl font-bold mb-3">Waiver Wire Picks</h3>
                            <p class="text-zinc-600 dark:text-zinc-400 leading-relaxed">
                                "Who should I pick up this week?" Get trending player analysis and AI recommendations for roster optimization
                            </p>
                        </div>
                    </div>

                    <div class="text-center mt-12">
                        <a href="{{ route('mcp') }}"
                           class="group inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-zinc-100 to-zinc-200 px-6 py-3 font-semibold text-zinc-900 transition-all hover:from-zinc-200 hover:to-zinc-300 hover:scale-105 dark:from-zinc-800 dark:to-zinc-700 dark:text-zinc-100 dark:hover:from-zinc-700 dark:hover:to-zinc-600">
                            <span>View detailed documentation</span>
                            <svg class="h-4 w-4 transition-transform group-hover:translate-x-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5 10a1 1 0 011-1h5.586L9.293 6.707a1 1 0 111.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 11-1.414-1.414L11.586 11H6a1 1 0 01-1-1z" clip-rule="evenodd" />
                            </svg>
                        </a>
                    </div>
                </div>

                <!-- AI Capabilities Showcase -->
                <div class="relative rounded-3xl border border-zinc-200/60 bg-white/60 backdrop-blur-sm p-8 shadow-2xl shadow-zinc-900/5 dark:border-zinc-800/60 dark:bg-zinc-900/60 dark:shadow-zinc-100/5 mt-16">
                    <div class="text-center mb-12">
                        <h2 class="text-3xl sm:text-4xl font-bold tracking-tight mb-4">Intelligent Fantasy Football Tools</h2>
                        <p class="text-lg text-zinc-600 dark:text-zinc-400 max-w-2xl mx-auto">
                            Advanced AI analysis for every aspect of fantasy football decision making
                        </p>
                    </div>

                    <div class="grid md:grid-cols-2 gap-8 max-w-6xl mx-auto">
                        <div class="space-y-6">
                            <div class="flex items-start gap-4 p-6 rounded-xl bg-gradient-to-r from-emerald-50/50 to-teal-50/50 dark:from-emerald-900/10 dark:to-teal-900/10 border border-emerald-200/30 dark:border-emerald-800/30">
                                <div class="w-10 h-10 rounded-full bg-emerald-500 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-zinc-900 dark:text-zinc-100 mb-2">Trade Evaluation Engine</h3>
                                    <p class="text-sm text-zinc-600 dark:text-zinc-400 mb-3">AI analyzes player stats, projections, and positional value to provide intelligent trade recommendations with confidence scores and risk assessment.</p>
                                    <div class="text-xs font-mono bg-emerald-100 dark:bg-emerald-900/30 px-3 py-1 rounded text-emerald-700 dark:text-emerald-300 inline-block">evaluate-trade</div>
                                </div>
                            </div>

                            <div class="flex items-start gap-4 p-6 rounded-xl bg-gradient-to-r from-blue-50/50 to-indigo-50/50 dark:from-blue-900/10 dark:to-indigo-900/10 border border-blue-200/30 dark:border-blue-800/30">
                                <div class="w-10 h-10 rounded-full bg-blue-500 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-zinc-900 dark:text-zinc-100 mb-2">Matchup Prediction AI</h3>
                                    <p class="text-sm text-zinc-600 dark:text-zinc-400 mb-3">Machine learning models calculate win probabilities, identify key matchups, and provide confidence intervals for accurate forecasting.</p>
                                    <div class="text-xs font-mono bg-blue-100 dark:bg-blue-900/30 px-3 py-1 rounded text-blue-700 dark:text-blue-300 inline-block">fetch-matchups</div>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-6">
                            <div class="flex items-start gap-4 p-6 rounded-xl bg-gradient-to-r from-purple-50/50 to-pink-50/50 dark:from-purple-900/10 dark:to-pink-900/10 border border-purple-200/30 dark:border-purple-800/30">
                                <div class="w-10 h-10 rounded-full bg-purple-500 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-zinc-900 dark:text-zinc-100 mb-2">Real-Time Trend Analysis</h3>
                                    <p class="text-sm text-zinc-600 dark:text-zinc-400 mb-3">Monitor player trending patterns, waiver wire movements, and market sentiment with automated alerts and data-driven insights.</p>
                                    <div class="text-xs font-mono bg-purple-100 dark:bg-purple-900/30 px-3 py-1 rounded text-purple-700 dark:text-purple-300 inline-block">fetch-trending-players</div>
                                </div>
                            </div>

                            <div class="flex items-start gap-4 p-6 rounded-xl bg-gradient-to-r from-orange-50/50 to-red-50/50 dark:from-orange-900/10 dark:to-red-900/10 border border-orange-200/30 dark:border-orange-800/30">
                                <div class="w-10 h-10 rounded-full bg-orange-500 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-zinc-900 dark:text-zinc-100 mb-2">Comprehensive Player Analytics</h3>
                                    <p class="text-sm text-zinc-600 dark:text-zinc-400 mb-3">Detailed player data including stats, projections, volatility metrics, performance analysis, and comparative rankings.</p>
                                    <div class="text-xs font-mono bg-orange-100 dark:bg-orange-900/30 px-3 py-1 rounded text-orange-700 dark:text-orange-300 inline-block">fetch-player</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
</x-layouts.app.marketing>
