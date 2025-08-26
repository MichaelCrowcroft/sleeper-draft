<?php

use App\MCP\Tools\Data\UnifiedDataTool;
use App\Services\SleeperSdk;
use Illuminate\Support\Facades\App;
use Mockery;

afterEach(function () {
    Mockery::close();
});

test('player search ignores surrounding whitespace', function () {
    $mockSdk = Mockery::mock(SleeperSdk::class);
    $mockSdk->shouldReceive('getPlayersCatalog')
        ->once()
        ->with('nfl')
        ->andReturn([
            '1' => ['full_name' => 'Tom Brady', 'position' => 'QB', 'team' => 'TB'],
        ]);

    App::instance(SleeperSdk::class, $mockSdk);

    $tool = new UnifiedDataTool;

    $result = $tool->execute([
        'data_type' => 'players',
        'query' => ' Tom Brady ',
    ]);

    expect($result['count'])->toBe(1)
        ->and($result['results'][0]['name'])->toBe('Tom Brady');
});
