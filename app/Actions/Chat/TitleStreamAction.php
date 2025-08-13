<?php

namespace App\Actions\Chat;

use App\Models\Chat;
use Illuminate\Http\StreamedEvent;
use Illuminate\Support\Facades\Log;

class TitleStreamAction
{
    public function __construct(private readonly GenerateChatTitleAction $generateChatTitle) {}

    public function __invoke(Chat $chat)
    {
        Log::info('Title stream requested for chat', ['chat_id' => $chat->id, 'title' => $chat->title]);

        return response()->eventStream(function () use ($chat) {
            if ($chat->title && $chat->title !== 'Untitled') {
                yield new StreamedEvent(
                    event: 'title-update',
                    data: json_encode(['title' => $chat->title])
                );

                return;
            }

            ($this->generateChatTitle)($chat);

            $startTime = time();
            $timeout = 30;

            while (time() - $startTime < $timeout) {
                $chat->refresh();

                if ($chat->title !== 'Untitled') {
                    yield new StreamedEvent(
                        event: 'title-update',
                        data: json_encode(['title' => $chat->title])
                    );
                    break;
                }

                usleep(500000);
            }
        }, endStreamWith: new StreamedEvent(event: 'title-update', data: '</stream>'));
    }
}
