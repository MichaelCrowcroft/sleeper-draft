<?php

namespace App\Actions\Matchups;

class FilterMatchups
{
    public function execute(array $matchups, string $sleeper_id): array
    {
        return collect($matchups)->filter(function ($teams) use ($sleeper_id) {
            return collect($teams)->contains(function ($team) use ($sleeper_id) {
                return $team['owner_id'] === $sleeper_id;
            });
        })->toArray();
    }
}
