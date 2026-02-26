<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DataTables\Transformers\InvoiceRowTransformer;
use App\Domain\Invoice\Repositories\InvoiceRepositoryInterface;
use App\Domain\Invoice\Services\InvoicePdfService;
use App\Domain\Invoice\Services\InvoiceService;
use App\Domain\School\Repositories\SchoolRepositoryInterface;
use App\Domain\Service\Services\ServiceCatalogService;
use App\Domain\Student\Services\StudentService;
use App\Domain\Therapist\Services\TherapistService;
use App\DTOs\AttachSessionsDTO;
use App\DTOs\CreateInvoiceDTO;
use App\DTOs\InvoiceFilterDTO;
use App\DTOs\SendInvoiceDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Invoice\AttachSessionsRequest;
use App\Http\Requests\Admin\Invoice\CreateInvoiceRequest;
use App\Http\Requests\Admin\Invoice\InvoiceDataRequest;
use App\Http\Requests\Admin\Invoice\InvoiceIndexRequest;
use App\Http\Requests\Admin\Invoice\SendInvoiceRequest;
use App\Http\Support\DataTablesRequest;
use App\Http\Support\DataTablesResponse;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

final class InvoiceController extends Controller
{
    use DataTablesResponse;

    /**
     * @var array<int, string>
     */
    private const INVOICES_ORDER_WHITELIST = [
        0 => 'invoice_number',
        1 => 'school_display_name',
        2 => 'billing_period_start',
        3 => 'total',
        4 => 'status',
        5 => 'due_date',
    ];

    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly InvoicePdfService $pdfService,
        private readonly InvoiceRepositoryInterface $invoiceRepository,
        private readonly SchoolRepositoryInterface $schoolRepository,
        private readonly TherapistService $therapistService,
        private readonly StudentService $studentService,
        private readonly ServiceCatalogService $serviceCatalogService,
    ) {}

    public function index(InvoiceIndexRequest $request): View
    {
        $this->authorize('viewAny', Invoice::class);

        $filters = InvoiceFilterDTO::fromArray($request->validated());

        return view('admin.invoices.index', [
            'invoices' => collect(), // Server-side DataTables loads via AJAX
            'filters' => $request->validated(),
            'schools' => $this->schoolRepository->listActiveForSelect(),
            'datatableUrl' => route('admin.invoices.data'),
        ]);
    }

    public function data(InvoiceDataRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Invoice::class);

        $params = DataTablesRequest::fromRequest($request, self::INVOICES_ORDER_WHITELIST);

        $filterData = [
            'school_id' => $request->input('filter_school_id'),
            'status' => $request->input('filter_status'),
            'date_from' => $request->input('filter_date_from'),
            'date_to' => $request->input('filter_date_to'),
            'invoice_number' => $request->input('filter_invoice_number'),
            'per_page' => $params->length,
        ];

        $filters = InvoiceFilterDTO::fromArray($filterData);

        $result = $this->invoiceService->listForDataTables($filters, $params);

        return $this->dataTablesResponse(
            $params,
            $result['recordsTotal'],
            $result['recordsFiltered'],
            $result['rows'],
            static fn (Invoice $invoice): array => InvoiceRowTransformer::transform($invoice),
        );
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Invoice::class);

        $dateFrom = now()->subDays(30)->format('Y-m-d');
        $dateTo = now()->format('Y-m-d');

        return view('admin.invoices.create', [
            'schools' => $this->schoolRepository->listActiveForSelect(),
            'invoiceNumber' => $this->invoiceRepository->generateInvoiceNumber(),
            'defaultDateFrom' => $dateFrom,
            'defaultDateTo' => $dateTo,
        ]);
    }

    public function store(CreateInvoiceRequest $request): RedirectResponse
    {
        $this->authorize('create', Invoice::class);

        $dto = CreateInvoiceDTO::fromArray($request->validated());

        try {
            /** @var \App\Models\User $user */
            $user = $request->user();
            $invoice = $this->invoiceService->generateInvoice($user, $dto);

            if ($invoice->sessionLogs->isEmpty()) {
                return redirect()
                    ->route('admin.invoices.attach-sessions', $invoice)
                    ->with('success', 'Draft invoice created. Add session logs below.');
            }

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
            /** @var \App\Models\User $user */
            $user = $request->user();
            $this->invoiceService->sendInvoice($user, $invoice, $dto);

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

    public function attachSessions(Request $request, Invoice $invoice): View
    {
        $this->authorize('update', $invoice);

        if (! $invoice->isDraft()) {
            abort(404);
        }

        $filters = [
            'school_id' => $invoice->school_id,
            'date_from' => $request->input('date_from', $invoice->billing_period_start?->format('Y-m-d')),
            'date_to' => $request->input('date_to', $invoice->billing_period_end?->format('Y-m-d')),
            'therapist_id' => $request->input('therapist_id'),
            'student_id' => $request->input('student_id'),
            'service_id' => $request->input('service_id'),
        ];

        $attachedSessionLogs = $invoice->sessionLogs()
            ->with(['student', 'service', 'therapist', 'school'])
            ->orderBy('session_date', 'desc')
            ->get();

        $availableSessionLogs = $this->invoiceRepository->getAvailableSessionLogsForInvoiceCreation($filters);
        $attachedIds = $attachedSessionLogs->pluck('id')->all();

        $schoolId = (int) $invoice->school_id;
        $therapists = $this->therapistService->listActiveTherapistsBySchool($schoolId);
        $students = $this->studentService->listActiveStudentsBySchool($schoolId);
        $serviceIds = $this->invoiceRepository->getAvailableServiceIdsForSchool($schoolId);
        $services = $serviceIds->isNotEmpty()
            ? $this->serviceCatalogService->listActiveForSelect()->whereIn('id', $serviceIds->toArray())
            : collect();

        $sessionLogs = $attachedSessionLogs->concat($availableSessionLogs)->unique('id');

        return view('admin.invoices.attach-sessions', [
            'invoice' => $invoice,
            'sessionLogs' => $sessionLogs,
            'attachedIds' => $attachedIds,
            'therapists' => $therapists,
            'students' => $students,
            'services' => $services,
            'filters' => $filters,
        ]);
    }

    public function storeAttachedSessions(AttachSessionsRequest $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);

        if (! $invoice->isDraft()) {
            abort(404);
        }

        $dto = AttachSessionsDTO::fromArray($request->validated());

        try {
            $this->invoiceService->attachSessionsToDraft($invoice, $dto);

            return redirect()
                ->route('admin.invoices.show', $invoice)
                ->with('success', 'Invoice sessions updated successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->back()
                ->withErrors(['error' => $e->getMessage()])
                ->withInput();
        }
    }
}
