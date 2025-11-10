<?php

declare(strict_types=1);

namespace App\Http\Controllers\Therapist;

use App\Http\Controllers\Controller;
use App\Domain\Therapist\Services\DashboardService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $service) {}

    public function index(): View
    {
        $metrics = $this->service->getDashboardMetrics();

        return view('dashboard', $metrics);
    }
}
