<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;

class MasterController extends Controller
{
    /** マスタ種別ごとのモデルクラス・表示名 */
    private const MASTER_CONFIG = [
        'work-statuses'        => [\App\Models\WorkStatus::class, '作業ステータス'],
        'work-priorities'      => [\App\Models\WorkPriority::class, '優先度'],
        'work-purposes'        => [\App\Models\WorkPurpose::class, '作業目的'],
        'work-content-tags'    => [\App\Models\WorkContentTag::class, '作業タグ'],
        'repair-types'         => [\App\Models\RepairType::class, '修理内容'],
        'attachment-types'     => [\App\Models\AttachmentType::class, '添付種別'],
        'work-activity-types'  => [\App\Models\WorkActivityType::class, '操作履歴種別'],
        'work-cost-categories' => [\App\Models\WorkCostCategory::class, '費用カテゴリ'],
    ];

    private function getModelAndTitle(string $key): array
    {
        if (! isset(self::MASTER_CONFIG[$key])) {
            abort(404, 'Unknown master type.');
        }
        return self::MASTER_CONFIG[$key];
    }

    /**
     * 一覧表示
     */
    public function index(string $masterKey)
    {
        [$modelClass, $title] = $this->getModelAndTitle($masterKey);
        $items = $modelClass::orderBy('sort_order')->orderBy('id')->get();

        return Inertia::render('Master/List', [
            'masterKey' => $masterKey,
            'title'     => $title,
            'items'     => $items,
        ]);
    }

    /**
     * 追加フォーム表示
     */
    public function create(string $masterKey)
    {
        [$modelClass, $title] = $this->getModelAndTitle($masterKey);

        return Inertia::render('Master/Create', [
            'masterKey' => $masterKey,
            'title'     => $title,
        ]);
    }

    /**
     * 新規保存
     */
    public function store(Request $request, string $masterKey)
    {
        [$modelClass, $title] = $this->getModelAndTitle($masterKey);

        $allowedColors = array_merge([''], config('badge.hex_colors', []));
        $colorRules = ['nullable', 'string', \Illuminate\Validation\Rule::in($allowedColors)];

        $validated = $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'color'      => $colorRules,
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active'  => ['nullable', 'boolean'],
        ], [], [
            'name'       => '表示名',
            'color'      => '表示色',
            'sort_order' => '並び順',
            'is_active'  => '有効',
        ]);

        $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);
        $validated['is_active'] = (bool) ($validated['is_active'] ?? true);
        $validated['color'] = ! empty(trim($validated['color'] ?? '')) ? trim($validated['color']) : null;

        $modelClass::create($validated);

        return redirect()->route('master.index', ['masterKey' => $masterKey])
            ->with('success', "{$title}を追加しました。");
    }

    /**
     * 詳細表示
     */
    public function show(string $masterKey, int $id)
    {
        [$modelClass, $title] = $this->getModelAndTitle($masterKey);
        $item = $modelClass::findOrFail($id);

        return Inertia::render('Master/Show', [
            'masterKey' => $masterKey,
            'title'     => $title,
            'item'      => $item,
        ]);
    }

    /**
     * 編集フォーム表示
     */
    public function edit(string $masterKey, int $id)
    {
        [$modelClass, $title] = $this->getModelAndTitle($masterKey);
        $item = $modelClass::findOrFail($id);

        return Inertia::render('Master/Edit', [
            'masterKey' => $masterKey,
            'title'     => $title,
            'item'      => $item,
        ]);
    }

    /**
     * 更新
     */
    public function update(Request $request, string $masterKey, int $id)
    {
        [$modelClass, $title] = $this->getModelAndTitle($masterKey);
        $item = $modelClass::findOrFail($id);

        $allowedColors = array_merge([''], config('badge.hex_colors', []));
        $colorRules = ['nullable', 'string', \Illuminate\Validation\Rule::in($allowedColors)];

        $validated = $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'color'      => $colorRules,
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active'  => ['nullable', 'boolean'],
        ], [], [
            'name'       => '表示名',
            'color'      => '表示色',
            'sort_order' => '並び順',
            'is_active'  => '有効',
        ]);

        $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);
        $validated['is_active'] = (bool) ($validated['is_active'] ?? true);
        $validated['color'] = ! empty(trim($validated['color'] ?? '')) ? trim($validated['color']) : null;

        $item->update($validated);

        return redirect()->route('master.show', ['masterKey' => $masterKey, 'id' => $item->id])
            ->with('success', "{$title}を更新しました。");
    }

    /**
     * 削除
     */
    public function destroy(string $masterKey, int $id)
    {
        [$modelClass, $title] = $this->getModelAndTitle($masterKey);
        $item = $modelClass::findOrFail($id);

        $item->delete();

        return redirect()->route('master.index', ['masterKey' => $masterKey])
            ->with('success', "{$title}を削除しました。");
    }
}
