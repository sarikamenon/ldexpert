<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\Enums\BillingReminderType;
use App\Enums\InvoiceStatus;
use App\Mail\InvoiceOverdueMail;
use App\Mail\InvoiceReminderMail;
use App\Models\BillingReminder;
use App\Models\BillingSetting;
use App\Models\Invoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

final class BillingReminderService
{
    /**
     * Process all pending billing reminders.
     *
     * @return array{sent: int, skipped: int}
     */
    public function processReminders(bool $dryRun = false): array
    {
        $settings = BillingSetting::getSettings();
        $sent = 0;
        $skipped = 0;

        $sent += $this->processUpcomingDueReminders($settings, $dryRun, $skipped);
        $sent += $this->processOverdueReminders($settings, $dryRun, $skipped);

        return ['sent' => $sent, 'skipped' => $skipped];
    }

    private function processUpcomingDueReminders(BillingSetting $settings, bool $dryRun, int &$skipped): int
    {
        $sent = 0;
        $reminderDate = Carbon::now()->addDays((int) $settings->reminder_days_before_due);

        $invoices = Invoice::query()
            ->where('status', InvoiceStatus::SENT->value)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', $reminderDate->toDateString())
            ->whereDoesntHave('billingReminders', function ($q): void {
                $q->where('reminder_type', BillingReminderType::UPCOMING_DUE->value); // @phpstan-ignore argument.type
            })
            ->get();

        foreach ($invoices as $invoice) {
            if ($dryRun) {
                $sent++;

                continue;
            }

            $this->sendReminderEmail($invoice);

            BillingReminder::create([
                'remindable_type' => Invoice::class,
                'remindable_id' => $invoice->id,
                'reminder_type' => BillingReminderType::UPCOMING_DUE->value,
                'sent_at' => now(),
            ]);

            $sent++;
        }

        return $sent;
    }

    private function processOverdueReminders(BillingSetting $settings, bool $dryRun, int &$skipped): int
    {
        $sent = 0;
        $overdueThreshold = Carbon::now()->subDays((int) $settings->reminder_days_after_due);

        $invoices = Invoice::query()
            ->where('status', InvoiceStatus::SENT->value)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $overdueThreshold->toDateString())
            ->get();

        foreach ($invoices as $invoice) {
            $recentOverdue = BillingReminder::query()
                ->where('remindable_type', Invoice::class)
                ->where('remindable_id', $invoice->id)
                ->whereIn('reminder_type', [
                    BillingReminderType::OVERDUE->value,
                    BillingReminderType::OVERDUE_FOLLOWUP->value,
                ])
                ->orderByDesc('sent_at')
                ->first();

            if ($recentOverdue !== null) {
                $daysSinceLastReminder = Carbon::parse($recentOverdue->sent_at)->diffInDays(now());
                if ($daysSinceLastReminder < (int) $settings->reminder_overdue_repeat_days) {
                    $skipped++;

                    continue;
                }

                $totalOverdueReminders = BillingReminder::query()
                    ->where('remindable_type', Invoice::class)
                    ->where('remindable_id', $invoice->id)
                    ->whereIn('reminder_type', [
                        BillingReminderType::OVERDUE->value,
                        BillingReminderType::OVERDUE_FOLLOWUP->value,
                    ])
                    ->count();

                if ($totalOverdueReminders >= (int) $settings->max_overdue_reminders) {
                    $skipped++;

                    continue;
                }
            }

            if ($dryRun) {
                $sent++;

                continue;
            }

            $reminderType = $recentOverdue === null
                ? BillingReminderType::OVERDUE
                : BillingReminderType::OVERDUE_FOLLOWUP;

            $daysOverdue = $invoice->due_date !== null
                ? (int) $invoice->due_date->diffInDays(now())
                : 0;

            $this->sendOverdueEmail($invoice, $daysOverdue);

            BillingReminder::create([
                'remindable_type' => Invoice::class,
                'remindable_id' => $invoice->id,
                'reminder_type' => $reminderType->value,
                'sent_at' => now(),
            ]);

            $sent++;
        }

        return $sent;
    }

    private function sendReminderEmail(Invoice $invoice): void
    {
        $recipientEmail = $this->resolveRecipientEmail($invoice);

        if ($recipientEmail === null) {
            Log::warning('No recipient email for invoice reminder', ['invoice_id' => $invoice->id]);

            return;
        }

        $paymentUrl = $invoice->getPaymentUrl();

        Mail::to($recipientEmail)->send(new InvoiceReminderMail($invoice, $paymentUrl));
    }

    private function sendOverdueEmail(Invoice $invoice, int $daysOverdue): void
    {
        $recipientEmail = $this->resolveRecipientEmail($invoice);

        if ($recipientEmail === null) {
            Log::warning('No recipient email for overdue notice', ['invoice_id' => $invoice->id]);

            return;
        }

        $paymentUrl = $invoice->getPaymentUrl();

        Mail::to($recipientEmail)->send(new InvoiceOverdueMail($invoice, $daysOverdue, $paymentUrl));
    }

    private function resolveRecipientEmail(Invoice $invoice): ?string
    {
        return $invoice->parent_email
            ?? $invoice->school_invoice_email
            ?? $invoice->school_contact_email;
    }
}
