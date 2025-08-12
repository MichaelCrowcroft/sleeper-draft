## Fantasy Football MCP Server (Sleeper)

An HTTP Model Context Protocol (MCP) server built with Laravel that gives LLMs tool access to Sleeper fantasy football data and workflows: league insights, lineup validation/optimization, waivers, drafting helpers, projections, and utilities.

### What this provides
- Sleeper integration via tools for users, leagues, rosters, drafts, transactions, waivers, projections, ADP, and trending players
- Strategy and planning helpers (draft board, pick recommendations, playoffs planning)
- Roster analyses (needs, standings) and trade/waiver heuristics
- Utility tools (context defaults, time/week resolution, health check, cache, tool listing/schema)


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


## Non-technical quick start (hosted MCP)

If you don’t want to run anything locally, you can use the hosted MCP server.

- Host: [www.sleeperdraft.com](https://www.sleeperdraft.com)
- Endpoint (HTTP JSON-RPC): [https://www.sleeperdraft.com/mcp](https://www.sleeperdraft.com/mcp)

How to connect from an MCP-compatible client (e.g., Cursor, Claude Desktop):

1) Open your client’s settings and find the MCP or “Tool/Server” section.
2) Add a new HTTP/JSON-RPC server and set the endpoint to the link above.
3) Leave auth/headers empty (no API key required). Save/apply.
4) Ask your assistant to list tools and start using them.

Try these prompts once connected:
- “List available tools.”
- “Look up Sleeper user by username ‘your_username’ and list their 2024 leagues.”
- “Resolve the current NFL week, then get weekly projections.”

Notes
- This endpoint uses public Sleeper data; no account or token needed.
- Don’t send secrets. Usage may be rate-limited by upstream APIs.


## MCP overview

This MCP server exposes Sleeper fantasy football data and workflows as tools your assistant can call. High‑level capabilities:

- Users and leagues: lookups, list leagues for a season
- League data: rosters, matchups, transactions, waivers, drafts, computed standings
- Players and market: search, trending adds/drops, ADP
- Projections: weekly projections; blend multiple sources
- Lineups and decisions: validate lineups, optimize starters, start/sit comparisons
- Waivers and trades: waiver recommendations, FAAB optimization, trade analysis
- Draft helpers: draft board building, pick recommendations, live draft observe, draft picks
- Roster analysis: roster needs vs. starting slots
- Planning and preferences: playoff planning, strategy levers
- Utilities: set context defaults, resolve current week, health check, cache invalidation, tool listing/schema


## Typical flows

- Find a user and their leagues
 1) `user_lookup` with `username`
 2) `user_leagues` with the returned `user_id`, optional `season` and `sport`

- Explore a league
  - `league_get`, `league_rosters`, `league_matchups`, `league_transactions`, `league_waivers`, `league_drafts`, `league_standings`

- Weekly operations
  - `time_resolve_week` to auto-detect `season` and `week`
  - `projections_week`, `lineup_validate`, `lineup_optimize`, `start_sit_compare`, `waiver_recommendations`

- Drafting
  - `adp_get`, `projections_week`, `draft_board_build`, `draft_pick_recommend`, `draft_observe`, `draft_picks`


## Tools reference

All tools below are registered in `config/mcp-server.php`. Each entry lists name, description, and input schema. Outputs are summarized where helpful.

### Sleeper: Users and Leagues

- user_lookup
  - Description: Get Sleeper user by username and return user_id and profile info.
  - Input schema:
    ```json
    {"type":"object","required":["username"],"properties":{"username":{"type":"string","minLength":1}},"additionalProperties":false}
    ```

- user_leagues
  - Description: List Sleeper leagues for a user in a season. Use user_lookup tool first to get user_id from username.
  - Input schema:
    ```json
    {"type":"object","required":["user_id"],"properties":{"user_id":{"type":"string"},"season":{"type":"string","default":"YYYY"},"sport":{"type":"string","enum":["nfl","nba","mlb","nhl"],"default":"nfl"}},"additionalProperties":false}
    ```

### Sleeper: League Data

- league_get
  - Description: Get Sleeper league metadata and settings by league_id.
  - Input schema:
    ```json
    {"type":"object","required":["league_id"],"properties":{"league_id":{"type":"string"}},"additionalProperties":false}
    ```

- league_rosters
  - Description: Get all rosters for a Sleeper league.
  - Input schema:
    ```json
    {"type":"object","required":["league_id"],"properties":{"league_id":{"type":"string"}},"additionalProperties":false}
    ```

- league_matchups
  - Description: Get weekly matchups for a Sleeper league.
  - Input schema:
    ```json
    {"type":"object","required":["league_id"],"properties":{"league_id":{"type":"string"},"week":{"type":"integer","minimum":1}},"additionalProperties":false}
    ```

- league_transactions
  - Description: Get league transactions for a given week.
  - Input schema:
    ```json
    {"type":"object","required":["league_id","week"],"properties":{"league_id":{"type":"string"},"week":{"type":"integer","minimum":1}},"additionalProperties":false}
    ```

- league_waivers
  - Description: Alias for league_transactions of type waivers for a given week.
  - Input schema:
    ```json
    {"type":"object","required":["league_id","week"],"properties":{"league_id":{"type":"string"},"week":{"type":"integer","minimum":1}},"additionalProperties":false}
    ```

- league_drafts
  - Description: List drafts for a league.
  - Input schema:
    ```json
    {"type":"object","required":["league_id"],"properties":{"league_id":{"type":"string"}},"additionalProperties":false}
    ```

- draft_picks
  - Description: Get picks for a draft.
  - Input schema:
    ```json
    {"type":"object","required":["draft_id"],"properties":{"draft_id":{"type":"string"}},"additionalProperties":false}
    ```

- league_standings
  - Description: Compute league standings from roster records and points.
  - Input schema:
    ```json
    {"type":"object","required":["league_id"],"properties":{"league_id":{"type":"string"}},"additionalProperties":false}
    ```

### Sleeper: Players, Projections, ADP

- players_search
  - Description: Search players by name, team, or position using the Sleeper players catalog.
  - Input schema:
    ```json
    {"type":"object","required":["query"],"properties":{"query":{"type":"string","minLength":1},"sport":{"type":"string","default":"nfl"},"position":{"type":"string"},"team":{"type":"string"},"limit":{"type":"integer","minimum":1,"default":25}},"additionalProperties":false}
    ```

- players_trending
  - Description: Get trending adds/drops over a lookback window.
  - Input schema:
    ```json
    {"type":"object","properties":{"type":{"type":"string","enum":["add","drop"],"default":"add"},"sport":{"type":"string","default":"nfl"},"lookback_hours":{"type":"integer","minimum":1,"default":24},"limit":{"type":"integer","minimum":1,"default":25}},"additionalProperties":false}
    ```

- projections_week
  - Description: Get weekly projections for a season/week (raw Sleeper output).
  - Input schema:
    ```json
    {"type":"object","required":["season","week"],"properties":{"sport":{"type":"string","default":"nfl"},"season":{"type":"string"},"week":{"type":"integer","minimum":1}},"additionalProperties":false}
    ```

- adp_get
  - Description: Get current ADP/market values.
  - Input schema:
    ```json
    {"type":"object","required":["season"],"properties":{"sport":{"type":"string","default":"nfl"},"season":{"type":"string"},"format":{"type":"string","enum":["redraft","dynasty","bestball"],"default":"redraft"}},"additionalProperties":false}
    ```

### Lineups, Start/Sit, Waivers, Trades

- lineup_validate
  - Description: Validate a proposed starting lineup against league roster settings and roster eligibility.
  - Input schema:
    ```json
    {"type":"object","required":["league_id","roster_id","starters"],"properties":{"league_id":{"type":"string"},"roster_id":{"type":"integer"},"starters":{"type":"array","items":{"type":"string"},"minItems":1},"sport":{"type":"string","default":"nfl"}},"additionalProperties":false}
    ```

- lineup_optimize
  - Description: Recommend an optimal lineup using weekly projections and eligibility constraints.
  - Input schema:
    ```json
    {"type":"object","required":["league_id","roster_id","season","week"],"properties":{"league_id":{"type":"string"},"roster_id":{"type":"integer"},"season":{"type":"string"},"week":{"type":"integer","minimum":1},"sport":{"type":"string","default":"nfl"},"strategy":{"type":"string","enum":["median","ceiling","floor"],"default":"median"}},"additionalProperties":false}
    ```

- start_sit_compare
  - Description: Compare two players for a given week using projections (fallback to approximate position averages).
  - Input schema:
    ```json
    {"type":"object","required":["player_a_id","player_b_id","season","week"],"properties":{"sport":{"type":"string","default":"nfl"},"season":{"type":"string"},"week":{"type":"integer","minimum":1},"player_a_id":{"type":"string"},"player_b_id":{"type":"string"}},"additionalProperties":false}
    ```

- waiver_recommendations
  - Description: Recommend waiver pickups for a roster with simple heuristic using trending + projections.
  - Input schema:
    ```json
    {"type":"object","required":["league_id","roster_id","season","week"],"properties":{"league_id":{"type":"string"},"roster_id":{"type":"integer"},"season":{"type":"string"},"week":{"type":"integer","minimum":1},"sport":{"type":"string","default":"nfl"},"max_candidates":{"type":"integer","minimum":1,"default":10}},"additionalProperties":false}
    ```

- waiver_optimize_faab
  - Description: Suggest FAAB bids for candidates based on projections delta and trend.
  - Input schema:
    ```json
    {"type":"object","required":["league_id","roster_id","season","week","budget"],"properties":{"league_id":{"type":"string"},"roster_id":{"type":"integer"},"season":{"type":"string"},"week":{"type":"integer","minimum":1},"budget":{"type":"number","minimum":0},"sport":{"type":"string","default":"nfl"},"candidates":{"type":"array","items":{"type":"string"}},"max_candidates":{"type":"integer","default":10}},"additionalProperties":false}
    ```

- trade_analyze
  - Description: Evaluate a proposed trade with simple value proxy via ADP and projections.
  - Input schema:
    ```json
    {"type":"object","required":["league_id","season","week","offer"],"properties":{"league_id":{"type":"string"},"season":{"type":"string"},"week":{"type":"integer","minimum":1},"sport":{"type":"string","default":"nfl"},"offer":{"type":"object","required":["from_roster_id","to_roster_id","sending","receiving"],"properties":{"from_roster_id":{"type":"integer"},"to_roster_id":{"type":"integer"},"sending":{"type":"array","items":{"type":"string"}},"receiving":{"type":"array","items":{"type":"string"}}},"additionalProperties":false},"format":{"type":"string","enum":["redraft","dynasty","bestball"],"default":"redraft"}},"additionalProperties":false}
    ```

### Draft Helpers

- draft_board_build
  - Description: Build a draft board from ADP + projections with simple positional tiers.
  - Input schema:
    ```json
    {"type":"object","required":["season","week"],"properties":{"sport":{"type":"string","default":"nfl"},"season":{"type":"string"},"week":{"type":"integer","minimum":1},"format":{"type":"string","enum":["redraft","dynasty","bestball"],"default":"redraft"},"tier_gaps":{"type":"number","default":10.0},"limit":{"type":"integer","minimum":1,"default":300}},"additionalProperties":false}
    ```

- draft_pick_recommend
  - Description: Recommend best available picks given current pick, roster needs, and board.
  - Input schema:
    ```json
    {"type":"object","required":["league_id","roster_id","season","week"],"properties":{"league_id":{"type":"string"},"roster_id":{"type":"integer"},"season":{"type":"string"},"week":{"type":"integer","minimum":1},"sport":{"type":"string","default":"nfl"},"format":{"type":"string","enum":["redraft","dynasty","bestball"],"default":"redraft"},"limit":{"type":"integer","default":10},"already_drafted":{"type":"array","items":{"type":"string"}}},"additionalProperties":false}
    ```

- draft_observe
  - Description: Fetch current draft picks to update a live draft board.
  - Input schema:
    ```json
    {"type":"object","required":["draft_id"],"properties":{"draft_id":{"type":"string"},"limit":{"type":"integer","minimum":1,"default":1000}},"additionalProperties":false}
    ```

### Roster Analysis

- roster_needs
  - Description: Compute roster needs based on league starting slots and current roster composition.
  - Input schema:
    ```json
    {"type":"object","required":["league_id","roster_id"],"properties":{"league_id":{"type":"string"},"roster_id":{"type":"integer"},"sport":{"type":"string","default":"nfl"}},"additionalProperties":false}
    ```

### Projections Blending

- projections_blend
  - Description: Blend multiple projection sources (currently single-source placeholder with configurable weights).
  - Input schema:
    ```json
    {"type":"object","required":["season","week"],"properties":{"sport":{"type":"string","default":"nfl"},"season":{"type":"string"},"week":{"type":"integer","minimum":1},"weights":{"type":"object"}},"additionalProperties":false}
    ```

### Planning and Preferences

- playoffs_plan
  - Description: Highlight playoff weeks (15–17) schedule and recommend stash/stream targets by positional SOS.
  - Input schema:
    ```json
    {"type":"object","required":["season"],"properties":{"sport":{"type":"string","default":"nfl"},"season":{"type":"string"},"weeks":{"type":"array","items":{"type":"integer"},"default":[15,16,17]}},"additionalProperties":false}
    ```

- strategy_set
  - Description: Configure draft/season strategy levers (risk tolerance, stacking, exposure caps).
  - Input schema:
    ```json
    {"type":"object","properties":{"risk":{"type":"string","enum":["low","medium","high"]},"stack_qb":{"type":"boolean"},"hero_rb":{"type":"boolean"},"zero_rb":{"type":"boolean"},"exposure_caps":{"type":"object"}},"additionalProperties":false}
    ```

### Utility

- context_set_defaults
  - Description: Set default username/user_id, league_id, sport, season, week for subsequent calls.
  - Input schema:
    ```json
    {"type":"object","properties":{"username":{"type":"string"},"user_id":{"type":"string"},"league_id":{"type":"string"},"sport":{"type":"string"},"season":{"type":"string"},"week":{"type":"integer"}},"additionalProperties":false}
    ```

- time_resolve_week
  - Description: Resolve current season/week from Sleeper state.
  - Input schema:
    ```json
    {"type":"object","properties":{"sport":{"type":"string","default":"nfl"}},"additionalProperties":false}
    ```

- health_check
  - Description: Verify MCP server and Sleeper reachability.
  - Input schema:
    ```json
    {"type":"object","properties":{},"additionalProperties":false}
    ```

- cache_invalidate
  - Description: Invalidate cached keys by scope.
  - Input schema:
    ```json
    {"type":"object","required":["scope"],"properties":{"scope":{"type":"string","enum":["user","league","season","all"]},"id":{"type":"string"}},"additionalProperties":false}
    ```

- tool_list
  - Description: Return available tools and short descriptions.
  - Input schema:
    ```json
    {"type":"object","properties":{},"additionalProperties":false}
    ```

- tool_schema
  - Description: Return the JSON schema of a tool for debugging.
  - Input schema:
    ```json
    {"type":"object","required":["tool_name"],"properties":{"tool_name":{"type":"string"}},"additionalProperties":false}
    ```


## Troubleshooting
- Ensure the server is running: `php artisan serve` (default port 8000)
- Verify reachability with `health_check` via `tools/call`
- If you see cache-related issues, consider running `php artisan config:clear` and retry
- For production, add auth/rate limiting middleware in `config/mcp-server.php`


## License
MIT
