<?php

namespace App\Http\Controllers;

use App\Models\Work;
use App\Models\WorkStatus;
use Inertia\Inertia;

class DashboardController extends Controller
{
    /**
     * ダッシュボード表示
     */
    public function index()
    {
        $userId = auth()->id();

        $statusCounts = WorkStatus::select('id', 'name', 'sort_order')
            ->withCount('works')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $myAssignedWorks = Work::query()
            ->where(function ($q) use ($userId) {
                $q->where('assigned_user_id', $userId)
                    ->orWhere('additional_user_id', $userId);
            })
            ->with(['equipment:id,name', 'workStatus:id,name,sort_order', 'workPriority:id,name', 'assignedUser:id,name'])
            ->join('work_statuses', 'works.work_status_id', '=', 'work_statuses.id')
            ->orderBy('work_statuses.sort_order')
            ->orderByDesc('works.created_at')
            ->select('works.*')
            ->limit(10)
            ->get();

        $recentWorks = Work::with(['equipment:id,name', 'workStatus:id,name', 'workPriority:id,name', 'assignedUser:id,name'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return Inertia::render('Dashboard', [
            'statusCounts' => $statusCounts,
            'myAssignedWorks' => $myAssignedWorks,
            'recentWorks' => $recentWorks,
        ]);
    }
}
