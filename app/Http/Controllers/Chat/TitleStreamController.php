<?php

namespace App\Http\Controllers\Chat;

use App\Actions\Chat\TitleStreamAction;
use App\Http\Controllers\Controller;
use App\Models\Chat;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class TitleStreamController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(Chat $chat, TitleStreamAction $titleStream)
    {
        $this->authorize('view', $chat);

        return $titleStream($chat);
    }
}
