<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\Part;
use App\Models\PartUserDisplayName;
use App\Services\PartDisplayNameService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class MasterPartController extends Controller
{
    /**
     * 一覧表示
     */
    public function index(PartDisplayNameService $displayNameService)
    {
        $parts = Part::orderBy('part_no')->get();
        $items = $displayNameService->resolveDisplayNames($parts, auth()->user());

        return Inertia::render('Master/Parts/Index', [
            'items' => $items,
        ]);
    }

    /**
     * 新規追加画面（API検索フォーム）
     */
    public function create()
    {
        return Inertia::render('Master/Parts/Create');
    }

    /**
     * Conservation API（物品/stocks）で部品検索
     * クエリパラメータ: name, s_name, ids, per_page
     */
    public function searchApi(Request $request)
    {
        $request->validate([
            'query' => ['nullable', 'array'],
            'query.name' => ['nullable', 'string', 'max:255'],
            'query.s_name' => ['nullable', 'string', 'max:255'],
            'query.ids' => ['nullable', 'string', 'max:255'],
            'query.per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = $request->input('query', []);
        $query = array_filter($query, fn ($v) => $v !== null && $v !== '');

        $baseUrl = rtrim(config('services.conservation_api.base_url'), '/');
        $url = $baseUrl . '/stocks?' . http_build_query($query);

        try {
            $response = Http::timeout(10)->get($url);

            if (! $response->successful()) {
                return response()->json(['items' => [], 'message' => 'APIリクエストに失敗しました。']);
            }

            $data = $response->json();
            $items = $data['data'] ?? [];
            if (! is_array($items)) {
                $items = [];
            }

            $existingExternalIds = Part::whereNotNull('external_id')->pluck('external_id')->toArray();
            $baseUrl = rtrim(config('services.conservation_api.base_url'), '/');
            $siteRoot = preg_replace('#/api$#', '', $baseUrl) ?: $baseUrl;

            $normalized = collect($items)->map(function ($row) use ($existingExternalIds, $siteRoot) {
                $id = $row['id'] ?? null;
                $externalId = $id !== null ? (string) $id : '';
                $suppliers = $row['stock_suppliers'] ?? [];
                $mainSupplier = collect($suppliers)->firstWhere('main_flg', 1) ?? $suppliers[0] ?? null;
                $supplierName = $mainSupplier ? (($mainSupplier['supplier'] ?? [])['name'] ?? null) : null;

                $thumbnailUrl = $this->resolveThumbnailUrl($row, $siteRoot);

                return [
                    'id' => $id,
                    'external_id' => $externalId,
                    'thumbnail_url' => $thumbnailUrl,
                    'name' => $row['name'] ?? '',
                    's_name' => $row['s_name'] ?? null,
                    'stock_no' => $row['stock_no'] ?? null,
                    'jan_code' => $row['jan_code'] ?? null,
                    'price' => $row['price'] ?? null,
                    'supplier' => $supplierName,
                    'already_registered' => $externalId !== '' && in_array($externalId, $existingExternalIds, true),
                ];
            })->values()->all();

            return response()->json(['items' => $normalized]);
        } catch (\Throwable $e) {
            return response()->json(['items' => [], 'message' => '検索中にエラーが発生しました。']);
        }
    }

    /**
     * Conservation API レスポンスからサムネイル画像URLを解決する
     */
    private function resolveThumbnailUrl(array $row, string $siteRoot): ?string
    {
        $pathOrUrl = null;
        $stockImages = $row['stock_images'] ?? [];
        if (is_array($stockImages) && count($stockImages) > 0) {
            $first = $stockImages[0];
            $pathOrUrl = $first['thumbnail_url'] ?? $first['thumbnail_path'] ?? $first['url'] ?? $first['path'] ?? $first['image_path'] ?? null;
        }
        if ($pathOrUrl === null) {
            $pathOrUrl = $row['img_path'] ?? null;
        }
        if (empty($pathOrUrl)) {
            return null;
        }
        if (str_starts_with($pathOrUrl, 'http://') || str_starts_with($pathOrUrl, 'https://')) {
            return $pathOrUrl;
        }
        $path = ltrim($pathOrUrl, '/');

        return rtrim($siteRoot, '/') . '/' . $path;
    }

    /**
     * APIで検索した部品をローカルに登録（external_id のみ保存、詳細はAPI経由で取得）
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'external_id' => ['required', 'string', 'max:255'],
        ], [], [
            'external_id' => '外部ID',
        ]);

        if (Part::where('external_id', $validated['external_id'])->exists()) {
            return redirect()->route('master.parts.create')->with('error', 'この部品は既に登録されています。');
        }

        $part = Part::create([
            'external_id' => $validated['external_id'],
            'part_no' => 'EXT-' . $validated['external_id'],
            'name' => '（API連携）',
        ]);

        return redirect()->route('master.parts.show', $part->id)->with('success', '部品を追加しました。');
    }

    /**
     * 詳細表示（external_id がある場合は Conservation API から詳細取得）
     */
    public function show(int $id, PartDisplayNameService $displayNameService)
    {
        $part = Part::with('equipments')->findOrFail($id);
        $resolved = $displayNameService->resolveDisplayNames(collect([$part]), auth()->user());
        $item = $resolved->first();
        $item['equipments'] = $part->equipments->map(fn ($e) => [
            'id' => $e->id,
            'name' => $e->name,
            'pivot' => ['note' => $e->pivot->note ?? null],
        ])->values()->all();

        $apiDetail = null;
        if ($part->external_id) {
            $baseUrl = rtrim(config('services.conservation_api.base_url'), '/');
            $url = $baseUrl . '/stocks/' . $part->external_id;

            try {
                $response = Http::timeout(10)->get($url);
                if ($response->successful()) {
                    $apiDetail = $response->json();
                }
            } catch (\Throwable $e) {
                // API 取得失敗時は apiDetail は null のまま
            }
        }

        $allEquipments = Equipment::orderBy('name')->get(['id', 'name']);

        $baseUrl = rtrim(config('services.conservation_api.base_url'), '/');
        $siteRoot = preg_replace('#/api$#', '', $baseUrl) ?: $baseUrl;
        $apiThumbnailUrl = $apiDetail ? $this->resolveThumbnailUrl($apiDetail, $siteRoot) : null;
        $localThumbnailUrl = $part->image_path
            ? url('/storage/' . $part->image_path)
            : null;
        $thumbnailUrl = $localThumbnailUrl ?? $apiThumbnailUrl;

        return Inertia::render('Master/Parts/Show', [
            'item' => $item,
            'apiDetail' => $apiDetail,
            'allEquipments' => $allEquipments,
            'thumbnailUrl' => $thumbnailUrl,
            'hasLocalImage' => (bool) $part->image_path,
        ]);
    }

    /**
     * 部品のサムネイル画像をアップロード
     */
    public function uploadImage(Request $request, int $id)
    {
        $part = Part::findOrFail($id);

        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:5120'],
        ], [], [
            'image' => '画像',
        ]);

        if ($part->image_path) {
            Storage::disk('public')->delete($part->image_path);
        }

        $file = $request->file('image');
        $path = $file->store('parts/' . $part->id, 'public');

        $part->update(['image_path' => $path]);

        return back()->with('success', '画像をアップロードしました。');
    }

    /**
     * 部品のアップロード画像を削除
     */
    public function destroyImage(int $id)
    {
        $part = Part::findOrFail($id);

        if ($part->image_path) {
            Storage::disk('public')->delete($part->image_path);
            $part->update(['image_path' => null]);
        }

        return back()->with('success', '画像を削除しました。');
    }

    /**
     * 部品に設備を紐づけ（仕様設備）
     */
    public function attachEquipment(Request $request, int $id)
    {
        $part = Part::findOrFail($id);

        $validated = $request->validate([
            'equipment_id' => ['required', 'exists:equipments,id'],
            'note'         => ['nullable', 'string', 'max:65535'],
        ]);

        if ($part->equipments()->where('equipment_id', $validated['equipment_id'])->exists()) {
            return back()->with('error', 'この設備は既に紐づけ済みです。');
        }

        $part->equipments()->attach($validated['equipment_id'], [
            'note' => $validated['note'] ?? null,
        ]);

        return back()->with('success', '設備を紐づけました。');
    }

    /**
     * 部品と設備の紐づけを解除
     */
    public function detachEquipment(int $partId, int $equipmentId)
    {
        $part = Part::findOrFail($partId);
        $part->equipments()->detach($equipmentId);

        return back()->with('success', '設備の紐づけを解除しました。');
    }

    /**
     * ユーザー別表示名を保存・更新・削除
     */
    public function updateDisplayName(Request $request, int $id)
    {
        $part = Part::findOrFail($id);
        $user = auth()->user();

        $validated = $request->validate([
            'display_name' => ['nullable', 'string', 'max:255'],
            'display_s_name' => ['nullable', 'string', 'max:255'],
        ]);

        $displayName = isset($validated['display_name']) ? trim($validated['display_name']) : '';
        $displaySName = isset($validated['display_s_name']) ? trim($validated['display_s_name']) : '';
        $record = PartUserDisplayName::where('part_id', $part->id)->where('user_id', $user->id)->first();

        if ($displayName === '' && $displaySName === '') {
            if ($record) {
                $record->delete();
            }

            return back()->with('success', '表示名を解除しました。');
        }

        if ($record) {
            $record->update([
                'display_name' => $displayName,
                'display_s_name' => $displaySName ?: null,
            ]);
        } else {
            PartUserDisplayName::create([
                'part_id' => $part->id,
                'user_id' => $user->id,
                'display_name' => $displayName,
                'display_s_name' => $displaySName ?: null,
            ]);
        }

        return back()->with('success', '表示名を保存しました。');
    }

    /**
     * メモを更新
     */
    public function updateMemo(Request $request, int $id)
    {
        $part = Part::findOrFail($id);

        $validated = $request->validate([
            'memo' => ['nullable', 'string', 'max:65535'],
        ]);

        $part->update([
            'memo' => isset($validated['memo']) ? trim($validated['memo']) : null,
        ]);

        return back()->with('success', 'メモを保存しました。');
    }

    /**
     * 削除
     */
    public function destroy(int $id)
    {
        $part = Part::findOrFail($id);
        $part->delete();

        return redirect()->route('master.parts.index')->with('success', '部品を削除しました。');
    }
}
