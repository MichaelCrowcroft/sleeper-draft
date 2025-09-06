<?php

use Livewire\Volt\Volt;

it('can render', function () {
    $component = Volt::test('pages.players.show');

    $component->assertSee('');
});
