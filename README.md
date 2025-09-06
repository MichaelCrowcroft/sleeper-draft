# Sleeper Draft MCP Server

A Laravel MCP (Model Context Protocol) server that exposes tools for Sleeper fantasy football analytics: player trending data, ADP rankings, season statistics, and projections powered by Laravel's robust data layer.

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

- Player Analytics: trending players, ADP rankings, season statistics
- Fantasy Data Access: comprehensive player data with projections and performance metrics
- Market Intelligence: real-time player trends and draft positioning
- Season Analysis: historical stats and current season projections
- GPT Actions Compatible: optimized endpoints for OpenAI Custom GPT integration
- Laravel Powered: built with Laravel's robust ORM and caching capabilities

## Tools Reference

### Player Analytics & Data
- `fetch-trending-players` — Get trending players based on adds/drops in the last 24 hours.
- `fetch-adp-players` — Get top players by Average Draft Position (ADP).
- `fetch-players-season-data` — Get season stats and projections for multiple players.
- `fetch-player-season-data` — Get detailed season data for a specific player by ID or name.

## Usage Examples

### Fetch Trending Players
```bash
curl -X POST https://www.sleeperdraft.com/api/mcp/tools/fetch-trending-players \
  -H "Content-Type: application/json" \
  -d '{"type": "add"}'
```

### Get ADP Rankings
```bash
curl -X POST https://www.sleeperdraft.com/api/mcp/tools/fetch-adp-players \
  -H "Content-Type: application/json" \
  -d '{}'
```

### Get Player Season Data
```bash
curl -X POST https://www.sleeperdraft.com/api/mcp/tools/fetch-player-season-data \
  -H "Content-Type: application/json" \
  -d '{"player_id": "4046"}'
```

### Get Multiple Players Data
```bash
curl -X POST https://www.sleeperdraft.com/api/mcp/tools/fetch-players-season-data \
  -H "Content-Type: application/json" \
  -d '{"limit": 10}'
```

## API Endpoints

- **MCP Server**: `https://www.sleeperdraft.com/mcp`
- **Tools List**: `https://www.sleeperdraft.com/api/mcp/tools`
- **OpenAPI Spec**: `https://www.sleeperdraft.com/api/openapi.yaml`
- **Health Check**: `https://www.sleeperdraft.com/api/health`
