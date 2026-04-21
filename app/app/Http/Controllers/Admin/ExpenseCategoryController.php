<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DataTables\Transformers\ExpenseCategoryRowTransformer;
use App\Domain\Finance\Services\ExpenseCategoryService;
use App\DTOs\ExpenseCategoryFilterDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\ExpenseCategoryDataRequest;
use App\Http\Support\DataTablesRequest;
use App\Http\Support\DataTablesResponse;
use App\Models\ExpenseCategory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ExpenseCategoryController extends Controller
{
    use DataTablesResponse;

    /**
     * @var array<int, string>
     */
    private const ORDER_WHITELIST = [
        0 => 'name',
        1 => 'slug',
        2 => 'is_active',
        3 => 'created_at',
    ];

    public function __construct(
        private readonly ExpenseCategoryService $expenseCategoryService,
    ) {}

    public function index(Request $request): View
    {
        return view('admin.settings.expense-categories.index', [
            'categories' => collect(),
            'datatableUrl' => route('admin.settings.expense-categories.data'),
        ]);
    }

    public function data(ExpenseCategoryDataRequest $request): JsonResponse
    {
        $params = DataTablesRequest::fromRequest($request, self::ORDER_WHITELIST);
        $filterData = [
            'search' => $request->input('filter_search'),
            'status' => $request->input('filter_status'),
            'per_page' => $params->length,
        ];
        $filters = ExpenseCategoryFilterDTO::fromArray($filterData);

        $result = $this->expenseCategoryService->listForDataTables($filters, $params);

        return $this->dataTablesResponse(
            $params,
            $result['recordsTotal'],
            $result['recordsFiltered'],
            $result['rows'],
            static fn (ExpenseCategory $category): array => ExpenseCategoryRowTransformer::transform($category),
        );
    }

    public function create(): View
    {
        return view('admin.settings.expense-categories.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:expense_categories,name'],
            'is_active' => ['boolean'],
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        ExpenseCategory::create($validated);

        return redirect()
            ->route('admin.settings.expense-categories.index')
            ->with('success', 'Expense category created successfully.');
    }

    public function edit(ExpenseCategory $expenseCategory): View
    {
        return view('admin.settings.expense-categories.edit', compact('expenseCategory'));
    }

    public function update(Request $request, ExpenseCategory $expenseCategory): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('expense_categories', 'name')->ignore($expenseCategory->id)],
            'is_active' => ['boolean'],
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        if ($expenseCategory->isProtected()) {
            $validated['is_active'] = true;
        }

        $expenseCategory->update($validated);

        return redirect()
            ->route('admin.settings.expense-categories.index')
            ->with('success', 'Expense category updated successfully.');
    }

    public function toggleStatus(ExpenseCategory $expenseCategory): JsonResponse
    {
        if ($expenseCategory->isProtected()) {
            return response()->json([
                'success' => false,
                'message' => 'This category is required by the system and cannot be deactivated.',
                'is_active' => $expenseCategory->is_active,
            ], 403);
        }

        $expenseCategory->update(['is_active' => ! $expenseCategory->is_active]);

        $message = $expenseCategory->is_active
            ? 'Expense category activated successfully.'
            : 'Expense category deactivated successfully.';

        return response()->json([
            'success' => true,
            'message' => $message,
            'is_active' => $expenseCategory->is_active,
        ]);
    }
}
