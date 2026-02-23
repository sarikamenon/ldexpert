<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\Billing\TherapistBillController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ExpenseCategoryController;
use App\Http\Controllers\Admin\ExpenseController;
use App\Http\Controllers\Admin\Finance\IrsReportController;
use App\Http\Controllers\Admin\FinanceDashboardController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\InvoicePaymentController;
use App\Http\Controllers\Admin\InvoicePaymentsListController;
use App\Http\Controllers\Admin\LedgerAccountController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\Reports\SSACaseloadReportController;
use App\Http\Controllers\Admin\Reports\SSAExpirationReportController;
use App\Http\Controllers\Admin\Reports\SSAUtilizationReportController;
use App\Http\Controllers\Admin\SchoolCalendarEventController;
use App\Http\Controllers\Admin\SchoolContractController;
use App\Http\Controllers\Admin\SchoolController;
use App\Http\Controllers\Admin\PositionController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\SessionLogController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SSAController;
use App\Http\Controllers\Admin\StudentCommentController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\StudentDocumentController;
use App\Http\Controllers\Admin\TherapistBillPaymentController;
use App\Http\Controllers\Admin\TherapistBillPaymentsListController;
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
        Route::get('schools/{school}/calendar-events', [SchoolCalendarEventController::class, 'index'])
            ->name('schools.calendar-events.index');
        Route::post('schools/{school}/calendar-events', [SchoolCalendarEventController::class, 'store'])
            ->name('schools.calendar-events.store');
        Route::put('schools/{school}/calendar-events/{event}', [SchoolCalendarEventController::class, 'update'])
            ->name('schools.calendar-events.update');
        Route::delete('schools/{school}/calendar-events/{event}', [SchoolCalendarEventController::class, 'destroy'])
            ->name('schools.calendar-events.destroy');

        Route::get('therapists/export', [TherapistController::class, 'export'])->name('therapists.export');
        Route::patch('therapists/{therapist}/status', [TherapistController::class, 'updateStatus'])->name('therapists.status');
        Route::resource('therapists', TherapistController::class)->except(['destroy']);

        Route::get('students/export', [StudentController::class, 'export'])->name('students.export');
        Route::get('students/import', [StudentController::class, 'showImportForm'])->name('students.import');
        Route::post('students/import', [StudentController::class, 'import'])->name('students.import.store');
        Route::get('students/imports', [StudentController::class, 'importHistory'])->name('students.imports.index');
        Route::get('students/imports/{import}', [StudentController::class, 'showImportStatus'])->name('students.imports.show');
        Route::get('students/imports/{import}/status', [StudentController::class, 'showImportStatus'])->name('students.imports.status');
        Route::get('students/import/template', [StudentController::class, 'downloadTemplate'])->name('students.import.template');
        Route::patch('students/{student}/status', [StudentController::class, 'updateStatus'])->name('students.status');
        Route::post('students/{student}/comments', [StudentCommentController::class, 'store'])->name('students.comments.store');
        Route::resource('students', StudentController::class)->except(['destroy']);

        // Student Documents
        Route::prefix('student-documents')->name('student-documents.')->group(function () {
            Route::get('/', [StudentDocumentController::class, 'index'])->name('index');
            Route::post('students/{student}', [StudentDocumentController::class, 'store'])->name('store');
            Route::get('{document}/download', [StudentDocumentController::class, 'download'])->name('download');
            Route::delete('{document}', [StudentDocumentController::class, 'destroy'])->name('destroy');
        });

        Route::get('services/export', [ServiceController::class, 'export'])->name('services.export');
        Route::patch('services/{service}/status', [ServiceController::class, 'updateStatus'])->name('services.status');
        Route::resource('services', ServiceController::class)->except(['destroy', 'show']);

        Route::get('positions/export', [PositionController::class, 'export'])->name('positions.export');
        Route::patch('positions/{position}/status', [PositionController::class, 'updateStatus'])->name('positions.status');
        Route::resource('positions', PositionController::class)->except(['destroy', 'show']);

        Route::get('ssas/export', [SSAController::class, 'export'])->name('ssas.export');
        Route::get('ssas/import', [SSAController::class, 'showImportForm'])->name('ssas.import');
        Route::post('ssas/import', [SSAController::class, 'import'])->name('ssas.import.store');
        Route::get('ssas/imports', [SSAController::class, 'importHistory'])->name('ssas.imports.index');
        Route::get('ssas/imports/{import}', [SSAController::class, 'showImportStatus'])->name('ssas.imports.show');
        Route::get('ssas/imports/{import}/status', [SSAController::class, 'showImportStatus'])->name('ssas.imports.status');
        Route::get('ssas/import/template', [SSAController::class, 'downloadTemplate'])->name('ssas.import.template');
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
            Route::post('{sessionLog}/approve', [SessionLogController::class, 'approve'])->name('approve');
            Route::post('{sessionLog}/send-back', [SessionLogController::class, 'sendBack'])->name('send-back');
            Route::post('{sessionLog}/cancel', [SessionLogController::class, 'cancel'])->name('cancel');
        });

        // Settings
        Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');

        // Finance Dashboard
        Route::get('finance/dashboard', [FinanceDashboardController::class, 'index'])->name('finance.dashboard');

        // IRS Report
        Route::get('finance/irs-report', [IrsReportController::class, 'index'])->name('finance.irs-report.index');
        Route::get('finance/irs-report/export', [IrsReportController::class, 'export'])->name('finance.irs-report.export');

        // Ledger Accounts
        Route::get('ledger/accounts/export', [LedgerAccountController::class, 'export'])->name('ledger.accounts.export');
        Route::get('ledger/accounts', [LedgerAccountController::class, 'index'])->name('ledger.accounts.index');
        Route::get('ledger/accounts/{type}/{id}', [LedgerAccountController::class, 'show'])->name('ledger.accounts.show');

        // Invoices
        Route::get('invoices/{invoice}/download', [InvoiceController::class, 'download'])->name('invoices.download');
        Route::post('invoices/{invoice}/send', [InvoiceController::class, 'send'])->name('invoices.send');
        Route::get('invoices/{invoice}/attach-sessions', [InvoiceController::class, 'attachSessions'])->name('invoices.attach-sessions');
        Route::post('invoices/{invoice}/attach-sessions', [InvoiceController::class, 'storeAttachedSessions'])->name('invoices.attach-sessions.store');
        Route::resource('invoices', InvoiceController::class);

        // Invoice Payments
        Route::get('payments/invoices', [InvoicePaymentsListController::class, 'index'])->name('payments.invoices.index');
        Route::get('payments/invoices/create', [InvoicePaymentsListController::class, 'create'])->name('payments.invoices.create');
        Route::post('payments/invoices', [InvoicePaymentsListController::class, 'store'])->name('payments.invoices.store');
        Route::delete('payments/invoices/{payment}', [InvoicePaymentsListController::class, 'destroy'])->name('payments.invoices.destroy');
        Route::post('invoices/{invoice}/payments', [InvoicePaymentController::class, 'store'])->name('invoices.payments.store');
        Route::delete('invoices/{invoice}/payments/{payment}', [InvoicePaymentController::class, 'destroy'])->name('invoices.payments.destroy');

        // Therapist Billing
        Route::prefix('billing/therapist-bills')->name('billing.therapist-bills.')->group(function () {
            Route::get('/', [TherapistBillController::class, 'index'])->name('index');
            Route::get('create', [TherapistBillController::class, 'create'])->name('create');
            Route::post('/', [TherapistBillController::class, 'store'])->name('store');
            Route::get('{bill}', [TherapistBillController::class, 'show'])->name('show');
            Route::get('{bill}/download', [TherapistBillController::class, 'download'])->name('download');
            Route::post('{bill}/send', [TherapistBillController::class, 'send'])->name('send');
        });

        // Therapist Bill Payments
        Route::get('payments/therapist-bills', [TherapistBillPaymentsListController::class, 'index'])->name('payments.therapist-bills.index');
        Route::get('payments/therapist-bills/create', [TherapistBillPaymentsListController::class, 'create'])->name('payments.therapist-bills.create');
        Route::post('payments/therapist-bills', [TherapistBillPaymentsListController::class, 'store'])->name('payments.therapist-bills.store');
        Route::delete('payments/therapist-bills/{payment}', [TherapistBillPaymentsListController::class, 'destroy'])->name('payments.therapist-bills.destroy');
        Route::post('billing/therapist-bills/{therapist_bill}/payments', [TherapistBillPaymentController::class, 'store'])->name('billing.therapist-bills.payments.store');
        Route::delete('billing/therapist-bills/{therapist_bill}/payments/{payment}', [TherapistBillPaymentController::class, 'destroy'])->name('billing.therapist-bills.payments.destroy');

        // Expenses
        Route::resource('expenses', ExpenseController::class);

        // Expense Categories
        Route::patch('settings/expense-categories/{expenseCategory}/toggle-status', [ExpenseCategoryController::class, 'toggleStatus'])->name('settings.expense-categories.toggle-status');
        Route::resource('settings/expense-categories', ExpenseCategoryController::class)
            ->except(['show', 'destroy'])
            ->names([
                'index' => 'settings.expense-categories.index',
                'create' => 'settings.expense-categories.create',
                'store' => 'settings.expense-categories.store',
                'edit' => 'settings.expense-categories.edit',
                'update' => 'settings.expense-categories.update',
            ]);

        // SSA Reports
        Route::prefix('reports/ssa')->name('reports.ssa.')->group(function () {
            Route::get('utilization', [SSAUtilizationReportController::class, 'index'])
                ->name('utilization.index');
            Route::get('utilization/export', [SSAUtilizationReportController::class, 'export'])
                ->name('utilization.export');

            Route::get('caseload', [SSACaseloadReportController::class, 'index'])
                ->name('caseload.index');
            Route::get('caseload/export', [SSACaseloadReportController::class, 'export'])
                ->name('caseload.export');

            Route::get('expirations', [SSAExpirationReportController::class, 'index'])
                ->name('expirations.index');
            Route::get('expirations/export', [SSAExpirationReportController::class, 'export'])
                ->name('expirations.export');
        });
    });
