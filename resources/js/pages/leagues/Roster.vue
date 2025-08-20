<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import LeagueLayout from '@/layouts/leagues/Layout.vue'
import { Head, usePage } from '@inertiajs/vue3'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import Icon from '@/components/Icon.vue'

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
}

const page = usePage()
const league = page.props.league as League

const breadcrumbs = [
  { title: 'Leagues', href: '/leagues' },
  { title: league.name, href: `/leagues/${league.id}` },
  { title: 'Roster', href: `/leagues/${league.id}/roster` }
]
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbs">
    <Head :title="`${league.name} - Roster`" />

    <LeagueLayout :league="league">
      <div class="space-y-6">
        <div class="flex items-center justify-between">
          <h2 class="text-2xl font-bold">Roster Management</h2>
          <Button variant="outline" size="sm">
            <Icon name="refresh-cw" class="h-4 w-4 mr-2" />
            Sync Roster
          </Button>
        </div>

        <!-- Placeholder content for roster page -->
        <Card>
          <CardHeader>
            <CardTitle>Your Roster</CardTitle>
          </CardHeader>
          <CardContent>
            <div class="text-center py-12">
              <div class="mx-auto h-12 w-12 text-muted-foreground mb-4">
                <Icon name="users" class="h-12 w-12" />
              </div>
              <h3 class="text-lg font-semibold mb-2">Roster View Coming Soon</h3>
              <p class="text-muted-foreground max-w-md mx-auto">
                This page will show detailed roster management, player stats, and lineup optimization tools.
              </p>
            </div>
          </CardContent>
        </Card>
      </div>
    </LeagueLayout>
  </AppLayout>
</template>
