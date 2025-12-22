<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\SchoolContractController;
use App\Http\Controllers\Admin\SchoolController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\SessionLogController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SSAController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\TherapistContractController;
use App\Http\Controllers\Admin\TherapistController;
use Illuminate\Support\Facades\Route;

Route::middleware('role:admin')
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('schools/export', [SchoolController::class, 'export'])->name('schools.export');
        Route::patch('schools/{school}/status', [SchoolController::class, 'updateStatus'])->name('schools.status');
        Route::resource('schools', SchoolController::class)->except(['destroy']);

        Route::get('therapists/export', [TherapistController::class, 'export'])->name('therapists.export');
        Route::patch('therapists/{therapist}/status', [TherapistController::class, 'updateStatus'])->name('therapists.status');
        Route::resource('therapists', TherapistController::class)->except(['destroy']);

        Route::get('students/export', [StudentController::class, 'export'])->name('students.export');
        Route::patch('students/{student}/status', [StudentController::class, 'updateStatus'])->name('students.status');
        Route::resource('students', StudentController::class)->except(['destroy']);

        Route::patch('services/{service}/status', [ServiceController::class, 'updateStatus'])->name('services.status');
        Route::resource('services', ServiceController::class)->except(['destroy', 'show']);

        Route::get('ssas/export', [SSAController::class, 'export'])->name('ssas.export');
        Route::patch('ssas/{ssa}/status', [SSAController::class, 'updateStatus'])->name('ssas.status');
        Route::post('ssas/{ssa}/assign-therapist', [SSAController::class, 'assignTherapist'])->name('ssas.assign-therapist');
        Route::post('ssas/{ssa}/unassign-therapist', [SSAController::class, 'unassignTherapist'])->name('ssas.unassign-therapist');
        Route::resource('ssas', SSAController::class)->except(['destroy']);

        Route::prefix('contracts')
            ->name('contracts.')
            ->group(function () {
                Route::patch('schools/{schoolContract}/status', [SchoolContractController::class, 'updateStatus'])
                    ->name('schools.status');
                Route::resource('schools', SchoolContractController::class)
                    ->parameters(['schools' => 'schoolContract'])
                    ->except(['destroy']);

                Route::patch('therapists/{therapistContract}/status', [TherapistContractController::class, 'updateStatus'])
                    ->name('therapists.status');
                Route::resource('therapists', TherapistContractController::class)
                    ->parameters(['therapists' => 'therapistContract'])
                    ->except(['destroy']);
            });

        // Activity Logs
        Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
        Route::get('activity-logs/export', [ActivityLogController::class, 'export'])->name('activity-logs.export');

        // Analytics
        Route::get('analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
        Route::get('analytics/schools', [AnalyticsController::class, 'schools'])->name('analytics.schools');
        Route::get('analytics/therapists', [AnalyticsController::class, 'therapists'])->name('analytics.therapists');

        // Notifications
        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('notifications/unread', [NotificationController::class, 'unread'])->name('notifications.unread');
        Route::post('notifications/{id}/mark-read', [NotificationController::class, 'markAsRead'])->name('notifications.mark-read');
        Route::post('notifications/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');
        Route::delete('notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

        // Session Logs
        Route::prefix('session-logs')->name('session-logs.')->group(function () {
            Route::get('/', [SessionLogController::class, 'index'])->name('index');
            Route::get('{sessionLog}', [SessionLogController::class, 'show'])->name('show');
            Route::get('{sessionLog}/edit', [SessionLogController::class, 'edit'])->name('edit');
            Route::put('{sessionLog}', [SessionLogController::class, 'update'])->name('update');
            Route::post('{sessionLog}/finalize', [SessionLogController::class, 'finalize'])->name('finalize');
            Route::post('{sessionLog}/cancel', [SessionLogController::class, 'cancel'])->name('cancel');
        });

        // Settings
        Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
    });
