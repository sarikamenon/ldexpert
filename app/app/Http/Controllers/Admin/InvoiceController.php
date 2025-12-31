<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Invoice\Repositories\InvoiceRepositoryInterface;
use App\Domain\Invoice\Services\InvoicePdfService;
use App\Domain\Invoice\Services\InvoiceService;
use App\Domain\Therapist\Repositories\SessionLogRepositoryInterface;
use App\DTOs\CreateInvoiceDTO;
use App\DTOs\InvoiceFilterDTO;
use App\DTOs\SendInvoiceDTO;
use App\Enums\SessionLogStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Invoice\CreateInvoiceRequest;
use App\Http\Requests\Admin\Invoice\InvoiceIndexRequest;
use App\Http\Requests\Admin\Invoice\SendInvoiceRequest;
use App\Models\Invoice;
use App\Models\School;
use App\Models\SessionLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

final class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly InvoicePdfService $pdfService,
        private readonly InvoiceRepositoryInterface $invoiceRepository,
        private readonly SessionLogRepositoryInterface $sessionLogRepository,
    ) {}

    public function index(InvoiceIndexRequest $request): View
    {
        $this->authorize('viewAny', Invoice::class);

        $filters = InvoiceFilterDTO::fromArray($request->validated());
        $perPage = $request->integer('per_page', 15);

        $invoices = $this->invoiceRepository->list($filters, $perPage);

        return view('admin.invoices.index', [
            'invoices' => $invoices,
            'filters' => $request->validated(),
            'schools' => School::query()
                ->active()
                ->orderBy('display_name')
                ->get(['id', 'display_name']),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Invoice::class);

        // Get finalized session logs that are billable and not already invoiced
        $sessionLogs = SessionLog::query()
            ->where('status', SessionLogStatus::FINALIZED->value)
            ->where('is_billable_school', true)
            ->whereNull('invoice_id')
            ->with(['student', 'service', 'therapist', 'school'])
            ->orderBy('session_date', 'desc')
            ->get();

        return view('admin.invoices.create', [
            'sessionLogs' => $sessionLogs,
            'schools' => School::query()
                ->active()
                ->orderBy('display_name')
                ->get(['id', 'display_name']),
        ]);
    }

    public function store(CreateInvoiceRequest $request): RedirectResponse
    {
        $this->authorize('create', Invoice::class);

        $dto = CreateInvoiceDTO::fromArray($request->validated());

        try {
            $invoice = $this->invoiceService->generateInvoice($request->user(), $dto);

            return redirect()
                ->route('admin.invoices.show', $invoice)
                ->with('success', 'Invoice created successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->back()
                ->withErrors(['error' => $e->getMessage()])
                ->withInput();
        }
    }

    public function show(Invoice $invoice): View
    {
        $this->authorize('view', $invoice);

        $invoice->load([
            'sessionLogs.student',
            'sessionLogs.service',
            'sessionLogs.therapist',
            'school',
            'sentBy',
        ]);

        return view('admin.invoices.show', [
            'invoice' => $invoice,
        ]);
    }

    public function send(SendInvoiceRequest $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('send', $invoice);

        $dto = SendInvoiceDTO::fromArray($request->validated());

        try {
            $this->invoiceService->sendInvoice($request->user(), $invoice, $dto);

            return redirect()
                ->route('admin.invoices.show', $invoice)
                ->with('success', 'Invoice sent successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->back()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function download(Invoice $invoice): Response
    {
        $this->authorize('view', $invoice);

        $pdf = $this->pdfService->generatePdf($invoice);

        return $pdf->download("invoice-{$invoice->invoice_number}.pdf");
    }
}
