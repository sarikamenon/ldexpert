<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Invoice\Repositories\InvoiceRepositoryInterface;
use App\Domain\Invoice\Services\InvoicePdfService;
use App\Domain\Invoice\Services\InvoiceService;
use App\Domain\School\Repositories\SchoolRepositoryInterface;
use App\Domain\Service\Services\ServiceCatalogService;
use App\Domain\Student\Services\StudentService;
use App\Domain\Therapist\Services\TherapistService;
use App\DTOs\CreateInvoiceDTO;
use App\DTOs\InvoiceFilterDTO;
use App\DTOs\SendInvoiceDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Invoice\CreateInvoiceRequest;
use App\Http\Requests\Admin\Invoice\InvoiceIndexRequest;
use App\Http\Requests\Admin\Invoice\SendInvoiceRequest;
use App\Models\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

final class InvoiceController extends Controller
{
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
        $perPage = $request->integer('per_page', 15);

        $invoices = $this->invoiceRepository->list($filters, $perPage);

        return view('admin.invoices.index', [
            'invoices' => $invoices,
            'filters' => $request->validated(),
            'schools' => $this->schoolRepository->listActiveForSelect(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Invoice::class);

        // Default to last 30 days
        $selectedSchoolId = $request->input('school_id');
        $filters = [
            'date_from' => $request->input('date_from', now()->subDays(30)->format('Y-m-d')),
            'date_to' => $request->input('date_to', now()->format('Y-m-d')),
            'school_id' => $selectedSchoolId,
            'therapist_id' => $request->input('therapist_id'),
            'student_id' => $request->input('student_id'),
            'service_id' => $request->input('service_id'),
            'search' => $request->input('search'),
        ];

        $sessionLogs = $this->invoiceRepository->getAvailableSessionLogsForInvoiceCreation($filters);

        // Get schools that have available session logs (only filter by date, not by selected school)
        $schoolFilterForDropdown = [
            'date_from' => $filters['date_from'],
            'date_to' => $filters['date_to'],
        ];
        $availableSchoolIds = $this->invoiceRepository->getAvailableSchoolIdsForInvoiceCreation($schoolFilterForDropdown);

        // Get filter options based on selected school
        $therapists = collect();
        $students = collect();
        $services = collect();

        if ($selectedSchoolId) {
            $therapists = $this->therapistService->listActiveTherapistsBySchool((int) $selectedSchoolId);
            $students = $this->studentService->listActiveStudentsBySchool((int) $selectedSchoolId);

            // Get unique services from session logs for this school
            $serviceIds = $this->invoiceRepository->getAvailableServiceIdsForSchool((int) $selectedSchoolId);

            if ($serviceIds->isNotEmpty()) {
                $services = $this->serviceCatalogService->listActiveForSelect()
                    ->whereIn('id', $serviceIds->toArray());
            }
        }

        // Generate invoice number for display
        $invoiceNumber = $this->invoiceRepository->generateInvoiceNumber();

        // Get schools that have available session logs (filtered by date range only)
        $schools = $this->schoolRepository->listAllForSelect()
            ->whereIn('id', $availableSchoolIds->toArray());

        return view('admin.invoices.create', [
            'sessionLogs' => $sessionLogs,
            'schools' => $schools,
            'therapists' => $therapists,
            'students' => $students,
            'services' => $services,
            'filters' => $filters,
            'invoiceNumber' => $invoiceNumber,
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
