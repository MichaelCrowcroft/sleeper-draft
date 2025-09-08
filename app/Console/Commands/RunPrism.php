<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Prism;
use Prism\Relay\Facades\Relay;

class RunPrism extends Command
{
    protected $signature = 'prism:run';


    public function handle()
    {
        $response = Prism::text()
            ->using(Provider::Groq, 'openai/gpt-oss-120b')
            ->withTools(Relay::tools('sleeperdraft'))
            ->withPrompt('Who is the best QB in the 2024 season based on their total points?')
            ->withMaxSteps(10)
            ->asText();

        echo $response->text;
        // dd($response);
    }
}
