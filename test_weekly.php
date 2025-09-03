<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Player;
use App\Jobs\ProcessPlayerStatsAndProjectionsChunk;
use MichaelCrowcroft\SleeperLaravel\Facades\Sleeper;

// Get 2 player IDs
$ids = Player::query()->where('sport','nfl')->limit(2)->pluck('player_id')->all();

echo "Testing with player IDs: " . implode(', ', $ids) . PHP_EOL;

// Test API call directly first
echo "Testing API call for first player " . $ids[0] . "...\n";
try {
    $response = Sleeper::players()->stats($ids[0], '2024', 'nfl', 'regular', 'week');
    $data = $response->json();
    echo "API Response type: " . gettype($data) . "\n";
    if (is_array($data)) {
        echo "Array size: " . count($data) . "\n";
        echo "Keys: " . implode(', ', array_keys($data)) . "\n";
        if (!empty($data)) {
            $firstWeek = array_key_first($data);
            echo "First week: $firstWeek\n";
            if (isset($data[$firstWeek]['stats'])) {
                echo "Sample stats: " . json_encode(array_slice($data[$firstWeek]['stats'], 0, 5)) . "\n";
            }
        }
    }
} catch (Exception $e) {
    echo "API Error: " . $e->getMessage() . "\n";
}

// Run the job synchronously
echo "Running job...\n";
\Illuminate\Support\Facades\Log::info("Starting weekly test job for players: " . implode(', ', $ids));
try {
    ProcessPlayerStatsAndProjectionsChunk::dispatchSync($ids, '2024', 'nfl', 'regular', 250);
    echo "Job completed successfully!" . PHP_EOL;
} catch (Exception $e) {
    echo "Job Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

// Check what was saved
echo "Checking saved data...\n";
$stats = \App\Models\PlayerStats::whereIn('player_id', $ids)->where('week', '>', 0)->count();
$projs = \App\Models\PlayerProjections::whereIn('player_id', $ids)->where('week', '>', 0)->count();

echo "Stats records: $stats\n";
echo "Projections records: $projs\n";

if ($stats > 0) {
    $sample = \App\Models\PlayerStats::whereIn('player_id', $ids)->where('week', '>', 0)->first();
    echo "Sample stats record: " . json_encode($sample->toArray()) . "\n";
}
