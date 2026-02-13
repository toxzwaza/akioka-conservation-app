<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'work_id',
        'work_content_id',
        'attachment_type_id',
        'path',
        'original_name',
        'uploaded_by',
    ];

    /**
     * 作業
     */
    public function work(): BelongsTo
    {
        return $this->belongsTo(Work::class);
    }

    /**
     * 作業内容（任意）
     */
    public function workContent(): BelongsTo
    {
        return $this->belongsTo(WorkContent::class, 'work_content_id');
    }

    /**
     * 添付種別
     */
    public function attachmentType(): BelongsTo
    {
        return $this->belongsTo(AttachmentType::class, 'attachment_type_id');
    }

    /**
     * アップロード者
     */
    public function uploadedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
