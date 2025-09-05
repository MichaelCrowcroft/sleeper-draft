@props([
    'search' => '',
    'position' => '',
    'team' => '',
    'adpRange' => '',
    'injuryStatus' => '',
    'selectedLeagueId' => '',
    'leagues' => [],
    'loadingLeagues' => false,
    'availablePositions' => [],
    'availableTeams' => []
])

<div class="space-y-4">
    <!-- Primary Filters -->
    <flux:callout>
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <!-- Search -->
            <div>
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search players..."
                    type="search"
                />
            </div>

            <!-- Position Filter -->
            <div>
                <flux:select wire:model.live="position">
                    <option value="">All Positions</option>
                    @foreach ($availablePositions as $pos)
                        <option value="{{ $pos }}">{{ $pos }}</option>
                    @endforeach
                </flux:select>
            </div>

            <!-- Team Filter -->
            <div>
                <flux:select wire:model.live="team">
                    <option value="">All Teams</option>
                    @foreach ($availableTeams as $teamCode)
                        <option value="{{ $teamCode }}">{{ $teamCode }}</option>
                    @endforeach
                </flux:select>
            </div>

            <!-- League Selection -->
            <div>
                <flux:select wire:model.live="selectedLeagueId">
                    <option value="">No League Selected</option>
                    @if ($loadingLeagues)
                        <option disabled>Loading leagues...</option>
                    @else
                        @foreach ($leagues as $league)
                            <option value="{{ $league['id'] }}">{{ $league['name'] }}</option>
                        @endforeach
                    @endif
                </flux:select>
            </div>
        </div>
    </flux:callout>

    <!-- Advanced Filters -->
    <flux:callout>
        <div class="grid gap-4 md:grid-cols-3">
            <!-- ADP Range -->
            <div>
                <flux:select wire:model.live="adpRange">
                    <option value="">All ADP Ranges</option>
                    <option value="1-5">Round 1 (ADP 1-5)</option>
                    <option value="6-10">Early Round 2 (ADP 6-10)</option>
                    <option value="11-15">Mid Round 2 (ADP 11-15)</option>
                    <option value="16-20">Late Round 2 (ADP 16-20)</option>
                    <option value="21-30">Round 3-4 (ADP 21-30)</option>
                    <option value="31-50">Mid-Tier (ADP 31-50)</option>
                    <option value="51-75">Late Round (ADP 51-75)</option>
                    <option value="76-100">Deep League (ADP 76-100)</option>
                    <option value="100+">Very Deep (ADP 100+)</option>
                </flux:select>
            </div>

            <!-- Injury Status -->
            <div>
                <flux:select wire:model.live="injuryStatus">
                    <option value="">All Injury Status</option>
                    <option value="healthy">Healthy Only</option>
                    <option value="injured">Injured Only</option>
                    <option value="questionable">Questionable</option>
                    <option value="doubtful">Doubtful</option>
                    <option value="out">Out</option>
                </flux:select>
            </div>

            <!-- Sort Options -->
            <div>
                <flux:select wire:model.live="sortBy">
                    <option value="last_name">Name (A-Z)</option>
                    <option value="adp">ADP (Low to High)</option>
                    <option value="position">Position</option>
                    <option value="team">Team</option>
                </flux:select>
            </div>
        </div>
    </flux:callout>

    <!-- Active Filters Summary -->
    @if ($search || $position || $team || $adpRange || $injuryStatus || $selectedLeagueId)
        <div class="flex flex-wrap gap-2">
            @if ($search)
                <flux:badge variant="outline">
                    Search: {{ $search }}
                    <button wire:click="$set('search', '')" class="ml-1 hover:text-destructive">×</button>
                </flux:badge>
            @endif
            @if ($position)
                <flux:badge variant="outline">
                    Position: {{ $position }}
                    <button wire:click="$set('position', '')" class="ml-1 hover:text-destructive">×</button>
                </flux:badge>
            @endif
            @if ($team)
                <flux:badge variant="outline">
                    Team: {{ $team }}
                    <button wire:click="$set('team', '')" class="ml-1 hover:text-destructive">×</button>
                </flux:badge>
            @endif
            @if ($adpRange)
                <flux:badge variant="outline">
                    ADP: {{ $adpRange }}
                    <button wire:click="$set('adpRange', '')" class="ml-1 hover:text-destructive">×</button>
                </flux:badge>
            @endif
            @if ($injuryStatus)
                <flux:badge variant="outline">
                    Status: {{ $injuryStatus }}
                    <button wire:click="$set('injuryStatus', '')" class="ml-1 hover:text-destructive">×</button>
                </flux:badge>
            @endif
            @if ($selectedLeagueId)
                @php
                    $selectedLeague = collect($leagues)->firstWhere('id', $selectedLeagueId);
                @endphp
                <flux:badge variant="outline">
                    League: {{ $selectedLeague['name'] ?? 'Unknown' }}
                    <button wire:click="$set('selectedLeagueId', '')" class="ml-1 hover:text-destructive">×</button>
                </flux:badge>
            @endif
        </div>
    @endif
</div>
