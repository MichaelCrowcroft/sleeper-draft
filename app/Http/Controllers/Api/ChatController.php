<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    public function index()
    {
        if (! Auth::check()) {
            return response()->json([]);
        }

        $chats = Auth::user()->chats()
            ->latest()
            ->get()
            ->map(function ($chat) {
                return [
                    'id' => $chat->id,
                    'title' => $chat->title ?? 'Untitled Chat',
                    'created_at' => $chat->created_at,
                    'updated_at' => $chat->updated_at,
                ];
            });

        return response()->json($chats);
    }
}
