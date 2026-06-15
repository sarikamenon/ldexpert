<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService
    ) {}

    public function index(): View
    {
        return view('admin.dashboard', [
            'metrics' => $this->dashboardService->getKeyMetrics(),
            'alerts' => $this->dashboardService->getCriticalAlerts(),
            'charts' => $this->dashboardService->getChartData(),
            'upcomingSchoolContracts' => $this->dashboardService->getExpiringSchoolContractEvents(),
            'upcomingSSAs' => $this->dashboardService->getExpiringSSAEvents(),
            'pendingSSAs' => $this->dashboardService->getPendingSSAEvents(),
            'pendingSSACount' => $this->dashboardService->getPendingSSACount(),
            'operationalMetrics' => $this->dashboardService->getOperationalMetrics(),
            'quickActions' => $this->dashboardService->getQuickActions(),
        ]);
    }
}
