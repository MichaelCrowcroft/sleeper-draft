<?php

namespace App\Console\Commands;

use App\Models\ApiAnalytics;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillAnalyticsData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'analytics:backfill
                            {--dry-run : Show what would be updated without making changes}
                            {--batch-size=1000 : Number of records to process at once}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill historic analytics data with new tool naming and categorization format';

    /**
     * Tool name mappings for backfilling
     */
    private array $toolMappings = [
        'fetch-trending-players' => 'mcp_fantasy-football-mcp_fetch-trending-players',
        'fetch-adp-players' => 'mcp_fantasy-football-mcp_fetch-adp-players',
        'fetch-user-leagues' => 'mcp_fantasy-football-mcp_fetch-user-leagues',
        'get-league' => 'mcp_fantasy-football-mcp_get-league',
        'draft-picks' => 'mcp_fantasy-football-mcp_draft-picks',
        'fetch-rosters' => 'mcp_fantasy-football-mcp_fetch-rosters',
        'fetch-matchups' => 'mcp_fantasy-football-mcp_fetch-matchups',
        'fetch-trades' => 'mcp_fantasy-football-mcp_fetch-trades',
    ];

    /**
     * Route name to tool mappings for MCP tools
     */
    private array $routeMappings = [
        'api.mcp.fetch-trending-players' => 'mcp_fantasy-football-mcp_fetch-trending-players',
        'api.mcp.fetch-adp-players' => 'mcp_fantasy-football-mcp_fetch-adp-players',
        'api.mcp.fetch-user-leagues' => 'mcp_fantasy-football-mcp_fetch-user-leagues',
        'api.mcp.get-league' => 'mcp_fantasy-football-mcp_get-league',
        'api.mcp.draft-picks' => 'mcp_fantasy-football-mcp_draft-picks',
        'api.mcp.fetch-rosters' => 'mcp_fantasy-football-mcp_fetch-rosters',
        'api.mcp.fetch-matchups' => 'mcp_fantasy-football-mcp_fetch-matchups',
        'api.mcp.fetch-trades' => 'mcp_fantasy-football-mcp_fetch-trades',
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $batchSize = (int) $this->option('batch-size');

        $this->info($isDryRun ? 'DRY RUN MODE - No changes will be made' : 'Starting analytics data backfill...');

        // Get total count of records that need updating
        $totalRecords = $this->getRecordsNeedingUpdate();
        $this->info("Found {$totalRecords} records that need updating");

        if ($totalRecords === 0) {
            $this->info('No records need updating. All data is already up to date.');
            return self::SUCCESS;
        }

        if (!$this->confirm('Continue with backfilling?', true)) {
            $this->info('Operation cancelled.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('Starting backfill process...');

        $progressBar = $this->output->createProgressBar($totalRecords);
        $progressBar->start();

        $updatedCount = 0;
        $batchNumber = 0;

        ApiAnalytics::chunk($batchSize, function ($records) use (&$updatedCount, $progressBar, $isDryRun, &$batchNumber) {
            $batchNumber++;

            foreach ($records as $record) {
                $wasUpdated = $this->updateRecord($record, $isDryRun);

                if ($wasUpdated) {
                    $updatedCount++;
                }

                $progressBar->advance();
            }

            // Optional: Add small delay between batches to prevent overwhelming the database
            if ($batchNumber % 10 === 0) {
                usleep(10000); // 10ms delay
            }
        });

        $progressBar->finish();
        $this->newLine(2);

        if ($isDryRun) {
            $this->info("DRY RUN COMPLETE: {$updatedCount} records would be updated");
        } else {
            $this->info("SUCCESS: Updated {$updatedCount} records");
        }

        $this->info('Backfill process completed successfully.');

        return self::SUCCESS;
    }

    /**
     * Get count of records that need updating
     */
    private function getRecordsNeedingUpdate(): int
    {
        return ApiAnalytics::where(function ($query) {
            // Records with old tool names that need prefixing
            $query->whereNotNull('tool_name')
                  ->where('tool_name', 'not like', 'mcp_fantasy-football-mcp_%');

            // OR records with old categorization that need updating
            $query->orWhere(function ($subQuery) {
                $subQuery->where('endpoint_category', 'mcp')
                         ->where('endpoint', 'like', 'api/mcp%');
            });
        })->count();
    }

    /**
     * Update a single record
     */
    private function updateRecord(ApiAnalytics $record, bool $isDryRun): bool
    {
        $originalToolName = $record->tool_name;
        $originalCategory = $record->endpoint_category;
        $wasUpdated = false;

        // Update tool name if needed
        if ($record->tool_name && !str_starts_with($record->tool_name, 'mcp_fantasy-football-mcp_')) {
            $newToolName = $this->mapToolName($record);
            if ($newToolName !== $record->tool_name) {
                if (!$isDryRun) {
                    $record->tool_name = $newToolName;
                }
                $wasUpdated = true;
            }
        }

        // Update category if needed
        $newCategory = $this->determineCorrectCategory($record);
        if ($newCategory !== $record->endpoint_category) {
            if (!$isDryRun) {
                $record->endpoint_category = $newCategory;
            }
            $wasUpdated = true;
        }

        // Save the record if not a dry run
        if ($wasUpdated && !$isDryRun) {
            $record->save();
        }

        return $wasUpdated;
    }

    /**
     * Map old tool name to new format
     */
    private function mapToolName(ApiAnalytics $record): string
    {
        $oldToolName = $record->tool_name;

        // Direct mapping for known tools
        if (isset($this->toolMappings[$oldToolName])) {
            return $this->toolMappings[$oldToolName];
        }

        // Map from route name if available
        if ($record->route_name && isset($this->routeMappings[$record->route_name])) {
            return $this->routeMappings[$record->route_name];
        }

        // Extract from generic MCP route
        if ($record->route_name === 'api.mcp.invoke' && $record->endpoint) {
            $toolFromUrl = basename($record->endpoint);
            if (isset($this->toolMappings[$toolFromUrl])) {
                return $this->toolMappings[$toolFromUrl];
            }
        }

        // Extract from MCP JSON-RPC payload
        if ($record->endpoint === 'mcp' && $record->request_payload) {
            $payload = $record->request_payload;
            if (is_array($payload) && isset($payload['params']['name'])) {
                $toolName = $payload['params']['name'];
                if (isset($this->toolMappings[$toolName])) {
                    return $this->toolMappings[$toolName];
                }
            }
        }

        // If no mapping found, return original (don't break existing data)
        return $oldToolName;
    }

    /**
     * Determine the correct category for a record
     */
    private function determineCorrectCategory(ApiAnalytics $record): string
    {
        $endpoint = $record->endpoint;
        $routeName = $record->route_name;

        // Direct MCP endpoint
        if ($endpoint === 'mcp') {
            return 'mcp';
        }

        // API MCP tools endpoints
        if (str_starts_with($endpoint, 'api/mcp')) {
            if ($routeName === 'api.mcp.invoke' || str_starts_with($routeName, 'api.mcp.')) {
                return 'mcp_tools_api';
            }
        }

        // Keep existing category if it doesn't need updating
        return $record->endpoint_category ?? 'api';
    }
}
