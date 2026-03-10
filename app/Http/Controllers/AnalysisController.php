<?php

namespace App\Http\Controllers;

use App\Models\Work;
use App\Models\WorkCost;
use App\Models\WorkContent;
use App\Models\WorkPurpose;
use App\Models\WorkStatus;
use App\Models\WorkUsedPart;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AnalysisController extends Controller
{
    /**
     * 期間フィルター用の日付範囲を取得
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function getDateRange(Request $request): array
    {
        $from = $request->query('from');
        $to = $request->query('to');
        $preset = $request->query('preset', 'month');

        $now = Carbon::now()->startOfDay();

        if ($from && $to) {
            return [
                Carbon::parse($from)->startOfDay(),
                Carbon::parse($to)->endOfDay(),
            ];
        }

        return match ($preset) {
            'month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'last_month' => [
                $now->copy()->subMonth()->startOfMonth(),
                $now->copy()->subMonth()->endOfMonth(),
            ],
            'quarter' => [
                $now->copy()->startOfQuarter(),
                $now->copy()->endOfQuarter(),
            ],
            'year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
        };
    }

    /**
     * 分析トップ（サマリー）
     */
    public function index(Request $request)
    {
        [$from, $to] = $this->getDateRange($request);

        $baseQuery = Work::query()
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('occurred_at', [$from, $to])
                    ->orWhereBetween('created_at', [$from, $to]);
            });

        $totalWorks = (clone $baseQuery)->count();
        $completedWorks = (clone $baseQuery)->whereHas('workStatus', fn ($q) => $q->where('name', '完了'))->count();

        $completedWithDates = Work::query()
            ->whereNotNull('occurred_at')
            ->whereNotNull('completed_at')
            ->whereBetween('occurred_at', [$from, $to])
            ->get();

        $avgCompletionDays = $completedWithDates->isEmpty()
            ? null
            : round($completedWithDates->avg(fn ($w) => Carbon::parse($w->occurred_at)->diffInDays(Carbon::parse($w->completed_at))), 1);

        $totalCost = WorkCost::query()
            ->whereIn('work_id', (clone $baseQuery)->pluck('id'))
            ->sum('amount');

        $totalStopMinutes = (clone $baseQuery)->sum('production_stop_minutes');

        $uniqueAssignees = (clone $baseQuery)
            ->whereNotNull('assigned_user_id')
            ->distinct('assigned_user_id')
            ->count('assigned_user_id');

        $dateExpr = DB::connection()->getDriverName() === 'mysql'
            ? 'DATE_FORMAT(COALESCE(occurred_at, created_at), "%Y-%m")'
            : 'strftime("%Y-%m", COALESCE(occurred_at, created_at))';

        $monthlyWorks = Work::query()
            ->selectRaw("{$dateExpr} as month")
            ->selectRaw('count(*) as count')
            ->whereBetween(DB::raw('COALESCE(occurred_at, created_at)'), [$from, $to])
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->pluck('count', 'month')
            ->all();

        $statusCounts = WorkStatus::select('id', 'name', 'sort_order')
            ->withCount(['works' => fn ($q) => $q->whereIn('id', (clone $baseQuery)->pluck('id'))])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Analysis/Index', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'preset' => $request->query('preset', 'month'),
            'kpi' => [
                'totalWorks' => $totalWorks,
                'completedWorks' => $completedWorks,
                'avgCompletionDays' => $avgCompletionDays,
                'totalCost' => $totalCost,
                'totalStopMinutes' => (int) $totalStopMinutes,
                'uniqueAssignees' => $uniqueAssignees,
            ],
            'monthlyWorks' => $monthlyWorks,
            'statusCounts' => $statusCounts,
        ]);
    }

    /**
     * 作業量分析
     */
    public function workVolume(Request $request)
    {
        [$from, $to] = $this->getDateRange($request);

        $baseQuery = Work::query()->where(function ($q) use ($from, $to) {
            $q->whereBetween(DB::raw('COALESCE(occurred_at, created_at)'), [$from, $to]);
        });

        $workIds = (clone $baseQuery)->pluck('id');

        $dateColumn = DB::connection()->getDriverName() === 'mysql'
            ? 'DATE_FORMAT(COALESCE(works.occurred_at, works.created_at), "%Y-%m")'
            : 'strftime("%Y-%m", COALESCE(works.occurred_at, works.created_at))';

        $monthlyWorks = Work::query()
            ->selectRaw("{$dateColumn} as month")
            ->selectRaw('count(*) as count')
            ->whereIn('id', $workIds)
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->pluck('count', 'month')
            ->all();

        $statusMonthly = Work::query()
            ->join('work_statuses', 'works.work_status_id', '=', 'work_statuses.id')
            ->selectRaw("{$dateColumn} as month")
            ->selectRaw('work_statuses.name as status_name')
            ->selectRaw('count(*) as count')
            ->whereIn('works.id', $workIds)
            ->where('work_statuses.is_active', true)
            ->groupBy('month', 'work_statuses.id', 'work_statuses.name', 'work_statuses.sort_order')
            ->orderBy('month')
            ->orderBy('work_statuses.sort_order')
            ->get()
            ->groupBy('month');

        $equipmentCounts = DB::table('work_equipment')
            ->join('equipments', 'work_equipment.equipment_id', '=', 'equipments.id')
            ->whereIn('work_equipment.work_id', $workIds)
            ->select('equipments.id', 'equipments.name', 'equipments.parent_id')
            ->selectRaw('count(*) as works_count')
            ->groupBy('equipments.id', 'equipments.name', 'equipments.parent_id')
            ->orderByDesc('works_count')
            ->limit(15)
            ->get();

        $userCounts = Work::query()
            ->join('users', 'works.assigned_user_id', '=', 'users.id')
            ->whereIn('works.id', $workIds)
            ->whereNotNull('works.assigned_user_id')
            ->select('users.id', 'users.name')
            ->selectRaw('count(*) as works_count')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('works_count')
            ->limit(15)
            ->get();

        $purposeCounts = DB::table('work_work_purpose')
            ->join('work_purposes', 'work_work_purpose.work_purpose_id', '=', 'work_purposes.id')
            ->whereIn('work_work_purpose.work_id', $workIds)
            ->where('work_purposes.is_active', true)
            ->select('work_purposes.id', 'work_purposes.name')
            ->selectRaw('count(*) as works_count')
            ->groupBy('work_purposes.id', 'work_purposes.name')
            ->orderByDesc('works_count')
            ->get();

        $priorityCounts = Work::query()
            ->leftJoin('work_priorities', 'works.work_priority_id', '=', 'work_priorities.id')
            ->whereIn('works.id', $workIds)
            ->select(DB::raw('COALESCE(work_priorities.name, "未設定") as name'))
            ->selectRaw('count(*) as works_count')
            ->groupBy('work_priorities.id', 'work_priorities.name')
            ->orderByDesc('works_count')
            ->get();

        return Inertia::render('Analysis/WorkVolume', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'preset' => $request->query('preset', 'month'),
            'monthlyWorks' => $monthlyWorks,
            'statusMonthly' => $statusMonthly->map(fn ($items) => $items->toArray())->all(),
            'equipmentCounts' => $equipmentCounts,
            'userCounts' => $userCounts,
            'purposeCounts' => $purposeCounts,
            'priorityCounts' => $priorityCounts,
        ]);
    }

    /**
     * 費用分析
     */
    public function cost(Request $request)
    {
        [$from, $to] = $this->getDateRange($request);

        $workIds = Work::query()
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween(DB::raw('COALESCE(occurred_at, created_at)'), [$from, $to]);
            })
            ->pluck('id');

        $costsQuery = WorkCost::query()->whereIn('work_id', $workIds);

        $dateColumn = DB::connection()->getDriverName() === 'mysql'
            ? 'DATE_FORMAT(COALESCE(work_costs.occurred_at, work_costs.created_at), "%Y-%m")'
            : 'strftime("%Y-%m", COALESCE(work_costs.occurred_at, work_costs.created_at))';

        $monthlyCosts = WorkCost::query()
            ->whereIn('work_id', $workIds)
            ->selectRaw("{$dateColumn} as month")
            ->selectRaw('SUM(amount) as total')
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->pluck('total', 'month')
            ->all();

        $categoryCosts = WorkCost::query()
            ->join('work_cost_categories', 'work_costs.work_cost_category_id', '=', 'work_cost_categories.id')
            ->whereIn('work_costs.work_id', $workIds)
            ->where('work_cost_categories.is_active', true)
            ->select('work_cost_categories.id', 'work_cost_categories.name')
            ->selectRaw('SUM(work_costs.amount) as total')
            ->groupBy('work_cost_categories.id', 'work_cost_categories.name')
            ->orderByDesc('total')
            ->get();

        $vendorCosts = WorkCost::query()
            ->leftJoin('vendors', 'work_costs.vendor_id', '=', 'vendors.id')
            ->whereIn('work_costs.work_id', $workIds)
            ->select(DB::raw('COALESCE(vendors.name, "業者なし") as name'))
            ->selectRaw('SUM(work_costs.amount) as total')
            ->groupBy('vendors.id', 'vendors.name')
            ->orderByDesc('total')
            ->limit(15)
            ->get();

        $equipmentCosts = DB::table('work_equipment')
            ->join('work_costs', 'work_equipment.work_id', '=', 'work_costs.work_id')
            ->join('equipments', 'work_equipment.equipment_id', '=', 'equipments.id')
            ->whereIn('work_equipment.work_id', $workIds)
            ->select('equipments.id', 'equipments.name')
            ->selectRaw('SUM(work_costs.amount) as total')
            ->groupBy('equipments.id', 'equipments.name')
            ->orderByDesc('total')
            ->limit(15)
            ->get();

        $topWorksByCost = Work::query()
            ->with('equipments:id,name')
            ->whereIn('id', $workIds)
            ->withSum('workCosts', 'amount')
            ->having('work_costs_sum_amount', '>', 0)
            ->orderByDesc('work_costs_sum_amount')
            ->limit(10)
            ->get(['id', 'title']);

        return Inertia::render('Analysis/Cost', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'preset' => $request->query('preset', 'month'),
            'monthlyCosts' => $monthlyCosts,
            'categoryCosts' => $categoryCosts,
            'vendorCosts' => $vendorCosts,
            'equipmentCosts' => $equipmentCosts,
            'topWorksByCost' => $topWorksByCost,
        ]);
    }

    /**
     * 設備分析
     */
    public function equipment(Request $request)
    {
        [$from, $to] = $this->getDateRange($request);

        $workIds = Work::query()
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween(DB::raw('COALESCE(occurred_at, created_at)'), [$from, $to]);
            })
            ->pluck('id');

        $equipmentStats = DB::table('work_equipment')
            ->join('equipments', 'work_equipment.equipment_id', '=', 'equipments.id')
            ->leftJoin('equipments as parent', 'equipments.parent_id', '=', 'parent.id')
            ->join('works', 'work_equipment.work_id', '=', 'works.id')
            ->whereIn('work_equipment.work_id', $workIds)
            ->select(
                'equipments.id',
                'equipments.name',
                'equipments.parent_id',
                'parent.name as parent_name'
            )
            ->selectRaw('COUNT(DISTINCT work_equipment.work_id) as works_count')
            ->selectRaw('COALESCE(SUM(works.production_stop_minutes), 0) as total_stop_minutes')
            ->groupBy('equipments.id', 'equipments.name', 'equipments.parent_id', 'parent.name');

        $equipmentCosts = DB::table('work_equipment')
            ->join('work_costs', 'work_equipment.work_id', '=', 'work_costs.work_id')
            ->whereIn('work_equipment.work_id', $workIds)
            ->groupBy('work_equipment.equipment_id')
            ->select('work_equipment.equipment_id')
            ->selectRaw('SUM(work_costs.amount) as total_cost')
            ->get()
            ->keyBy('equipment_id');

        $stats = $equipmentStats->get()->map(function ($row) use ($equipmentCosts) {
            $cost = $equipmentCosts->get($row->id);
            $row->total_cost = $cost ? (float) $cost->total_cost : 0;

            return $row;
        })->sortByDesc('works_count')->values();

        return Inertia::render('Analysis/Equipment', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'preset' => $request->query('preset', 'month'),
            'equipmentStats' => $stats,
        ]);
    }

    /**
     * 担当者分析
     */
    public function users(Request $request)
    {
        [$from, $to] = $this->getDateRange($request);

        $workIds = Work::query()
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween(DB::raw('COALESCE(occurred_at, created_at)'), [$from, $to]);
            })
            ->pluck('id');

        $userStats = Work::query()
            ->join('users', 'works.assigned_user_id', '=', 'users.id')
            ->whereIn('works.id', $workIds)
            ->whereNotNull('works.assigned_user_id')
            ->select('users.id', 'users.name')
            ->selectRaw('COUNT(*) as works_count')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('works_count')
            ->get();

        $completedStatusId = WorkStatus::where('name', '完了')->value('id');

        $userStats = $userStats->map(function ($user) use ($workIds, $completedStatusId) {
            $completed = Work::query()
                ->where('assigned_user_id', $user->id)
                ->whereIn('id', $workIds)
                ->where('work_status_id', $completedStatusId)
                ->count();

            $avgDays = Work::query()
                ->where('assigned_user_id', $user->id)
                ->whereIn('id', $workIds)
                ->where('work_status_id', $completedStatusId)
                ->whereNotNull('occurred_at')
                ->whereNotNull('completed_at')
                ->get()
                ->avg(fn ($w) => Carbon::parse($w->occurred_at)->diffInDays(Carbon::parse($w->completed_at)));

            $user->completed_count = $completed;
            $user->avg_completion_days = $avgDays !== null ? round($avgDays, 1) : null;

            return $user;
        });

        return Inertia::render('Analysis/Users', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'preset' => $request->query('preset', 'month'),
            'userStats' => $userStats,
        ]);
    }

    /**
     * 部品使用分析
     */
    public function parts(Request $request)
    {
        [$from, $to] = $this->getDateRange($request);

        $workIds = Work::query()
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween(DB::raw('COALESCE(occurred_at, created_at)'), [$from, $to]);
            })
            ->pluck('id');

        $partStats = WorkUsedPart::query()
            ->join('parts', 'work_used_parts.part_id', '=', 'parts.id')
            ->whereIn('work_used_parts.work_id', $workIds)
            ->select('parts.id', 'parts.part_no', 'parts.name')
            ->selectRaw('COUNT(*) as use_count')
            ->selectRaw('SUM(work_used_parts.qty) as total_qty')
            ->groupBy('parts.id', 'parts.part_no', 'parts.name')
            ->orderByDesc('use_count')
            ->limit(20)
            ->get();

        $equipmentParts = DB::table('work_used_parts')
            ->join('work_equipment', 'work_used_parts.work_id', '=', 'work_equipment.work_id')
            ->join('equipments', 'work_equipment.equipment_id', '=', 'equipments.id')
            ->join('parts', 'work_used_parts.part_id', '=', 'parts.id')
            ->whereIn('work_used_parts.work_id', $workIds)
            ->select('equipments.name as equipment_name', 'parts.name as part_name')
            ->selectRaw('SUM(work_used_parts.qty) as total_qty')
            ->groupBy('equipments.id', 'equipments.name', 'parts.id', 'parts.name')
            ->orderByDesc('total_qty')
            ->limit(30)
            ->get();

        return Inertia::render('Analysis/Parts', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'preset' => $request->query('preset', 'month'),
            'partStats' => $partStats,
            'equipmentParts' => $equipmentParts,
        ]);
    }

    /**
     * 業者分析
     */
    public function vendors(Request $request)
    {
        [$from, $to] = $this->getDateRange($request);

        $workIds = Work::query()
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween(DB::raw('COALESCE(occurred_at, created_at)'), [$from, $to]);
            })
            ->pluck('id');

        $vendorCosts = WorkCost::query()
            ->leftJoin('vendors', 'work_costs.vendor_id', '=', 'vendors.id')
            ->whereIn('work_costs.work_id', $workIds)
            ->select(DB::raw('COALESCE(vendors.name, "業者なし") as vendor_name'))
            ->selectRaw('vendors.id as vendor_id')
            ->selectRaw('SUM(work_costs.amount) as total')
            ->selectRaw('COUNT(DISTINCT work_costs.work_id) as works_count')
            ->groupBy('vendors.id', 'vendors.name')
            ->orderByDesc('total')
            ->get();

        $dateColumn = DB::connection()->getDriverName() === 'mysql'
            ? 'DATE_FORMAT(COALESCE(work_costs.occurred_at, work_costs.created_at), "%Y-%m")'
            : 'strftime("%Y-%m", COALESCE(work_costs.occurred_at, work_costs.created_at))';

        $vendorMonthly = WorkCost::query()
            ->leftJoin('vendors', 'work_costs.vendor_id', '=', 'vendors.id')
            ->whereIn('work_costs.work_id', $workIds)
            ->selectRaw("{$dateColumn} as month")
            ->selectRaw('COALESCE(vendors.name, "業者なし") as vendor_name')
            ->selectRaw('SUM(work_costs.amount) as total')
            ->groupBy('month', 'vendors.id', 'vendors.name')
            ->orderBy('month')
            ->orderByDesc('total')
            ->get()
            ->groupBy('month');

        $categoryVendor = WorkCost::query()
            ->leftJoin('vendors', 'work_costs.vendor_id', '=', 'vendors.id')
            ->join('work_cost_categories', 'work_costs.work_cost_category_id', '=', 'work_cost_categories.id')
            ->whereIn('work_costs.work_id', $workIds)
            ->where('work_cost_categories.is_active', true)
            ->select('work_cost_categories.name as category_name')
            ->selectRaw('COALESCE(vendors.name, "業者なし") as vendor_name')
            ->selectRaw('SUM(work_costs.amount) as total')
            ->groupBy('work_cost_categories.id', 'work_cost_categories.name', 'vendors.id', 'vendors.name')
            ->orderBy('work_cost_categories.sort_order')
            ->orderByDesc('total')
            ->get();

        return Inertia::render('Analysis/Vendors', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'preset' => $request->query('preset', 'month'),
            'vendorCosts' => $vendorCosts,
            'vendorMonthly' => $vendorMonthly->map(fn ($items) => $items->toArray())->all(),
            'categoryVendor' => $categoryVendor,
        ]);
    }

    /**
     * 修理傾向分析
     */
    public function repairTrends(Request $request)
    {
        [$from, $to] = $this->getDateRange($request);

        $workIds = Work::query()
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween(DB::raw('COALESCE(occurred_at, created_at)'), [$from, $to]);
            })
            ->pluck('id');

        $wcIds = WorkContent::query()->whereIn('work_id', $workIds)->pluck('id');

        $repairTypeCounts = DB::table('work_content_repair_type')
            ->join('repair_types', 'work_content_repair_type.repair_type_id', '=', 'repair_types.id')
            ->whereIn('work_content_repair_type.work_content_id', $wcIds)
            ->where('repair_types.is_active', true)
            ->select('repair_types.id', 'repair_types.name')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('repair_types.id', 'repair_types.name')
            ->orderByDesc('count')
            ->get();

        $tagCounts = DB::table('work_content_work_content_tag')
            ->join('work_content_tags', 'work_content_work_content_tag.work_content_tag_id', '=', 'work_content_tags.id')
            ->whereIn('work_content_work_content_tag.work_content_id', $wcIds)
            ->where('work_content_tags.is_active', true)
            ->select('work_content_tags.id', 'work_content_tags.name')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('work_content_tags.id', 'work_content_tags.name')
            ->orderByDesc('count')
            ->get();

        $repairEquipment = DB::table('work_content_repair_type')
            ->join('work_contents', 'work_content_repair_type.work_content_id', '=', 'work_contents.id')
            ->join('works', 'work_contents.work_id', '=', 'works.id')
            ->join('work_equipment', 'works.id', '=', 'work_equipment.work_id')
            ->join('equipments', 'work_equipment.equipment_id', '=', 'equipments.id')
            ->join('repair_types', 'work_content_repair_type.repair_type_id', '=', 'repair_types.id')
            ->whereIn('work_contents.work_id', $workIds)
            ->where('repair_types.is_active', true)
            ->select('repair_types.name as repair_name', 'equipments.name as equipment_name')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('repair_types.id', 'repair_types.name', 'equipments.id', 'equipments.name')
            ->orderByDesc('count')
            ->limit(30)
            ->get();

        $dateColumn = DB::connection()->getDriverName() === 'mysql'
            ? 'DATE_FORMAT(COALESCE(works.occurred_at, works.created_at), "%Y-%m")'
            : 'strftime("%Y-%m", COALESCE(works.occurred_at, works.created_at))';

        $repairMonthly = DB::table('work_content_repair_type')
            ->join('work_contents', 'work_content_repair_type.work_content_id', '=', 'work_contents.id')
            ->join('works', 'work_contents.work_id', '=', 'works.id')
            ->join('repair_types', 'work_content_repair_type.repair_type_id', '=', 'repair_types.id')
            ->whereIn('work_contents.work_id', $workIds)
            ->where('repair_types.is_active', true)
            ->selectRaw("{$dateColumn} as month")
            ->selectRaw('repair_types.name as repair_name')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('month', 'repair_types.id', 'repair_types.name')
            ->orderBy('month')
            ->orderBy('repair_types.sort_order')
            ->get()
            ->groupBy('month');

        $purposeRepair = DB::table('work_content_repair_type')
            ->join('work_contents', 'work_content_repair_type.work_content_id', '=', 'work_contents.id')
            ->join('work_work_purpose', 'work_contents.work_id', '=', 'work_work_purpose.work_id')
            ->join('work_purposes', 'work_work_purpose.work_purpose_id', '=', 'work_purposes.id')
            ->join('repair_types', 'work_content_repair_type.repair_type_id', '=', 'repair_types.id')
            ->whereIn('work_contents.work_id', $workIds)
            ->where('repair_types.is_active', true)
            ->where('work_purposes.is_active', true)
            ->select('work_purposes.name as purpose_name', 'repair_types.name as repair_name')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('work_purposes.id', 'work_purposes.name', 'repair_types.id', 'repair_types.name')
            ->orderBy('work_purposes.sort_order')
            ->orderByDesc('count')
            ->limit(30)
            ->get();

        return Inertia::render('Analysis/RepairTrends', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'preset' => $request->query('preset', 'month'),
            'repairTypeCounts' => $repairTypeCounts,
            'tagCounts' => $tagCounts,
            'repairEquipment' => $repairEquipment,
            'repairMonthly' => $repairMonthly->map(fn ($items) => $items->toArray())->all(),
            'purposeRepair' => $purposeRepair,
        ]);
    }
}
