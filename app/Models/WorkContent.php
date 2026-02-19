<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkContent extends Model
{
    use HasFactory;

    protected $fillable = [
        'work_id',
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
     * 作業タグ（複数）
     */
    public function workContentTags(): BelongsToMany
    {
        return $this->belongsToMany(WorkContentTag::class, 'work_content_work_content_tag')
            ->orderBy('work_content_tags.sort_order')
            ->orderBy('work_content_tags.id');
    }

    /**
     * 修理内容（複数）
     */
    public function repairTypes(): BelongsToMany
    {
        return $this->belongsToMany(RepairType::class, 'work_content_repair_type')
            ->orderBy('repair_types.sort_order')
            ->orderBy('repair_types.id');
    }

    /**
     * 添付ファイル（この作業内容に紐づく）
     */
    public function workAttachments(): HasMany
    {
        return $this->hasMany(WorkAttachment::class, 'work_content_id');
    }
}
