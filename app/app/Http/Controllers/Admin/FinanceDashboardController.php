<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Finance\Services\FinanceDashboardService;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

class FinanceDashboardController extends Controller
{
    public function __construct(
        private readonly FinanceDashboardService $service,
    ) {}

    /**
     * Display the finance dashboard.
     */
    public function index(): View
    {
        $data = $this->service->getDashboardData();

        return view('admin.finance.dashboard', $data);
    }
}
