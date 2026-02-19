<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Work extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'equipment_id',
        'work_status_id',
        'work_priority_id',
        'work_purpose_id',
        'assigned_user_id',
        'additional_user_id',
        'production_stop_minutes',
        'occurred_at',
        'started_at',
        'completed_at',
        'note',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * 設備
     */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    /**
     * 作業ステータス
     */
    public function workStatus(): BelongsTo
    {
        return $this->belongsTo(WorkStatus::class, 'work_status_id');
    }

    /**
     * 優先度
     */
    public function workPriority(): BelongsTo
    {
        return $this->belongsTo(WorkPriority::class, 'work_priority_id');
    }

    /**
     * 作業目的
     */
    public function workPurpose(): BelongsTo
    {
        return $this->belongsTo(WorkPurpose::class, 'work_purpose_id');
    }

    /**
     * 主担当
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * 追加担当
     */
    public function additionalUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'additional_user_id');
    }

    /**
     * 作業内容
     */
    public function workContents(): HasMany
    {
        return $this->hasMany(WorkContent::class);
    }

    /**
     * 添付ファイル
     */
    public function workAttachments(): HasMany
    {
        return $this->hasMany(WorkAttachment::class);
    }

    /**
     * 作業添付資料（work_content_id が null の添付：PDF, Excel, Word, 画像など）
     */
    public function summaryDocuments(): HasMany
    {
        return $this->hasMany(WorkAttachment::class)->whereNull('work_content_id')->orderBy('id');
    }

    /**
     * 使用部品
     */
    public function workUsedParts(): HasMany
    {
        return $this->hasMany(WorkUsedPart::class);
    }

    /**
     * 作業費用
     */
    public function workCosts(): HasMany
    {
        return $this->hasMany(WorkCost::class);
    }

    /**
     * 操作履歴
     */
    public function workActivities(): HasMany
    {
        return $this->hasMany(WorkActivity::class);
    }
}
