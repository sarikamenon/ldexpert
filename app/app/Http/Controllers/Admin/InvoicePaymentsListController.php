<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DataTables\Transformers\InvoicePaymentRowTransformer;
use App\Domain\Finance\Services\PaymentsListService;
use App\DTOs\InvoicePaymentFilterDTO;
use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Invoice\InvoicePaymentDataRequest;
use App\Http\Requests\Admin\Invoice\InvoicePaymentIndexRequest;
use App\Http\Support\DataTablesRequest;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InvoicePaymentsListController extends Controller
{
    /**
     * @var array<int, string>
     */
    private const ORDER_WHITELIST = [
        0 => 'paid_at',
        1 => 'amount',
        2 => 'method',
        3 => 'reference',
    ];

    public function __construct(
        private readonly \App\Domain\Finance\Services\InvoicePaymentService $service,
        private readonly PaymentsListService $paymentsListService,
    ) {}

    public function index(InvoicePaymentIndexRequest $request): View
    {
        $this->authorize('viewAny', InvoicePayment::class);

        return view('admin.payments.invoice-payments.index', [
            'payments' => collect(),
            'totalAmount' => 0,
            'datatableUrl' => route('admin.payments.invoices.data'),
        ]);
    }

    public function data(InvoicePaymentDataRequest $request): JsonResponse
    {
        $this->authorize('viewAny', InvoicePayment::class);

        $params = DataTablesRequest::fromRequest($request, self::ORDER_WHITELIST);
        $filterData = [
            'from_date' => $request->input('filter_from_date'),
            'to_date' => $request->input('filter_to_date'),
            'method' => $request->input('filter_method'),
            'search' => $request->input('filter_search'),
        ];
        $filters = InvoicePaymentFilterDTO::fromArray($filterData);

        $result = $this->paymentsListService->listInvoicePaymentsForDataTables($filters, $params);
        $totalAmount = $this->paymentsListService->getInvoicePaymentsTotalAmount($filters);

        $data = $result['rows']->map(
            static fn (InvoicePayment $payment): array => InvoicePaymentRowTransformer::transform($payment)
        )->all();

        return response()->json([
            'draw' => $params->draw,
            'recordsTotal' => $result['recordsTotal'],
            'recordsFiltered' => $result['recordsFiltered'],
            'data' => $data,
            'totalAmount' => round($totalAmount, 2),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('viewAny', InvoicePayment::class);

        $invoices = Invoice::whereIn('status', [InvoiceStatus::DRAFT, InvoiceStatus::SENT])
            ->with('school')
            ->orderBy('invoice_date')
            ->orderBy('id')
            ->get();

        return view('admin.payments.record-payment', [
            'mode' => 'invoice',
            'invoices' => $invoices,
        ]);
    }

    public function store(\App\Http\Requests\Admin\Invoice\RecordInvoicePaymentRequest $request): RedirectResponse
    {
        $this->authorize('viewAny', InvoicePayment::class);

        $data = $request->validated();
        /** @var \App\Models\User $user */
        $user = $request->user();
        $data['recorded_by_id'] = $user->id;

        $dto = \App\DTOs\RecordInvoicePaymentDTO::fromArray($data);

        try {
            $this->service->recordPayment($dto);

            return redirect()
                ->route('admin.payments.invoices.index')
                ->with('success', 'Payment recorded successfully.');
        } catch (\Throwable $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Failed to record payment: '.$e->getMessage());
        }
    }

    public function destroy(InvoicePayment $payment): RedirectResponse
    {
        $this->authorize('delete', $payment);

        try {
            $this->service->deletePayment($payment);

            return redirect()
                ->route('admin.payments.invoices.index')
                ->with('success', 'Payment deleted successfully.');
        } catch (\Throwable $e) {
            return redirect()
                ->back()
                ->with('error', 'Failed to delete payment: '.$e->getMessage());
        }
    }
}
