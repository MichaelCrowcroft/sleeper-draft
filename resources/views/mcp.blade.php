<x-layouts.app.marketing>
    <div class="min-h-screen bg-[#FDFDFC] dark:bg-[#0a0a0a] text-[#1b1b18] dark:text-[#EDEDEC] px-4 sm:px-6 py-8 sm:py-12 break-words overflow-x-hidden">
        <div class="mx-auto max-w-6xl w-full">
            @include('partials.marketing-nav')

            <!-- Hero Section -->
            <div class="rounded-2xl border border-black/10 bg-white p-6 sm:p-8 shadow-sm dark:border-white/10 dark:bg-[#141414] mb-8">
                <div class="text-center">
                    <h1 class="text-3xl leading-tight font-semibold sm:text-4xl lg:text-5xl mb-4">MCP Tools for Fantasy Football</h1>
                    <p class="text-lg text-[#575654] dark:text-[#A1A09A] max-w-3xl mx-auto">
                        This Laravel server exposes a Model Context Protocol (MCP) endpoint with tools for Sleeper: unified data access, fantasy recommendations, lineup management, strategy tools, and core utilities.
                    </p>
                </div>
            </div>

            <!-- Setup Instructions -->
            <div class="rounded-xl border border-black/10 bg-[#FCFCFB] p-4 sm:p-5 shadow-sm dark:border-white/10 dark:bg-[#0f0f0f] mb-8">
                <div class="mb-2 text-[12px] font-semibold tracking-wide text-[#706f6c] uppercase dark:text-[#A1A09A]"> Quickstart — Claude and Cursor </div>
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
                <p class="mt-4 text-[12px] leading-6 text-[#706f6c] dark:text-[#A1A09A]">
                    Restart your client. In Cursor, manage servers in Settings → MCP; in Claude, the server appears under MCP servers.
                </p>
            </div>

            <!-- MCP Overview -->
            <div class="rounded-xl border border-black/10 bg-[#FCFCFB] p-4 sm:p-5 shadow-sm dark:border-white/10 dark:bg-[#0f0f0f] mb-8">
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

            <!-- Tools Reference -->
            <div class="rounded-xl border border-black/10 bg-[#FCFCFB] p-4 sm:p-5 shadow-sm dark:border-white/10 dark:bg-[#0f0f0f] mb-8">
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

            <!-- Quick Connect Endpoints -->
            <div class="rounded-xl border border-black/10 bg-[#FCFCFB] p-4 sm:p-5 shadow-sm dark:border-white/10 dark:bg-[#0f0f0f]">
                <div class="mb-3 text-[12px] font-semibold tracking-wide text-[#706f6c] uppercase dark:text-[#A1A09A]">Endpoints</div>
                <div class="space-y-4">
                    <div class="rounded-md border border-black/10 bg-white p-4 dark:border-white/10 dark:bg-[#0f0f0f]">
                        <div class="mb-2 text-[12px] tracking-wide text-[#706f6c] uppercase dark:text-[#A1A09A]">Live MCP Endpoint</div>
                        <div class="rounded-md bg-[#FCFCFB] px-3 py-2 text-[13px] break-all shadow-inner select-all dark:bg-[#161615] max-w-full overflow-hidden"> https://www.sleeperdraft.com/mcp </div>
                    </div>
                    <div class="rounded-md border border-black/10 bg-white p-4 dark:border-white/10 dark:bg-[#0f0f0f]">
                        <div class="mb-2 text-[12px] tracking-wide text-[#706f6c] uppercase dark:text-[#A1A09A]">Local MCP Endpoint</div>
                        <div class="rounded-md bg-[#FCFCFB] px-3 py-2 text-[13px] break-all shadow-inner select-all dark:bg-[#161615] max-w-full overflow-hidden"> {{ url('/mcp') }} </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app.marketing>
