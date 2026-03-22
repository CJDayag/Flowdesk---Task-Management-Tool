<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['task_id', 'uploaded_by', 'filename', 'path', 'size', 'mime_type'])]
class TaskAttachment extends Model
{
    /** @use HasFactory<\\Database\\Factories\\TaskAttachmentFactory> */
    use HasFactory;

    /**
     * Task for this attachment.
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * User who uploaded this attachment.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
