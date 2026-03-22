<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['workspace_id', 'created_by', 'name', 'color'])]
class TaskLabel extends Model
{
    /** @use HasFactory<\\Database\\Factories\\TaskLabelFactory> */
    use HasFactory;

    /**
     * Workspace this label belongs to.
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * User who created this label.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Tasks tagged with this label.
     */
    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'label_task')
            ->withTimestamps();
    }
}
