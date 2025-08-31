<?php

declare(strict_types=1);

it('renders the privacy policy page', function () {
    $response = $this->get('/privacy');

    $response->assertSuccessful()
        ->assertSee('Privacy Policy')
        ->assertSee('Information We Collect')
        ->assertSee('How We Use Your Information')
        ->assertSee('Data Security')
        ->assertSee('Contact Us');
});

it('privacy policy page has correct route name', function () {
    $response = $this->get(route('privacy'));

    $response->assertSuccessful();
});

it('privacy policy page includes back to home link', function () {
    $response = $this->get('/privacy');

    $response->assertSuccessful()
        ->assertSee('Back to Home');
});
