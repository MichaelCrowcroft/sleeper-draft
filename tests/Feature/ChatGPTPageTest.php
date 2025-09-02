<?php

test('chatgpt page renders successfully', function () {
    $response = $this->get('/chatgpt');

    $response->assertStatus(200)
        ->assertSee('ChatGPT Custom GPT')
        ->assertSee('Try the Custom GPT')
        ->assertSee('Custom GPT Features');
});
