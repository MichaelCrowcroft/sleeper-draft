
<x-layouts.app.marketing>
    <div class="min-h-screen bg-[#FDFDFC] dark:bg-[#0a0a0a] text-[#1b1b18] dark:text-[#EDEDEC] px-4 sm:px-6 py-8 sm:py-12 break-words overflow-x-hidden">
        <div class="mx-auto max-w-6xl w-full">
            <!-- <header class="mb-6 flex justify-end">
                @if (Route::has('login'))
                    <nav class="flex items-center gap-2 sm:gap-4 flex-wrap">
                        @auth
                            <a href="{{ url('/dashboard') }}" class="inline-flex items-center gap-2 rounded-md border border-black/10 bg-white px-3 py-2 text-sm font-medium text-black hover:bg-gray-50 dark:border-white/10 dark:bg-zinc-800 dark:text-white dark:hover:bg-zinc-700">
                                Dashboard
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">
                                Log in
                            </a>
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="inline-flex items-center gap-2 rounded-md border border-black/10 bg-white px-3 py-2 text-sm font-medium text-black hover:bg-gray-50 dark:border-white/10 dark:bg-zinc-800 dark:text-white dark:hover:bg-zinc-700">
                                    Register
                                </a>
                            @endif
                        @endauth
                    </nav>
                @endif
            </header> -->

            <div class="rounded-2xl border border-black/10 bg-white p-6 sm:p-8 shadow-sm dark:border-white/10 dark:bg-[#141414]">
                <div class="grid items-start gap-8 md:gap-10 md:grid-cols-2 w-full min-w-0">
                    <div class="min-w-0">
                        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
                            <x-app-logo-icon class="size-20 sm:size-24 flex-shrink-0" />
                            <h1 class="text-2xl leading-tight font-semibold sm:text-3xl lg:text-4xl">MCP Tools for Fantasy Football</h1>
                        </div>
                        <p class="mt-3 text-[15px] leading-7 text-[#575654] dark:text-[#A1A09A]">
                            This Laravel server exposes a Model Context Protocol (MCP) endpoint with tools for Sleeper: unified data access, fantasy recommendations, lineup management, strategy tools, and core utilities.
                        </p>
                        <div class="mt-6 flex flex-col sm:flex-row flex-wrap items-stretch sm:items-center gap-3">
                            <a href="https://github.com/MichaelCrowcroft/fantasy-football-mcp" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center gap-2 rounded-md border border-black bg-black px-4 py-2 text-sm font-medium text-white hover:bg-[#1b1b18] dark:border-white dark:bg-white dark:text-[#1b1b18] dark:hover:bg-[#EDEDEC]">
                                View GitHub repo
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4"><path fill-rule="evenodd" d="M5 10a1 1 0 011-1h5.586L9.293 6.707a1 1 0 111.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 11-1.414-1.414L11.586 11H6a1 1 0 01-1-1z" clip-rule="evenodd" /></svg>
                            </a>
                            <a href="https://donate.stripe.com/3cI7sKcfB6sB0FC33le3e00" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center gap-2 rounded-md border border-[#2563eb]/20 bg-[#eff6ff] px-4 py-2 text-sm font-medium text-[#2563eb] hover:bg-[#dbeafe] dark:border-[#60a5fa]/30 dark:bg-[#172554] dark:text-[#93c5fd] dark:hover:bg-[#1e293b]">
                                Support the project
                            </a>
                            <a href="https://chatgpt.com/g/g-68b45e513c9881918831ae2a2dc294a5" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center gap-2 rounded-md border border-[#10a37f]/20 bg-[#f0fdf9] px-4 py-2 text-sm font-medium text-[#0c8c67] hover:bg-[#dcfce7] dark:border-[#10a37f]/30 dark:bg-[#052e21] dark:text-[#6ee7b7] dark:hover:bg-[#064e3b]">
                                Try the Custom GPT
                            </a>
                        </div>
                        <div class="mt-8 space-y-2 text-[13px] leading-6">
                            <div class="font-semibold">Quick connect</div>
                            <div class="rounded-md border border-black/10 bg-[#FCFCFB] p-4 dark:border-white/10 dark:bg-[#0f0f0f]">
                                <div class="mb-2 text-[12px] tracking-wide text-[#706f6c] uppercase dark:text-[#A1A09A]">Live MCP Endpoint</div>
                                <div class="rounded-md bg-white px-3 py-2 text-[13px] break-all shadow-inner select-all dark:bg-[#161615] max-w-full overflow-hidden"> https://www.sleeperdraft.com/mcp </div>
                            </div>
                            <div class="rounded-md border border-black/10 bg-[#FCFCFB] p-4 dark:border-white/10 dark:bg-[#0f0f0f]">
                                <div class="mb-2 text-[12px] tracking-wide text-[#706f6c] uppercase dark:text-[#A1A09A]">Local MCP Endpoint</div>
                                <div class="rounded-md bg-white px-3 py-2 text-[13px] break-all shadow-inner select-all dark:bg-[#161615] max-w-full overflow-hidden"> {{ url('/mcp') }} </div>
                            </div>
                        </div>
                    </div>
                    <div class="min-w-0">
                        <div class="rounded-md border border-black/10 bg-[#FCFCFB] p-4 dark:border-white/10 dark:bg-[#0f0f0f]">
                            <div class="mb-2 text-[12px] tracking-wide text-[#706f6c] uppercase dark:text-[#A1A09A]"> Quickstart — Claude and Cursor </div>
                            <ol class="mb-3 list-decimal space-y-1 pl-5 text-[13px] text-[#575654] dark:text-[#A1A09A]">
                                <li>Close your client (Claude Desktop or Cursor).</li>
                                <li> Create or edit the config file:
                                    <ul class="mt-1 list-disc space-y-1 pl-5">
                                        <li> Claude Desktop: <span class="rounded bg-white px-1.5 py-0.5 text-[12px] dark:bg-[#161615]">~/Library/Application Support/Claude/claude_desktop_config.json</span></li>
                                        <li> Cursor: <span class="rounded bg-white px-1.5 py-0.5 text-[12px] dark:bg-[#161615]">~/.cursor/mcp.json</span></li>
                                    </ul>
                                </li>
                                <li>Add this server:</li>
                            </ol>
                            <div class="space-y-4">
                                <div>
                                    <div class="mb-2 text-[12px] font-medium text-[#575654] dark:text-[#A1A09A]">Basic Setup (No Authentication)</div>
                                    <div class="space-y-3">
                                        <div>
                                            <div class="mb-1 text-[12px] text-[#706f6c] dark:text-[#A1A09A]">Claude Desktop</div>
                                            <div class="w-full overflow-x-auto rounded-md border border-[#2d2d2d] bg-[#1e1e1e] p-3 text-[12px] leading-relaxed shadow-inner dark:border-[#2d2d2d] dark:bg-[#1e1e1e]"><pre class="overflow-x-auto max-w-full"><code class="json whitespace-pre">{!! App\Helpers\CodeHighlighter::highlightJson('{
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
}') !!}</code></pre></div>
                                        </div>
                                        <div>
                                            <div class="mb-1 text-[12px] text-[#706f6c] dark:text-[#A1A09A]">Cursor</div>
                                            <div class="w-full overflow-x-auto rounded-md border border-[#2d2d2d] bg-[#1e1e1e] p-3 text-[12px] leading-relaxed shadow-inner dark:border-[#2d2d2d] dark:bg-[#1e1e1e]"><pre class="overflow-x-auto max-w-full"><code class="json whitespace-pre">{!! App\Helpers\CodeHighlighter::highlightJson('{
  "mcpServers": {
    "fantasy-football-mcp": {
      "transport": {
        "type": "http",
        "url": "https://www.sleeperdraft.com/mcp"
      }
    }
  }
}') !!}</code></pre></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p class="mt-2 text-[12px] leading-6 text-[#706f6c] dark:text-[#A1A09A]">
                            Restart your client. In Cursor, manage servers in Settings → MCP; in Claude, the server appears under MCP servers.
                        </p>
                    </div>
                </div>
            </div>

            <div class="mt-6 sm:mt-8 rounded-xl border border-black/10 bg-[#FCFCFB] p-4 sm:p-5 shadow-sm dark:border-white/10 dark:bg-[#0f0f0f]">
                <div class="mb-3 text-[12px] font-semibold tracking-wide text-[#706f6c] uppercase dark:text-[#A1A09A]">MCP Overview</div>
                <ul class="list-disc space-y-1 pl-4 sm:pl-5 text-[13px] text-[#575654] dark:text-[#A1A09A]">
                    <li><span class="font-medium">Users and leagues</span>: lookups; list leagues by season</li>
                    <li><span class="font-medium">Data access</span>: unified tool for leagues, rosters, drafts, players, transactions</li>
                    <li><span class="font-medium">Players and market</span>: projections, ADP, trending data</li>
                    <li><span class="font-medium">Fantasy recommendations</span>: draft picks, waiver acquisitions, trade analysis, playoff planning</li>
                    <li><span class="font-medium">Lineup management</span>: optimization, validation, player comparisons</li>
                    <li><span class="font-medium">Strategy tools</span>: configure draft approach</li>
                    <li><span class="font-medium">Utilities</span>: resolve current week, health check, cache management, tool discovery</li>
                </ul>
            </div>

            <div class="mt-6 rounded-xl border border-black/10 bg-[#FCFCFB] p-4 sm:p-5 shadow-sm dark:border-white/10 dark:bg-[#0f0f0f]">
                <div class="mb-3 text-[12px] font-semibold tracking-wide text-[#706f6c] uppercase dark:text-[#A1A09A]">Tools Reference</div>
                <div class="space-y-5 text-[13px] text-[#575654] dark:text-[#A1A09A]">
                    <div>
                        <div class="font-semibold">Sleeper: Users and Leagues</div>
                        <ul class="mt-2 list-disc space-y-1 pl-4 sm:pl-5">
                            <li><span class="font-medium">user_lookup</span> — Get Sleeper user by username.</li>
                            <li><span class="font-medium">user_leagues</span> — List Sleeper leagues for a user in a season.</li>
                        </ul>
                    </div>
                    <div>
                        <div class="font-semibold">Unified Data</div>
                        <ul class="mt-2 list-disc space-y-1 pl-4 sm:pl-5">
                            <li><span class="font-medium">unified_data</span> — Leagues, rosters, drafts, players, transactions.</li>
                            <li><span class="font-medium">league_matchups</span> — Weekly matchups for a league.</li>
                            <li><span class="font-medium">league_standings</span> — Computed standings from records and points.</li>
                        </ul>
                    </div>
                    <div>
                        <div class="font-semibold">Players &amp; Market</div>
                        <ul class="mt-2 list-disc space-y-1 pl-4 sm:pl-5">
                            <li><span class="font-medium">players_trending</span> — Trending adds/drops.</li>
                            <li><span class="font-medium">projections_week</span> — Weekly projections.</li>
                            <li><span class="font-medium">adp_get</span> — Current ADP values.</li>
                        </ul>
                    </div>
                    <div>
                        <div class="font-semibold">Fantasy Recommendations</div>
                        <ul class="mt-2 list-disc space-y-1 pl-4 sm:pl-5">
                            <li><span class="font-medium">fantasy_recommendations</span> — Draft, waiver, trade, playoff guidance.</li>
                        </ul>
                    </div>
                    <div>
                        <div class="font-semibold">Lineup Management</div>
                        <ul class="mt-2 list-disc space-y-1 pl-4 sm:pl-5">
                            <li><span class="font-medium">unified_lineup</span> — Optimize or validate lineups, compare players.</li>
                        </ul>
                    </div>
                    <div>
                        <div class="font-semibold">Strategy &amp; Utilities</div>
                        <ul class="mt-2 list-disc space-y-1 pl-4 sm:pl-5">
                            <li><span class="font-medium">strategy_set</span> — Configure draft/season strategy levers.</li>
                            <li><span class="font-medium">time_resolve_week</span> — Resolve current season/week.</li>
                            <li><span class="font-medium">health_check</span> — Verify server and Sleeper reachability.</li>
                            <li><span class="font-medium">cache_invalidate</span> — Invalidate cached keys by scope.</li>
                            <li><span class="font-medium">tool_list</span> — List available tools.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app.marketing>
