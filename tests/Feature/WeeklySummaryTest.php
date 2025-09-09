<?php

use App\Models\WeeklySummary;

test('weekly summary can be created and retrieved', function () {
    $summary = WeeklySummary::getOrCreate('test-league-123', 2025, 1);

    expect($summary)->toBeInstanceOf(WeeklySummary::class);
    expect($summary->league_id)->toBe('test-league-123');
    expect($summary->year)->toBe(2025);
    expect($summary->week)->toBe(1);
});

test('weekly summary scope works correctly', function () {
    // Create a summary
    WeeklySummary::create([
        'league_id' => 'test-league-123',
        'year' => 2025,
        'week' => 1,
        'content' => 'Test content',
    ]);

    // Create another summary for different league/week
    WeeklySummary::create([
        'league_id' => 'test-league-456',
        'year' => 2025,
        'week' => 1,
        'content' => 'Other content',
    ]);

    $summary = WeeklySummary::forLeagueWeek('test-league-123', 2025, 1)->first();

    expect($summary)->not->toBeNull();
    expect($summary->content)->toBe('Test content');
});

test('weekly summary is marked as recent when generated recently', function () {
    $summary = new WeeklySummary();
    $summary->generated_at = now();
    expect($summary->isRecent())->toBeTrue();

    $summary->generated_at = now()->subDays(2);
    expect($summary->isRecent())->toBeFalse();
});

test('weekly summary markGenerated method works', function () {
    $summary = WeeklySummary::create([
        'league_id' => 'test-league-123',
        'year' => 2025,
        'week' => 1,
    ]);

    $content = 'Generated content';
    $prompt = 'Test prompt';

    $summary->markGenerated($content, $prompt);

    expect($summary->content)->toBe($content);
    expect($summary->prompt_used)->toBe($prompt);
    expect($summary->generated_at)->not->toBeNull();
});
