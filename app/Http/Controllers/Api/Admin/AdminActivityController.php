<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivity;
use App\Services\AdminActivityService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminActivityController extends Controller
{
    protected AdminActivityService $activityService;

    public function __construct(AdminActivityService $activityService)
    {
        $this->activityService = $activityService;
    }

    /**
     * Get all admin activities with filters
     */
    public function index(Request $request): JsonResponse
    {
        $query = AdminActivity::with('admin');

        // Filter by admin
        if ($request->has('admin_id')) {
            $query->where('admin_id', $request->admin_id);
        }

        // Filter by action
        if ($request->has('action')) {
            $query->where('action', $request->action);
        }

        // Filter by entity type
        if ($request->has('entity_type')) {
            $query->where('entity_type', $request->entity_type);
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Search in description
        if ($request->has('search')) {
            $query->where('description', 'like', '%' . $request->search . '%');
        }

        $activities = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $activities->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'admin' => [
                        'id' => $activity->admin->id,
                        'name' => $activity->admin->first_name . ' ' . $activity->admin->last_name,
                        'email' => $activity->admin->email,
                    ],
                    'action' => $activity->action,
                    'entity_type' => $activity->entity_type,
                    'entity_id' => $activity->entity_id,
                    'description' => $activity->description,
                    'metadata' => $activity->metadata,
                    'ip_address' => $activity->ip_address,
                    'created_at' => $activity->created_at->format('Y-m-d H:i:s'),
                    'time_ago' => $activity->created_at->diffForHumans(),
                ];
            }),
            'pagination' => [
                'total' => $activities->total(),
                'per_page' => $activities->perPage(),
                'current_page' => $activities->currentPage(),
                'last_page' => $activities->lastPage(),
            ],
        ]);
    }

    /**
     * Get admin activity summary
     */
    public function summary(Request $request): JsonResponse
    {
        $admin = auth()->user();
        $period = $request->get('period', 'today');

        $summary = $this->activityService->getAdminSummary($admin, $period);

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    /**
     * Get activity statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from', now()->subDays(30)->toDateString());
        $dateTo = $request->get('date_to', now()->toDateString());

        $totalActivities = AdminActivity::whereBetween('created_at', [$dateFrom, $dateTo])->count();

        // Activities by admin
        $activitiesByAdmin = AdminActivity::with('admin')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->select('admin_id', \DB::raw('COUNT(*) as count'))
            ->groupBy('admin_id')
            ->orderBy('count', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'admin' => [
                        'id' => $item->admin->id,
                        'name' => $item->admin->first_name . ' ' . $item->admin->last_name,
                        'email' => $item->admin->email,
                    ],
                    'count' => $item->count,
                ];
            });

        // Activities by action
        $activitiesByAction = AdminActivity::whereBetween('created_at', [$dateFrom, $dateTo])
            ->select('action', \DB::raw('COUNT(*) as count'))
            ->groupBy('action')
            ->orderBy('count', 'desc')
            ->get();

        // Activities by entity type
        $activitiesByEntity = AdminActivity::whereBetween('created_at', [$dateFrom, $dateTo])
            ->select('entity_type', \DB::raw('COUNT(*) as count'))
            ->groupBy('entity_type')
            ->orderBy('count', 'desc')
            ->get();

        // Revenue collected by admin
        $revenueByAdmin = AdminActivity::with('admin')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->where('action', 'revenue_collected')
            ->get()
            ->groupBy('admin_id')
            ->map(function ($activities) {
                $totalRevenue = $activities->sum(fn($activity) => $activity->metadata['amount'] ?? 0);
                $admin = $activities->first()->admin;

                return [
                    'admin' => [
                        'id' => $admin->id,
                        'name' => $admin->first_name . ' ' . $admin->last_name,
                        'email' => $admin->email,
                    ],
                    'total_revenue' => (float) number_format($totalRevenue, 2, '.', ''),
                    'orders_processed' => $activities->count(),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'from' => $dateFrom,
                    'to' => $dateTo,
                ],
                'total_activities' => $totalActivities,
                'activities_by_admin' => $activitiesByAdmin,
                'activities_by_action' => $activitiesByAction,
                'activities_by_entity' => $activitiesByEntity,
                'revenue_by_admin' => $revenueByAdmin,
            ],
        ]);
    }

    /**
     * Get activity timeline for specific entity
     */
    public function entityTimeline(Request $request, string $entityType, int $entityId): JsonResponse
    {
        $activities = AdminActivity::forEntity($entityType, $entityId);

        return response()->json([
            'success' => true,
            'data' => $activities->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'admin' => [
                        'id' => $activity->admin->id,
                        'name' => $activity->admin->first_name . ' ' . $activity->admin->last_name,
                        'email' => $activity->admin->email,
                    ],
                    'action' => $activity->action,
                    'description' => $activity->description,
                    'metadata' => $activity->metadata,
                    'created_at' => $activity->created_at->format('Y-m-d H:i:s'),
                    'time_ago' => $activity->created_at->diffForHumans(),
                ];
            }),
        ]);
    }
}
