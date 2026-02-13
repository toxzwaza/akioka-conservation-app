<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkCost extends Model
{
    use HasFactory;

    protected $fillable = [
        'work_id',
        'work_cost_category_id',
        'amount',
        'vendor_name',
        'occurred_at',
        'note',
        'file_path',
    ];

    protected $casts = [
        'occurred_at' => 'date',
    ];

    /**
     * 作業
     */
    public function work(): BelongsTo
    {
        return $this->belongsTo(Work::class);
    }

    /**
     * 費用カテゴリ
     */
    public function workCostCategory(): BelongsTo
    {
        return $this->belongsTo(WorkCostCategory::class, 'work_cost_category_id');
    }
}
