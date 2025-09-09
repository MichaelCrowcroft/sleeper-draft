<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Prism;
use Prism\Prism\ValueObjects\ProviderTool;
use Prism\Relay\Facades\Relay;

class RunPrism extends Command
{
    protected $signature = 'prism:run';

    public function handle()
    {
        $response = Prism::text()
            // ->using(Provider::Gemini, 'gemini-2.5-flash')
            ->using(Provider::Groq, 'openai/gpt-oss-120b')
            ->withProviderTools([
                new ProviderTool(type: 'browser_search')
            ])
            ->withTools(Relay::tools('sleeperdraft'))
            ->withPrompt('You are the commisioner of a Sleeper Fantasy Football league. You are producing a summary of the week that has just been to share who the winners and loser are. You want to make sure this update highlights that big boom and bust players, and any upsets. Make it hype for the league. Validate your response with search. Look up CoachCanCrusher to find the league, Week: 1, Season: 2025 Use the MCP multiple times to get information')
            ->withMaxSteps(50)
            ->asText();

        echo $response->text;
        dd($response);
    }
}
