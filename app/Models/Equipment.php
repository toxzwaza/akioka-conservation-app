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
}
