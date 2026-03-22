<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['workspace_id', 'user_id', 'action', 'description', 'subject_type', 'subject_id', 'properties'])]
class ActivityLog extends Model
{
    /** @use HasFactory<\\Database\\Factories\\ActivityLogFactory> */
    use HasFactory;

    /**
     * Cast attributes to native types.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'properties' => 'array',
        ];
    }

    /**
     * User who performed the action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Workspace where the action happened.
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Optional related model for this event.
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
