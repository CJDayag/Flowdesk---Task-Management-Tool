import { FormEvent, useMemo, useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import type { BreadcrumbItem } from '@/types';

type WorkspaceMember = {
    id: number;
    name: string;
    email: string;
    role: 'owner' | 'admin' | 'member';
    joined_at: string;
    is_owner: boolean;
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Workspace settings',
        href: '/settings/workspace',
    },
];

export default function WorkspaceSettings({
    workspace,
    members,
    pendingInvitations,
    canUpdateWorkspace,
    canManageMembers,
}: {
    workspace: { id: number; name: string; theme: 'system' | 'light' | 'dark' } | null;
    members: WorkspaceMember[];
    pendingInvitations: Array<{
        id: number;
        email: string;
        role: 'admin' | 'member';
        expires_at: string;
        created_at: string;
    }>;
    canUpdateWorkspace: boolean;
    canManageMembers: boolean;
}) {
    const workspaceForm = useForm({
        name: workspace?.name ?? '',
        theme: workspace?.theme ?? 'system',
    });

    const [memberRoles, setMemberRoles] = useState<Record<number, 'admin' | 'member'>>(() => {
        const map: Record<number, 'admin' | 'member'> = {};

        for (const member of members) {
            if (member.role === 'owner') {
                continue;
            }

            map[member.id] = member.role === 'admin' ? 'admin' : 'member';
        }

        return map;
    });

    const inviteForm = useForm({
        email: '',
        role: 'member' as 'admin' | 'member',
        expires_in_hours: 48,
    });

    const createWorkspaceForm = useForm({
        name: '',
        theme: 'system' as 'system' | 'light' | 'dark',
    });

    const isDirtyName = useMemo(
        () => workspace ? workspaceForm.data.name.trim() !== workspace.name : false,
        [workspaceForm.data.name, workspace],
    );

    const submitWorkspace = (event: FormEvent) => {
        event.preventDefault();

        if (! workspace) {
            return;
        }

        workspaceForm.patch(`/workspaces/${workspace.id}`, {
            preserveScroll: true,
        });
    };

    const updateMemberRole = (member: WorkspaceMember) => {
        if (! workspace) {
            return;
        }

        const role = memberRoles[member.id];

        if (! role) {
            return;
        }

        router.patch(`/workspaces/${workspace.id}/members/${member.id}`, {
            role,
        }, {
            preserveScroll: true,
        });
    };

    const removeMember = (member: WorkspaceMember) => {
        if (! workspace) {
            return;
        }

        router.delete(`/workspaces/${workspace.id}/members/${member.id}`, {
            preserveScroll: true,
        });
    };

    const submitInvitation = (event: FormEvent) => {
        event.preventDefault();

        if (! workspace) {
            return;
        }

        inviteForm.post(`/workspaces/${workspace.id}/invitations`, {
            preserveScroll: true,
            onSuccess: () => inviteForm.reset('email'),
        });
    };

    const submitCreateWorkspace = (event: FormEvent) => {
        event.preventDefault();

        createWorkspaceForm.post('/workspaces', {
            preserveScroll: true,
            onSuccess: () => createWorkspaceForm.reset('name'),
        });
    };

    const resendInvitation = (invitationId: number) => {
        if (! workspace) {
            return;
        }

        router.patch(`/workspaces/${workspace.id}/invitations/${invitationId}/resend`, {}, {
            preserveScroll: true,
        });
    };

    const revokeInvitation = (invitationId: number) => {
        if (! workspace) {
            return;
        }

        router.delete(`/workspaces/${workspace.id}/invitations/${invitationId}`, {
            preserveScroll: true,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Workspace settings" />

            <h1 className="sr-only">Workspace settings</h1>

            <SettingsLayout>
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title={workspace ? 'Create Workspace' : 'Create your first workspace'}
                        description={workspace
                            ? 'Create an additional workspace for another team or stream.'
                            : 'You are not part of any workspace yet. Create one to start managing projects and tasks.'}
                    />

                    <form onSubmit={submitCreateWorkspace} className="space-y-4 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <div className="grid gap-3 md:grid-cols-3">
                            <div className="grid gap-1 md:col-span-2">
                                <Label htmlFor="new-workspace-name">Workspace name</Label>
                                <Input
                                    id="new-workspace-name"
                                    value={createWorkspaceForm.data.name}
                                    onChange={(event) => createWorkspaceForm.setData('name', event.target.value)}
                                    placeholder="Acme Engineering"
                                />
                                <InputError message={createWorkspaceForm.errors.name} />
                            </div>

                            <div className="grid gap-1">
                                <Label htmlFor="new-workspace-theme">Theme</Label>
                                <select
                                    id="new-workspace-theme"
                                    className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                                    value={createWorkspaceForm.data.theme}
                                    onChange={(event) => createWorkspaceForm.setData('theme', event.target.value as 'system' | 'light' | 'dark')}
                                >
                                    <option value="system">System</option>
                                    <option value="light">Light</option>
                                    <option value="dark">Dark</option>
                                </select>
                            </div>
                        </div>

                        <Button type="submit" disabled={createWorkspaceForm.processing}>
                            Create workspace
                        </Button>
                    </form>
                </div>

                {!workspace && (
                    <div className="rounded-xl border border-dashed border-sidebar-border/70 p-4 text-sm text-muted-foreground dark:border-sidebar-border">
                        Workspace settings, members, and invitations will appear here after you create your first workspace.
                    </div>
                )}

                {workspace && (
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="Workspace Settings"
                        description="Rename your workspace and keep base settings aligned."
                    />

                    <form onSubmit={submitWorkspace} className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="workspace-name">Workspace name</Label>
                            <Input
                                id="workspace-name"
                                value={workspaceForm.data.name}
                                onChange={(event) => workspaceForm.setData('name', event.target.value)}
                                disabled={!canUpdateWorkspace}
                            />
                            <InputError message={workspaceForm.errors.name} />
                        </div>

                        <input type="hidden" value={workspaceForm.data.theme} />

                        <Button type="submit" disabled={!canUpdateWorkspace || workspaceForm.processing || !isDirtyName}>
                            Save workspace name
                        </Button>
                    </form>
                </div>
                )}

                {workspace && (
                <div className="space-y-4">
                    <Heading
                        variant="small"
                        title="Manage Members"
                        description="Promote, demote, or remove workspace members."
                    />

                    <div className="space-y-2 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        {members.map((member) => {
                            const role = member.role === 'owner'
                                ? 'owner'
                                : (memberRoles[member.id] ?? member.role);

                            return (
                                <div
                                    key={member.id}
                                    className="flex flex-col gap-2 rounded-lg border border-border/60 p-3 md:flex-row md:items-center md:justify-between"
                                >
                                    <div>
                                        <p className="text-sm font-medium">{member.name}</p>
                                        <p className="text-xs text-muted-foreground">{member.email}</p>
                                    </div>

                                    <div className="flex items-center gap-2">
                                        <select
                                            className="h-9 rounded-md border border-input bg-background px-2 text-sm"
                                            value={role}
                                            disabled={!canManageMembers || member.is_owner}
                                            onChange={(event) => {
                                                const nextRole = event.target.value as 'admin' | 'member';
                                                setMemberRoles((current) => ({ ...current, [member.id]: nextRole }));
                                            }}
                                        >
                                            <option value="owner">Owner</option>
                                            <option value="admin">Admin</option>
                                            <option value="member">Member</option>
                                        </select>

                                        {!member.is_owner && (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                disabled={!canManageMembers}
                                                onClick={() => updateMemberRole(member)}
                                            >
                                                Update role
                                            </Button>
                                        )}

                                        {!member.is_owner && (
                                            <Button
                                                type="button"
                                                variant="destructive"
                                                disabled={!canManageMembers}
                                                onClick={() => removeMember(member)}
                                            >
                                                Remove
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>
                )}

                {workspace && (
                <div className="space-y-4">
                    <Heading
                        variant="small"
                        title="Invitations"
                        description="Invite new members and manage pending invitations."
                    />

                    <form onSubmit={submitInvitation} className="space-y-3 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <div className="grid gap-3 md:grid-cols-3">
                            <div className="grid gap-1 md:col-span-2">
                                <Label htmlFor="invite-email">Email</Label>
                                <Input
                                    id="invite-email"
                                    type="email"
                                    value={inviteForm.data.email}
                                    onChange={(event) => inviteForm.setData('email', event.target.value)}
                                    disabled={!canManageMembers}
                                />
                                <InputError message={inviteForm.errors.email} />
                            </div>

                            <div className="grid gap-1">
                                <Label htmlFor="invite-role">Role</Label>
                                <select
                                    id="invite-role"
                                    className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                                    value={inviteForm.data.role}
                                    onChange={(event) => inviteForm.setData('role', event.target.value as 'admin' | 'member')}
                                    disabled={!canManageMembers}
                                >
                                    <option value="member">Member</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                        </div>

                        <div className="flex items-center justify-between gap-3">
                            <p className="text-xs text-muted-foreground">Default expiry: 48 hours</p>
                            <Button type="submit" disabled={!canManageMembers || inviteForm.processing}>Send invite</Button>
                        </div>
                    </form>

                    <div className="space-y-2 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        {pendingInvitations.map((invite) => (
                            <div
                                key={invite.id}
                                className="flex flex-col gap-2 rounded-lg border border-border/60 p-3 md:flex-row md:items-center md:justify-between"
                            >
                                <div>
                                    <p className="text-sm font-medium">{invite.email}</p>
                                    <p className="text-xs text-muted-foreground">
                                        {invite.role} • expires {new Date(invite.expires_at).toLocaleString()}
                                    </p>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={!canManageMembers}
                                        onClick={() => resendInvitation(invite.id)}
                                    >
                                        Resend
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        disabled={!canManageMembers}
                                        onClick={() => revokeInvitation(invite.id)}
                                    >
                                        Revoke
                                    </Button>
                                </div>
                            </div>
                        ))}

                        {pendingInvitations.length === 0 && (
                            <p className="text-sm text-muted-foreground">No pending invitations.</p>
                        )}
                    </div>
                </div>
                )}
            </SettingsLayout>
        </AppLayout>
    );
}
