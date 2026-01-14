<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    protected DashboardService $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * Get dashboard statistics
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $stats = $this->dashboardService->getDashboardStats();

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
