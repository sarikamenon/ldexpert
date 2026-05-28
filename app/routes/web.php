<?php

use App\Http\Controllers\DashboardRedirectController;
use App\Http\Controllers\Payment\PaymentController;
use App\Http\Controllers\Payment\WebhookController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Public\ScheduleMakeupResponseController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', DashboardRedirectController::class)
    ->middleware(['auth'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/cache/clear', function () {
        Artisan::call('cache:clear');
        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');

        return response()->json(['message' => 'Application caches cleared.']);
    })->name('cache.clear');

    require __DIR__.'/therapist.php';
    require __DIR__.'/admin.php';
    require __DIR__.'/student.php';
});

// Payment routes (public, no auth required - accessed via token)
Route::prefix('payment')->name('payment.')->group(function () {
    Route::get('/success', [PaymentController::class, 'success'])->name('success');
    Route::get('/cancel/{token}', [PaymentController::class, 'cancel'])->name('cancel');
    Route::get('/{token}', [PaymentController::class, 'show'])->name('show');
    Route::post('/{token}/checkout', [PaymentController::class, 'checkout'])->name('checkout');
});

// Payment gateway webhooks (CSRF excluded via bootstrap/app.php)
Route::post('/webhooks/stripe', [WebhookController::class, 'handle'])
    ->name('webhooks.stripe');

// Public make-up response endpoints (unauthenticated; signed + throttled).
// Hit from the parent reminder email's Request / Decline buttons.
Route::prefix('makeup-response')
    ->name('makeup-response.')
    ->middleware(['signed', 'throttle:10,1'])
    ->group(function () {
        Route::get('/{token}/request', [ScheduleMakeupResponseController::class, 'request'])->name('request');
        Route::post('/{token}/pick-slots', [ScheduleMakeupResponseController::class, 'pickSlots'])->name('pick-slots');
        Route::get('/{token}/decline', [ScheduleMakeupResponseController::class, 'decline'])->name('decline');
    });

require __DIR__.'/auth.php';
