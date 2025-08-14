<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, usePage, router, Link } from '@inertiajs/vue3'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Avatar, AvatarImage, AvatarFallback } from '@/components/ui/avatar'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import Icon from '@/components/Icon.vue'
import { ref } from 'vue'

type League = {
  id: number
  sleeper_league_id: string
  name: string
  season: string
  sport: string
  avatar?: string | null
  rosters_count?: number
  total_rosters?: number
}

const page = usePage()
const leagues = page.props.leagues as League[]
const hasSleeperAccount = page.props.hasSleeperAccount as boolean

const isLoading = ref(false)

function initials(name: string): string {
  const parts = name.trim().split(/\s+/)
  const first = parts[0]?.[0] ?? ''
  const second = parts[1]?.[0] ?? ''
  return (first + second).toUpperCase()
}

function sync() {
  isLoading.value = true
  router.post('/leagues/sync', {}, {
    onFinish: () => {
      isLoading.value = false
    }
  })
}

function formatRecord(league: League): string {
  const roster = league.rosters_count ? league.rosters_count : 0
  const total = league.total_rosters ? league.total_rosters : 0
  return total > 0 ? `${roster}/${total} teams` : `${roster} teams`
}

function getStatusBadge(league: League): { variant: 'default' | 'secondary' | 'destructive' | 'outline', text: string } {
  const currentYear = new Date().getFullYear().toString()
  const isCurrentSeason = league.season === currentYear

  if (!isCurrentSeason) {
    return { variant: 'secondary', text: 'Past Season' }
  }

  const hasRoster = (league.rosters_count ?? 0) > 0
  if (hasRoster) {
    return { variant: 'default', text: 'Active' }
  }

  return { variant: 'outline', text: 'No Team' }
}
</script>

<template>
  <AppLayout :breadcrumbs="[{ title: 'Leagues', href: '/leagues' }]">
    <Head title="Leagues" />

    <div class="space-y-6">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold">Leagues</h1>
        <Button
          v-if="hasSleeperAccount"
          @click="sync"
          :disabled="isLoading"
          class="gap-2"
        >
          <Icon
            name="refresh-cw"
            :class="{ 'animate-spin': isLoading }"
            class="h-4 w-4"
          />
          {{ isLoading ? 'Updating...' : 'Update Leagues' }}
        </Button>
      </div>

      <!-- Empty state when no Sleeper account -->
      <div v-if="!hasSleeperAccount" class="text-center py-12">
        <div class="mx-auto h-12 w-12 text-muted-foreground mb-4">
          <Icon name="users" class="h-12 w-12" />
        </div>
        <h3 class="text-lg font-semibold mb-2">Connect Your Sleeper Account</h3>
        <p class="text-muted-foreground mb-4 max-w-md mx-auto">
          Connect your Sleeper account to automatically sync and manage your fantasy football leagues.
        </p>
        <Link href="/settings/sleeper">
          <Button>
            <Icon name="external-link" class="h-4 w-4 mr-2" />
            Go to Settings
          </Button>
        </Link>
      </div>

      <!-- Empty state when no leagues -->
      <div v-else-if="leagues.length === 0" class="text-center py-12">
        <div class="mx-auto h-12 w-12 text-muted-foreground mb-4">
          <Icon name="trophy" class="h-12 w-12" />
        </div>
        <h3 class="text-lg font-semibold mb-2">No Leagues Found</h3>
        <p class="text-muted-foreground mb-4 max-w-md mx-auto">
          We couldn't find any leagues for your Sleeper account. Try updating your leagues or check your Sleeper account.
        </p>
        <Button @click="sync" :disabled="isLoading" class="gap-2">
          <Icon
            name="refresh-cw"
            :class="{ 'animate-spin': isLoading }"
            class="h-4 w-4"
          />
          {{ isLoading ? 'Updating...' : 'Update Leagues' }}
        </Button>
      </div>

      <!-- Leagues grid -->
      <div v-else class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        <Link
          v-for="league in leagues"
          :key="league.id"
          :href="`/leagues/${league.id}`"
          class="group"
        >
          <Card class="h-full transition-all group-hover:shadow-md group-hover:border-primary/20">
            <CardHeader class="flex flex-row items-start gap-4 space-y-0">
              <Avatar class="h-12 w-12">
                <AvatarImage
                  v-if="league.avatar"
                  :src="`https://sleepercdn.com/avatars/${league.avatar}`"
                />
                <AvatarFallback class="text-sm">{{ initials(league.name) }}</AvatarFallback>
              </Avatar>
              <div class="flex-1 min-w-0">
                <div class="flex items-start justify-between gap-2">
                  <CardTitle class="text-base line-clamp-2 group-hover:text-primary transition-colors">
                    {{ league.name }}
                  </CardTitle>
                  <Badge
                    :variant="getStatusBadge(league).variant"
                    class="shrink-0 text-xs"
                  >
                    {{ getStatusBadge(league).text }}
                  </Badge>
                </div>
                <p class="text-sm text-muted-foreground mt-1">
                  {{ league.sport.toUpperCase() }} • {{ league.season }}
                </p>
              </div>
            </CardHeader>
            <CardContent class="pt-2">
              <div class="flex items-center gap-4 text-sm text-muted-foreground">
                <div class="flex items-center gap-1">
                  <Icon name="users" class="h-4 w-4" />
                  {{ formatRecord(league) }}
                </div>
                <div class="flex items-center gap-1">
                  <Icon name="arrow-right" class="h-4 w-4 opacity-50 group-hover:opacity-100 transition-opacity" />
                  <span class="text-xs">View Details</span>
                </div>
              </div>
            </CardContent>
          </Card>
        </Link>
      </div>

      <!-- Loading skeleton -->
      <div v-if="isLoading" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        <Card v-for="i in 3" :key="i" class="h-32">
          <CardHeader class="flex flex-row items-center gap-4">
            <Skeleton class="h-12 w-12 rounded-full" />
            <div class="space-y-2 flex-1">
              <Skeleton class="h-4 w-3/4" />
              <Skeleton class="h-3 w-1/2" />
            </div>
          </CardHeader>
        </Card>
      </div>
    </div>
  </AppLayout>
</template>
