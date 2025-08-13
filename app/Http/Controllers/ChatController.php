<?php

namespace App\Http\Controllers;

use App\Http\Requests\Chat\StoreChatRequest;
use App\Http\Requests\Chat\UpdateChatRequest;
use App\Models\Chat;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ChatController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        return Inertia::render('Chats');
    }

    public function show(Chat $chat)
    {
        $this->authorize('view', $chat);

        $chat->load('messages');

        return Inertia::render('Chat', [
            'chat' => $chat,
        ]);
    }

    public function store(StoreChatRequest $request)
    {
        $validated = $request->validated();
        $title = $validated['title'] ?? null;

        // If no title provided, use "Untitled" initially
        if (! $title) {
            $title = 'Untitled';
        }

        $chat = Auth::user()->chats()->create([
            'title' => $title,
        ]);

        // If firstMessage provided, save it and trigger streaming via URL parameter
        if (! empty($validated['firstMessage'])) {
            // Save the first message
            $chat->messages()->create([
                'type' => 'prompt',
                'role' => 'user',
                'content' => $validated['firstMessage'],
            ]);

            return redirect()->route('chat.show', $chat)->with('stream', true);
        }

        return redirect()->route('chat.show', $chat);
    }

    public function update(UpdateChatRequest $request, Chat $chat)
    {
        $this->authorize('update', $chat);

        $chat->update([
            'title' => $request->validated('title'),
        ]);

        return redirect()->back();
    }

    public function destroy(Chat $chat)
    {
        $this->authorize('delete', $chat);

        $chatId = $chat->id;
        $chat->delete();

        // Check if this is the current chat being viewed
        $currentUrl = request()->header('Referer') ?? '';
        $isCurrentChat = str_contains($currentUrl, "/chat/{$chatId}");

        if ($isCurrentChat) {
            // If deleting the current chat, redirect to home
            return redirect()->route('home');
        } else {
            // If deleting from sidebar, redirect back to current page
            return redirect()->back();
        }
    }
}
