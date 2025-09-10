<?php

declare(strict_types=1);

use App\Actions\Matchups\AggregateTeamTotals;
use App\Actions\Matchups\AssembleMatchupViewModel;
use App\Actions\Matchups\BuildLineupsFromRosters;
use App\Actions\Matchups\ComputePlayerWeekPoints;
use App\Actions\Sleeper\DetermineCurrentWeek;
use App\Actions\Sleeper\FetchLeague;
use App\Actions\Sleeper\FetchLeagueUsers;
use App\Actions\Sleeper\FetchMatchups;
use App\Actions\Sleeper\FetchRosters;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

it('selects the authenticated user roster when rosterId is 0 and pairs correct opponent', function () {
    $appUser = User::factory()->create([
        'sleeper_user_id' => '1260036576767905792',
    ]);

    actingAs($appUser);

    mock(DetermineCurrentWeek::class)
        ->shouldReceive('execute')
        ->with('nfl')
        ->andReturn(['season' => 2025, 'week' => 1]);

    mock(FetchLeague::class)
        ->shouldReceive('execute')
        ->andReturn(['name' => 'Test League']);

    mock(FetchRosters::class)
        ->shouldReceive('execute')
        ->andReturn([
            ['roster_id' => 3, 'owner_id' => '1260294010228981760', 'players' => ['A', 'B'], 'starters' => ['A']],
            ['roster_id' => 4, 'owner_id' => '1260036576767905792', 'players' => ['C', 'D'], 'starters' => ['C']],
        ]);

    mock(FetchLeagueUsers::class)
        ->shouldReceive('execute')
        ->andReturn([
            ['user_id' => '1260294010228981760', 'username' => 'majorkeyalert', 'display_name' => 'majorkeyalert'],
            ['user_id' => '1260036576767905792', 'username' => 'coach', 'display_name' => 'CoachCanCrusher'],
        ]);

    mock(FetchMatchups::class)
        ->shouldReceive('execute')
        ->andReturn([
            ['roster_id' => 3, 'matchup_id' => 4],
            ['roster_id' => 4, 'matchup_id' => 4],
        ]);

    mock(BuildLineupsFromRosters::class)
        ->shouldReceive('execute')
        ->andReturn([
            3 => ['starters' => ['A'], 'bench' => ['B']],
            4 => ['starters' => ['C'], 'bench' => ['D']],
        ]);

    mock(ComputePlayerWeekPoints::class)
        ->shouldReceive('execute')
        ->andReturnUsing(function (array $players) {
            $out = [];
            foreach ($players as $pid) {
                $out[$pid] = ['actual' => 0.0, 'projected' => 0.0, 'used' => 0.0, 'status' => 'upcoming'];
            }

            return $out;
        });

    mock(AggregateTeamTotals::class)
        ->shouldReceive('execute')
        ->andReturn(['actual' => 0.0, 'projected_remaining' => 0.0, 'total_estimated' => 0.0]);

    $vm = app(AssembleMatchupViewModel::class)->execute('1258530595068198912', 1, 0);

    expect($vm['home']['roster_id'])->toBe(4);
    expect($vm['home']['owner_id'])->toBe('1260036576767905792');
    expect($vm['away']['roster_id'])->toBe(3);
    expect($vm['away']['owner_id'])->toBe('1260294010228981760');
    expect($vm['away']['owner_name'])->toBe('majorkeyalert');
});
