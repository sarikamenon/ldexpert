<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Analytics\AnalyticsFilterRequest;
use App\Services\AnalyticsService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;

final class AnalyticsController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analyticsService
    ) {}

    public function index(AnalyticsFilterRequest $request): View
    {
        [$startDate, $endDate] = $this->getDateRange($request);

        return view('admin.analytics.index', [
            'analytics' => $this->analyticsService->getOverallAnalytics($startDate, $endDate),
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d'),
            'dateRange' => $request->input('date_range', 'last_30_days'),
        ]);
    }

    public function schools(AnalyticsFilterRequest $request): View
    {
        [$startDate, $endDate] = $this->getDateRange($request);

        return view('admin.analytics.schools', [
            'analytics' => $this->analyticsService->getSchoolsAnalytics($startDate, $endDate),
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d'),
            'dateRange' => $request->input('date_range', 'last_30_days'),
        ]);
    }

    public function therapists(AnalyticsFilterRequest $request): View
    {
        [$startDate, $endDate] = $this->getDateRange($request);

        return view('admin.analytics.therapists', [
            'analytics' => $this->analyticsService->getTherapistsAnalytics($startDate, $endDate),
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d'),
            'dateRange' => $request->input('date_range', 'last_30_days'),
        ]);
    }

    private function getDateRange(AnalyticsFilterRequest $request): array
    {
        $dateRange = $request->input('date_range', 'last_30_days');

        if ($dateRange === 'custom') {
            $startDate = $request->input('start_date')
                ? Carbon::parse($request->input('start_date'))
                : now()->subDays(30);
            $endDate = $request->input('end_date')
                ? Carbon::parse($request->input('end_date'))
                : now();
        } else {
            [$startDate, $endDate] = match ($dateRange) {
                'last_7_days' => [now()->subDays(7), now()],
                'last_30_days' => [now()->subDays(30), now()],
                'last_90_days' => [now()->subDays(90), now()],
                'this_month' => [now()->startOfMonth(), now()->endOfMonth()],
                'last_month' => [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()],
                'this_year' => [now()->startOfYear(), now()->endOfYear()],
                default => [now()->subDays(30), now()],
            };
        }

        return [$startDate, $endDate];
    }
}
