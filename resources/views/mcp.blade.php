@php
$title = 'Fantasy Football MCP - AI-Powered Fantasy Analytics & Trade Evaluation';
$description = 'Connect AI assistants to comprehensive fantasy football data through our Model Context Protocol server. Access real-time player analytics, trade evaluation, matchup analysis, and season projections for fantasy football.';
$keywords = 'fantasy football, MCP, model context protocol, AI analytics, trade evaluation, player projections, sleeper API, fantasy football tools, GPT actions';
@endphp

@push('head')
    <meta name="description" content="{{ $description }}">
    <meta name="keywords" content="{{ $keywords }}">
    <meta name="robots" content="index, follow">
    <meta name="author" content="Sleeper Draft">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:site_name" content="Sleeper Draft">

    <!-- Twitter -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title }}">
    <meta name="twitter:description" content="{{ $description }}">

    <!-- Structured Data for SEO -->
    <script type="application/ld+json">
    {
        "\u0040context": "https://schema.org",
        "\u0040type": "WebApplication",
        "name": "Fantasy Football MCP",
        "description": "{{ $description }}",
        "applicationCategory": "SportsApplication",
        "operatingSystem": "Web",
        "offers": {
            "@type": "Offer",
            "price": "0",
            "priceCurrency": "USD"
        },
        "featureList": [
            "AI-powered trade evaluation",
            "Real-time player analytics",
            "Matchup analysis with win probabilities",
            "Season projections and statistics",
            "GPT Actions integration",
            "Sleeper API integration"
        ],
        "provider": {
            "@type": "Organization",
            "name": "Sleeper Draft"
        }
    }
    </script>
@endpush

<x-layouts.app.marketing :title="$title">
            <!-- Hero Section -->
            <div class="relative pt-16 pb-16 sm:pt-20 sm:pb-20">
                <div class="relative rounded-3xl border border-zinc-200/60 bg-white/60 backdrop-blur-sm p-8 sm:p-12 shadow-2xl shadow-zinc-900/5 dark:border-zinc-800/60 dark:bg-zinc-900/60 dark:shadow-zinc-100/5 mb-16">
                    <div class="text-center">
                        <div class="inline-flex items-center gap-2 rounded-full bg-emerald-100 px-4 py-2 text-sm font-medium text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300 mb-6">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                            </svg>
                            Developer Documentation
                        </div>

                        <h1 class="text-3xl sm:text-4xl lg:text-5xl xl:text-6xl font-bold tracking-tight mb-6">
                            <span class="bg-gradient-to-br from-zinc-900 via-zinc-800 to-zinc-700 dark:from-zinc-100 dark:via-zinc-200 dark:to-zinc-300 bg-clip-text text-transparent">Fantasy Football MCP</span>
                            <br>
                            <span class="bg-gradient-to-r from-emerald-500 to-teal-600 bg-clip-text text-transparent">AI-Powered Analytics</span>
                        </h1>

                        <p class="text-xl text-zinc-600 dark:text-zinc-400 max-w-4xl mx-auto mb-12 leading-relaxed">
                            Connect AI assistants to comprehensive fantasy football data through our Model Context Protocol server. Access real-time player analytics, trade evaluation, matchup analysis, and season projections powered by Laravel's robust data layer and Sleeper API integration.
                        </p>

                        <div class="flex flex-col sm:flex-row justify-center gap-4">
                            <a href="https://chatgpt.com/g/g-68b45e513c9881918831ae2a2dc294a5" target="_blank" rel="noopener noreferrer"
                               class="group relative inline-flex items-center justify-center gap-3 rounded-2xl bg-gradient-to-r from-emerald-500 to-teal-600 px-8 py-4 text-lg font-semibold text-white shadow-xl shadow-emerald-500/25 transition-all duration-300 hover:shadow-2xl hover:shadow-emerald-500/40 hover:scale-105">
                                <span class="relative z-10">Try the Custom GPT</span>
                                <svg class="relative z-10 h-5 w-5 transition-transform group-hover:translate-x-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5 10a1 1 0 011-1h5.586L9.293 6.707a1 1 0 111.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 11-1.414-1.414L11.586 11H6a1 1 0 01-1-1z" clip-rule="evenodd" />
                                </svg>
                            </a>

                            <a href="{{ route('home') }}"
                               class="group inline-flex items-center justify-center gap-3 rounded-2xl border-2 border-zinc-200 bg-white/80 backdrop-blur-sm px-8 py-4 text-lg font-semibold text-zinc-900 shadow-lg transition-all duration-300 hover:border-zinc-300 hover:shadow-xl hover:scale-105 dark:border-zinc-700 dark:bg-zinc-800/80 dark:text-zinc-100 dark:hover:border-zinc-600">
                                <span>Back to Home</span>
                                <svg class="h-5 w-5 transition-transform group-hover:translate-x-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5 10a1 1 0 011-1h5.586L9.293 6.707a1 1 0 111.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 11-1.414-1.414L11.586 11H6a1 1 0 01-1-1z" clip-rule="evenodd" />
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Background decoration -->
                <div class="absolute inset-0 -z-10">
                    <div class="absolute top-10 left-10 w-72 h-72 bg-gradient-to-br from-emerald-400/20 to-teal-600/20 rounded-full blur-3xl animate-pulse"></div>
                    <div class="absolute bottom-10 right-10 w-96 h-96 bg-gradient-to-br from-emerald-400/10 to-teal-600/10 rounded-full blur-3xl animate-pulse delay-1000"></div>
                </div>
            </div>

            <div class="grid lg:grid-cols-2 gap-8 pb-16">
                <!-- Setup Instructions -->
                <div class="relative rounded-3xl border border-zinc-200/60 bg-white/60 backdrop-blur-sm p-8 shadow-2xl shadow-zinc-900/5 dark:border-zinc-800/60 dark:bg-zinc-900/60 dark:shadow-zinc-100/5">
                    <div class="mb-8">
                        <div class="inline-flex items-center gap-2 rounded-full bg-emerald-100 px-4 py-2 text-sm font-medium text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300 mb-4">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                            </svg>
                            Quickstart
                        </div>
                        <h2 class="text-2xl sm:text-3xl font-bold tracking-tight mb-4">Claude & Cursor Setup</h2>
                        <p class="text-zinc-600 dark:text-zinc-400 mb-6">Connect your AI assistant to fantasy football data in three simple steps</p>
                    </div>

                    <div class="space-y-6">
                        <div class="flex items-start gap-4">
                            <div class="flex-shrink-0 w-8 h-8 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300 text-sm font-bold flex items-center justify-center">1</div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-zinc-600 dark:text-zinc-400">Close your client (Claude Desktop or Cursor)</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-4">
                            <div class="flex-shrink-0 w-8 h-8 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300 text-sm font-bold flex items-center justify-center">2</div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-zinc-600 dark:text-zinc-400 mb-2">Create or edit the config file:</p>
                                <ul class="space-y-1 text-xs text-zinc-500 dark:text-zinc-500">
                                    <li>• <span class="font-mono bg-zinc-100 dark:bg-zinc-800 px-2 py-0.5 rounded">~/Library/Application Support/Claude/claude_desktop_config.json</span></li>
                                    <li>• <span class="font-mono bg-zinc-100 dark:bg-zinc-800 px-2 py-0.5 rounded">~/.cursor/mcp.json</span></li>
                                </ul>
                            </div>
                        </div>

                        <div class="flex items-start gap-4">
                            <div class="flex-shrink-0 w-8 h-8 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300 text-sm font-bold flex items-center justify-center">3</div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-zinc-600 dark:text-zinc-400">Add this server using the configuration examples below</p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 space-y-6">
                        <div>
                            <div class="mb-3 text-sm font-semibold text-zinc-900 dark:text-zinc-100">Claude Desktop Configuration</div>
                            <div class="rounded-lg border border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/50 overflow-hidden">
                                <div class="w-full overflow-x-auto p-4">
                                    <x-phiki::code grammar="json" theme="github-light">{
  "mcpServers": {
    "fantasy-football-mcp": {
      "command": "npx",
      "args": [
        "-y",
        "supergateway",
        "--streamableHttp",
        "https://www.sleeperdraft.com/mcp"
      ]
    }
  }
}</x-phiki::code>
                                </div>
                            </div>
                        </div>

                        <div>
                            <div class="mb-3 text-sm font-semibold text-zinc-900 dark:text-zinc-100">Cursor Configuration</div>
                            <div class="rounded-lg border border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/50 overflow-hidden">
                                <div class="w-full overflow-x-auto p-4">
                                    <x-phiki::code grammar="json" theme="github-light">{
  "mcpServers": {
    "fantasy-football-mcp": {
      "transport": {
        "type": "http",
        "url": "https://www.sleeperdraft.com/mcp"
      }
    }
  }
}</x-phiki::code>
                                </div>
                            </div>
                        </div>
                    </div>

                    <p class="mt-6 text-xs text-zinc-500 dark:text-zinc-500">
                        Restart your client. In Cursor, manage servers in Settings → MCP; in Claude, the server appears under MCP servers.
                    </p>
                </div>

                <!-- MCP Overview & Features -->
                <div class="space-y-8">
                    <!-- MCP Overview -->
                    <div class="relative rounded-3xl border border-zinc-200/60 bg-white/60 backdrop-blur-sm p-8 shadow-2xl shadow-zinc-900/5 dark:border-zinc-800/60 dark:bg-zinc-900/60 dark:shadow-zinc-100/5">
                        <div class="mb-6">
                            <div class="inline-flex items-center gap-2 rounded-full bg-emerald-100 px-4 py-2 text-sm font-medium text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300 mb-4">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Features
                            </div>
                            <h2 class="text-2xl font-bold tracking-tight mb-4">MCP Overview</h2>
                        </div>

                        <ul class="space-y-3 text-sm text-zinc-600 dark:text-zinc-400">
                            <li class="flex items-start gap-3">
                                <div class="w-1.5 h-1.5 bg-emerald-500 rounded-full mt-2 flex-shrink-0"></div>
                                <div><span class="font-semibold text-zinc-900 dark:text-zinc-100">Trade Evaluation</span>: AI-powered trade analysis with stat comparisons and confidence recommendations</div>
                            </li>
                            <li class="flex items-start gap-3">
                                <div class="w-1.5 h-1.5 bg-emerald-500 rounded-full mt-2 flex-shrink-0"></div>
                                <div><span class="font-semibold text-zinc-900 dark:text-zinc-100">Matchup Analysis</span>: enriched matchups with win probabilities and player projections</div>
                            </li>
                            <li class="flex items-start gap-3">
                                <div class="w-1.5 h-1.5 bg-emerald-600 rounded-full mt-2 flex-shrink-0"></div>
                                <div><span class="font-semibold text-zinc-900 dark:text-zinc-100">Player Analytics</span>: comprehensive stats, projections, and volatility metrics for all players</div>
                            </li>
                            <li class="flex items-start gap-3">
                                <div class="w-1.5 h-1.5 bg-emerald-700 rounded-full mt-2 flex-shrink-0"></div>
                                <div><span class="font-semibold text-zinc-900 dark:text-zinc-100">Trending Data</span>: real-time player adds/drops and market intelligence</div>
                            </li>
                            <li class="flex items-start gap-3">
                                <div class="w-1.5 h-1.5 bg-teal-500 rounded-full mt-2 flex-shrink-0"></div>
                                <div><span class="font-semibold text-zinc-900 dark:text-zinc-100">League Integration</span>: free agent tracking and roster management for your leagues</div>
                            </li>
                            <li class="flex items-start gap-3">
                                <div class="w-1.5 h-1.5 bg-emerald-800 rounded-full mt-2 flex-shrink-0"></div>
                                <div><span class="font-semibold text-zinc-900 dark:text-zinc-100">GPT Actions Ready</span>: optimized endpoints for seamless OpenAI Custom GPT integration</div>
                            </li>
                        </ul>
                    </div>

                    <!-- Tools Reference -->
                    <div class="relative rounded-3xl border border-zinc-200/60 bg-white/60 backdrop-blur-sm p-8 shadow-2xl shadow-zinc-900/5 dark:border-zinc-800/60 dark:bg-zinc-900/60 dark:shadow-zinc-100/5">
                        <div class="mb-6">
                            <div class="inline-flex items-center gap-2 rounded-full bg-emerald-100 px-4 py-2 text-sm font-medium text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300 mb-4">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                API Reference
                            </div>
                            <h2 class="text-2xl font-bold tracking-tight mb-4">Available Tools</h2>
                        </div>

                        <div class="space-y-4">
                            <div class="rounded-xl border border-emerald-200/50 bg-gradient-to-r from-emerald-50/30 to-teal-50/30 p-4 dark:border-emerald-800/50 dark:from-emerald-900/5 dark:to-teal-900/5">
                                <h3 class="font-semibold text-zinc-900 dark:text-zinc-100 mb-2">Fantasy Football Tools</h3>
                                <ul class="space-y-2 text-sm text-zinc-600 dark:text-zinc-400">
                                    <li class="flex items-start gap-2">
                                        <span class="font-mono text-xs bg-emerald-100 dark:bg-emerald-900/30 px-2 py-0.5 rounded text-emerald-700 dark:text-emerald-300 mt-0.5 flex-shrink-0">evaluate-trade</span>
                                        <span>AI-powered trade analysis with stat comparisons and confidence recommendations</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <span class="font-mono text-xs bg-emerald-100 dark:bg-emerald-900/30 px-2 py-0.5 rounded text-emerald-700 dark:text-emerald-300 mt-0.5 flex-shrink-0">fetch-matchups</span>
                                        <span>Get enriched matchups with win probabilities and player projections</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <span class="font-mono text-xs bg-emerald-100 dark:bg-emerald-900/30 px-2 py-0.5 rounded text-emerald-700 dark:text-emerald-300 mt-0.5 flex-shrink-0">fetch-player</span>
                                        <span>Comprehensive player data including stats, projections, and performance analysis</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <span class="font-mono text-xs bg-emerald-100 dark:bg-emerald-900/30 px-2 py-0.5 rounded text-emerald-700 dark:text-emerald-300 mt-0.5 flex-shrink-0">fetch-players</span>
                                        <span>Paginated player list with filtering and league integration</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <span class="font-mono text-xs bg-emerald-100 dark:bg-emerald-900/30 px-2 py-0.5 rounded text-emerald-700 dark:text-emerald-300 mt-0.5 flex-shrink-0">fetch-trending-players</span>
                                        <span>Real-time trending data based on adds/drops in the last 24 hours</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <span class="font-mono text-xs bg-emerald-100 dark:bg-emerald-900/30 px-2 py-0.5 rounded text-emerald-700 dark:text-emerald-300 mt-0.5 flex-shrink-0">fetch-user-leagues</span>
                                        <span>Get all leagues for a user by username or user ID</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Usage Examples & Endpoints -->
            <div class="grid lg:grid-cols-2 gap-8 pb-16">
                <!-- Usage Examples -->
                <div class="relative rounded-3xl border border-zinc-200/60 bg-white/60 backdrop-blur-sm p-8 shadow-2xl shadow-zinc-900/5 dark:border-zinc-800/60 dark:bg-zinc-900/60 dark:shadow-zinc-100/5">
                    <div class="mb-8">
                        <div class="inline-flex items-center gap-2 rounded-full bg-emerald-100 px-4 py-2 text-sm font-medium text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300 mb-4">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            Examples
                        </div>
                        <h2 class="text-2xl font-bold tracking-tight mb-4">Usage Examples</h2>
                        <p class="text-zinc-600 dark:text-zinc-400">Direct API calls for custom integrations</p>
                    </div>

                    <div class="space-y-6">
                        <div>
                            <div class="font-semibold text-zinc-900 dark:text-zinc-100 mb-3">Evaluate a Fantasy Trade</div>
                            <div class="rounded-lg border border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/50 overflow-hidden">
                                <div class="w-full overflow-x-auto p-4">
                                    <pre class="text-xs text-zinc-800 dark:text-zinc-200 whitespace-pre"><code>POST /api/mcp/tools/evaluate-trade
{
  "receiving": [
    {"search": "Josh Allen"},
    {"search": "Travis Kelce"}
  ],
  "sending": [
    {"search": "Patrick Mahomes"},
    {"search": "Tyreek Hill"}
  ]
}</code></pre>
                                </div>
                            </div>
                        </div>

                        <div>
                            <div class="font-semibold text-zinc-900 dark:text-zinc-100 mb-3">Get League Matchups</div>
                            <div class="rounded-lg border border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/50 overflow-hidden">
                                <div class="w-full overflow-x-auto p-4">
                                    <pre class="text-xs text-zinc-800 dark:text-zinc-200 whitespace-pre"><code>POST /api/mcp/tools/fetch-matchups
{
  "league_id": "123456789012345678",
  "week": 5
}</code></pre>
                                </div>
                            </div>
                        </div>

                        <div>
                            <div class="font-semibold text-zinc-900 dark:text-zinc-100 mb-3">Get Player Details</div>
                            <div class="rounded-lg border border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/50 overflow-hidden">
                                <div class="w-full overflow-x-auto p-4">
                                    <pre class="text-xs text-zinc-800 dark:text-zinc-200 whitespace-pre"><code>POST /api/mcp/tools/fetch-player
{
  "search": "Christian McCaffrey"
}</code></pre>
                                </div>
                            </div>
                        </div>

                        <div>
                            <div class="font-semibold text-zinc-900 dark:text-zinc-100 mb-3">Get Trending Players</div>
                            <div class="rounded-lg border border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/50 overflow-hidden">
                                <div class="w-full overflow-x-auto p-4">
                                    <pre class="text-xs text-zinc-800 dark:text-zinc-200 whitespace-pre"><code>POST /api/mcp/tools/fetch-trending-players
{
  "type": "add"
}</code></pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Endpoints -->
                <div class="relative rounded-3xl border border-zinc-200/60 bg-white/60 backdrop-blur-sm p-8 shadow-2xl shadow-zinc-900/5 dark:border-zinc-800/60 dark:bg-zinc-900/60 dark:shadow-zinc-100/5">
                    <div class="mb-8">
                        <div class="inline-flex items-center gap-2 rounded-full bg-teal-100 px-4 py-2 text-sm font-medium text-teal-800 dark:bg-teal-900/30 dark:text-teal-300 mb-4">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                            </svg>
                            Endpoints
                        </div>
                        <h2 class="text-2xl font-bold tracking-tight mb-4">API Endpoints</h2>
                    </div>

                    <div class="space-y-6">
                        <div class="group rounded-xl border border-emerald-200/50 bg-gradient-to-r from-emerald-50/30 to-teal-50/30 p-4 transition-all hover:border-emerald-300/50 dark:border-emerald-800/50 dark:from-emerald-900/5 dark:to-teal-900/5 dark:hover:border-emerald-700/50">
                            <div class="mb-2 text-xs font-semibold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">Live MCP Endpoint</div>
                            <div class="relative rounded-lg bg-zinc-100 p-3 dark:bg-zinc-800">
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

                        <div class="group rounded-xl border border-emerald-200/50 bg-gradient-to-r from-emerald-50/30 to-teal-50/30 p-4 transition-all hover:border-emerald-300/50 dark:border-emerald-800/50 dark:from-emerald-900/5 dark:to-teal-900/5 dark:hover:border-emerald-700/50">
                            <div class="mb-2 text-xs font-semibold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">Available Tools</div>
                            <div class="text-sm text-zinc-600 dark:text-zinc-400 mb-3">
                                evaluate-trade, fetch-matchups, fetch-player, fetch-players, fetch-trending-players, fetch-user-leagues
                            </div>
                            <div class="relative rounded-lg bg-zinc-100 p-3 dark:bg-zinc-800">
                                <code class="text-sm font-mono text-zinc-800 dark:text-zinc-200 break-all select-all">https://www.sleeperdraft.com/api/mcp/tools</code>
                                <div class="absolute top-2 right-2">
                                    <button class="rounded-md p-1.5 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 transition-colors" onclick="navigator.clipboard.writeText('https://www.sleeperdraft.com/api/mcp/tools')">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="group rounded-xl border border-emerald-200/50 bg-gradient-to-r from-emerald-50/30 to-teal-50/30 p-4 transition-all hover:border-emerald-300/50 dark:border-emerald-800/50 dark:from-emerald-900/5 dark:to-teal-900/5 dark:hover:border-emerald-700/50">
                            <div class="mb-2 text-xs font-semibold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">OpenAPI Spec</div>
                            <div class="relative rounded-lg bg-zinc-100 p-3 dark:bg-zinc-800">
                                <code class="text-sm font-mono text-zinc-800 dark:text-zinc-200 break-all select-all">https://www.sleeperdraft.com/api/openapi.yaml</code>
                                <div class="absolute top-2 right-2">
                                    <button class="rounded-md p-1.5 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 transition-colors" onclick="navigator.clipboard.writeText('https://www.sleeperdraft.com/api/openapi.yaml')">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
</x-layouts.app.marketing>
