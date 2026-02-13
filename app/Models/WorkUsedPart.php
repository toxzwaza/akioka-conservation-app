<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkUsedPart extends Model
{
    use HasFactory;

    protected $fillable = [
        'work_id',
        'part_id',
        'qty',
        'note',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
    ];

    /**
     * 作業
     */
    public function work(): BelongsTo
    {
        return $this->belongsTo(Work::class);
    }

    /**
     * 部品
     */
    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }
}
