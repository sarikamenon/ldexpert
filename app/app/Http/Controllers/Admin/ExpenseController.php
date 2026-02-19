<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Finance\Services\ExpenseService;
use App\DTOs\CreateExpenseDTO;
use App\DTOs\UpdateExpenseDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Expense\CreateExpenseRequest;
use App\Http\Requests\Admin\Expense\UpdateExpenseRequest;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function __construct(
        private readonly ExpenseService $service,
    ) {}

    /**
     * Display a listing of expenses.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Expense::class);

        $query = Expense::with(['category', 'createdBy'])
            ->orderBy('expense_date', 'desc');

        // Apply filters
        if ($request->filled('category_id')) {
            $query->where('expense_category_id', $request->input('category_id'));
        }

        if ($request->filled('date_from')) {
            $query->where('expense_date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('expense_date', '<=', $request->input('date_to'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('vendor_payee', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%");
            });
        }

        $expenses = $query->paginate(15);

        // Get totals for the filtered results
        $totalAmount = $query->sum('amount');

        return view('admin.expenses.index', [
            'expenses' => $expenses,
            'categories' => ExpenseCategory::active()->orderBy('name')->get(),
            'filters' => $request->all(),
            'totalAmount' => $totalAmount,
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
