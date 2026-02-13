<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkActivity extends Model
{
    protected $fillable = [
        'work_id',
        'user_id',
        'work_activity_type_id',
        'message',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 作業
     */
    public function work(): BelongsTo
    {
        return $this->belongsTo(Work::class);
    }

    /**
     * 操作ユーザー
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 操作種別
     */
    public function workActivityType(): BelongsTo
    {
        return $this->belongsTo(WorkActivityType::class, 'work_activity_type_id');
    }
}
