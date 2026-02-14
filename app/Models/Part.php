<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Part extends Model
{
    use HasFactory;

    protected $fillable = [
        'external_id',
        'part_no',
        'name',
        'memo',
        'image_path',
    ];

    /**
     * 紐付く設備（中間テーブル: equipment_parts）
     */
    public function equipments(): BelongsToMany
    {
        return $this->belongsToMany(Equipment::class, 'equipment_parts')
            ->withPivot('note')
            ->withTimestamps();
    }

    /**
     * 使用部品履歴
     */
    public function workUsedParts(): HasMany
    {
        return $this->hasMany(WorkUsedPart::class);
    }

    /**
     * ユーザー別表示名
     */
    public function userDisplayNames(): HasMany
    {
        return $this->hasMany(PartUserDisplayName::class);
    }
}
