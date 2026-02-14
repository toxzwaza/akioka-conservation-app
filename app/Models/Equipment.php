<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Equipment extends Model
{
    use HasFactory;

    protected $table = 'equipments';

    protected $fillable = [
        'parent_id',
        'name',
        'model_number',
        'status',
        'installed_at',
        'vendor_contact',
        'manufacturer',
        'note',
        'image_path',
    ];

    protected $casts = [
        'installed_at' => 'date',
    ];

    /**
     * 親設備
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Equipment::class, 'parent_id');
    }

    /**
     * 子設備
     */
    public function children(): HasMany
    {
        return $this->hasMany(Equipment::class, 'parent_id');
    }

    /**
     * 紐付く部品（中間テーブル: equipment_parts）
     */
    public function parts(): BelongsToMany
    {
        return $this->belongsToMany(Part::class, 'equipment_parts')
            ->withPivot('note')
            ->withTimestamps();
    }

    /**
     * 作業
     */
    public function works(): HasMany
    {
        return $this->hasMany(Work::class);
    }

    /**
     * セレクトボックス用に階層表示のフラットリストを返す
     * $excludeId: 除外する設備ID（自分自身とその子孫。編集時など）
     *
     * @return array<int, array{id: int, name: string, display_label: string, depth: int}>
     */
    public static function getOptionsForSelect(?int $excludeId = null): array
    {
        $all = self::orderBy('name')->get(['id', 'name', 'parent_id']);
        $excludeIds = $excludeId ? array_merge([$excludeId], self::getDescendantIds($all, $excludeId)) : [];

        $byParent = $all->groupBy('parent_id');
        $roots = ($byParent->get(null) ?? collect())->sortBy('name')->values();

        $result = [];
        $prefix = '　'; // 全角スペース

        $flatten = function ($items, int $depth) use (&$flatten, $byParent, &$result, $excludeIds, $prefix) {
            foreach ($items as $item) {
                if (in_array($item->id, $excludeIds, true)) {
                    continue;
                }
                $indent = $depth > 0 ? str_repeat($prefix, $depth) . '└ ' : '';
                $result[] = [
                    'id' => $item->id,
                    'name' => $item->name,
                    'display_label' => $indent . $item->name,
                    'depth' => $depth,
                ];
                $children = ($byParent->get($item->id) ?? collect())->sortBy('name')->values();
                $flatten($children, $depth + 1);
            }
        };

        $flatten($roots, 0);

        return $result;
    }

    /**
     * 指定IDの子孫のID一覧を返す（自分自身は含まない）
     *
     * @param  \Illuminate\Support\Collection<int, Equipment>  $equipments
     * @return array<int, int>
     */
    private static function getDescendantIds($equipments, int $id): array
    {
        $byParent = $equipments->groupBy('parent_id');
        $ids = [$id];

        $collect = function (int $parentId) use (&$collect, $byParent, &$ids) {
            $children = $byParent->get($parentId) ?? collect();
            foreach ($children as $child) {
                $ids[] = $child->id;
                $collect($child->id);
            }
        };

        $collect($id);

        return array_values(array_diff($ids, [$id]));
    }
}
