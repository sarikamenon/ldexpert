<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DataTables\Transformers\ExpenseRowTransformer;
use App\Domain\Finance\Services\ExpenseService;
use App\DTOs\CreateExpenseDTO;
use App\DTOs\ExpenseFilterDTO;
use App\DTOs\UpdateExpenseDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Expense\CreateExpenseRequest;
use App\Http\Requests\Admin\Expense\ExpenseDataRequest;
use App\Http\Requests\Admin\Expense\UpdateExpenseRequest;
use App\Http\Support\DataTablesRequest;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    /**
     * @var array<int, string>
     */
    private const ORDER_WHITELIST = [
        0 => 'expense_date',
        1 => 'expense_category_id',
        2 => 'vendor_payee',
        3 => 'description',
        4 => 'amount',
    ];

    public function __construct(
        private readonly ExpenseService $service,
    ) {}

    /**
     * Display a listing of expenses.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Expense::class);

        return view('admin.expenses.index', [
            'expenses' => collect(),
            'categories' => ExpenseCategory::active()->orderBy('name')->get(),
            'filters' => $request->all(),
            'totalAmount' => 0,
            'datatableUrl' => route('admin.expenses.data'),
        ]);
    }

    public function data(ExpenseDataRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Expense::class);

        $params = DataTablesRequest::fromRequest($request, self::ORDER_WHITELIST);
        $filterData = [
            'category_id' => $request->input('filter_category_id'),
            'date_from' => $request->input('filter_date_from'),
            'date_to' => $request->input('filter_date_to'),
            'search' => $request->input('filter_search'),
            'per_page' => $params->length,
        ];
        $filters = ExpenseFilterDTO::fromArray($filterData);

        $result = $this->service->listForDataTables($filters, $params);
        $totalAmount = $this->service->getTotalAmountForFilters($filters);

        $data = $result['rows']->map(
            static fn (Expense $expense): array => ExpenseRowTransformer::transform($expense)
        )->all();

        return response()->json([
            'draw' => $params->draw,
            'recordsTotal' => $result['recordsTotal'],
            'recordsFiltered' => $result['recordsFiltered'],
            'data' => $data,
            'totalAmount' => round($totalAmount, 2),
        ]);
    }

    /**
     * Show the form for creating a new expense.
     */
    public function create(): View
    {
        $this->authorize('create', Expense::class);

        return view('admin.expenses.create', [
            'categories' => ExpenseCategory::active()->orderBy('name')->get(),
        ]);
    }

    /**
     * Store a newly created expense.
     */
    public function store(CreateExpenseRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['created_by_id'] = $request->user()?->id;

        $dto = CreateExpenseDTO::fromArray($data);

        try {
            $this->service->createExpense($dto);

            return redirect()
                ->route('admin.expenses.index')
                ->with('success', 'Expense created successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Failed to create expense: '.$e->getMessage());
        }
    }

    /**
     * Display the specified expense.
     */
    public function show(Expense $expense): View
    {
        $this->authorize('view', $expense);

        $expense->load(['category', 'createdBy']);

        return view('admin.expenses.show', [
            'expense' => $expense,
        ]);
    }

    /**
     * Show the form for editing the specified expense.
     */
    public function edit(Expense $expense): View
    {
        $this->authorize('update', $expense);

        return view('admin.expenses.edit', [
            'expense' => $expense,
            'categories' => ExpenseCategory::active()->orderBy('name')->get(),
        ]);
    }

    /**
     * Update the specified expense.
     */
    public function update(UpdateExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $dto = UpdateExpenseDTO::fromArray($request->validated());

        try {
            $this->service->updateExpense($expense, $dto);

            return redirect()
                ->route('admin.expenses.show', $expense)
                ->with('success', 'Expense updated successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Failed to update expense: '.$e->getMessage());
        }
    }

    /**
     * Remove the specified expense.
     */
    public function destroy(Expense $expense): RedirectResponse
    {
        $this->authorize('delete', $expense);

        try {
            $this->service->deleteExpense($expense);

            return redirect()
                ->route('admin.expenses.index')
                ->with('success', 'Expense deleted successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', 'Failed to delete expense: '.$e->getMessage());
        }
    }
}
