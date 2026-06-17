<?php

declare(strict_types=1);

use Laravel\Dusk\Browser;
use Tests\BrowserQA\QaDuskTestCase;

uses(QaDuskTestCase::class);

it('guest visiting home is redirected to login', function (): void {
    $this->browse(function (Browser $browser): void {
        $browser->visit('/')
            ->assertPathIs('/login')
            ->waitFor('input[name="username"]', 10)
            ->assertPresent('input[name="username"]')
            ->assertPresent('input[name="password"]');
    });
})->group('smoke');
