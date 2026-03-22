<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['task_id', 'user_id', 'parent_id', 'body'])]
class TaskComment extends Model
{
    /** @use HasFactory<\\Database\\Factories\\TaskCommentFactory> */
    use HasFactory;

    /**
     * Task this comment belongs to.
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Comment author.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Parent comment for threaded replies.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(TaskComment::class, 'parent_id');
    }

    /**
     * Reply comments.
     */
    public function replies(): HasMany
    {
        return $this->hasMany(TaskComment::class, 'parent_id')->latest();
    }
}
