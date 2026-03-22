<?php

namespace App\Models;

use App\Enums\TaskPriority;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['workspace_id', 'project_id', 'project_column_id', 'created_by', 'assigned_to', 'title', 'description', 'status', 'priority', 'sort_order', 'due_at'])]
class Task extends Model
{
    /** @use HasFactory<\\Database\\Factories\\TaskFactory> */
    use HasFactory;

    /**
     * Cast attributes to native types.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'priority' => TaskPriority::class,
        ];
    }

    /**
     * Workspace the task belongs to.
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Project this task belongs to.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Kanban column this task is currently in.
     */
    public function column(): BelongsTo
    {
        return $this->belongsTo(ProjectColumn::class, 'project_column_id');
    }

    /**
     * User who created the task.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * User assigned to the task.
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Multi-user task assignees.
     */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_user')
            ->withPivot(['assigned_at'])
            ->withTimestamps();
    }

    /**
     * Labels attached to this task.
     */
    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(TaskLabel::class, 'label_task')
            ->withTimestamps();
    }

    /**
     * Files attached to this task.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class);
    }

    /**
     * Threaded comments for this task.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->whereNull('parent_id')->latest();
    }
}
