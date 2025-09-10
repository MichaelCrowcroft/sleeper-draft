<?php

declare(strict_types=1);

use App\Models\Player;
use Illuminate\Support\Str;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class);

it('filters active players via scope', function () {
    Player::factory()->create(['active' => true]);
    Player::factory()->create(['active' => false]);

    $active = Player::query()->active()->get();

    expect($active)->toHaveCount(1);
    expect($active->first()->active)->toBeTrue();
});

it('filters by position and team via scopes', function () {
    Player::factory()->create(['position' => 'QB', 'team' => 'KC']);
    Player::factory()->create(['position' => 'RB', 'team' => 'BUF']);

    $qbs = Player::query()->position('qb')->get();
    expect($qbs)->toHaveCount(1);
    expect($qbs->first()->position)->toBe('QB');

    $kc = Player::query()->team('kc')->get();
    expect($kc)->toHaveCount(1);
    expect($kc->first()->team)->toBe('KC');
});

it('searches by name fields', function () {
    Player::factory()->create(['first_name' => 'Alpha', 'last_name' => 'One', 'full_name' => 'Alpha One', 'search_full_name' => Str::lower('Alpha One')]);
    Player::factory()->create(['first_name' => 'Beta', 'last_name' => 'Two', 'full_name' => 'Beta Two', 'search_full_name' => Str::lower('Beta Two')]);

    $alpha = Player::query()->search('Alpha')->get();
    expect($alpha)->toHaveCount(1);
    expect($alpha->first()->full_name)->toBe('Alpha One');
});

it('excludes player_ids via scope', function () {
    $a = Player::factory()->create();
    $b = Player::factory()->create();

    $list = Player::query()->excludePlayerIds([$a->player_id])->get();
    expect($list->pluck('player_id')->all())->not()->toContain($a->player_id);
    expect($list->pluck('player_id')->all())->toContain($b->player_id);
});

it('limits to playable positions via scope', function () {
    Player::factory()->create(['position' => 'QB']);
    Player::factory()->create(['position' => 'RB']);
    Player::factory()->create(['position' => 'P']); // Punter (not allowed)

    $list = Player::query()->playablePositions()->get();
    expect($list->pluck('position')->all())->not()->toContain('P');
});

it('orders by adp with nulls last according to direction', function () {
    $low = Player::factory()->create(['adp' => 10.0]);
    $high = Player::factory()->create(['adp' => 20.0]);
    $null = Player::factory()->create(['adp' => null]);

    $asc = Player::query()->orderByAdpNullLast('asc')->pluck('adp');
    expect($asc->toArray())->toBe([10.0, 20.0, null]);

    $desc = Player::query()->orderByAdpNullLast('desc')->pluck('adp');
    expect($desc->toArray())->toBe([20.0, 10.0, null]);
});

it('orders by age with nulls appropriately', function () {
    $y = Player::factory()->create(['age' => 22]);
    $o = Player::factory()->create(['age' => 30]);
    $n = Player::factory()->create(['age' => null]);

    $asc = Player::query()->orderByAgeNullLast('asc')->pluck('age');
    expect($asc->toArray())->toBe([22, 30, null]);

    $desc = Player::query()->orderByAgeNullLast('desc')->pluck('age');
    expect($desc->toArray())->toBe([30, 22, null]);
});

it('orders by name via scope', function () {
    Player::factory()->create(['first_name' => 'Charlie', 'last_name' => 'Zed']);
    Player::factory()->create(['first_name' => 'Alice', 'last_name' => 'Young']);

    $asc = Player::query()->orderByName('asc')->pluck('first_name');
    expect($asc->toArray())->toBe(['Alice', 'Charlie']);
});
