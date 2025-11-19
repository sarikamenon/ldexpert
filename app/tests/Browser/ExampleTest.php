<?php

use Laravel\Dusk\Browser;

test('landing redirects to login page', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/')
            ->assertPathIs('/login')
            ->assertSee('Sign in');
    });
});
