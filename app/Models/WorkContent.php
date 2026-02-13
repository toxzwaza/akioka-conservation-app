<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkContent extends Model
{
    use HasFactory;

    protected $fillable = [
        'work_id',
        'work_content_tag_id',
        'repair_type_id',
        'content',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    /**
     * 作業
     */
    public function work(): BelongsTo
    {
        return $this->belongsTo(Work::class);
    }

    /**
     * 作業タグ
     */
    public function workContentTag(): BelongsTo
    {
        return $this->belongsTo(WorkContentTag::class, 'work_content_tag_id');
    }

    /**
     * 修理内容
     */
    public function repairType(): BelongsTo
    {
        return $this->belongsTo(RepairType::class, 'repair_type_id');
    }

    /**
     * 添付ファイル（この作業内容に紐づく）
     */
    public function workAttachments(): HasMany
    {
        return $this->hasMany(WorkAttachment::class, 'work_content_id');
    }
}
