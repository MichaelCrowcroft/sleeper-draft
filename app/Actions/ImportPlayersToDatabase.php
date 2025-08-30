<?php

namespace App\Actions;

use Illuminate\Support\Facades\DB;

class ImportPlayersToDatabase
{
    public function execute(array $chunks): int
    {
        $totalImported = 0;

        foreach ($chunks as $rows) {
            if (empty($rows)) {
                continue;
            }

            $this->upsertChunk($rows);
            $totalImported += count($rows);
        }

        return $totalImported;
    }

    /**
     * Upsert a chunk of player data.
     */
    private function upsertChunk(array $rows): void
    {
        // All columns except the unique key and created_at should be updated on conflict
        $updateColumns = array_diff(array_keys($rows[0]), ['player_id', 'created_at']);

        DB::table('players')->upsert($rows, ['player_id'], $updateColumns);
    }
}
