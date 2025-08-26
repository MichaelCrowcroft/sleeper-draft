## Fantasy Football MCP Server (Sleeper + ESPN)

An HTTP Model Context Protocol (MCP) server built with Laravel that gives LLMs tool access to consolidated fantasy football data and workflows: unified data access, fantasy recommendations, lineup management, and core utilities.

### What this provides

- **Unified Data Access**: Single tool for leagues, rosters, drafts, players, and transactions
- **Fantasy Recommendations**: Consolidated tool for draft picks, waiver acquisitions, trade analysis, and playoff planning
- **Lineup Management**: Unified tool for optimization, validation, and player comparisons
- **Core Utilities**: Time resolution, health checks, cache management, and tool discovery
- **Sleeper Integration**: Primary data source with reliable API access
- **Authentication Support**: Optional user authentication via Sanctum tokens for personalized data access

## Quick start

### Requirements

- PHP 8.2+
- Composer
- Node.js 18+ (optional; only if you want to run the full Laravel + Vite dev environment)

### Setup

```bash
git clone <this-repo>
cd fantasy-football-mcp
composer install
cp .env.example .env
php artisan key:generate

# Optional: set up DB-backed cache/queue and run migrations
# php artisan migrate

# Start the server (default: http://127.0.0.1:8000)
php artisan serve
```

Or connect directly to the live MCP endpoint at
`https://www.sleeperdraft.com/mcp` without running the server locally.

## Connecting to Claude Desktop and Cursor

### Claude Desktop Setup

Create or edit: `~/Library/Application Support/Claude/claude_desktop_config.json`

**Configuration:**

```json
{
    "mcpServers": {
        "fantasy-football-mcp": {
            "command": "npx",
            "args": ["-y", "supergateway", "--streamableHttp", "https://www.sleeperdraft.com/mcp"]
        }
    }
}
```

### Cursor Setup

Create or edit: `~/.cursor/mcp.json`

**Configuration:**

```json
{
    "mcpServers": {
        "fantasy-football-mcp": {
            "transport": {
                "type": "http",
                "url": "https://www.sleeperdraft.com/mcp"
            }
        }
    }
}
```

### Testing the Connection

Restart Claude Desktop or Cursor after saving the config. Try these prompts:

- "List available tools."
- "Check the health of the MCP server."
- "Look up Sleeper user 'username' and show their leagues."

## MCP overview

This MCP server exposes consolidated fantasy football data and workflows as tools your assistant can call. High‑level capabilities:

- **Core Data Tools**: User lookups, league listings, matchups, standings, ADP, projections
- **Unified Data Access**: Single tool for leagues, rosters, drafts, players, transactions
- **Fantasy Recommendations**: Draft picks, waiver acquisitions, trade analysis, playoff planning
- **Lineup Management**: Optimization, validation, and player comparisons
- **Analysis & Strategy**: Roster needs, strategy configuration, FAAB optimization
- **Utilities**: Time resolution, health checks, cache management, tool discovery

## Typical flows

### Unauthenticated Usage

- Find a user and their leagues
    1. `user_lookup` with `username`
    2. `user_leagues` with the returned `user_id`, optional `season` and `sport`

## Tools reference

The server exposes a small set of consolidated tools. Each tool accepts JSON arguments as described in its schema.

### Sleeper: Users and Leagues

- **user_lookup** — Get Sleeper user by username. When authenticated, falls back to your connected account.
- **user_leagues** — List leagues for a user in a season.

### Unified Data Access

- **unified_data** — Fetch leagues, rosters, drafts, players, or transactions by setting `data_type`.
- **league_matchups** — Weekly matchups for a league.
- **league_standings** — Computed standings from records and points.

### Players and Market

- **players_trending** — Trending adds/drops over a window.
- **projections_week** — Weekly projections for a season/week.
- **adp_get** — Current ADP/market values.

### Fantasy Recommendations

- **fantasy_recommendations** — Draft picks, waiver acquisitions, trade analysis, and playoff planning.

### Lineup Management

- **unified_lineup** — Optimization, validation, and player comparisons.

### Strategy

- **strategy_set** — Configure draft/season strategy levers.

### Utilities

- **time_resolve_week** — Resolve current season/week from Sleeper state.
- **health_check** — Verify MCP server and Sleeper reachability.
- **cache_invalidate** — Invalidate cached keys by scope.
- **tool_list** — Return available tools and short descriptions.
## Troubleshooting

- Ensure the server is running: `php artisan serve` (default port 8000)
- Verify reachability with `health_check` via `tools/call`
- If you see cache-related issues, consider running `php artisan config:clear` and retry
- For authentication issues, verify your token is valid and has the `mcp:access` ability
- Check that your Sleeper username is connected in Settings → Sleeper
- For production, authentication and rate limiting are configured in `config/mcp-server.php`
- If tools don't auto-detect your user, try the unauthenticated approach with explicit parameters

## License

MIT
