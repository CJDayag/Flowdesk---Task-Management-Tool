export type Workspace = {
    id: number;
    name: string;
    slug: string;
    theme: string;
    logo_path?: string | null;
};

export type Activity = {
    id: number;
    action: string;
    description: string;
    created_at: string;
};

export type InAppNotification = {
    id: string;
    read_at: string | null;
    created_at: string;
    data: {
        kind: 'task_assigned' | 'comment_added' | 'due_approaching' | string;
        title?: string;
        message?: string;
        url?: string;
        [key: string]: unknown;
    };
};

export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    avatar_path?: string | null;
    bio?: string | null;
    profile_role?: string | null;
    compact_view?: boolean;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    current_workspace_id?: number | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
    currentWorkspace?: Workspace | null;
    currentWorkspaceRole?: 'owner' | 'admin' | 'member' | null;
    notifications?: InAppNotification[];
    unreadNotificationsCount?: number;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
