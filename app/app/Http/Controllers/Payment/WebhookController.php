<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payment;

use App\Domain\Payment\Services\OnlinePaymentService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function __construct(
        private readonly OnlinePaymentService $paymentService,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $signature = (string) $request->header('Stripe-Signature', '');

        /** @var array<string, mixed> $payload */
        $payload = $request->all();

        try {
            $this->paymentService->processWebhook($payload, $signature);

            return response()->json(['status' => 'ok']);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
