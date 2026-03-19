<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payment;

use App\Domain\Payment\Services\OnlinePaymentService;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(
        private readonly OnlinePaymentService $paymentService,
    ) {}

    public function show(string $token): View
    {
        $invoice = $this->findInvoiceByToken($token);

        if ($invoice->isPaid() || $invoice->isFullyPaid()) {
            return view('payment.already-paid', compact('invoice'));
        }

        if ($invoice->isDraft()) {
            abort(404);
        }

        return view('payment.checkout', compact('invoice'));
    }

    public function checkout(string $token): RedirectResponse
    {
        $invoice = $this->findInvoiceByToken($token);

        if ($invoice->isPaid() || $invoice->isFullyPaid()) {
            return redirect()->route('payment.show', $token);
        }

        if ($invoice->isDraft()) {
            abort(404);
        }

        $customerEmail = $invoice->school_invoice_email
            ?? $invoice->school_contact_email
            ?? '';

        try {
            $session = $this->paymentService->createCheckoutSession($invoice, $customerEmail);

            return redirect()->away($session->paymentUrl);
        } catch (\RuntimeException $e) {
            return redirect()->route('payment.show', $token)
                ->with('error', 'Unable to initialize payment. Please try again later.');
        }
    }

    public function success(Request $request): View
    {
        $sessionId = $request->query('session_id');

        if (! $sessionId || ! is_string($sessionId)) {
            abort(404);
        }

        try {
            $payment = $this->paymentService->processPaymentCompletion($sessionId);
            $invoice = $payment->invoice;

            return view('payment.success', compact('invoice', 'payment'));
        } catch (\Exception $e) {
            abort(404);
        }
    }

    public function cancel(string $token): View
    {
        $invoice = $this->findInvoiceByToken($token);

        return view('payment.cancel', compact('invoice'));
    }

    private function findInvoiceByToken(string $token): Invoice
    {
        /** @var Invoice $invoice */
        $invoice = Invoice::where('payment_token', $token)->firstOrFail();

        return $invoice;
    }
}
