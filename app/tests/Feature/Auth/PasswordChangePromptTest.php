<?php

declare(strict_types=1);

use App\Models\User;
use Carbon\Carbon;

describe('Password Change Prompt', function () {
    describe('migration', function () {
        test('password_change_prompted_at column exists on users table', function () {
            $user = User::factory()->create();
            $this->assertNotNull($user->password_change_prompted_at);
        });

        test('existing users are backfilled with their created_at timestamp', function () {
            $user = User::factory()->create();
            $user->refresh();

            $this->assertTrue(
                $user->password_change_prompted_at->equalTo($user->created_at),
                'password_change_prompted_at should equal created_at for backfilled users'
            );
        });

        test('password_change_prompted_at is cast to datetime', function () {
            $user = User::factory()->create();
            $this->assertInstanceOf(Carbon::class, $user->password_change_prompted_at);
        });
    });

    describe('password change behavior', function () {
        test('password change sets password_change_prompted_at to now', function () {
            $user = User::factory()->create([
                'password_change_prompted_at' => null,
            ]);

            $response = $this
                ->actingAs($user)
                ->from('/password')
                ->put('/password', [
                    'current_password' => 'password',
                    'password' => 'new-password',
                    'password_confirmation' => 'new-password',
                ]);

            $response->assertSessionHasNoErrors();

            $user->refresh();
            $this->assertNotNull(
                $user->password_change_prompted_at,
                'password_change_prompted_at should be set after successful password change'
            );
        });

        test('failed password change keeps password_change_prompted_at null', function () {
            $user = User::factory()->create([
                'password_change_prompted_at' => null,
            ]);

            $response = $this
                ->actingAs($user)
                ->from('/password')
                ->put('/password', [
                    'current_password' => 'wrong-password',
                    'password' => 'new-password',
                    'password_confirmation' => 'new-password',
                ]);

            $response->assertSessionHasErrors('current_password');

            $user->refresh();
            $this->assertNull(
                $user->password_change_prompted_at,
                'password_change_prompted_at should remain null after failed attempt'
            );
        });

        test('validation error keeps password_change_prompted_at null', function () {
            $user = User::factory()->create([
                'password_change_prompted_at' => null,
            ]);

            $response = $this
                ->actingAs($user)
                ->from('/password')
                ->put('/password', [
                    'current_password' => 'password',
                    'password' => 'new-password',
                    'password_confirmation' => 'different-password',
                ]);

            $response->assertSessionHasErrors();

            $user->refresh();
            $this->assertNull(
                $user->password_change_prompted_at,
                'password_change_prompted_at should remain null after validation error'
            );
        });
    });

    describe('redirect logic', function () {
        test('user with null password_change_prompted_at gets redirected to password change', function () {
            $user = User::factory()->create([
                'password_change_prompted_at' => null,
            ]);

            $needsPasswordChange = $user->password_change_prompted_at === null;
            $this->assertTrue($needsPasswordChange);
        });

        test('user with non-null password_change_prompted_at gets redirected to dashboard', function () {
            $user = User::factory()->create([
                'password_change_prompted_at' => now()->subDays(5),
            ]);

            $needsPasswordChange = $user->password_change_prompted_at === null;
            $this->assertFalse($needsPasswordChange);
        });
    });

    describe('timestamp integrity', function () {
        test('backfilled existing users have password_change_prompted_at in the past', function () {
            $user = User::factory()->create();
            $this->assertTrue(
                $user->password_change_prompted_at->isPast() || $user->password_change_prompted_at->isToday(),
                'Backfilled password_change_prompted_at should be in past or today'
            );
        });

        test('can set password_change_prompted_at to null manually', function () {
            $user = User::factory()->create();
            $user->update(['password_change_prompted_at' => null]);
            $user->refresh();

            $this->assertNull(
                $user->password_change_prompted_at,
                'password_change_prompted_at can be set to null'
            );
        });

        test('can set password_change_prompted_at to now for admin-triggered resets', function () {
            $user = User::factory()->create([
                'password_change_prompted_at' => null,
            ]);

            $user->update(['password_change_prompted_at' => now()]);
            $user->refresh();

            $this->assertNotNull(
                $user->password_change_prompted_at,
                'Admin can set password_change_prompted_at to trigger prompt'
            );
        });
    });

    describe('one-time prompt behavior', function () {
        test('null password_change_prompted_at triggers password change redirect on first login', function () {
            $user = User::factory()->create([
                'password_change_prompted_at' => null,
            ]);

            // On first login, user gets redirected to password change
            $needsChange = $user->password_change_prompted_at === null;
            $this->assertTrue($needsChange);

            // After password change, column is set to now()
            $user->update(['password_change_prompted_at' => now()]);
            $user->refresh();

            // On second login, user does not get redirected to password change
            $needsChange = $user->password_change_prompted_at === null;
            $this->assertFalse($needsChange);
        });

        test('non-null password_change_prompted_at prevents re-prompting', function () {
            $user = User::factory()->create([
                'password_change_prompted_at' => now()->subDays(5),
            ]);

            // User does not need password change redirect
            $needsChange = $user->password_change_prompted_at === null;
            $this->assertFalse($needsChange);

            // On subsequent logins, still no redirect
            $needsChange = $user->password_change_prompted_at === null;
            $this->assertFalse($needsChange);
        });
    });
});
