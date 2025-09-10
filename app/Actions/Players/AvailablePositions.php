<?php

namespace App\Actions\Players;

use App\Models\Player;
use Illuminate\Support\Facades\Cache;

class AvailablePositions
{
    public function execute(): array
    {
        return ['QB', 'RB', 'WR', 'TE', 'K', 'DEF'];
    }
}
