<?php

use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\Billing\TherapistBillController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ExpenseCategoryController;
use App\Http\Controllers\Admin\ExpenseController;
use App\Http\Controllers\Admin\Finance\PayStubReportController;
use App\Http\Controllers\Admin\FinanceDashboardController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\InvoicePaymentController;
use App\Http\Controllers\Admin\InvoicePaymentsListController;
use App\Http\Controllers\Admin\LeadController;
use App\Http\Controllers\Admin\LeadNoteController;
use App\Http\Controllers\Admin\LedgerAccountController;
use App\Http\Controllers\Admin\LedgerAdjustmentController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\PositionController;
use App\Http\Controllers\Admin\QGlobRequestController;
use App\Http\Controllers\Admin\Reports\SSACaseloadReportController;
use App\Http\Controllers\Admin\Reports\SSAExpirationReportController;
use App\Http\Controllers\Admin\Reports\SSAUtilizationReportController;
use App\Http\Controllers\Admin\ScheduleCalendarController;
use App\Http\Controllers\Admin\SchoolCalendarEventController;
use App\Http\Controllers\Admin\SchoolContractController;
use App\Http\Controllers\Admin\SchoolController;
use App\Http\Controllers\Admin\ServiceAliasController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\SessionLogController;
use App\Http\Controllers\Admin\SessionLogImportController;
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
        Route::post('schools/data', [SchoolController::class, 'data'])->name('schools.data');
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
        Route::post('therapists/data', [TherapistController::class, 'data'])->name('therapists.data');
        Route::patch('therapists/{therapist}/status', [TherapistController::class, 'updateStatus'])->name('therapists.status');
        Route::resource('therapists', TherapistController::class)->except(['destroy']);

        Route::get('students/export', [StudentController::class, 'export'])->name('students.export');
        Route::get('students/import', [StudentController::class, 'showImportForm'])->name('students.import');
        Route::post('students/import', [StudentController::class, 'import'])->name('students.import.store');
        Route::get('students/imports', [StudentController::class, 'importHistory'])->name('students.imports.index');
        Route::post('students/imports/data', [StudentController::class, 'importHistoryData'])->name('students.imports.data');
        Route::get('students/imports/{import}', [StudentController::class, 'showImportStatus'])->name('students.imports.show');
        Route::get('students/imports/{import}/status', [StudentController::class, 'showImportStatus'])->name('students.imports.status');
        Route::get('students/imports/{import}/download', [StudentController::class, 'downloadImported'])->name('students.imports.download');
        Route::get('students/import/template', [StudentController::class, 'downloadTemplate'])->name('students.import.template');
        Route::patch('students/{student}/status', [StudentController::class, 'updateStatus'])->name('students.status');
        Route::post('students/{student}/comments', [StudentCommentController::class, 'store'])->name('students.comments.store');
        Route::post('students/data', [StudentController::class, 'data'])->name('students.data');
        Route::post('students/{student}/schedules/data', [StudentController::class, 'scheduleData'])->name('students.schedules.data');
        Route::resource('students', StudentController::class)->except(['destroy']);

        // Leads
        Route::post('leads/data', [LeadController::class, 'data'])->name('leads.data');
        Route::patch('leads/{lead}/status', [LeadController::class, 'updateStatus'])->name('leads.status');
        Route::get('leads/{lead}/convert', [LeadController::class, 'showConvertForm'])->name('leads.convert');
        Route::post('leads/{lead}/convert', [LeadController::class, 'convert'])->name('leads.convert.store');
        Route::post('leads/{lead}/notes', [LeadNoteController::class, 'store'])->name('leads.notes.store');
        Route::delete('leads/{lead}', [LeadController::class, 'destroy'])->name('leads.destroy');
        Route::resource('leads', LeadController::class)->except(['destroy']);

        // Student Documents
        Route::prefix('student-documents')->name('student-documents.')->group(function () {
            Route::get('/', [StudentDocumentController::class, 'index'])->name('index');
            Route::post('students/{student}', [StudentDocumentController::class, 'store'])->name('store');
            Route::get('{document}/download', [StudentDocumentController::class, 'download'])->name('download');
            Route::delete('{document}', [StudentDocumentController::class, 'destroy'])->name('destroy');
        });

        Route::get('services/export', [ServiceController::class, 'export'])->name('services.export');
        Route::post('services/data', [ServiceController::class, 'data'])->name('services.data');
        Route::patch('services/{service}/status', [ServiceController::class, 'updateStatus'])->name('services.status');
        Route::resource('services', ServiceController::class)->except(['destroy', 'show']);

        Route::get('positions/export', [PositionController::class, 'export'])->name('positions.export');
        Route::post('positions/data', [PositionController::class, 'data'])->name('positions.data');
        Route::patch('positions/{position}/status', [PositionController::class, 'updateStatus'])->name('positions.status');
        Route::resource('positions', PositionController::class)->except(['destroy', 'show']);

        Route::post('service-aliases/data', [ServiceAliasController::class, 'data'])->name('service-aliases.data');
        Route::resource('service-aliases', ServiceAliasController::class)->except(['show']);

        Route::get('ssas/therapists-for-service', [SSAController::class, 'therapistsForService'])->name('ssas.therapists-for-service');
        Route::get('ssas/export', [SSAController::class, 'export'])->name('ssas.export');
        Route::get('ssas/import', [SSAController::class, 'showImportForm'])->name('ssas.import');
        Route::post('ssas/import', [SSAController::class, 'import'])->name('ssas.import.store');
        Route::get('ssas/imports', [SSAController::class, 'importHistory'])->name('ssas.imports.index');
        Route::post('ssas/imports/data', [SSAController::class, 'importHistoryData'])->name('ssas.imports.data');
        Route::get('ssas/imports/{import}', [SSAController::class, 'showImportStatus'])->name('ssas.imports.show');
        Route::get('ssas/imports/{import}/status', [SSAController::class, 'showImportStatus'])->name('ssas.imports.status');
        Route::get('ssas/imports/{import}/download', [SSAController::class, 'downloadImported'])->name('ssas.imports.download');
        Route::get('ssas/import/template', [SSAController::class, 'downloadTemplate'])->name('ssas.import.template');
        Route::post('ssas/data', [SSAController::class, 'data'])->name('ssas.data');
        Route::patch('ssas/{ssa}/status', [SSAController::class, 'updateStatus'])->name('ssas.status');
        Route::post('ssas/{ssa}/assign-therapist', [SSAController::class, 'assignTherapist'])->name('ssas.assign-therapist');
        Route::post('ssas/{ssa}/unassign-therapist', [SSAController::class, 'unassignTherapist'])->name('ssas.unassign-therapist');
        Route::resource('ssas', SSAController::class)->except(['destroy']);

        Route::prefix('contracts')
            ->name('contracts.')
            ->group(function () {
                Route::post('schools/data', [SchoolContractController::class, 'data'])->name('schools.data');
                Route::patch('schools/{schoolContract}/status', [SchoolContractController::class, 'updateStatus'])
                    ->name('schools.status');
                Route::get('schools/{schoolContract}/download-document', [SchoolContractController::class, 'downloadDocument'])
                    ->name('schools.download-document');
                Route::resource('schools', SchoolContractController::class)
                    ->parameters(['schools' => 'schoolContract'])
                    ->except(['destroy']);

                Route::post('therapists/data', [TherapistContractController::class, 'data'])->name('therapists.data');
                Route::patch('therapists/{therapistContract}/status', [TherapistContractController::class, 'updateStatus'])
                    ->name('therapists.status');
                Route::get('therapists/{therapistContract}/download-document', [TherapistContractController::class, 'downloadDocument'])
                    ->name('therapists.download-document');
                Route::resource('therapists', TherapistContractController::class)
                    ->parameters(['therapists' => 'therapistContract'])
                    ->except(['destroy']);
            });

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

        // Schedule Calendar
        Route::prefix('schedule/calendar')->name('schedule-calendar.')->group(function () {
            Route::get('/', [ScheduleCalendarController::class, 'index'])->name('index');
            Route::get('events', [ScheduleCalendarController::class, 'events'])->name('events');
            Route::get('{id}', [ScheduleCalendarController::class, 'show'])->name('show');
        });

        Route::prefix('qglob-requests')->name('qglob-requests.')->group(function () {
            Route::get('/', [QGlobRequestController::class, 'index'])->name('index');
            Route::post('data', [QGlobRequestController::class, 'data'])->name('data');
            Route::get('{qglob_request}', [QGlobRequestController::class, 'show'])->name('show');
            Route::post('{qglob_request}/respond', [QGlobRequestController::class, 'respond'])->name('respond');
        });

        // Session Logs
        Route::prefix('session-logs')->name('session-logs.')->group(function () {
            Route::get('import', [SessionLogImportController::class, 'showImportForm'])->name('import');
            Route::post('import', [SessionLogImportController::class, 'import'])->name('import.store');
            Route::get('imports', [SessionLogImportController::class, 'importHistory'])->name('imports.index');
            Route::post('imports/data', [SessionLogImportController::class, 'importHistoryData'])->name('imports.data');
            Route::get('imports/{import}', [SessionLogImportController::class, 'showImportStatus'])->name('imports.show');
            Route::get('imports/{import}/status', [SessionLogImportController::class, 'showImportStatus'])->name('imports.status');
            Route::get('imports/{import}/download', [SessionLogImportController::class, 'downloadImported'])->name('imports.download');
            Route::get('import/template', [SessionLogImportController::class, 'downloadTemplate'])->name('import.template');

            Route::get('/', [SessionLogController::class, 'index'])->name('index');
            Route::post('data', [SessionLogController::class, 'data'])->name('data');
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

        // Pay Stub Report
        Route::post('finance/pay-stub-report/data', [PayStubReportController::class, 'data'])->name('finance.pay-stub-report.data');
        Route::get('finance/pay-stub-report/download', [PayStubReportController::class, 'download'])->name('finance.pay-stub-report.download');
        Route::get('finance/pay-stub-report', [PayStubReportController::class, 'index'])->name('finance.pay-stub-report.index');

        // Ledger Accounts
        Route::post('ledger/accounts/data', [LedgerAccountController::class, 'data'])->name('ledger.accounts.data');
        Route::post('ledger/accounts/transactions/data', [LedgerAccountController::class, 'transactionsData'])->name('ledger.accounts.transactions.data');
        Route::post('ledger/accounts/all-transactions/data', [LedgerAccountController::class, 'allTransactionsData'])->name('ledger.accounts.all-transactions.data');
        Route::get('ledger/accounts/export', [LedgerAccountController::class, 'export'])->name('ledger.accounts.export');
        Route::get('ledger/accounts/all-transactions/export', [LedgerAccountController::class, 'allTransactionsExport'])->name('ledger.accounts.all-transactions.export');
        Route::get('ledger/accounts', [LedgerAccountController::class, 'index'])->name('ledger.accounts.index');
        Route::get('ledger/accounts/{type}/{id}', [LedgerAccountController::class, 'show'])->name('ledger.accounts.show');
        Route::get('ledger/accounts/{type}/{id}/stats', [LedgerAccountController::class, 'statsData'])->name('ledger.accounts.stats');
        Route::post('ledger/accounts/{type}/{id}/adjustment', [LedgerAdjustmentController::class, 'store'])->name('ledger.accounts.adjustment.store');
        Route::get('ledger/adjustments/{entry}', [LedgerAdjustmentController::class, 'show'])->name('ledger.adjustment.show');
        Route::put('ledger/adjustments/{entry}', [LedgerAdjustmentController::class, 'update'])->name('ledger.adjustment.update');
        Route::delete('ledger/adjustments/{entry}', [LedgerAdjustmentController::class, 'destroy'])->name('ledger.adjustment.destroy');

        // Invoices
        Route::post('invoices/data', [InvoiceController::class, 'data'])->name('invoices.data');
        Route::get('invoices/{invoice}/download', [InvoiceController::class, 'download'])->name('invoices.download');
        Route::post('invoices/{invoice}/send', [InvoiceController::class, 'send'])->name('invoices.send');
        Route::post('invoices/{invoice}/resend-email', [InvoiceController::class, 'resendEmail'])->name('invoices.resend-email');
        Route::get('invoices/{invoice}/attach-sessions', [InvoiceController::class, 'attachSessions'])->name('invoices.attach-sessions');
        Route::post('invoices/{invoice}/attach-sessions', [InvoiceController::class, 'storeAttachedSessions'])->name('invoices.attach-sessions.store');
        Route::resource('invoices', InvoiceController::class);

        // Invoice Payments
        Route::post('payments/invoices/data', [InvoicePaymentsListController::class, 'data'])->name('payments.invoices.data');
        Route::get('payments/invoices', [InvoicePaymentsListController::class, 'index'])->name('payments.invoices.index');
        Route::get('payments/invoices/create', [InvoicePaymentsListController::class, 'create'])->name('payments.invoices.create');
        Route::post('payments/invoices', [InvoicePaymentsListController::class, 'store'])->name('payments.invoices.store');
        Route::delete('payments/invoices/{payment}', [InvoicePaymentsListController::class, 'destroy'])->name('payments.invoices.destroy');
        Route::post('invoices/{invoice}/payments', [InvoicePaymentController::class, 'store'])->name('invoices.payments.store');
        Route::delete('invoices/{invoice}/payments/{payment}', [InvoicePaymentController::class, 'destroy'])->name('invoices.payments.destroy');

        // Therapist Billing
        Route::prefix('billing/therapist-bills')->name('billing.therapist-bills.')->group(function () {
            Route::post('data', [TherapistBillController::class, 'data'])->name('data');
            Route::get('/', [TherapistBillController::class, 'index'])->name('index');
            Route::get('create', [TherapistBillController::class, 'create'])->name('create');
            Route::post('/', [TherapistBillController::class, 'store'])->name('store');
            Route::get('{bill}', [TherapistBillController::class, 'show'])->name('show');
            Route::get('{bill}/download', [TherapistBillController::class, 'download'])->name('download');
            Route::post('{bill}/send', [TherapistBillController::class, 'send'])->name('send');
            Route::get('{bill}/attach-sessions', [TherapistBillController::class, 'attachSessions'])->name('attach-sessions');
            Route::post('{bill}/attach-sessions', [TherapistBillController::class, 'storeAttachedSessions'])->name('attach-sessions.store');
            Route::delete('{bill}', [TherapistBillController::class, 'destroy'])->name('destroy');
        });

        // Therapist Bill Payments
        Route::post('payments/therapist-bills/data', [TherapistBillPaymentsListController::class, 'data'])->name('payments.therapist-bills.data');
        Route::get('payments/therapist-bills', [TherapistBillPaymentsListController::class, 'index'])->name('payments.therapist-bills.index');
        Route::get('payments/therapist-bills/create', [TherapistBillPaymentsListController::class, 'create'])->name('payments.therapist-bills.create');
        Route::post('payments/therapist-bills', [TherapistBillPaymentsListController::class, 'store'])->name('payments.therapist-bills.store');
        Route::delete('payments/therapist-bills/{payment}', [TherapistBillPaymentsListController::class, 'destroy'])->name('payments.therapist-bills.destroy');
        Route::post('billing/therapist-bills/{therapist_bill}/payments', [TherapistBillPaymentController::class, 'store'])->name('billing.therapist-bills.payments.store');
        Route::delete('billing/therapist-bills/{therapist_bill}/payments/{payment}', [TherapistBillPaymentController::class, 'destroy'])->name('billing.therapist-bills.payments.destroy');

        // Entity Billing Configuration
        Route::prefix('billing/entity-config')->name('billing.entity-config.')->group(function () {
            Route::get('{entity_type}/{entity_id}', [App\Http\Controllers\Admin\EntityBillingController::class, 'show'])->name('show');
            Route::post('/', [App\Http\Controllers\Admin\EntityBillingController::class, 'storeOrUpdate'])->name('store');
            Route::delete('{entity_type}/{entity_id}', [App\Http\Controllers\Admin\EntityBillingController::class, 'destroy'])->name('destroy');
        });

        // Billing Settings
        Route::prefix('billing/settings')->name('billing.settings.')->group(function () {
            Route::get('/', [App\Http\Controllers\Admin\BillingSettingsController::class, 'edit'])->name('edit');
            Route::put('/', [App\Http\Controllers\Admin\BillingSettingsController::class, 'update'])->name('update');
        });

        // Billing Schedules
        Route::prefix('billing/schedules')->name('billing.schedules.')->group(function () {
            Route::post('data', [App\Http\Controllers\Admin\BillingScheduleController::class, 'data'])->name('data');
            Route::get('/', [App\Http\Controllers\Admin\BillingScheduleController::class, 'index'])->name('index');
            Route::get('create', [App\Http\Controllers\Admin\BillingScheduleController::class, 'create'])->name('create');
            Route::post('/', [App\Http\Controllers\Admin\BillingScheduleController::class, 'store'])->name('store');
            Route::get('{schedule}/edit', [App\Http\Controllers\Admin\BillingScheduleController::class, 'edit'])->name('edit');
            Route::put('{schedule}', [App\Http\Controllers\Admin\BillingScheduleController::class, 'update'])->name('update');
            Route::patch('{schedule}/toggle', [App\Http\Controllers\Admin\BillingScheduleController::class, 'toggleActive'])->name('toggle');
            Route::post('{schedule}/run', [App\Http\Controllers\Admin\BillingScheduleController::class, 'runNow'])->name('run');
            Route::post('{schedule}/history/data', [App\Http\Controllers\Admin\BillingScheduleController::class, 'runHistoryData'])->name('history.data');
            Route::get('{schedule}/history', [App\Http\Controllers\Admin\BillingScheduleController::class, 'runHistory'])->name('history');
        });

        // Expenses
        Route::post('expenses/data', [ExpenseController::class, 'data'])->name('expenses.data');
        Route::resource('expenses', ExpenseController::class);

        // Expense Categories
        Route::post('settings/expense-categories/data', [ExpenseCategoryController::class, 'data'])->name('settings.expense-categories.data');
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
            Route::post('utilization/data', [SSAUtilizationReportController::class, 'data'])
                ->name('utilization.data');
            Route::get('utilization/export', [SSAUtilizationReportController::class, 'export'])
                ->name('utilization.export');

            Route::get('caseload', [SSACaseloadReportController::class, 'index'])
                ->name('caseload.index');
            Route::post('caseload/therapist-data', [SSACaseloadReportController::class, 'therapistData'])
                ->name('caseload.therapist-data');
            Route::post('caseload/unassigned-data', [SSACaseloadReportController::class, 'unassignedData'])
                ->name('caseload.unassigned-data');
            Route::get('caseload/export', [SSACaseloadReportController::class, 'export'])
                ->name('caseload.export');

            Route::get('expirations', [SSAExpirationReportController::class, 'index'])
                ->name('expirations.index');
            Route::post('expirations/data', [SSAExpirationReportController::class, 'data'])
                ->name('expirations.data');
            Route::get('expirations/export', [SSAExpirationReportController::class, 'export'])
                ->name('expirations.export');
        });
    });
