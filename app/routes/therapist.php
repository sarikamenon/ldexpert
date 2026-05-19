<?php

use App\Http\Controllers\Therapist\Billing\TherapistBillController;
use App\Http\Controllers\Therapist\DashboardController;
use App\Http\Controllers\Therapist\Finance\PayStubController;
use App\Http\Controllers\Therapist\QGlobRequestController;
use App\Http\Controllers\Therapist\ScheduleCalendarController;
use App\Http\Controllers\Therapist\ScheduleController;
use App\Http\Controllers\Therapist\SchoolCalendarController;
use App\Http\Controllers\Therapist\SessionLogController;
use App\Http\Controllers\Therapist\SessionLogDocumentController;
use App\Http\Controllers\Therapist\SSAController;
use App\Http\Controllers\Therapist\SSAGoalController;
use App\Http\Controllers\Therapist\StudentCommentController;
use App\Http\Controllers\Therapist\StudentController;
use App\Http\Controllers\Therapist\StudentDocumentController;
use App\Http\Controllers\Therapist\SubRequestController;
use Illuminate\Support\Facades\Route;

// Therapist area
Route::middleware('role:therapist')
    ->name('therapist.')
    ->prefix('therapist')
    ->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // Schedule routes
        Route::prefix('schedule')->name('schedule.')->group(function () {
            Route::get('create', [ScheduleController::class, 'create'])->name('create');
            Route::get('schedules', [ScheduleController::class, 'getSchedules'])->name('schedules');
            Route::get('pending', [ScheduleController::class, 'pending'])->name('pending');
            Route::post('/', [ScheduleController::class, 'store'])->name('store');
            Route::get('{id}/edit', [ScheduleController::class, 'edit'])->name('edit')->whereNumber('id');
            Route::get('{id}', [ScheduleController::class, 'show'])->name('show')->whereNumber('id');
            Route::put('{id}', [ScheduleController::class, 'update'])->name('update')->whereNumber('id');
            Route::delete('{id}', [ScheduleController::class, 'destroy'])->name('destroy')->whereNumber('id');
            Route::delete('{id}/future-recurring', [ScheduleController::class, 'destroyFutureRecurring'])->name('destroy-future-recurring')->whereNumber('id');
            Route::post('{id}/remove-student', [ScheduleController::class, 'removeStudent'])->name('remove-student')->whereNumber('id');
            Route::put('{id}/billing-status', [ScheduleController::class, 'updateBillingStatus'])->name('update-billing-status')->whereNumber('id');
            Route::post('bulk-billing-status', [ScheduleController::class, 'bulkUpdateBillingStatus'])->name('bulk-billing-status');
        });

        // Schedule Full Calendar
        Route::prefix('schedule/calendar')->name('schedule-calendar.')->group(function () {
            Route::get('/', [ScheduleCalendarController::class, 'index'])->name('index');
            Route::get('events', [ScheduleCalendarController::class, 'events'])->name('events');
        });

        // School Calendar (read-only)
        Route::prefix('school-calendar')->name('school-calendar.')->group(function () {
            Route::get('/', [SchoolCalendarController::class, 'index'])->name('index');
            Route::get('{school}/events', [SchoolCalendarController::class, 'events'])->name('events');
        });

        // SSA routes
        Route::get('ssas', [SSAController::class, 'index'])->name('ssas.index');
        Route::post('ssas/data', [SSAController::class, 'data'])->name('ssas.data');
        Route::get('ssas/{ssa}', [SSAController::class, 'show'])->name('ssas.show');

        Route::prefix('ssas/{ssa}/goals')->name('ssas.goals.')->group(function () {
            Route::get('create', [SSAGoalController::class, 'create'])->name('create');
            Route::post('/', [SSAGoalController::class, 'store'])->name('store');
            Route::get('{goal}/edit', [SSAGoalController::class, 'edit'])->name('edit');
            Route::put('{goal}', [SSAGoalController::class, 'update'])->name('update');
            Route::patch('{goal}/status', [SSAGoalController::class, 'changeStatus'])->name('change-status');
        });

        // Student routes
        Route::post('students/data', [StudentController::class, 'data'])->name('students.data');
        Route::get('students', [StudentController::class, 'index'])->name('students.index');
        Route::get('students/{student}', [StudentController::class, 'show'])->name('students.show');
        Route::post('students/{student}/comments', [StudentCommentController::class, 'store'])->name('students.comments.store');
        Route::post('students/{student}/documents', [StudentDocumentController::class, 'store'])->name('students.documents.store');

        Route::prefix('student-documents')->name('student-documents.')->group(function () {
            Route::get('{document}/download', [StudentDocumentController::class, 'download'])->name('download');
            Route::delete('{document}', [StudentDocumentController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('qglob-requests')->name('qglob-requests.')->group(function () {
            Route::get('/', [QGlobRequestController::class, 'index'])->name('index');
            Route::post('data', [QGlobRequestController::class, 'data'])->name('data');
            Route::get('create', [QGlobRequestController::class, 'create'])->name('create');
            Route::post('/', [QGlobRequestController::class, 'store'])->name('store');
            Route::get('{qglob_request}', [QGlobRequestController::class, 'show'])->name('show');
            Route::delete('{qglob_request}', [QGlobRequestController::class, 'destroy'])->name('destroy');
        });

        // Sub request routes
        Route::prefix('sub-requests')->name('sub-requests.')->group(function () {
            Route::get('/', [SubRequestController::class, 'index'])->name('index');
            Route::post('data', [SubRequestController::class, 'data'])->name('data');
            Route::get('eligible-subs', [SubRequestController::class, 'eligibleSubs'])->name('eligible-subs');
            Route::post('{subRequest}/accept', [SubRequestController::class, 'accept'])->name('accept')->whereNumber('subRequest');
            Route::post('{subRequest}/decline', [SubRequestController::class, 'decline'])->name('decline')->whereNumber('subRequest');
            Route::post('{subRequest}/cancel', [SubRequestController::class, 'cancel'])->name('cancel')->whereNumber('subRequest');
            Route::patch('{subRequest}/invitees', [SubRequestController::class, 'updateInvitees'])->name('invitees.update')->whereNumber('subRequest');
            Route::get('{subRequest}/eligible-subs', [SubRequestController::class, 'eligibleSubs'])->name('eligible-subs-for-request')->whereNumber('subRequest');
        });

        Route::post('schedules/{schedule}/sub-request', [SubRequestController::class, 'store'])->name('sub-requests.store-for-schedule')->whereNumber('schedule');

        // Session Log routes
        Route::prefix('session-logs')->name('session-logs.')->group(function () {
            Route::get('/', [SessionLogController::class, 'index'])->name('index');
            Route::post('data', [SessionLogController::class, 'data'])->name('data');
            Route::get('select-ssa', [SessionLogController::class, 'selectSSA'])->name('select-ssa');
            Route::get('entry-window', [SessionLogController::class, 'entryWindow'])->name('entry-window');
            Route::get('create', [SessionLogController::class, 'create'])->name('create');
            Route::get('create/schedule/{schedule}', [SessionLogController::class, 'create'])->name('create.from-schedule');
            Route::post('/', [SessionLogController::class, 'store'])->name('store');
            Route::get('{sessionLog}', [SessionLogController::class, 'show'])->name('show');
            Route::get('{sessionLog}/edit', [SessionLogController::class, 'edit'])->name('edit');
            Route::put('{sessionLog}', [SessionLogController::class, 'update'])->name('update');
            Route::post('{sessionLog}/submit', [SessionLogController::class, 'submit'])->name('submit');
            Route::post('{sessionLog}/cancel', [SessionLogController::class, 'cancel'])->name('cancel');
            Route::post('{sessionLog}/comment', [SessionLogController::class, 'addComment'])->name('comment');

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

        // Pay Stub routes
        Route::prefix('finance/pay-stub')->name('finance.pay-stub.')->group(function () {
            Route::get('download', [PayStubController::class, 'download'])->name('download');
            Route::get('/', [PayStubController::class, 'index'])->name('index');
        });
    });
