<?php

use App\Http\Controllers\Therapist\Billing\TherapistBillController;
use App\Http\Controllers\Therapist\DashboardController;
use App\Http\Controllers\Therapist\ScheduleController;
use App\Http\Controllers\Therapist\SessionLogController;
use App\Http\Controllers\Therapist\SessionLogDocumentController;
use App\Http\Controllers\Therapist\SSAController;
use App\Http\Controllers\Therapist\StudentCommentController;
use App\Http\Controllers\Therapist\StudentController;
use App\Http\Controllers\Therapist\StudentDocumentController;
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
            Route::get('calendar-events', [ScheduleController::class, 'getCalendarEvents'])->name('calendar-events');
            Route::get('create', [ScheduleController::class, 'create'])->name('create');
            Route::get('schedules', [ScheduleController::class, 'getSchedules'])->name('schedules');
            Route::get('pending', [ScheduleController::class, 'pending'])->name('pending');
            Route::get('/', [ScheduleController::class, 'calendar'])->name('index');
            Route::post('/', [ScheduleController::class, 'store'])->name('store');
            Route::get('{id}/edit', [ScheduleController::class, 'edit'])->name('edit');
            Route::get('{id}', [ScheduleController::class, 'show'])->name('show');
            Route::put('{id}', [ScheduleController::class, 'update'])->name('update');
            Route::delete('{id}', [ScheduleController::class, 'destroy'])->name('destroy');
            Route::post('{id}/remove-student', [ScheduleController::class, 'removeStudent'])->name('remove-student');
            Route::put('{id}/billing-status', [ScheduleController::class, 'updateBillingStatus'])->name('update-billing-status');
            Route::post('bulk-billing-status', [ScheduleController::class, 'bulkUpdateBillingStatus'])->name('bulk-billing-status');
        });

        // SSA routes
        Route::get('ssas', [SSAController::class, 'index'])->name('ssas.index');
        Route::get('ssas/{ssa}', [SSAController::class, 'show'])->name('ssas.show');

        // Student routes
        Route::get('students', [StudentController::class, 'index'])->name('students.index');
        Route::get('students/{student}', [StudentController::class, 'show'])->name('students.show');
        Route::post('students/{student}/comments', [StudentCommentController::class, 'store'])->name('students.comments.store');
        Route::post('students/{student}/documents', [StudentDocumentController::class, 'store'])->name('students.documents.store');

        Route::prefix('student-documents')->name('student-documents.')->group(function () {
            Route::get('{document}/download', [StudentDocumentController::class, 'download'])->name('download');
            Route::delete('{document}', [StudentDocumentController::class, 'destroy'])->name('destroy');
        });

        // Session Log routes
        Route::prefix('session-logs')->name('session-logs.')->group(function () {
            Route::get('/', [SessionLogController::class, 'index'])->name('index');
            Route::get('select-ssa', [SessionLogController::class, 'selectSSA'])->name('select-ssa');
            Route::get('create', [SessionLogController::class, 'create'])->name('create');
            Route::get('create/schedule/{schedule}', [SessionLogController::class, 'create'])->name('create.from-schedule');
            Route::post('/', [SessionLogController::class, 'store'])->name('store');
            Route::get('{sessionLog}', [SessionLogController::class, 'show'])->name('show');
            Route::get('{sessionLog}/edit', [SessionLogController::class, 'edit'])->name('edit');
            Route::put('{sessionLog}', [SessionLogController::class, 'update'])->name('update');
            Route::post('{sessionLog}/submit', [SessionLogController::class, 'submit'])->name('submit');
            Route::post('{sessionLog}/cancel', [SessionLogController::class, 'cancel'])->name('cancel');

            // Session Log Documents
            Route::prefix('{sessionLog}/documents')->name('documents.')->group(function () {
                Route::post('/', [SessionLogDocumentController::class, 'store'])->name('store');
                Route::get('{document}/download', [SessionLogDocumentController::class, 'download'])->name('download');
                Route::delete('{document}', [SessionLogDocumentController::class, 'destroy'])->name('destroy');
            });
        });

        // Billing routes
        Route::get('billing/{bill}/download', [TherapistBillController::class, 'download'])->name('billing.download');
        Route::get('billing/{bill}', [TherapistBillController::class, 'show'])->name('billing.show');
        Route::get('billing', [TherapistBillController::class, 'index'])->name('billing.index');
    });
