<?php

namespace App\Models;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['workspace_id', 'invited_by', 'email', 'role', 'token_hash', 'expires_at', 'accepted_at'])]
class WorkspaceInvitation extends Model
{
    /** @use HasFactory<\\Database\\Factories\\WorkspaceInvitationFactory> */
    use HasFactory;

    /**
     * Cast attributes to native types.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    /**
     * Workspace this invitation belongs to.
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * User who sent this invitation.
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Check whether the invite can still be used.
     */
    public function isPending(): bool
    {
        return is_null($this->accepted_at) && now()->lt($this->expires_at);
    }

    /**
     * Verify a plaintext token against the stored hash.
     */
    public function matchesToken(string $token): bool
    {
        return hash_equals($this->token_hash, hash('sha256', $token));
    }
}
