<?php

namespace Tests;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->guardTestDatabase();

        // Disable CSRF for test runs to avoid 419s on form posts
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    /**
     * Abort the entire run if tests are about to touch a non-test database.
     *
     * RefreshDatabase migrates/truncates whatever connection is actually active
     * at runtime. If tests are launched without the bird_test overrides (e.g. a
     * raw `php artisan test` with cached config), that connection can resolve to
     * the real `bird` dev DB and wipe it. This fails loudly before any migration
     * runs, regardless of how the suite was started.
     */
    private function guardTestDatabase(): void
    {
        $database = DB::connection()->getDatabaseName();

        if (! is_string($database) || ! str_ends_with($database, '_test')) {
            throw new RuntimeException(sprintf(
                'ABORTING: tests are pointed at database [%s], not a *_test database. '
                .'This would wipe real data. Run tests via `make test` (which forces '
                .'DB_DATABASE=bird_test), and clear cached config with `php artisan config:clear`.',
                is_string($database) ? $database : '(unknown)'
            ));
        }
    }
}
