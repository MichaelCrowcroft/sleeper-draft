# Sleeper Draft MCP Server

A Laravel MCP (Model Context Protocol) server that exposes tools for the Sleeper fantasy football platform — unified data access, fantasy recommendations, lineup management, and utilities.

## Quickstart (Claude Desktop and Cursor)

- Live endpoint: `https://www.sleeperdraft.com/mcp`
- Local (Herd): `http://sleeperdraft.test/mcp`

### Claude Desktop

Create or edit `~/Library/Application Support/Claude/claude_desktop_config.json`:

```json
{
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
}
```

### Cursor

Create or edit `~/.cursor/mcp.json`:

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

Restart your client. In Cursor, manage servers in Settings → MCP; in Claude, the server appears under MCP servers.

## MCP Overview

- Users and leagues: lookups; list leagues by season
- Data access: unified tool for leagues, rosters, drafts, players, transactions
- Players and market: projections, ADP, trending data
- Fantasy recommendations: draft picks, waiver acquisitions, trade analysis, playoff planning
- Lineup management: optimization, validation, player comparisons
- Strategy tools: configure draft approach
- Utilities: resolve current week, health check, cache management, tool discovery

## Tools Reference

### Sleeper: Users and Leagues
- `user_lookup` — Get Sleeper user by username.
- `user_leagues` — List Sleeper leagues for a user in a season.

### Unified Data
- `unified_data` — Leagues, rosters, drafts, players, transactions.
- `league_matchups` — Weekly matchups for a league.
- `league_standings` — Computed standings from records and points.

### Players & Market
- `players_trending` — Trending adds/drops.
- `projections_week` — Weekly projections.
- `adp_get` — Current ADP values.

### Fantasy Recommendations
- `fantasy_recommendations` — Draft, waiver, trade, playoff guidance.

### Lineup Management
- `unified_lineup` — Optimize or validate lineups, compare players.

### Strategy & Utilities
- `strategy_set` — Configure draft/season strategy levers.
- `time_resolve_week` — Resolve current season/week.
- `health_check` — Verify server and Sleeper reachability.
- `cache_invalidate` — Invalidate cached keys by scope.
- `tool_list` — List available tools.
