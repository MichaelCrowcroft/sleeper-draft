<?php

declare(strict_types=1);

use App\Actions\Matchups\OptimizeLineup;
use App\Models\Player;
use App\Models\PlayerProjections;
use App\Models\PlayerStats;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('optimizes lineup by selecting highest projected players', function () {
    // Create test players
    $qb1 = Player::factory()->create([
        'player_id' => 'QB1',
        'full_name' => 'Quarterback One',
        'position' => 'QB',
    ]);

    $qb2 = Player::factory()->create([
        'player_id' => 'QB2',
        'full_name' => 'Quarterback Two',
        'position' => 'QB',
    ]);

    $rb1 = Player::factory()->create([
        'player_id' => 'RB1',
        'full_name' => 'Running Back One',
        'position' => 'RB',
    ]);

    $rb2 = Player::factory()->create([
        'player_id' => 'RB2',
        'full_name' => 'Running Back Two',
        'position' => 'RB',
    ]);

    // Add projections for week 1, 2025
    PlayerProjections::create([
        'player_id' => 'QB1',
        'game_date' => '2025-09-05',
        'season' => 2025,
        'week' => 1,
        'season_type' => 'regular',
        'pts_ppr' => 25.0,
    ]);

    PlayerProjections::create([
        'player_id' => 'QB2',
        'game_date' => '2025-09-05',
        'season' => 2025,
        'week' => 1,
        'season_type' => 'regular',
        'pts_ppr' => 20.0, // Lower projection
    ]);

    PlayerProjections::create([
        'player_id' => 'RB1',
        'game_date' => '2025-09-05',
        'season' => 2025,
        'week' => 1,
        'season_type' => 'regular',
        'pts_ppr' => 18.0,
    ]);

    PlayerProjections::create([
        'player_id' => 'RB2',
        'game_date' => '2025-09-05',
        'season' => 2025,
        'week' => 1,
        'season_type' => 'regular',
        'pts_ppr' => 15.0, // Lower projection
    ]);

    // Add historical stats for volatility calculation
    PlayerStats::create([
        'player_id' => 'QB1',
        'game_date' => '2024-09-07',
        'season' => 2024,
        'week' => 1,
        'season_type' => 'regular',
        'stats' => ['pts_ppr' => 22.0],
    ]);

    PlayerStats::create([
        'player_id' => 'QB2',
        'game_date' => '2024-09-07',
        'season' => 2024,
        'week' => 1,
        'season_type' => 'regular',
        'stats' => ['pts_ppr' => 18.0],
    ]);

    $currentStarters = ['QB2', 'RB2']; // Suboptimal starters
    $benchPlayers = ['QB1', 'RB1']; // Better options
    $currentPoints = [
        'QB2' => ['actual' => 0.0, 'projected' => 20.0, 'used' => 20.0, 'status' => 'upcoming'],
        'RB2' => ['actual' => 0.0, 'projected' => 15.0, 'used' => 15.0, 'status' => 'upcoming'],
    ];

    $optimizer = app(OptimizeLineup::class);
    $result = $optimizer->execute($currentStarters, $benchPlayers, $currentPoints, 2025, 1);

    // Should recommend QB1 and RB1 as optimal starters
    expect($result['optimized_lineup']['starters'])->toContain('QB1', 'RB1');
    expect($result['optimized_lineup']['improvement'])->toBeGreaterThan(0);
    expect($result['recommendations'])->toHaveKey('QB1');
    expect($result['recommendations'])->toHaveKey('RB1');
});

it('calculates volatility from historical performance', function () {
    $player = Player::factory()->create([
        'player_id' => 'VOLQB',
        'full_name' => 'Volatile Quarterback',
        'position' => 'QB',
    ]);

    // Add variable historical performance
    PlayerStats::create([
        'player_id' => 'VOLQB',
        'game_date' => '2024-09-07',
        'season' => 2024,
        'week' => 1,
        'season_type' => 'regular',
        'stats' => ['pts_ppr' => 30.0],
    ]);

    PlayerStats::create([
        'player_id' => 'VOLQB',
        'game_date' => '2024-09-14',
        'season' => 2024,
        'week' => 2,
        'season_type' => 'regular',
        'stats' => ['pts_ppr' => 10.0],
    ]);

    PlayerStats::create([
        'player_id' => 'VOLQB',
        'game_date' => '2024-09-21',
        'season' => 2024,
        'week' => 3,
        'season_type' => 'regular',
        'stats' => ['pts_ppr' => 25.0],
    ]);

    $optimizer = app(OptimizeLineup::class);

    // Use reflection to test private method
    $reflection = new ReflectionClass($optimizer);
    $method = $reflection->getMethod('calculateVolatility');
    $method->setAccessible(true);

    $volatility = $method->invoke($optimizer, $player);

    expect($volatility['std_dev'])->toBeGreaterThan(0);
    expect($volatility['coefficient_of_variation'])->toBeGreaterThan(0);
    expect($volatility['games_analyzed'])->toBe(3);
});

it('assesses risk based on lineup volatility', function () {
    $optimizer = app(OptimizeLineup::class);

    // Test low risk scenario
    $lowRiskRecommendations = [
        'QB1' => ['confidence_score' => 0.8, 'volatility' => ['coefficient_of_variation' => 0.3]],
        'RB1' => ['confidence_score' => 0.9, 'volatility' => ['coefficient_of_variation' => 0.2]],
    ];

    $lowRiskAnalysis = [
        'QB1' => ['volatility' => ['coefficient_of_variation' => 0.3]],
        'RB1' => ['volatility' => ['coefficient_of_variation' => 0.2]],
    ];

    // Use reflection to test private method
    $reflection = new ReflectionClass($optimizer);
    $method = $reflection->getMethod('assessRisk');
    $method->setAccessible(true);

    $risk = $method->invoke($optimizer, $lowRiskRecommendations, $lowRiskAnalysis);

    expect($risk['level'])->toBe('low');
    expect($risk['average_confidence'])->toBeGreaterThan(0.7);
});

it('handles players with no historical data', function () {
    $player = Player::factory()->create([
        'player_id' => 'NEWQB',
        'full_name' => 'New Quarterback',
        'position' => 'QB',
    ]);

    // No historical stats added

    PlayerProjections::create([
        'player_id' => 'NEWQB',
        'game_date' => '2025-09-05',
        'season' => 2025,
        'week' => 1,
        'season_type' => 'regular',
        'pts_ppr' => 16.0,
    ]);

    $optimizer = app(OptimizeLineup::class);

    // Use reflection to test private method
    $reflection = new ReflectionClass($optimizer);
    $method = $reflection->getMethod('calculateVolatility');
    $method->setAccessible(true);

    $volatility = $method->invoke($optimizer, $player);

    expect($volatility['games_analyzed'])->toBe(0);
    expect($volatility['std_dev'])->toBe(6.0); // Default volatility
});

it('prioritizes projected points over confidence when optimizing', function () {
    // Create players where lower confidence player has higher projection
    $highProjLowConf = Player::factory()->create([
        'player_id' => 'HIGHPROJ',
        'full_name' => 'High Projection Player',
        'position' => 'QB',
    ]);

    $lowProjHighConf = Player::factory()->create([
        'player_id' => 'HIGHCONF',
        'full_name' => 'High Confidence Player',
        'position' => 'QB',
    ]);

    // Add projections
    PlayerProjections::create([
        'player_id' => 'HIGHPROJ',
        'game_date' => '2025-09-05',
        'season' => 2025,
        'week' => 1,
        'season_type' => 'regular',
        'pts_ppr' => 25.0,
    ]);

    PlayerProjections::create([
        'player_id' => 'HIGHCONF',
        'game_date' => '2025-09-05',
        'season' => 2025,
        'week' => 1,
        'season_type' => 'regular',
        'pts_ppr' => 20.0,
    ]);

    // Add historical stats (HIGHCONF has more consistent history)
    PlayerStats::create([
        'player_id' => 'HIGHCONF',
        'game_date' => '2024-09-07',
        'season' => 2024,
        'week' => 1,
        'season_type' => 'regular',
        'stats' => ['pts_ppr' => 20.0],
    ]);

    PlayerStats::create([
        'player_id' => 'HIGHCONF',
        'game_date' => '2024-09-14',
        'season' => 2024,
        'week' => 2,
        'season_type' => 'regular',
        'stats' => ['pts_ppr' => 21.0],
    ]);

    // HIGHPROJ has volatile history but higher projection
    PlayerStats::create([
        'player_id' => 'HIGHPROJ',
        'game_date' => '2024-09-07',
        'season' => 2024,
        'week' => 1,
        'season_type' => 'regular',
        'stats' => ['pts_ppr' => 15.0],
    ]);

    PlayerStats::create([
        'player_id' => 'HIGHPROJ',
        'game_date' => '2024-09-14',
        'season' => 2024,
        'week' => 2,
        'season_type' => 'regular',
        'stats' => ['pts_ppr' => 30.0],
    ]);

    $currentStarters = ['HIGHCONF'];
    $benchPlayers = ['HIGHPROJ'];
    $currentPoints = [
        'HIGHCONF' => ['actual' => 0.0, 'projected' => 20.0, 'used' => 20.0, 'status' => 'upcoming'],
    ];

    $optimizer = app(OptimizeLineup::class);
    $result = $optimizer->execute($currentStarters, $benchPlayers, $currentPoints, 2025, 1);

    // Should recommend HIGHPROJ despite lower confidence due to higher projection
    expect($result['optimized_lineup']['starters'])->toContain('HIGHPROJ');
    expect($result['optimized_lineup']['improvement'])->toBe(5.0); // 25 - 20
});
