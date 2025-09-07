<?php

use Livewire\Volt\Volt;

it('can render', function () {
    $component = Volt::test('players.index');

    $component->assertSee('');
});
