<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Work extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'work_status_id',
        'work_priority_id',
        'assigned_user_id',
        'production_stop_minutes',
        'occurred_at',
        'completed_at',
        'note',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * 設備（複数、代表は先頭）
     */
    public function equipments(): BelongsToMany
    {
        return $this->belongsToMany(Equipment::class, 'work_equipment')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order')
            ->orderByPivot('equipment_id');
    }

    /**
     * 代表設備（互換用、equipments の先頭1件）
     */
    public function getEquipmentAttribute(): ?Equipment
    {
        return $this->relationLoaded('equipments') ? $this->equipments->first() : $this->equipments()->first();
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
     * 作業目的（複数）
     */
    public function workPurposes(): BelongsToMany
    {
        return $this->belongsToMany(WorkPurpose::class, 'work_work_purpose')
            ->orderByPivot('work_purpose_id');
    }

    /**
     * 代表作業目的（互換用、workPurposes の先頭1件）
     */
    public function getWorkPurposeAttribute(): ?WorkPurpose
    {
        return $this->relationLoaded('workPurposes') ? $this->workPurposes->first() : $this->workPurposes()->first();
    }

    /**
     * 主担当
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * 追加担当（複数）
     */
    public function additionalUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'work_additional_user', 'work_id', 'user_id')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order')
            ->orderByPivot('user_id');
    }

    /**
     * 代表追加担当（互換用、additionalUsers の先頭1件）
     */
    public function getAdditionalUserAttribute(): ?User
    {
        return $this->relationLoaded('additionalUsers') ? $this->additionalUsers->first() : $this->additionalUsers()->first();
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
