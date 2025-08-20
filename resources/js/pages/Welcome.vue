<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import sleeperIcon from '../../images/sleeper-draft-icon.png';

const claudeConfig = `{
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
}`;

const cursorConfig = `{
  "mcpServers": {
    "fantasy-football-mcp": {
      "transport": {
        "type": "http",
        "url": "https://www.sleeperdraft.com/mcp"
      }
    }
  }
}`;

const cursorConfigAuth = `{
  "mcpServers": {
    "fantasy-football-mcp": {
      "transport": {
        "type": "http",
        "url": "https://www.sleeperdraft.com/mcp",
        "headers": {
          "Authorization": "Bearer YOUR_TOKEN_HERE"
        }
      }
    }
  }
}`;

const claudeConfigAuth = `{
  "mcpServers": {
    "fantasy-football-mcp": {
      "command": "npx",
      "args": [
        "-y",
        "supergateway",
        "--streamableHttp",
        "https://www.sleeperdraft.com/mcp",
        "--oauth2Bearer",
        "YOUR_TOKEN_HERE"
      ]
    }
  }
}`;
</script>

<template>
    <Head title="MCP Tools for Fantasy Football">
    </Head>
    <div class="min-h-screen bg-[#FDFDFC] px-6 py-12 text-[#1b1b18] dark:bg-[#0a0a0a] dark:text-[#EDEDEC]">
        <div class="mx-auto max-w-6xl">
            <div class="rounded-2xl border border-black/10 bg-white p-8 shadow-sm dark:border-white/10 dark:bg-[#141414]">
                <div class="grid items-start gap-10 lg:grid-cols-2">
                    <div>
                        <div class="flex items-center gap-4">
                            <img :src="sleeperIcon" alt="Sleeper Draft" class="size-24" />
                            <h1 class="text-3xl font-semibold leading-snug sm:text-4xl">
                                MCP Tools for Fantasy Football
                            </h1>
                        </div>
                        <p class="mt-3 text-[15px] leading-7 text-[#575654] dark:text-[#A1A09A]">
                            This Laravel server exposes a Model Context Protocol (MCP) endpoint with tools for Sleeper and ESPN: users, leagues, rosters, drafts, transactions, waivers, projections, ADP, and more.
                        </p>

                        <!-- Authentication Section -->
                        <div class="mt-6 rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-950/20">
                            <div class="flex items-start gap-3">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-blue-600 dark:text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="flex-1">
                                    <h3 class="text-sm font-semibold text-blue-900 dark:text-blue-100">Enhanced Experience with Authentication</h3>
                                    <p class="mt-1 text-sm text-blue-800 dark:text-blue-200">
                                        Create an account and connect your Sleeper username to unlock personalized tools that automatically use your leagues and data. No authentication required for basic functionality.
                                    </p>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <Button as-child size="sm" class="bg-blue-600 hover:bg-blue-700 dark:bg-blue-700 dark:hover:bg-blue-600">
                                            <Link href="/register">Create Account</Link>
                                        </Button>
                                        <Button as-child variant="outline" size="sm" class="border-blue-300 text-blue-700 hover:bg-blue-100 dark:border-blue-600 dark:text-blue-300 dark:hover:bg-blue-900/30">
                                            <Link href="/login">Sign In</Link>
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 flex flex-wrap items-center gap-3">
                            <a
                                href="https://github.com/MichaelCrowcroft/fantasy-football-mcp"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center gap-2 rounded-md border border-black bg-black px-4 py-2 text-sm font-medium text-white hover:bg-[#1b1b18] dark:border-white dark:bg-white dark:text-[#1b1b18] dark:hover:bg-[#EDEDEC]"
                            >
                                View GitHub repo
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                                    <path fill-rule="evenodd" d="M5 10a1 1 0 011-1h5.586L9.293 6.707a1 1 0 111.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 11-1.414-1.414L11.586 11H6a1 1 0 01-1-1z" clip-rule="evenodd" />
                                </svg>
                            </a>
                            <a
                                href="https://donate.stripe.com/3cI7sKcfB6sB0FC33le3e00"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center gap-2 rounded-md border border-[#2563eb]/20 bg-[#eff6ff] px-4 py-2 text-sm font-medium text-[#2563eb] hover:bg-[#dbeafe] dark:border-[#60a5fa]/30 dark:bg-[#172554] dark:text-[#93c5fd] dark:hover:bg-[#1e293b]"
                            >
                                Support the project
                            </a>
                        </div>

                        <div class="mt-8 space-y-2 text-[13px] leading-6">
                            <div class="font-semibold">Quick connect</div>
                            <div class="rounded-md border border-black/10 bg-[#FCFCFB] p-4 dark:border-white/10 dark:bg-[#0f0f0f]">
                                <div class="mb-2 text-[12px] uppercase tracking-wide text-[#706f6c] dark:text-[#A1A09A]">Endpoint</div>
                                <div class="select-all break-all rounded-md bg-white px-3 py-2 text-[13px] shadow-inner dark:bg-[#161615]">https://www.sleeperdraft.com/mcp</div>
                                <div class="mt-2 text-[11px] text-[#706f6c] dark:text-[#A1A09A]">
                                    Supports both authenticated and unauthenticated access. Authentication provides personalized features.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <div class="rounded-md border border-black/10 bg-[#FCFCFB] p-4 dark:border-white/10 dark:bg-[#0f0f0f]">
                            <div class="mb-2 text-[12px] uppercase tracking-wide text-[#706f6c] dark:text-[#A1A09A]">Quickstart — Claude and Cursor</div>
                            <ol class="mb-3 list-decimal space-y-1 pl-5 text-[13px] text-[#575654] dark:text-[#A1A09A]">
                                <li>Close your client (Claude Desktop or Cursor).</li>
                                <li>
                                    Create or edit the config file:
                                    <ul class="mt-1 list-disc space-y-1 pl-5">
                                        <li>Claude Desktop: <span class="rounded bg-white px-1.5 py-0.5 text-[12px] dark:bg-[#161615]">~/Library/Application Support/Claude/claude_desktop_config.json</span></li>
                                        <li>Cursor: <span class="rounded bg-white px-1.5 py-0.5 text-[12px] dark:bg-[#161615]">~/.cursor/mcp.json</span></li>
                                    </ul>
                                </li>
                                <li>Add this server:</li>
                            </ol>
                            <div class="space-y-4">
                                <!-- Basic Configuration -->
                                <div>
                                    <div class="mb-2 text-[12px] font-medium text-[#575654] dark:text-[#A1A09A]">Basic Setup (No Authentication)</div>
                                    <div class="space-y-3">
                                        <div>
                                            <div class="mb-1 text-[12px] text-[#706f6c] dark:text-[#A1A09A]">Claude Desktop</div>
                                            <div class="overflow-auto rounded-md border border-[#2d2d2d] bg-[#1e1e1e] p-3 text-[12px] leading-relaxed shadow-inner text-white dark:border-[#2d2d2d] dark:bg-[#1e1e1e] dark:text-white">
                                                <highlightjs language="json" :code="claudeConfig" :autodetect="false" />
                                            </div>
                                        </div>
                                        <div>
                                            <div class="mb-1 text-[12px] text-[#706f6c] dark:text-[#A1A09A]">Cursor</div>
                                            <div class="overflow-auto rounded-md border border-[#2d2d2d] bg-[#1e1e1e] p-3 text-[12px] leading-relaxed shadow-inner text-white dark:border-[#2d2d2d] dark:bg-[#1e1e1e] dark:text-white">
                                                <highlightjs language="json" :code="cursorConfig" :autodetect="false" />
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Authenticated Configuration -->
                                <div>
                                    <div class="mb-2 text-[12px] font-medium text-[#575654] dark:text-[#A1A09A]">Enhanced Setup (With Authentication)</div>
                                    <div class="rounded-lg border border-blue-200 bg-blue-50/30 p-3 dark:border-blue-800 dark:bg-blue-950/20">
                                        <div class="mb-2 text-[11px] text-blue-800 dark:text-blue-200">
                                            For personalized features, create an account and add authentication:
                                        </div>
                                        <ol class="mb-3 list-decimal space-y-1 pl-5 text-[11px] text-blue-800 dark:text-blue-200">
                                            <li>Create account and connect your Sleeper username in Settings → Sleeper</li>
                                            <li>Generate an API token in Settings → API Tokens</li>
                                            <li>Copy the full token (starts with numbers like "1|abc123...")</li>
                                            <li>Use the configuration below, replacing YOUR_TOKEN_HERE with your full token</li>
                                            <li><strong>Important:</strong> Use <code>--oauth2Bearer</code> for Bearer tokens to avoid supergateway command-line parsing issues</li>
                                        </ol>
                                    </div>
                                    <div class="space-y-3">
                                        <div>
                                            <div class="mb-1 text-[12px] text-[#706f6c] dark:text-[#A1A09A]">Claude Desktop (Authenticated)</div>
                                            <div class="overflow-auto rounded-md border border-[#2d2d2d] bg-[#1e1e1e] p-3 text-[12px] leading-relaxed shadow-inner text-white dark:border-[#2d2d2d] dark:bg-[#1e1e1e] dark:text-white">
                                                <highlightjs language="json" :code="claudeConfigAuth" :autodetect="false" />
                                            </div>
                                        </div>
                                        <div>
                                            <div class="mb-1 text-[12px] text-[#706f6c] dark:text-[#A1A09A]">Cursor (Authenticated)</div>
                                            <div class="overflow-auto rounded-md border border-[#2d2d2d] bg-[#1e1e1e] p-3 text-[12px] leading-relaxed shadow-inner text-white dark:border-[#2d2d2d] dark:bg-[#1e1e1e] dark:text-white">
                                                <highlightjs language="json" :code="cursorConfigAuth" :autodetect="false" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <p class="mt-2 text-[12px] leading-6 text-[#706f6c] dark:text-[#A1A09A]">Restart your client. In Cursor, manage servers in Settings → MCP; in Claude, the server appears under MCP servers.</p>
                            <div class="mt-2 p-3 bg-yellow-50 border border-yellow-200 rounded-md dark:bg-yellow-950/20 dark:border-yellow-800">
                                <p class="text-[11px] font-medium text-yellow-800 dark:text-yellow-200 mb-1">⚠️ Important Notes:</p>
                                <ul class="text-[10px] text-yellow-700 dark:text-yellow-300 space-y-1">
                                    <li>• Use the exact server name "fantasy-football-mcp" in your config</li>
                                    <li>• Copy the complete API token including the number prefix (e.g., "1|abc123...")</li>
                                    <li>• For Claude Desktop, use <code>--oauth2Bearer</code> instead of <code>--headers</code> to avoid command-line parsing issues</li>
                                    <li>• If authentication still fails, try creating a new token and updating your config</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-8 rounded-xl border border-black/10 bg-[#FCFCFB] p-5 shadow-sm dark:border-white/10 dark:bg-[#0f0f0f]">
                    <div class="mb-3 text-[12px] font-semibold uppercase tracking-wide text-[#706f6c] dark:text-[#A1A09A]">MCP Overview</div>
                    <ul class="list-disc space-y-1 pl-5 text-[13px] text-[#575654] dark:text-[#A1A09A]">
                        <li><span class="font-medium">Users and leagues</span>: lookups; list leagues by season</li>
                        <li><span class="font-medium">Data access</span>: unified tool for leagues, rosters, drafts, players, transactions</li>
                        <li><span class="font-medium">Players and market</span>: search; trending adds/drops; ADP</li>
                        <li><span class="font-medium">Fantasy recommendations</span>: draft picks, waiver acquisitions, trade analysis, playoff planning</li>
                        <li><span class="font-medium">Lineup management</span>: optimization, validation, and player comparisons</li>
                        <li><span class="font-medium">Projections and ADP</span>: weekly projections and average draft position</li>
                        <li><span class="font-medium">Matchups and standings</span>: league matchups and computed standings</li>
                        <li><span class="font-medium">Player insights</span>: search, trending data, roster needs analysis</li>
                        <li><span class="font-medium">Strategy tools</span>: strategy configuration and FAAB optimization</li>
                        <li><span class="font-medium">Utilities</span>: resolve current week, health check, cache management, tool discovery</li>
                    </ul>
                </div>

                <!-- Authentication Benefits -->
                <div class="mt-6 rounded-xl border border-blue-200 bg-blue-50/50 p-5 shadow-sm dark:border-blue-800 dark:bg-blue-950/10">
                    <div class="mb-3 flex items-center gap-2 text-[12px] font-semibold uppercase tracking-wide text-blue-900 dark:text-blue-100">
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        Authentication Benefits
                    </div>
                    <ul class="list-disc space-y-1 pl-5 text-[13px] text-blue-800 dark:text-blue-200">
                        <li>Auto-detect your Sleeper username and user ID</li>
                        <li>Direct access to your leagues and rosters</li>
                        <li>Personalized recommendations and analysis</li>
                        <li>Streamlined prompts - just ask "Show my leagues"</li>
                        <li>No need to specify usernames or user IDs manually</li>
                    </ul>
                    <div class="mt-3 text-[12px] text-blue-700 dark:text-blue-300">
                        <strong>Note:</strong> Authentication is optional. All tools work without it, but with reduced functionality.
                    </div>
                </div>

                <div class="mt-6 rounded-xl border border-black/10 bg-[#FCFCFB] p-5 shadow-sm dark:border-white/10 dark:bg-[#0f0f0f]">
                    <div class="mb-3 text-[12px] font-semibold uppercase tracking-wide text-[#706f6c] dark:text-[#A1A09A]">Tools Reference</div>
                    <div class="space-y-5 text-[13px] text-[#575654] dark:text-[#A1A09A]">
                        <div>
                            <div class="font-semibold">Sleeper: Users and Leagues</div>
                            <ul class="mt-2 list-disc space-y-1 pl-5">
                                <li><span class="font-medium">user_lookup</span> — Get Sleeper user by username. When authenticated, uses your connected account if no username provided.</li>
                                <li><span class="font-medium">user_leagues</span> — List Sleeper leagues for a user in a season. When authenticated, uses your connected account if no user_id provided.</li>
                            </ul>
                        </div>
                        <div>
                            <div class="font-semibold">Sleeper: League Data</div>
                            <ul class="mt-2 list-disc space-y-1 pl-5">
                                <li><span class="font-medium">league_get</span> — League metadata and settings.</li>
                                <li><span class="font-medium">league_rosters</span> — All rosters for a league.</li>
                                <li><span class="font-medium">league_matchups</span> — Weekly matchups for a league.</li>
                                <li><span class="font-medium">league_transactions</span> — Transactions for a given week.</li>
                                <li><span class="font-medium">league_waivers</span> — Waiver transactions for a given week.</li>
                                <li><span class="font-medium">league_drafts</span> — Drafts for a league.</li>
                                <li><span class="font-medium">draft_picks</span> — Picks for a draft.</li>
                                <li><span class="font-medium">league_standings</span> — Computed standings from records and points.</li>
                            </ul>
                        </div>
                        <div>
                            <div class="font-semibold">Players, Projections, ADP</div>
                            <ul class="mt-2 list-disc space-y-1 pl-5">
                                <li><span class="font-medium">players_search</span> — Search players by name, team, or position.</li>
                                <li><span class="font-medium">players_trending</span> — Trending adds/drops over a window.</li>
                                <li><span class="font-medium">projections_week</span> — Weekly projections for a season/week.</li>
                                <li><span class="font-medium">adp_get</span> — Current ADP/market values (Sleeper). Draft board can blend ESPN ADP.</li>
                            </ul>
                        </div>
                        <div>
                            <div class="font-semibold">Lineups, Start/Sit, Waivers, Trades</div>
                            <ul class="mt-2 list-disc space-y-1 pl-5">
                                <li><span class="font-medium">lineup_validate</span> — Validate a proposed starting lineup.</li>
                                <li><span class="font-medium">lineup_optimize</span> — Recommend optimal starters using projections.</li>
                                <li><span class="font-medium">start_sit_compare</span> — Compare two players for a week.</li>
                                <li><span class="font-medium">waiver_recommendations</span> — Recommend waiver pickups for a roster.</li>
                                <li><span class="font-medium">waiver_optimize_faab</span> — Suggest FAAB bids for candidates.</li>
                                <li><span class="font-medium">trade_analyze</span> — Evaluate a proposed trade.</li>
                            </ul>
                        </div>
                        <div>
                            <div class="font-semibold">Draft Helpers</div>
                            <ul class="mt-2 list-disc space-y-1 pl-5">
                                <li><span class="font-medium">draft_board_build</span> — Build a draft board with positional tiers. Supports blending ESPN ADP with Sleeper.</li>
                                <li><span class="font-medium">draft_pick_recommend</span> — Recommend best picks given needs and board.</li>
                                <li><span class="font-medium">draft_observe</span> — Fetch current draft picks for a live board.</li>
                            </ul>
                        </div>
                        <div>
                            <div class="font-semibold">Roster Analysis</div>
                            <ul class="mt-2 list-disc space-y-1 pl-5">
                                <li><span class="font-medium">roster_needs</span> — Compute roster needs vs. starting slots.</li>
                            </ul>
                        </div>
                        <div>
                            <div class="font-semibold">Projections Blending</div>
                            <ul class="mt-2 list-disc space-y-1 pl-5">
                                <li><span class="font-medium">projections_blend</span> — Blend multiple projection sources.</li>
                                <li><span class="font-medium">projections_blend_espn_sleeper</span> — Blend ESPN fantasy dataset with Sleeper projections and trending.</li>
                            </ul>
                        </div>
                        <div>
                            <div class="font-semibold">ESPN: Data</div>
                            <ul class="mt-2 list-disc space-y-1 pl-5">
                                <li><span class="font-medium">espn_core_athletes_get</span> — Get athletes from ESPN Core API.</li>
                                <li><span class="font-medium">espn_fantasy_players_get</span> — Get fantasy players from ESPN Fantasy API (supports views and X‑Fantasy‑Filter).</li>
                            </ul>
                        </div>
                        <div>
                            <div class="font-semibold">Planning and Preferences</div>
                            <ul class="mt-2 list-disc space-y-1 pl-5">
                                <li><span class="font-medium">playoffs_plan</span> — Highlight playoff weeks schedule and targets.</li>
                                <li><span class="font-medium">strategy_set</span> — Configure draft/season strategy levers.</li>
                            </ul>
                        </div>
                        <div>
                            <div class="font-semibold">Utility</div>
                            <ul class="mt-2 list-disc space-y-1 pl-5">
                                <li><span class="font-medium">context_set_defaults</span> — Set default username/user_id, league_id, sport, season, week.</li>
                                <li><span class="font-medium">time_resolve_week</span> — Resolve current season/week from Sleeper state.</li>
                                <li><span class="font-medium">health_check</span> — Verify MCP server and Sleeper reachability.</li>
                                <li><span class="font-medium">cache_invalidate</span> — Invalidate cached keys by scope.</li>
                                <li><span class="font-medium">tool_list</span> — Return available tools and short descriptions.</li>
                                <li><span class="font-medium">tool_schema</span> — Return the JSON schema of a tool.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
