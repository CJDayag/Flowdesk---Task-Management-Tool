<?php

namespace App\Models;

use App\Enums\ProjectVisibility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['workspace_id', 'created_by', 'name', 'slug', 'description', 'visibility'])]
class Project extends Model
{
    /** @use HasFactory<\\Database\\Factories\\ProjectFactory> */
    use HasFactory;

    /**
     * Cast attributes to native types.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visibility' => ProjectVisibility::class,
        ];
    }

    /**
     * Workspace that owns this project.
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * User who created this project.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Members explicitly assigned to this project.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_user')
            ->withPivot(['joined_at'])
            ->withTimestamps();
    }

    /**
     * Tasks linked to this project.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Kanban columns for this project.
     */
    public function columns(): HasMany
    {
        return $this->hasMany(ProjectColumn::class)->orderBy('sort_order');
    }

    /**
     * Determine if a user has access to this project.
     */
    public function userCanAccess(User $user): bool
    {
        $workspaceRole = $user->roleInWorkspace($this->workspace);

        if ($workspaceRole?->value === 'owner' || $workspaceRole?->value === 'admin') {
            return true;
        }

        if ($this->visibility === ProjectVisibility::Public) {
            return $user->belongsToWorkspace($this->workspace);
        }

        return $this->members()->where('user_id', $user->id)->exists();
    }

    /**
     * Generate a unique slug for a workspace-scoped project name.
     */
    public static function uniqueSlugForWorkspace(Workspace $workspace, string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 1;

        while (static::where('workspace_id', $workspace->id)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
