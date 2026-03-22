<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;

class ActivityLogger
{
    /**
     * Persist an activity event for history timelines.
     *
     * @param  array<string, mixed>  $properties
     */
    public static function log(
        User $actor,
        ?Workspace $workspace,
        string $action,
        string $description,
        ?Model $subject = null,
        array $properties = [],
    ): ActivityLog {
        return ActivityLog::create([
            'workspace_id' => $workspace?->id,
            'user_id' => $actor->id,
            'action' => $action,
            'description' => $description,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'properties' => $properties,
        ]);
    }
}
