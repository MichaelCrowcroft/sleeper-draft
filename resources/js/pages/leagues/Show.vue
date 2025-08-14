<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import LeagueLayout from '@/layouts/leagues/Layout.vue'
import { Head, usePage, Link } from '@inertiajs/vue3'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Avatar, AvatarImage, AvatarFallback } from '@/components/ui/avatar'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import Icon from '@/components/Icon.vue'

type Roster = {
  id: number
  roster_id: number
  owner_id: string
  wins: number
  losses: number
  ties: number
  fpts: number
  fpts_decimal: number
  players: string[] | null
  metadata: any
}

type League = {
  id: number
  sleeper_league_id: string
  name: string
  season: string
  sport: string
  avatar?: string | null
  total_rosters?: number
  settings: any
  metadata: any
  rosters: Roster[]
}

const page = usePage()
const league = page.props.league as League

function initials(name: string): string {
  const parts = name.trim().split(/\s+/)
  const first = parts[0]?.[0] ?? ''
  const second = parts[1]?.[0] ?? ''
  return (first + second).toUpperCase()
}

function formatRecord(roster: Roster): string {
  if (roster.ties > 0) {
    return `${roster.wins}-${roster.losses}-${roster.ties}`
  }
  return `${roster.wins}-${roster.losses}`
}

function getWinPercentage(roster: Roster): number {
  const totalGames = roster.wins + roster.losses + roster.ties
  if (totalGames === 0) return 0
  return (roster.wins + roster.ties * 0.5) / totalGames * 100
}

function formatPoints(points: number): string {
  return points.toFixed(2)
}

const breadcrumbs = [
  { title: 'Leagues', href: '/leagues' },
  { title: league.name, href: `/leagues/${league.id}` }
]
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbs">
    <Head :title="league.name" />

    <LeagueLayout :league="league">
      <!-- League Header -->
      <div class="flex items-start gap-6">
        <Avatar class="h-20 w-20">
          <AvatarImage
            v-if="league.avatar"
            :src="`https://sleepercdn.com/avatars/${league.avatar}`"
          />
          <AvatarFallback class="text-lg">{{ initials(league.name) }}</AvatarFallback>
        </Avatar>

        <div class="flex-1">
          <div class="flex items-start justify-between">
            <div>
              <h2 class="text-2xl font-bold">League Overview</h2>
              <div class="flex items-center gap-4 mt-2">
                <Badge variant="outline" class="gap-1">
                  <Icon name="users" class="h-3 w-3" />
                  {{ league.total_rosters }} teams
                </Badge>
                <Badge variant="outline">
                  ID: {{ league.sleeper_league_id }}
                </Badge>
              </div>
            </div>

            <div class="flex gap-2">
              <Link href="/leagues">
                <Button variant="outline" size="sm">
                  <Icon name="arrow-left" class="h-4 w-4 mr-2" />
                  Back to Leagues
                </Button>
              </Link>
            </div>
          </div>
        </div>
      </div>

      <!-- Your Team Section -->
      <div v-if="league.rosters.length > 0">
        <h2 class="text-xl font-semibold mb-4">Your Team</h2>
        <div class="grid gap-4">
          <Card v-for="roster in league.rosters" :key="roster.id">
            <CardHeader>
              <div class="flex items-center justify-between">
                <CardTitle class="text-lg">Team {{ roster.roster_id }}</CardTitle>
                <div class="flex items-center gap-2">
                  <Badge
                    :variant="getWinPercentage(roster) >= 50 ? 'default' : 'secondary'"
                    class="gap-1"
                  >
                    <Icon name="trophy" class="h-3 w-3" />
                    {{ formatRecord(roster) }}
                  </Badge>
                </div>
              </div>
            </CardHeader>
            <CardContent>
              <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="text-center">
                  <div class="text-2xl font-bold text-green-600">{{ roster.wins }}</div>
                  <div class="text-sm text-muted-foreground">Wins</div>
                </div>
                <div class="text-center">
                  <div class="text-2xl font-bold text-red-600">{{ roster.losses }}</div>
                  <div class="text-sm text-muted-foreground">Losses</div>
                </div>
                <div v-if="roster.ties > 0" class="text-center">
                  <div class="text-2xl font-bold text-yellow-600">{{ roster.ties }}</div>
                  <div class="text-sm text-muted-foreground">Ties</div>
                </div>
                <div class="text-center">
                  <div class="text-2xl font-bold">{{ formatPoints(roster.fpts) }}</div>
                  <div class="text-sm text-muted-foreground">Points For</div>
                </div>
              </div>

              <div v-if="roster.players && roster.players.length > 0" class="mt-4 pt-4 border-t">
                <div class="flex items-center justify-between mb-2">
                  <h4 class="font-semibold">Roster</h4>
                  <Badge variant="outline">
                    {{ roster.players.length }} players
                  </Badge>
                </div>
                <div class="text-sm text-muted-foreground">
                  <p>Player data available ({{ roster.players.length }} players)</p>
                  <p class="text-xs mt-1">
                    Use the MCP tools to get detailed player information and analysis
                  </p>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>

      <!-- Empty State for No Teams -->
      <div v-else class="text-center py-12">
        <div class="mx-auto h-12 w-12 text-muted-foreground mb-4">
          <Icon name="users-x" class="h-12 w-12" />
        </div>
        <h3 class="text-lg font-semibold mb-2">No Team Found</h3>
        <p class="text-muted-foreground max-w-md mx-auto mb-4">
          You don't have a team in this league, or the roster data hasn't been synced yet.
        </p>
        <Link href="/leagues">
          <Button variant="outline">
            <Icon name="refresh-cw" class="h-4 w-4 mr-2" />
            Update Leagues
          </Button>
        </Link>
      </div>

      <!-- League Settings (if available) -->
      <div v-if="league.settings">
        <h2 class="text-xl font-semibold mb-4">League Settings</h2>
        <Card>
          <CardContent class="pt-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
              <div v-if="league.settings.playoff_teams">
                <div class="font-medium">Playoff Teams</div>
                <div class="text-muted-foreground">{{ league.settings.playoff_teams }}</div>
              </div>
              <div v-if="league.settings.start_week">
                <div class="font-medium">Start Week</div>
                <div class="text-muted-foreground">Week {{ league.settings.start_week }}</div>
              </div>
              <div v-if="league.settings.playoff_start_week">
                <div class="font-medium">Playoff Start</div>
                <div class="text-muted-foreground">Week {{ league.settings.playoff_start_week }}</div>
              </div>
              <div v-if="league.settings.waiver_budget">
                <div class="font-medium">Waiver Budget</div>
                <div class="text-muted-foreground">${{ league.settings.waiver_budget }}</div>
              </div>
              <div v-if="league.settings.trade_deadline">
                <div class="font-medium">Trade Deadline</div>
                <div class="text-muted-foreground">Week {{ league.settings.trade_deadline }}</div>
              </div>
              <div v-if="typeof league.settings.waiver_clear_days === 'number'">
                <div class="font-medium">Waiver Clear Days</div>
                <div class="text-muted-foreground">{{ league.settings.waiver_clear_days }} days</div>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </LeagueLayout>
  </AppLayout>
</template>
