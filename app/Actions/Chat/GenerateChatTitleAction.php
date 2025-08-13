<?php

namespace App\Actions\Chat;

use App\Models\Chat;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Prism;

class GenerateChatTitleAction
{
    public function __invoke(Chat $chat): void
    {
        $firstMessage = $chat->messages()->where('type', 'prompt')->first();

        if (! $firstMessage) {
            return;
        }

        try {
            $response = Prism::text()
                ->using(Provider::OpenAI, 'gpt-4.1-nano')
                ->withSystemPrompt('Generate a concise, descriptive title (max 50 characters) for a chat that starts with the following message. Respond with only the title, no quotes or extra formatting.')
                ->withPrompt($firstMessage->content)
                ->asText();

            $generatedTitle = trim($response->text ?? '');

            if (strlen($generatedTitle) > 50) {
                $generatedTitle = substr($generatedTitle, 0, 47).'...';
            }

            $chat->update(['title' => $generatedTitle]);

            Log::info('Generated title for chat', ['chat_id' => $chat->id, 'title' => $generatedTitle]);
        } catch (\Exception $e) {
            $fallbackTitle = substr($firstMessage->content, 0, 47).'...';
            $chat->update(['title' => $fallbackTitle]);
            Log::error('Error generating title, using fallback', ['error' => $e->getMessage()]);
        }
    }
}
