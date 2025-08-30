<?php

namespace App\Console\Commands;

use App\Actions\FetchSleeperPlayers;
use App\Actions\ImportPlayersToDatabase;
use App\Actions\ProcessPlayerData;
use Illuminate\Console\Command;

class ImportSleeperPlayers extends Command
{
    protected $signature = 'sleeper:players:import {--sport=nfl} {--chunk=500}';

    protected $description = 'Import Sleeper players into the database';

    public function handle(
        FetchSleeperPlayers $fetchPlayers,
        ProcessPlayerData $processData,
        ImportPlayersToDatabase $importPlayers
    ): int {
        $sport = (string) $this->option('sport');
        $chunkSize = (int) $this->option('chunk') ?: 500;

        try {
            $this->info("Fetching Sleeper players for '{$sport}'...");

            $players = $fetchPlayers->execute($sport);

            $total = count($players);
            $this->info("Importing {$total} players (chunk size: {$chunkSize})...");

            // Process the data into chunks
            $chunks = $processData->execute($players, $sport, $chunkSize);

            // Import to database
            $imported = $importPlayers->execute($chunks);

            $this->info("Successfully imported {$imported} players.");
            $this->info('Done.');

            return self::SUCCESS;
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (\Exception $e) {
            $this->error('An unexpected error occurred: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
