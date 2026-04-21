<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Billing;

use App\DataTables\Transformers\TherapistBillRowTransformer;
use App\Domain\Billing\Repositories\TherapistBillRepositoryInterface;
use App\Domain\Billing\Services\TherapistBillPdfService;
use App\Domain\Billing\Services\TherapistBillService;
use App\Domain\Therapist\Services\TherapistService;
use App\DTOs\AttachSessionsDTO;
use App\DTOs\CreateTherapistBillDTO;
use App\DTOs\SendTherapistBillDTO;
use App\DTOs\TherapistBillFilterDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Billing\AttachTherapistBillSessionsRequest;
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

        $billNumber = $this->billRepository->generateBillNumber();

        return view('admin.billing.therapist-bills.create', [
            'therapists' => $this->therapistService->listActiveTherapists(),
            'billNumber' => $billNumber,
            'selectedTherapistId' => $request->input('therapist_id'),
            'defaultDateFrom' => $request->input('date_from', now()->subDays(30)->format('Y-m-d')),
            'defaultDateTo' => $request->input('date_to', now()->format('Y-m-d')),
        ]);
    }

    public function store(CreateTherapistBillRequest $request): RedirectResponse
    {
        $this->authorize('create', TherapistBill::class);

        $dto = CreateTherapistBillDTO::fromArray($request->validated());

        try {
            /** @var \App\Models\User $user */
            $user = $request->user();
            $bill = $this->billService->generateBill($user, $dto);

            if ($bill->sessionLogs->isEmpty()) {
                return redirect()
                    ->route('admin.billing.therapist-bills.attach-sessions', $bill)
                    ->with('success', 'Draft bill created. Add or remove sessions below.');
            }

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

    public function attachSessions(Request $request, TherapistBill $bill): View
    {
        $this->authorize('update', $bill);

        if (! $bill->isDraft()) {
            abort(404);
        }

        $bill->loadMissing(['therapist']);

        $filters = [
            'therapist_id' => $bill->therapist_id,
            'date_from' => $request->input('date_from', $bill->billing_period_start?->format('Y-m-d')),
            'date_to' => $request->input('date_to', $bill->billing_period_end?->format('Y-m-d')),
            'school_id' => $request->input('school_id'),
            'student_id' => $request->input('student_id'),
            'service_id' => $request->input('service_id'),
        ];

        $attachedSessionLogs = $bill->sessionLogs()
            ->with(['student', 'service', 'therapist', 'school'])
            ->orderBy('session_date', 'desc')
            ->get();

        $availableSessionLogs = $this->billRepository->getAvailableSessionLogsForBillingCreation($filters);

        $attachedIds = $attachedSessionLogs->pluck('id')->all();
        $sessionLogs = $attachedSessionLogs->concat($availableSessionLogs)->unique('id');

        $schools = $sessionLogs->pluck('school')->filter()->unique('id')->values();
        $students = $sessionLogs->pluck('student')->filter()->unique('id')->values();
        $services = $sessionLogs->pluck('service')->filter()->unique('id')->values();

        return view('admin.billing.therapist-bills.attach-sessions', [
            'bill' => $bill,
            'sessionLogs' => $sessionLogs,
            'attachedIds' => $attachedIds,
            'filters' => $filters,
            'schools' => $schools,
            'students' => $students,
            'services' => $services,
        ]);
    }

    public function storeAttachedSessions(AttachTherapistBillSessionsRequest $request, TherapistBill $bill): RedirectResponse
    {
        $dto = AttachSessionsDTO::fromArray($request->validated());

        try {
            $this->billService->attachSessionsToDraft($bill, $dto);

            return redirect()
                ->route('admin.billing.therapist-bills.show', $bill)
                ->with('success', 'Bill sessions updated successfully.');
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
            /** @var \App\Models\User $user */
            $user = $request->user();
            $this->billService->sendBill($user, $bill, $dto);

            return redirect()
                ->route('admin.billing.therapist-bills.show', $bill)
                ->with('success', 'Bill sent successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->back()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function destroy(TherapistBill $bill): RedirectResponse
    {
        $this->authorize('delete', $bill);

        try {
            $this->billService->deleteBill($bill);

            return redirect()
                ->route('admin.billing.therapist-bills.index')
                ->with('success', 'Bill deleted successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }
    }

    public function download(TherapistBill $bill): Response
    {
        $this->authorize('view', $bill);

        $pdf = $this->pdfService->generatePdf($bill);

        return $pdf->download("bill-{$bill->bill_number}.pdf");
    }
}
