<?php

namespace App\Enums;

enum WorkspaceRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';

    public function canManageMembers(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }

    public function canAssignTasks(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }

    public function canManageWorkspaceSettings(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }
}
