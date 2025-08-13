<?php

namespace App\Http\Controllers\Chat;

use App\Actions\Chat\StreamChatAction;
use App\Http\Controllers\Controller;
use App\Models\Chat;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class StreamController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(Request $request, ?Chat $chat, StreamChatAction $streamChat)
    {
        if ($chat) {
            $this->authorize('view', $chat);
        }

        return $streamChat($request, $chat);
    }
}
