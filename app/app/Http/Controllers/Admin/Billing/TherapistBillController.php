<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Billing;

use App\DataTables\Transformers\TherapistBillRowTransformer;
use App\Domain\Billing\Repositories\TherapistBillRepositoryInterface;
use App\Domain\Billing\Services\TherapistBillPdfService;
use App\Domain\Billing\Services\TherapistBillService;
use App\Domain\Service\Services\ServiceCatalogService;
use App\Domain\Student\Services\StudentService;
use App\Domain\Therapist\Services\TherapistService;
use App\DTOs\CreateTherapistBillDTO;
use App\DTOs\SendTherapistBillDTO;
use App\DTOs\TherapistBillFilterDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Billing\CreateTherapistBillRequest;
use App\Http\Requests\Admin\Billing\SendTherapistBillRequest;
use App\Http\Requests\Admin\Billing\TherapistBillDataRequest;
use App\Http\Requests\Admin\Billing\TherapistBillIndexRequest;
use App\Http\Support\DataTablesRequest;
use App\Http\Support\DataTablesResponse;
use App\Models\TherapistBill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

final class TherapistBillController extends Controller
{
    use DataTablesResponse;

    /**
     * @var array<int, string>
     */
    private const ORDER_WHITELIST = [
        0 => 'bill_number',
        1 => 'therapist_name',
        2 => 'billing_period_start',
        3 => 'total_due',
        4 => 'status',
        5 => 'due_date',
    ];

    public function __construct(
        private readonly TherapistBillService $billService,
        private readonly TherapistBillPdfService $pdfService,
        private readonly TherapistBillRepositoryInterface $billRepository,
        private readonly TherapistService $therapistService,
        private readonly StudentService $studentService,
        private readonly ServiceCatalogService $serviceCatalogService,
    ) {}

    public function index(TherapistBillIndexRequest $request): View
    {
        $this->authorize('viewAny', TherapistBill::class);

        return view('admin.billing.therapist-bills.index', [
            'bills' => collect(),
            'filters' => $request->validated(),
            'therapists' => $this->therapistService->listActiveTherapists(),
            'datatableUrl' => route('admin.billing.therapist-bills.data'),
        ]);
    }

    public function data(TherapistBillDataRequest $request): JsonResponse
    {
        $this->authorize('viewAny', TherapistBill::class);

        $params = DataTablesRequest::fromRequest($request, self::ORDER_WHITELIST);
        $filterData = [
            'therapist_id' => $request->input('filter_therapist_id'),
            'status' => $request->input('filter_status'),
            'date_from' => $request->input('filter_date_from'),
            'date_to' => $request->input('filter_date_to'),
            'bill_number' => $request->input('filter_bill_number'),
            'per_page' => $params->length,
        ];
        $filters = TherapistBillFilterDTO::fromArray($filterData);

        $result = $this->billService->listForDataTables($filters, $params);

        return $this->dataTablesResponse(
            $params,
            $result['recordsTotal'],
            $result['recordsFiltered'],
            $result['rows'],
            static fn (TherapistBill $bill): array => TherapistBillRowTransformer::transform($bill),
        );
    }

    public function create(Request $request): View
    {
        $this->authorize('create', TherapistBill::class);

        // Default to last 30 days
        $selectedTherapistId = $request->input('therapist_id');
        $filters = [
            'date_from' => $request->input('date_from', now()->subDays(30)->format('Y-m-d')),
            'date_to' => $request->input('date_to', now()->format('Y-m-d')),
            'therapist_id' => $selectedTherapistId,
            'student_id' => $request->input('student_id'),
            'service_id' => $request->input('service_id'),
            'school_id' => $request->input('school_id'),
            'search' => $request->input('search'),
        ];

        $sessionLogs = $this->billRepository->getAvailableSessionLogsForBillingCreation($filters);

        // Get therapists that have available session logs
        $therapistFilterForDropdown = [
            'date_from' => $filters['date_from'],
            'date_to' => $filters['date_to'],
        ];
        $availableTherapistIds = $this->billRepository->getAvailableTherapistIdsForBillingCreation($therapistFilterForDropdown);

        // Get filter options based on selected therapist
        $students = collect();
        $services = collect();

        if ($selectedTherapistId) {
            // Get students from session logs (already loaded with relationships)
            $students = $sessionLogs->pluck('student')->unique('id')->filter()->values();

            // Get unique services from session logs for this therapist
            $serviceIds = $sessionLogs->pluck('service_id')->unique();
            if ($serviceIds->isNotEmpty()) {
                $services = $this->serviceCatalogService->listActiveForSelect()
                    ->whereIn('id', $serviceIds->toArray());
            }
        }

        // Generate bill number for display
        $billNumber = $this->billRepository->generateBillNumber();

        // Get therapists that have available session logs (filtered by date range only)
        $therapists = $this->therapistService->listActiveTherapists()
            ->whereIn('id', $availableTherapistIds->toArray());

        return view('admin.billing.therapist-bills.create', [
            'sessionLogs' => $sessionLogs,
            'therapists' => $therapists,
            'students' => $students,
            'services' => $services,
            'filters' => $filters,
            'billNumber' => $billNumber,
        ]);
    }

    public function store(CreateTherapistBillRequest $request): RedirectResponse
    {
        $this->authorize('create', TherapistBill::class);

        $dto = CreateTherapistBillDTO::fromArray($request->validated());

        try {
            $bill = $this->billService->generateBill($request->user(), $dto);

            return redirect()
                ->route('admin.billing.therapist-bills.show', $bill)
                ->with('success', 'Bill created successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->back()
                ->withErrors(['error' => $e->getMessage()])
                ->withInput();
        }
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

        return view('admin.billing.therapist-bills.show', [
            'bill' => $bill,
        ]);
    }

    public function send(SendTherapistBillRequest $request, TherapistBill $bill): RedirectResponse
    {
        $this->authorize('send', $bill);

        $dto = SendTherapistBillDTO::fromArray($request->validated());

        try {
            $this->billService->sendBill($request->user(), $bill, $dto);

            return redirect()
                ->route('admin.billing.therapist-bills.show', $bill)
                ->with('success', 'Bill sent successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->back()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function download(TherapistBill $bill): Response
    {
        $this->authorize('view', $bill);

        $pdf = $this->pdfService->generatePdf($bill);

        return $pdf->download("bill-{$bill->bill_number}.pdf");
    }
}
