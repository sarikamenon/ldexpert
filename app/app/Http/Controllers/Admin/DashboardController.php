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
            'recentActivity' => $this->dashboardService->getRecentActivity(limit: 5),
            'upcomingEvents' => $this->dashboardService->getUpcomingEvents(),
            'operationalMetrics' => $this->dashboardService->getOperationalMetrics(),
            'quickActions' => $this->dashboardService->getQuickActions(),
        ]);
    }
}
