<?php

namespace App\Actions\Matchups;

class BuildLineupsFromRosters
{
    /**
     * Build starters and bench for each roster based on Sleeper roster payloads.
     *
     * @param array $rosters Sleeper rosters array
     * @return array<int, array{starters: array<int, string>, bench: array<int, string>, owner_id: mixed}>
     */
    public function execute(array $rosters): array
    {
        $result = [];

        foreach ($rosters as $roster) {
            $starters = [];
            $bench = [];

            $starterIds = isset($roster['starters']) && is_array($roster['starters']) ? $roster['starters'] : [];
            $players = isset($roster['players']) && is_array($roster['players']) ? $roster['players'] : [];

            $starterSet = [];
            foreach ($starterIds as $pid) {
                if ($pid !== '0' && $pid !== null) {
                    $starters[] = (string) $pid;
                    $starterSet[(string) $pid] = true;
                }
            }

            foreach ($players as $pid) {
                $pidStr = (string) $pid;
                if (!isset($starterSet[$pidStr])) {
                    $bench[] = $pidStr;
                }
            }

            $result[(int) ($roster['roster_id'] ?? 0)] = [
                'starters' => $starters,
                'bench' => $bench,
                'owner_id' => $roster['owner_id'] ?? null,
            ];
        }

        return $result;
    }
}
