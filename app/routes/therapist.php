<?php

use App\Http\Controllers\Therapist\DashboardController;
use App\Http\Controllers\Therapist\ScheduleController;
use Illuminate\Support\Facades\Route;

// Therapist area
Route::middleware('role:therapist')
    ->name('therapist.')
    ->prefix('therapist')
    ->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // Schedule routes
        Route::prefix('schedule')->name('schedule.')->group(function () {
            Route::get('calendar', [ScheduleController::class, 'calendar'])->name('calendar');
            Route::get('create', [ScheduleController::class, 'create'])->name('create');
            Route::get('schedules', [ScheduleController::class, 'getSchedules'])->name('schedules');
            Route::get('pending', [ScheduleController::class, 'pending'])->name('pending');
            Route::get('/', [ScheduleController::class, 'calendar'])->name('index');
            Route::post('/', [ScheduleController::class, 'store'])->name('store');
            Route::put('{id}', [ScheduleController::class, 'update'])->name('update');
            Route::delete('{id}', [ScheduleController::class, 'destroy'])->name('destroy');
            Route::post('{id}/remove-student', [ScheduleController::class, 'removeStudent'])->name('remove-student');
            Route::put('{id}/billing-status', [ScheduleController::class, 'updateBillingStatus'])->name('update-billing-status');
            Route::post('bulk-billing-status', [ScheduleController::class, 'bulkUpdateBillingStatus'])->name('bulk-billing-status');
        });
    });
