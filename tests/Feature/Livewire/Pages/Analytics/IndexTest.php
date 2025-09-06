<?php

use Livewire\Volt\Volt;

it('can render', function () {
    $component = Volt::test('pages.analytics.index');

    $component->assertSee('');
});
