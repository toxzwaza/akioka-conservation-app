<?php

namespace App\Http\Controllers;

use App\Models\AttachmentType;
use App\Models\Equipment;
use App\Models\Part;
use App\Models\User;
use App\Models\Work;
use App\Models\WorkActivity;
use App\Models\WorkActivityType;
use App\Models\WorkAttachment;
use App\Models\WorkContent;
use App\Models\WorkContentTag;
use App\Models\WorkCost;
use App\Services\PartDisplayNameService;
use App\Services\StockAdditionService;
use App\Services\StockDeductionService;
use App\Models\WorkCostCategory;
use App\Models\WorkPriority;
use App\Models\WorkPurpose;
use App\Models\RepairType;
use App\Models\Vendor;
use App\Models\WorkStatus;
use App\Models\WorkUsedPart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class WorkController extends Controller
{
    /**
     * 作業一覧
     */
    public function index(Request $request)
    {
        $query = Work::with([
            'equipments:id,name,parent_id',
            'equipments.parent' => fn ($q) => $q->select('id', 'name', 'parent_id'),
            'workStatus' => fn ($q) => $q->select('id', 'name', 'color'),
            'workPriority' => fn ($q) => $q->select('id', 'name', 'color'),
            'workPurposes' => fn ($q) => $q->select('work_purposes.id', 'name', 'color'),
            'assignedUser' => fn ($q) => $q->select('id', 'name', 'color'),
        ]);

        if ($request->filled('equipment_id')) {
            $query->whereHas('equipments', fn ($q) => $q->where('equipments.id', $request->equipment_id));
        } elseif ($request->filled('parent_equipment_id')) {
            // 親設備のみ選択時: その親を親に持つ設備（直接の子）を持つ作業をすべて表示
            $query->whereHas('equipments', fn ($q) => $q->where('equipments.parent_id', $request->parent_equipment_id));
        }
        if ($request->filled('work_status_id')) {
            $query->where('work_status_id', $request->work_status_id);
        }
        if ($request->filled('work_purpose_id')) {
            $query->whereHas('workPurposes', fn ($q) => $q->where('work_purposes.id', $request->work_purpose_id));
        }
        if ($request->filled('assigned_user_id')) {
            $query->where('assigned_user_id', $request->assigned_user_id);
        }
        if ($request->filled('occurred_from')) {
            $query->whereDate('occurred_at', '>=', $request->occurred_from);
        }
        if ($request->filled('occurred_to')) {
            $query->whereDate('occurred_at', '<=', $request->occurred_to);
        }

        $sortKey = $request->get('sort_key', 'occurred_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $allowedSortKeys = ['id', 'title', 'occurred_at', 'work_status_id', 'work_priority_id', 'assigned_user_id'];
        if (in_array($sortKey, $allowedSortKeys, true)) {
            $query->orderBy($sortKey, $sortOrder === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderByDesc('occurred_at');
            $sortKey = 'occurred_at';
            $sortOrder = 'desc';
        }

        $works = $query->paginate(15)->withQueryString();

        return Inertia::render('Works/Index', [
            'works' => $works,
            'parentEquipmentOptions' => self::cachedEquipmentRootOptions(),
            'equipmentChildrenByParentId' => self::buildEquipmentChildrenByParentId(),
            'workStatuses' => self::cachedWorkStatuses(),
            'workPurposes' => self::cachedWorkPurposes(),
            'users' => self::getUsersForSelect(),
            'sort_key' => $sortKey,
            'sort_order' => $sortOrder,
            'filters' => [
                'parent_equipment_id' => $request->get('parent_equipment_id', ''),
                'equipment_id' => $request->get('equipment_id', ''),
                'work_status_id' => $request->get('work_status_id', ''),
                'work_purpose_id' => $request->get('work_purpose_id', ''),
                'assigned_user_id' => $request->get('assigned_user_id', ''),
                'occurred_from' => $request->get('occurred_from', ''),
                'occurred_to' => $request->get('occurred_to', ''),
            ],
        ]);
    }

    /**
     * 作業詳細
     * 部品一覧は渡さず、使用部品追加は searchParts で都度取得。表示中の使用部品の display_name のみ解決する。
     */
    public function show(Work $work, PartDisplayNameService $displayNameService)
    {
        $work->load([
            'equipments' => fn ($q) => $q->with('parent.parent.parent.parent.parent')->orderByPivot('sort_order'),
            'workStatus' => fn ($q) => $q->select('id', 'name', 'color'),
            'workPriority' => fn ($q) => $q->select('id', 'name', 'color'),
            'workPurposes' => fn ($q) => $q->select('work_purposes.id', 'name', 'color'),
            'assignedUser' => fn ($q) => $q->select('id', 'name', 'color'),
            'additionalUsers' => fn ($q) => $q->select('users.id', 'name', 'color')->orderByPivot('sort_order'),
            'summaryDocuments',
            'workContents' => fn ($q) => $q->withoutGlobalScope('order')->with([
                'workContentTags' => fn ($q) => $q->select('work_content_tags.id', 'name', 'color'),
                'repairTypes' => fn ($q) => $q->select('repair_types.id', 'name', 'color'),
                'workAttachments' => fn ($q) => $q->orderBy('id'),
            ])->orderBy('display_order')->orderBy('id'),
            'workUsedParts' => fn ($q) => $q->with('part:id,part_no,name,external_id')->orderBy('id'),
            'workCosts' => fn ($q) => $q->with([
                'workCostCategory' => fn ($q) => $q->select('id', 'name', 'color'),
                'vendor' => fn ($q) => $q->select('id', 'name'),
            ])->orderBy('id'),
            'workActivities' => fn ($q) => $q->with([
                'user' => fn ($q) => $q->select('id', 'name', 'color'),
                'workActivityType' => fn ($q) => $q->select('id', 'name', 'color'),
            ])->orderByDesc('created_at'),
        ]);

        // 表示中の使用部品の part にのみ display_name を付与（全部品は読まない）
        $partIds = $work->workUsedParts->pluck('part_id')->filter()->unique()->values()->all();
        if ($partIds !== []) {
            $partsSubset = Part::whereIn('id', $partIds)->orderBy('part_no')->get();
            $partsWithDisplay = $displayNameService->resolveDisplayNames($partsSubset, auth()->user());
            $partDisplayMap = $partsWithDisplay->keyBy('id');
            foreach ($work->workUsedParts as $wup) {
                if ($wup->part) {
                    $dn = $partDisplayMap[$wup->part->id]['display_name'] ?? $wup->part->name ?? '—';
                    $wup->part->setAttribute('display_name', $dn);
                }
            }
        }

        // workUsedParts に単価を付与（external_id あり部品は API から取得）
        $externalIds = $work->workUsedParts->map(fn ($wup) => $wup->part?->external_id)->filter()->unique()->values()->all();
        $priceMap = $this->fetchStockPrices($externalIds);
        foreach ($work->workUsedParts as $wup) {
            if ($wup->part?->external_id) {
                $price = $priceMap[(string) $wup->part->external_id] ?? null;
                $wup->setAttribute('unit_price', $price);
            } else {
                $wup->setAttribute('unit_price', null);
            }
        }

        $equipmentIds = $work->equipments->pluck('id')->all();
        $relatedWorks = $equipmentIds === []
            ? collect([])
            : Work::where('id', '!=', $work->id)
                ->whereHas('equipments', fn ($q) => $q->whereIn('equipments.id', $equipmentIds))
                ->with([
                    'equipments' => fn ($q) => $q->with('parent')->orderByPivot('sort_order'),
                    'workStatus' => fn ($q) => $q->select('id', 'name', 'color'),
                    'workPriority' => fn ($q) => $q->select('id', 'name', 'color'),
                ])
                ->orderByDesc('occurred_at')
                ->limit(10)
                ->get();

        return Inertia::render('Works/Show', [
            'work' => $work,
            'workContentTags' => self::cachedWorkContentTags(),
            'repairTypes' => self::cachedRepairTypes(),
            'parts' => [],
            'workCostCategories' => self::cachedWorkCostCategories(),
            'vendors' => self::cachedVendors(),
            'workStatuses' => self::cachedWorkStatuses(),
            'workPriorities' => self::cachedWorkPriorities(),
            'workPurposes' => self::cachedWorkPurposes(),
            'parentEquipmentOptions' => self::cachedEquipmentRootOptions(),
            'equipmentChildrenByParentId' => self::buildEquipmentChildrenByParentId(),
            'users' => self::getUsersForSelect(),
            'relatedWorks' => $relatedWorks,
        ]);
    }

    /**
     * 担当者選択用ユーザー取得（external_id ありは API の氏名を付与、キャッシュで高速化）
     *
     * @return \Illuminate\Support\Collection<int, object{id: int, name: string, color: string|null, api_name?: string}>
     */
    private static function getUsersForSelect()
    {
        $users = User::orderBy('sort_order')->orderBy('id')->get(['id', 'name', 'color', 'external_id']);
        $baseUrl = rtrim(config('services.conservation_api.base_url', ''), '/');

        foreach ($users as $user) {
            if ($user->external_id && $baseUrl) {
                $cacheKey = 'user_api_name_' . $user->id;
                $user->api_name = Cache::remember($cacheKey, 600, function () use ($baseUrl, $user) {
                    try {
                        $response = Http::timeout(5)->get($baseUrl . '/users/' . $user->external_id);
                        if ($response->successful()) {
                            $api = $response->json();
                            return $api['name'] ?? null;
                        }
                    } catch (\Throwable $e) {
                        // API 取得失敗時は null（DB の name をそのまま使用）
                    }
                    return null;
                });
            }
        }

        return $users;
    }

    /**
     * マスタキャッシュ（TTL 5分）。
     * マスタ更新時は次のキーを無効化すること: work_statuses_active, work_priorities_active, work_purposes_active,
     * work_content_tags_active, repair_types_active, work_cost_categories_active, vendors_options,
     * equipment_root_options, equipment_children_by_parent_id
     */
    private static function cachedWorkStatuses()
    {
        return Cache::remember('work_statuses_active', 300, fn () => WorkStatus::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'color']));
    }

    private static function cachedWorkPriorities()
    {
        return Cache::remember('work_priorities_active', 300, fn () => WorkPriority::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'color']));
    }

    private static function cachedWorkPurposes()
    {
        return Cache::remember('work_purposes_active', 300, fn () => WorkPurpose::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'color']));
    }

    private static function cachedWorkContentTags()
    {
        return Cache::remember('work_content_tags_active', 300, fn () => WorkContentTag::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'color']));
    }

    private static function cachedRepairTypes()
    {
        return Cache::remember('repair_types_active', 300, fn () => RepairType::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'color']));
    }

    private static function cachedWorkCostCategories()
    {
        return Cache::remember('work_cost_categories_active', 300, fn () => WorkCostCategory::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'color']));
    }

    private static function cachedVendors()
    {
        return Cache::remember('vendors_options', 300, fn () => Vendor::getOptionsForSelect());
    }

    private static function cachedEquipmentRootOptions(): array
    {
        return Cache::remember('equipment_root_options', 300, fn () => Equipment::getRootOptionsForSelect());
    }

    /**
     * 作業登録フォームを表示
     * 部品は検索API（searchParts）で都度取得するため初期表示では渡さない
     */
    public function create()
    {
        return Inertia::render('Works/Create', [
            'parentEquipmentOptions' => self::cachedEquipmentRootOptions(),
            'equipmentChildrenByParentId' => self::buildEquipmentChildrenByParentId(),
            'workStatuses' => self::cachedWorkStatuses(),
            'workPriorities' => self::cachedWorkPriorities(),
            'workPurposes' => self::cachedWorkPurposes(),
            'workContentTags' => self::cachedWorkContentTags(),
            'repairTypes' => self::cachedRepairTypes(),
            'parts' => [],
            'workCostCategories' => self::cachedWorkCostCategories(),
            'vendors' => self::cachedVendors(),
            'users' => self::getUsersForSelect(),
        ]);
    }

    /**
     * 各ルート設備IDをキーに、子設備オプションをマップする
     * 設備を1回だけ取得してメモリ上でツリーを組み立て（N+1 回避）
     *
     * @return array<string, array<int, array{id: int, name: string, display_label: string, depth: int}>>
     */
    private static function buildEquipmentChildrenByParentId(): array
    {
        return Cache::remember('equipment_children_by_parent_id', 300, function () {
            return Equipment::buildTreeMapForChildrenByParentId();
        });
    }

    /**
     * 使用部品登録用：部品を検索（部品名・型番で部分一致）
     * ローカル: name, part_no。ユーザー独自表示名(display_name, display_s_name)。
     * external_id あり: API の stocks.name, stocks.s_name でも検索。
     */
    public function searchParts(Request $request, PartDisplayNameService $displayNameService)
    {
        $q = $request->input('q', '');
        $q = is_string($q) ? trim($q) : '';

        $partIds = collect();

        if ($q !== '') {
            $like = '%' . $q . '%';
            $userId = auth()->id();

            // ローカル: 部品名・型番(part_no) または ユーザー独自表示名
            $localIds = Part::query()
                ->where(function ($query) use ($like, $userId) {
                    $query->where('name', 'like', $like)
                        ->orWhere('part_no', 'like', $like)
                        ->orWhereHas('userDisplayNames', function ($q) use ($like, $userId) {
                            $q->where('user_id', $userId)
                                ->where(function ($q2) use ($like) {
                                    $q2->where('display_name', 'like', $like)
                                        ->orWhere('display_s_name', 'like', $like);
                                });
                        });
                })
                ->pluck('id');

            $partIds = $partIds->merge($localIds);

            // external_id あり: API の name / s_name で検索
            $partsWithExternal = Part::whereNotNull('external_id')->get(['id', 'external_id']);
            if ($partsWithExternal->isNotEmpty()) {
                $externalIds = $partsWithExternal->pluck('external_id')->unique()->values()->all();
                $apiMap = $this->fetchStocksForSearch($externalIds);
                $qLower = mb_strtolower($q);
                $matchingExternalIds = [];
                foreach ($apiMap as $extId => $row) {
                    $name = $row['name'] ?? '';
                    $sName = $row['s_name'] ?? '';
                    if ($name !== '' && mb_strpos(mb_strtolower($name), $qLower) !== false) {
                        $matchingExternalIds[] = $extId;
                    } elseif ($sName !== '' && mb_strpos(mb_strtolower((string) $sName), $qLower) !== false) {
                        $matchingExternalIds[] = $extId;
                    }
                }
                if ($matchingExternalIds !== []) {
                    $apiMatchedIds = Part::whereIn('external_id', $matchingExternalIds)->pluck('id');
                    $partIds = $partIds->merge($apiMatchedIds);
                }
            }

            $partIds = $partIds->unique()->values();
            $parts = Part::whereIn('id', $partIds)->orderBy('part_no')->limit(50)->get();
        } else {
            $parts = Part::query()->orderBy('part_no')->limit(50)->get();
        }

        $items = $displayNameService->resolveDisplayNames($parts, auth()->user())->values()->all();

        // 型番表示用・在庫情報の付与
        $externalIds = collect($items)->pluck('external_id')->filter()->unique()->values()->all();
        $stocksWithStorages = $this->fetchStocksWithStorages($externalIds);

        foreach ($items as &$item) {
            $item['s_name'] = $item['user_display_s_name'] ?? $item['api_s_name'] ?? $item['part_no'] ?? '—';
            if (! empty($item['external_id'])) {
                $stock = $stocksWithStorages[$item['external_id']] ?? null;
                $item['stock_storages'] = $stock['stock_storages'] ?? [];
                $item['total_quantity'] = $stock['total_quantity'] ?? null;
            } else {
                $item['stock_storages'] = [];
                $item['total_quantity'] = null;
            }
        }

        return response()->json(['items' => $items]);
    }

    /**
     * 部品検索用: API から stocks の name / s_name を一括取得
     *
     * @param  array<int|string>  $externalIds
     * @return array<string, array{name: string, s_name: string|null}>
     */
    private function fetchStocksForSearch(array $externalIds): array
    {
        if ($externalIds === []) {
            return [];
        }
        $ids = implode(',', array_map('strval', $externalIds));
        $baseUrl = rtrim(config('services.conservation_api.base_url'), '/');
        $url = $baseUrl . '/stocks?ids=' . $ids . '&per_page=100';
        try {
            $response = Http::timeout(10)->get($url);
            if (! $response->successful()) {
                return [];
            }
            $data = $response->json();
            $items = $data['data'] ?? [];
            if (! is_array($items)) {
                return [];
            }
            $map = [];
            foreach ($items as $row) {
                $id = $row['id'] ?? null;
                if ($id !== null) {
                    $map[(string) $id] = [
                        'name' => $row['name'] ?? '',
                        's_name' => $row['s_name'] ?? null,
                    ];
                }
            }
            return $map;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * 部品の単価取得（API 連携部品の external_id から価格を取得）
     */
    public function getPartPrice(Part $part)
    {
        if (! $part->external_id) {
            return response()->json(['price' => null]);
        }
        $baseUrl = rtrim(config('services.conservation_api.base_url', ''), '/');
        $url = $baseUrl . '/stocks?ids=' . $part->external_id . '&per_page=1';
        try {
            $response = Http::timeout(5)->get($url);
            if (! $response->successful()) {
                return response()->json(['price' => null]);
            }
            $data = $response->json();
            $items = $data['data'] ?? [];
            $row = is_array($items) && count($items) > 0 ? $items[0] : null;
            $price = $row && isset($row['price']) ? (float) $row['price'] : null;
            return response()->json(['price' => $price]);
        } catch (\Throwable $e) {
            return response()->json(['price' => null]);
        }
    }

    /**
     * API から stocks の単価を一括取得
     *
     * @param  array<int|string>  $externalIds
     * @return array<string, float|null>
     */
    private function fetchStockPrices(array $externalIds): array
    {
        if ($externalIds === []) {
            return [];
        }
        $ids = implode(',', array_map('strval', $externalIds));
        $baseUrl = rtrim(config('services.conservation_api.base_url'), '/');
        $url = $baseUrl . '/stocks?ids=' . $ids . '&per_page=100';
        try {
            $response = Http::timeout(10)->get($url);
            if (! $response->successful()) {
                return [];
            }
            $data = $response->json();
            $rows = $data['data'] ?? [];
            if (! is_array($rows)) {
                return [];
            }
            $map = [];
            foreach ($rows as $row) {
                $id = $row['id'] ?? null;
                if ($id === null) {
                    continue;
                }
                $price = isset($row['price']) ? (float) $row['price'] : null;
                $map[(string) $id] = $price;
            }
            return $map;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * 部品検索用: API から stocks の stock_storages（格納先・アドレス・数量）を一括取得
     *
     * @param  array<int|string>  $externalIds
     * @return array<string, array{stock_storages: array<int, array{location_name: string, address: string, quantity: int}>, total_quantity: int}>
     */
    private function fetchStocksWithStorages(array $externalIds): array
    {
        if ($externalIds === []) {
            return [];
        }
        $ids = implode(',', array_map('strval', $externalIds));
        $baseUrl = rtrim(config('services.conservation_api.base_url'), '/');
        $url = $baseUrl . '/stocks?ids=' . $ids . '&per_page=100';
        try {
            $response = Http::timeout(10)->get($url);
            if (! $response->successful()) {
                return [];
            }
            $data = $response->json();
            $rows = $data['data'] ?? [];
            if (! is_array($rows)) {
                return [];
            }
            $map = [];
            foreach ($rows as $row) {
                $id = $row['id'] ?? null;
                if ($id === null) {
                    continue;
                }
                $storages = $row['stock_storages'] ?? [];
                $list = [];
                $total = 0;
                foreach (is_array($storages) ? $storages : [] as $ss) {
                    $addr = $ss['storage_address'] ?? [];
                    $loc = $addr['location'] ?? [];
                    $qty = isset($ss['quantity']) ? (int) $ss['quantity'] : 0;
                    $list[] = [
                        'location_name' => $loc['name'] ?? '',
                        'address' => $addr['address'] ?? '',
                        'quantity' => $qty,
                    ];
                    $total += $qty;
                }
                $map[(string) $id] = [
                    'stock_storages' => $list,
                    'total_quantity' => $total,
                ];
            }
            return $map;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * 作業と作業内容を新規登録
     */
    public function store(Request $request)
    {
        $input = $request->all();
        foreach (['occurred_at', 'completed_at', 'production_stop_minutes'] as $key) {
            if (isset($input[$key]) && $input[$key] === '') {
                $input[$key] = null;
            }
        }

        $workRules = [
            'title' => ['required', 'string', 'max:255'],
            'equipments' => ['required', 'array', 'min:1'],
            'equipments.*.parent_id' => ['required', 'exists:equipments,id'],
            'equipments.*.equipment_id' => ['required', 'exists:equipments,id'],
            'work_status_id' => ['required', 'exists:work_statuses,id'],
            'work_priority_id' => ['nullable', 'exists:work_priorities,id'],
            'work_purpose_ids' => ['required', 'array', 'min:1'],
            'work_purpose_ids.*' => ['exists:work_purposes,id'],
            'assigned_user_id' => ['required', 'exists:users,id'],
            'additional_user_ids' => ['nullable', 'array'],
            'additional_user_ids.*.user_id' => ['nullable', 'exists:users,id'],
            'production_stop_minutes' => ['nullable', 'integer', 'min:0'],
            'occurred_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],
            'work_contents' => ['nullable', 'array'],
            'work_contents.*.work_content_tag_ids' => ['nullable', 'array'],
            'work_contents.*.work_content_tag_ids.*' => ['exists:work_content_tags,id'],
            'work_contents.*.repair_type_ids' => ['nullable', 'array'],
            'work_contents.*.repair_type_ids.*' => ['exists:repair_types,id'],
            'work_contents.*.content' => ['nullable', 'string'],
            'work_contents.*.started_at' => ['nullable', 'date'],
            'work_contents.*.ended_at' => ['nullable', 'date'],
            'work_contents.*.images' => ['nullable', 'array'],
            'work_contents.*.images.*' => ['nullable', 'file', 'image', 'max:10240'], // 10MB per image
            'work_used_parts' => ['nullable', 'array'],
            'work_used_parts.*.part_id' => ['nullable', 'exists:parts,id'],
            'work_used_parts.*.qty' => ['nullable', 'integer', 'min:1'],
            'work_used_parts.*.note' => ['nullable', 'string'],
            'work_costs' => ['nullable', 'array'],
            'work_costs.*.work_cost_category_id' => ['nullable', 'exists:work_cost_categories,id'],
            'work_costs.*.name' => ['nullable', 'string', 'max:255'],
            'work_costs.*.amount' => ['nullable', 'integer', 'min:0'],
            'work_costs.*.vendor_id' => ['nullable', 'exists:vendors,id'],
            'work_costs.*.vendor_name' => ['nullable', 'string', 'max:255'],
            'work_costs.*.occurred_at' => ['nullable', 'date'],
            'work_costs.*.note' => ['nullable', 'string'],
            'work_costs.*.file' => ['nullable', 'file', 'max:10240'], // 10MB
            'summary_documents' => ['nullable', 'array'],
            'summary_documents.*.display_name' => ['nullable', 'string', 'max:255'],
            'summary_documents.*.file' => ['nullable', 'file', 'mimes:pdf,xlsx,xls,docx,doc,jpg,jpeg,png,gif,webp', 'max:20480'], // 20MB
            'comments' => ['nullable', 'array'],
            'comments.*.message' => ['nullable', 'string', 'max:4000'],
        ];

        $validated = validator($input, $workRules)->validate();

        $workData = collect($validated)->except([
            'work_contents', 'work_used_parts', 'work_costs', 'summary_documents',
            'equipments', 'work_purpose_ids', 'additional_user_ids',
        ])->filter(fn ($v, $k) => in_array($k, ['title', 'work_status_id', 'work_priority_id', 'assigned_user_id', 'production_stop_minutes', 'occurred_at', 'completed_at', 'note'], true))->all();
        $work = Work::create($workData);

        foreach ($validated['equipments'] ?? [] as $sort => $row) {
            $eqId = $row['equipment_id'] ?? null;
            if ($eqId) {
                $work->equipments()->attach($eqId, ['sort_order' => $sort]);
            }
        }
        $purposeIds = array_values(array_filter(array_map('intval', $validated['work_purpose_ids'] ?? [])));
        if ($purposeIds !== []) {
            $work->workPurposes()->sync($purposeIds);
        }
        $additionalIds = [];
        foreach ($validated['additional_user_ids'] ?? [] as $sort => $row) {
            $uid = $row['user_id'] ?? null;
            if ($uid) {
                $additionalIds[$uid] = ['sort_order' => $sort];
            }
        }
        $work->additionalUsers()->sync($additionalIds);

        $photoAttachmentType = AttachmentType::where('name', '写真')->first();
        $photoTypeId = $photoAttachmentType?->id;

        $workContents = $request->input('work_contents', []);
        foreach ($workContents as $index => $item) {
            $tagIds = $item['work_content_tag_ids'] ?? [];
            $repairIds = $item['repair_type_ids'] ?? [];
            $content = trim((string) ($item['content'] ?? ''));
            $tagIds = array_filter(array_map('intval', is_array($tagIds) ? $tagIds : []));
            $repairIds = array_filter(array_map('intval', is_array($repairIds) ? $repairIds : []));

            if ($content !== '' && (count($tagIds) > 0 || count($repairIds) > 0)) {
                $wc = WorkContent::withoutGlobalScope('order')->create([
                    'work_id' => $work->id,
                    'display_order' => $index,
                    'content' => $content,
                    'started_at' => isset($item['started_at']) && $item['started_at'] !== '' ? $item['started_at'] : null,
                    'ended_at' => isset($item['ended_at']) && $item['ended_at'] !== '' ? $item['ended_at'] : null,
                ]);
                $wc->workContentTags()->sync($tagIds);
                $wc->repairTypes()->sync($repairIds);

                // 作業内容の添付画像（複数）
                if ($photoTypeId) {
                    $files = $request->file("work_contents.{$index}.images");
                    $files = is_array($files) ? $files : ($files ? [$files] : []);
                    foreach ($files as $file) {
                        if ($file && $file->isValid()) {
                            $dir = 'work_attachments/' . $work->id;
                            $name = Str::uuid() . '_' . $file->getClientOriginalName();
                            $path = $file->storeAs($dir, $name, 'public');
                            WorkAttachment::create([
                                'work_id' => $work->id,
                                'work_content_id' => $wc->id,
                                'attachment_type_id' => $photoTypeId,
                                'path' => $path,
                                'original_name' => $file->getClientOriginalName(),
                                'uploaded_by' => auth()->id(),
                            ]);
                        }
                    }
                }
            }
        }

        // 作業添付資料（PDF, Excel, Word, 画像）
        $docType = AttachmentType::where('name', 'マニュアル')->first();
        if ($docType) {
            $summaryDocs = $request->input('summary_documents', []);
            $summaryDocs = is_array($summaryDocs) ? $summaryDocs : [];
            foreach ($summaryDocs as $idx => $item) {
                $file = $request->file("summary_documents.{$idx}.file");
                if ($file && $file->isValid()) {
                    $dir = 'work_attachments/' . $work->id;
                    $name = Str::uuid() . '_' . $file->getClientOriginalName();
                    $path = $file->storeAs($dir, $name, 'public');
                    WorkAttachment::create([
                        'work_id' => $work->id,
                        'work_content_id' => null,
                        'attachment_type_id' => $docType->id,
                        'path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'display_name' => isset($item['display_name']) && trim((string) $item['display_name']) !== '' ? trim($item['display_name']) : $file->getClientOriginalName(),
                        'uploaded_by' => auth()->id(),
                    ]);
                }
            }
        }

        $usedParts = $request->input('work_used_parts', []);
        $deductionItems = [];
        foreach ($usedParts as $item) {
            $partId = $item['part_id'] ?? null;
            $qty = isset($item['qty']) && $item['qty'] !== '' ? (int) $item['qty'] : null;
            if ($partId && $qty !== null && $qty >= 1) {
                $part = Part::find($partId);
                WorkUsedPart::create([
                    'work_id' => $work->id,
                    'part_id' => $partId,
                    'qty' => $qty,
                    'note' => isset($item['note']) && $item['note'] !== '' ? $item['note'] : null,
                ]);
                if ($part && $part->external_id) {
                    $deductionItems[] = [
                        'part_id' => (int) $partId,
                        'external_id' => $part->external_id,
                        'qty' => $qty,
                    ];
                }
            }
        }

        $costs = $request->input('work_costs', []);
        foreach ($costs as $index => $item) {
            $categoryId = $item['work_cost_category_id'] ?? null;
            $amountRaw = $item['amount'] ?? '';
            $amount = $amountRaw !== '' && $amountRaw !== null ? (int) $amountRaw : null;
            if ($categoryId && $amount !== null && $amount >= 0) {
                $vendorId = isset($item['vendor_id']) && $item['vendor_id'] !== '' ? $item['vendor_id'] : null;
                $vendorName = isset($item['vendor_name']) && $item['vendor_name'] !== '' ? trim($item['vendor_name']) : null;
                if (! $vendorId && $vendorName) {
                    $vendor = Vendor::firstOrCreate(
                        ['name' => $vendorName],
                        ['is_active' => true, 'sort_order' => 0]
                    );
                    $vendorId = $vendor->id;
                    $vendorName = null;
                    Cache::forget('vendors_options');
                }
                $filePath = null;
                $file = $request->file("work_costs.{$index}.file");
                if ($file && $file->isValid()) {
                    $dir = 'work_costs/' . $work->id;
                    $name = Str::uuid() . '_' . $file->getClientOriginalName();
                    $filePath = $file->storeAs($dir, $name, 'local');
                }
                WorkCost::create([
                    'work_id' => $work->id,
                    'work_cost_category_id' => $categoryId,
                    'name' => isset($item['name']) && $item['name'] !== '' ? $item['name'] : null,
                    'amount' => $amount,
                    'vendor_id' => $vendorId,
                    'vendor_name' => $vendorName,
                    'occurred_at' => isset($item['occurred_at']) && $item['occurred_at'] !== '' ? $item['occurred_at'] : null,
                    'note' => isset($item['note']) && $item['note'] !== '' ? $item['note'] : null,
                    'file_path' => $filePath,
                ]);
            }
        }

        $contentCount = $work->workContents()->count();
        $usedPartsCount = $work->workUsedParts()->count();
        $costsCount = $work->workCosts()->count();
        $message = '作業を登録しました。（ID: ' . $work->id . '）';
        if ($contentCount > 0) {
            $message .= ' 作業内容' . $contentCount . '件';
        }
        if ($usedPartsCount > 0) {
            $message .= ' 使用部品' . $usedPartsCount . '件';
        }
        if ($costsCount > 0) {
            $message .= ' 費用' . $costsCount . '件';
        }
        if ($contentCount > 0 || $usedPartsCount > 0 || $costsCount > 0) {
            $message .= ' を登録しました。';
        }

        if ($deductionItems !== []) {
            $deduction = app(StockDeductionService::class)->subtractBatch($deductionItems);
            if (isset($deduction['message']) && $deduction['message'] !== '') {
                $message .= ' ' . $deduction['message'];
            }
        }

        $this->recordWorkActivity($work, '作成', $message);

        // 仮登録コメントを同時に登録
        $commentType = WorkActivityType::where('name', 'コメント')->first();
        if ($commentType) {
            foreach ($validated['comments'] ?? [] as $item) {
                $msg = isset($item['message']) ? trim((string) $item['message']) : '';
                if ($msg !== '') {
                    WorkActivity::create([
                        'work_id' => $work->id,
                        'user_id' => auth()->id(),
                        'work_activity_type_id' => $commentType->id,
                        'message' => $msg,
                    ]);
                }
            }
        }

        return redirect()->route('work.works.show', $work)->with('status', $message);
    }

    /**
     * 作業概要を更新（全項目編集可、変更履歴を記録）
     */
    public function update(Request $request, Work $work)
    {
        $input = $request->all();
        foreach (['occurred_at', 'completed_at', 'production_stop_minutes'] as $key) {
            if (isset($input[$key]) && $input[$key] === '') {
                $input[$key] = null;
            }
        }

        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'equipments' => ['required', 'array', 'min:1'],
            'equipments.*.parent_id' => ['required', 'exists:equipments,id'],
            'equipments.*.equipment_id' => ['required', 'exists:equipments,id'],
            'work_status_id' => ['required', 'exists:work_statuses,id'],
            'work_priority_id' => ['nullable', 'exists:work_priorities,id'],
            'work_purpose_ids' => ['required', 'array', 'min:1'],
            'work_purpose_ids.*' => ['exists:work_purposes,id'],
            'assigned_user_id' => ['required', 'exists:users,id'],
            'additional_user_ids' => ['nullable', 'array'],
            'additional_user_ids.*.user_id' => ['nullable', 'exists:users,id'],
            'production_stop_minutes' => ['nullable', 'integer', 'min:0'],
            'occurred_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:65535'],
        ];
        $validated = validator($input, $rules)->validate();

        foreach (['production_stop_minutes', 'note'] as $k) {
            if (isset($validated[$k]) && $validated[$k] === '') {
                $validated[$k] = null;
            }
        }
        foreach (['occurred_at', 'completed_at'] as $k) {
            if (isset($validated[$k]) && $validated[$k] === '') {
                $validated[$k] = null;
            }
        }

        $updateData = collect($validated)->only(['title', 'work_status_id', 'work_priority_id', 'assigned_user_id', 'production_stop_minutes', 'occurred_at', 'completed_at', 'note'])->all();
        $work->update($updateData);

        $work->equipments()->sync([]);
        foreach ($validated['equipments'] ?? [] as $sort => $row) {
            $eqId = $row['equipment_id'] ?? null;
            if ($eqId) {
                $work->equipments()->attach($eqId, ['sort_order' => $sort]);
            }
        }
        $purposeIds = array_values(array_filter(array_map('intval', $validated['work_purpose_ids'] ?? [])));
        $work->workPurposes()->sync($purposeIds);
        $additionalIds = [];
        foreach ($validated['additional_user_ids'] ?? [] as $sort => $row) {
            $uid = $row['user_id'] ?? null;
            if ($uid) {
                $additionalIds[$uid] = ['sort_order' => $sort];
            }
        }
        $work->additionalUsers()->sync($additionalIds);

        $this->recordWorkActivity($work, '更新', '作業概要を更新しました。');

        return redirect()->route('work.works.show', $work)->with('status', '作業を更新しました。');
    }

    /**
     * 作業を「完了」に更新し、完了日時をセット
     */
    public function complete(Request $request, Work $work)
    {
        $validated = $request->validate([
            'completed_at' => ['required', 'date'],
        ]);

        $completedStatus = WorkStatus::where('name', '完了')->first();
        if (! $completedStatus) {
            return redirect()->back()->withErrors(['work_status_id' => '「完了」ステータスが見つかりません。']);
        }

        $work->update([
            'work_status_id' => $completedStatus->id,
            'completed_at' => $validated['completed_at'],
        ]);

        $this->recordWorkActivity($work, '更新', 'ステータスを完了に更新しました。');

        return redirect()->route('work.works.show', $work)->with('status', '作業を完了に更新しました。');
    }

    /**
     * 作業内容の表示順を並び替え
     */
    public function reorderWorkContents(Request $request, Work $work)
    {
        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['required', 'integer', 'exists:work_contents,id'],
        ]);

        foreach ($validated['order'] as $sort => $id) {
            WorkContent::withoutGlobalScope('order')->where('id', $id)->where('work_id', $work->id)->update(['display_order' => $sort]);
        }

        return redirect()->route('work.works.show', $work)->with('status', '作業内容の並び順を更新しました。');
    }

    /**
     * 作業履歴を記録する
     */
    private function recordWorkActivity(Work $work, string $typeName, ?string $message = null, ?array $meta = null): void
    {
        $type = WorkActivityType::where('name', $typeName)->first();
        if (! $type) {
            return;
        }
        WorkActivity::create([
            'work_id' => $work->id,
            'user_id' => auth()->id(),
            'work_activity_type_id' => $type->id,
            'message' => $message,
            'meta' => $meta,
        ]);
    }

    /**
     * 作業内容を1件追加（複数タグ・複数修理内容・複数画像対応）
     */
    public function storeWorkContent(Request $request, Work $work)
    {
        $validated = $request->validate([
            'work_content_tag_ids' => ['nullable', 'array'],
            'work_content_tag_ids.*' => ['exists:work_content_tags,id'],
            'repair_type_ids' => ['nullable', 'array'],
            'repair_type_ids.*' => ['exists:repair_types,id'],
            'content' => ['required', 'string'],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date'],
            'images' => ['nullable', 'array'],
            'images.*' => ['nullable', 'file', 'image', 'max:10240'],
        ]);

        $tagIds = array_filter(array_map('intval', $validated['work_content_tag_ids'] ?? []));
        $repairIds = array_filter(array_map('intval', $validated['repair_type_ids'] ?? []));
        if (count($tagIds) === 0 && count($repairIds) === 0) {
            return redirect()->back()->withErrors(['work_content_tag_ids' => '作業タグを1つ以上選択してください。']);
        }

        $maxOrder = WorkContent::withoutGlobalScope('order')->where('work_id', $work->id)->max('display_order') ?? -1;
        $wc = WorkContent::withoutGlobalScope('order')->create([
            'work_id' => $work->id,
            'display_order' => $maxOrder + 1,
            'content' => $validated['content'],
            'started_at' => $validated['started_at'] ?? null,
            'ended_at' => $validated['ended_at'] ?? null,
        ]);
        $wc->workContentTags()->sync($tagIds);
        $wc->repairTypes()->sync($repairIds);

        $photoType = AttachmentType::where('name', '写真')->first();
        if ($photoType) {
            $files = $request->file('images') ?? [];
            foreach (is_array($files) ? $files : [] as $file) {
                if ($file && $file->isValid()) {
                    $dir = 'work_attachments/' . $work->id;
                    $name = Str::uuid() . '_' . $file->getClientOriginalName();
                    $path = $file->storeAs($dir, $name, 'public');
                    WorkAttachment::create([
                        'work_id' => $work->id,
                        'work_content_id' => $wc->id,
                        'attachment_type_id' => $photoType->id,
                        'path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'uploaded_by' => auth()->id(),
                    ]);
                }
            }
        }

        $this->recordWorkActivity($work, 'その他', '作業内容を追加しました。');
        return redirect()->route('work.works.show', $work)->with('status', '作業内容を追加しました。');
    }

    /**
     * 使用部品を1件追加（external_id ありの場合は在庫減算を実行）
     */
    public function storeWorkUsedPart(Request $request, Work $work, StockDeductionService $deductionService)
    {
        $validated = $request->validate([
            'part_id' => ['required', 'exists:parts,id'],
            'qty' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string'],
        ]);
        $validated['work_id'] = $work->id;
        $part = Part::find($validated['part_id']);
        WorkUsedPart::create($validated);

        $message = '使用部品を追加しました。';
        if ($part && $part->external_id) {
            $result = $deductionService->subtractBatch([
                ['part_id' => $part->id, 'external_id' => $part->external_id, 'qty' => (int) $validated['qty']],
            ]);
            if (isset($result['message']) && $result['message'] !== '') {
                $message .= ' ' . $result['message'];
            }
        }

        $this->recordWorkActivity($work, 'その他', '使用部品を追加しました。');

        return redirect()->route('work.works.show', $work)->with('status', $message);
    }

    /**
     * 使用部品を1件削除（external_id ありの場合は在庫加算を実行）
     */
    public function destroyWorkUsedPart(Work $work, WorkUsedPart $workUsedPart, StockAdditionService $additionService)
    {
        if ($workUsedPart->work_id !== $work->id) {
            abort(404);
        }

        $part = $workUsedPart->part;
        $qty = (int) $workUsedPart->qty;

        $message = '使用部品を削除しました。';
        if ($part && $part->external_id && $qty >= 1) {
            $result = $additionService->addBatch([
                ['part_id' => $part->id, 'external_id' => $part->external_id, 'qty' => $qty],
            ]);
            if (isset($result['message']) && $result['message'] !== '') {
                $message .= ' ' . $result['message'];
            }
        }

        $workUsedPart->delete();
        $this->recordWorkActivity($work, 'その他', '使用部品を削除しました。');

        return redirect()->route('work.works.show', $work)->with('status', $message);
    }

    /**
     * 費用を1件追加（添付ファイル可）
     */
    public function storeWorkCost(Request $request, Work $work)
    {
        $input = $request->all();
        if (isset($input['vendor_id']) && $input['vendor_id'] === '') {
            $input['vendor_id'] = null;
        }
        $validated = validator($input, [
            'work_cost_category_id' => ['required', 'exists:work_cost_categories,id'],
            'amount' => ['required', 'integer', 'min:0'],
            'name' => ['nullable', 'string', 'max:255'],
            'vendor_id' => ['nullable', 'exists:vendors,id'],
            'vendor_name' => ['nullable', 'string', 'max:255'],
            'occurred_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],
            'file' => ['nullable', 'file', 'max:10240'],
        ])->validate();
        $vendorId = $validated['vendor_id'] ?? null;
        $vendorName = isset($validated['vendor_name']) && trim($validated['vendor_name']) !== '' ? trim($validated['vendor_name']) : null;
        if (! $vendorId && $vendorName) {
            $vendor = Vendor::firstOrCreate(
                ['name' => $vendorName],
                ['is_active' => true, 'sort_order' => 0]
            );
            $vendorId = $vendor->id;
            $vendorName = null;
            Cache::forget('vendors_options');
        }
        $validated['vendor_id'] = $vendorId;
        $validated['vendor_name'] = $vendorName;
        $filePath = null;
        if ($request->hasFile('file') && $request->file('file')->isValid()) {
            $dir = 'work_costs/' . $work->id;
            $name = Str::uuid() . '_' . $request->file('file')->getClientOriginalName();
            $filePath = $request->file('file')->storeAs($dir, $name, 'local');
        }
        $validated['work_id'] = $work->id;
        $validated['file_path'] = $filePath;
        unset($validated['file']);
        WorkCost::create($validated);
        $this->recordWorkActivity($work, 'その他', '費用を追加しました。');
        return redirect()->route('work.works.show', $work)->with('status', '費用を追加しました。');
    }

    /**
     * 作業添付資料を追加（PDF, Excel, Word, 画像）
     */
    public function storeWorkSummaryDocument(Request $request, Work $work)
    {
        $validated = $request->validate([
            'display_name' => ['nullable', 'string', 'max:255'],
            'file' => ['required', 'file', 'mimes:pdf,xlsx,xls,docx,doc,jpg,jpeg,png,gif,webp', 'max:20480'],
        ]);

        $docType = AttachmentType::where('name', 'マニュアル')->first();
        if (! $docType) {
            return redirect()->back()->withErrors(['file' => '添付種別が見つかりません。']);
        }

        $file = $request->file('file');
        $dir = 'work_attachments/' . $work->id;
        $name = Str::uuid() . '_' . $file->getClientOriginalName();
        $path = $file->storeAs($dir, $name, 'public');

        WorkAttachment::create([
            'work_id' => $work->id,
            'work_content_id' => null,
            'attachment_type_id' => $docType->id,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'display_name' => isset($validated['display_name']) && trim((string) $validated['display_name']) !== '' ? trim($validated['display_name']) : $file->getClientOriginalName(),
            'uploaded_by' => auth()->id(),
        ]);

        $this->recordWorkActivity($work, 'その他', '作業概要に資料を追加しました。');
        return redirect()->route('work.works.show', $work)->with('status', '資料を追加しました。');
    }

    /**
     * コメントを追加
     */
    public function storeComment(Request $request, Work $work)
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
        ]);

        $type = WorkActivityType::where('name', 'コメント')->first();
        if (! $type) {
            return redirect()->route('work.works.show', $work)->with('error', 'コメント種別が見つかりません。');
        }

        WorkActivity::create([
            'work_id' => $work->id,
            'user_id' => auth()->id(),
            'work_activity_type_id' => $type->id,
            'message' => $validated['message'],
        ]);

        return redirect()->route('work.works.show', $work)->with('status', 'コメントを追加しました。');
    }

    /**
     * コメントを更新（作成者のみ）
     */
    public function updateComment(Request $request, Work $work, WorkActivity $activity)
    {
        $type = WorkActivityType::where('name', 'コメント')->first();
        if (! $type || (int) $activity->work_activity_type_id !== (int) $type->id) {
            abort(404);
        }
        if ((int) $activity->work_id !== (int) $work->id) {
            abort(404);
        }
        if ((int) $activity->user_id !== (int) auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
        ]);

        $activity->update(['message' => $validated['message']]);

        return redirect()->route('work.works.show', $work)->with('status', 'コメントを更新しました。');
    }

    /**
     * コメントを削除（作成者のみ）
     */
    public function destroyComment(Work $work, WorkActivity $activity)
    {
        $type = WorkActivityType::where('name', 'コメント')->first();
        if (! $type || (int) $activity->work_activity_type_id !== (int) $type->id) {
            abort(404);
        }
        if ((int) $activity->work_id !== (int) $work->id) {
            abort(404);
        }
        if ((int) $activity->user_id !== (int) auth()->id()) {
            abort(403);
        }

        $activity->delete();

        return redirect()->route('work.works.show', $work)->with('status', 'コメントを削除しました。');
    }
}
