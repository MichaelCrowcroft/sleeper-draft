<?php

namespace App\Actions\Players;

class ApplyPositionPreset
{
    public function execute(?string $position, array $selectedMetrics): array
    {
        $position = strtoupper((string) ($position ?? ''));

        $enable = function(array $keys) use (&$selectedMetrics) {
            foreach ($keys as $k) {
                if (array_key_exists($k, $selectedMetrics)) {
                    $selectedMetrics[$k] = true;
                }
            }
        };

        switch ($position) {
            case 'QB':
                $enable(['proj_pts_week', 'pass_att', 'pass_cmp', 'pass_yd', 'pass_td', 'pass_int', 'pass_sack', 'cmp_pct', 'rush_att', 'rush_yd', 'rush_td']);
                break;
            case 'RB':
                $enable(['proj_pts_week', 'rush_att', 'rush_yd', 'rush_td', 'rush_fd', 'rush_40p', 'rec', 'rec_tgt', 'rec_yd', 'rec_td', 'rec_fd']);
                break;
            case 'WR':
                $enable(['proj_pts_week', 'rec', 'rec_tgt', 'rec_yd', 'rec_td', 'rec_fd', 'rec_40p', 'rec_0_4', 'rec_5_9', 'rec_10_19', 'rec_20_29', 'rec_30_39']);
                break;
            case 'TE':
                $enable(['proj_pts_week', 'rec', 'rec_tgt', 'rec_yd', 'rec_td', 'rec_fd', 'rec_2pt']);
                break;
            case 'K':
                $enable(['proj_pts_week']);
                break;
            case 'DEF':
                $enable(['proj_pts_week', 'def_fum_td', 'fum', 'fum_lost']);
                break;
        }

        return $selectedMetrics;
    }
}
