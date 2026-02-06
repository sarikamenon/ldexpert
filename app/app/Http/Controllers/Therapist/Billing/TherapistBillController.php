<?php

declare(strict_types=1);

namespace App\Http\Controllers\Therapist\Billing;

use App\Domain\Billing\Repositories\TherapistBillRepositoryInterface;
use App\Domain\Billing\Services\TherapistBillPdfService;
use App\DTOs\TherapistBillFilterDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Therapist\Billing\TherapistBillIndexRequest;
use App\Models\TherapistBill;
use Illuminate\Http\Response;
use Illuminate\View\View;

final class TherapistBillController extends Controller
{
    public function __construct(
        private readonly TherapistBillRepositoryInterface $billRepository,
        private readonly TherapistBillPdfService $pdfService,
    ) {}

    public function index(TherapistBillIndexRequest $request): View
    {
        $this->authorize('viewAny', TherapistBill::class);

        $filters = TherapistBillFilterDTO::fromArray($request->validated());
        $perPage = $request->integer('per_page', 15);

        // Filter to current therapist's bills only
        $bills = $this->billRepository->getBillsByTherapist(
            $request->user()->id,
            $filters,
            $perPage
        );

        return view('therapist.billing.index', [
            'bills' => $bills,
            'filters' => $request->validated(),
        ]);
    }

    public function show(TherapistBill $bill): View
    {
        $this->authorize('view', $bill);

        $bill->load([
            'sessionLogs.student',
            'sessionLogs.service',
            'sessionLogs.therapist',
            'therapist',
            'sentBy',
        ]);

        return view('therapist.billing.show', [
            'bill' => $bill,
        ]);
    }

    public function download(TherapistBill $bill): Response
    {
        $this->authorize('download', $bill);

        $pdf = $this->pdfService->generatePdf($bill);

        return $pdf->download("bill-{$bill->bill_number}.pdf");
    }
}
