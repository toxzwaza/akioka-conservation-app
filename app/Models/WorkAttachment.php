<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class WorkAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'work_id',
        'work_content_id',
        'attachment_type_id',
        'path',
        'original_name',
        'display_name',
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

    /**
     * 表示用URL（public ディスクの場合）
     * url() を使いリクエストのホスト/ポートに合わせる（APP_URL とポートが異なる場合の対策）
     */
    protected function url(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->path && Storage::disk('public')->exists($this->path)
                ? url('/storage/' . $this->path)
                : null,
        );
    }

    protected $appends = ['url'];
}
