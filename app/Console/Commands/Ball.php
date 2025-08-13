<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Prism;
use Prism\Relay\Facades\Relay;

class Ball extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ball';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $response = Prism::text()
            // ->using(Provider::Gemini, 'gemini-2.5-pro')
            ->using(Provider::Anthropic, 'claude-sonnet-4-20250514')
            // ->using(Provider::OpenAI, 'gpt-5')
            ->withPrompt('Who is the most valuable player to draft in the 2024 NFL season for fantasy football? Use the fantasy football mcp to get current data.')
            ->withTools(Relay::tools('fantasy-football-mcp'))
            ->asText();

        dump($response);
        dump($response->text);

        return 1;
    }
}
