import { Head, router, usePage } from '@inertiajs/react';
import AlertError from '@/components/alert-error';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Workspace Invitation',
        href: '#',
    },
];

export default function WorkspaceInvitationShow({
    invitation,
    workspace,
    token,
}: {
    invitation: {
        id: number;
        email: string;
        role: 'admin' | 'member';
        expires_at: string;
    };
    workspace: {
        id: number;
        name: string;
        slug: string;
    };
    token: string;
}) {
    const page = usePage();
    const errors = page.props.errors as Record<string, string> | undefined;

    const acceptInvitation = () => {
        router.post(`/workspace-invitations/${invitation.id}/accept?token=${encodeURIComponent(token)}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Workspace Invitation" />

            <div className="mx-auto w-full max-w-2xl p-4">
                <div className="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                    <Heading
                        variant="small"
                        title="You're invited"
                        description={`Join ${workspace.name} as ${invitation.role}.`}
                    />

                    {errors?.invitation && (
                        <div className="mt-4">
                            <AlertError title="Could not accept invitation" errors={[errors.invitation]} />
                        </div>
                    )}

                    <div className="mt-4 space-y-2 rounded-lg border border-border/70 p-4 text-sm">
                        <p><span className="font-medium">Workspace:</span> {workspace.name}</p>
                        <p><span className="font-medium">Invited as:</span> {invitation.role}</p>
                        <p><span className="font-medium">Invite email:</span> {invitation.email}</p>
                        <p><span className="font-medium">Expires:</span> {new Date(invitation.expires_at).toLocaleString()}</p>
                    </div>

                    <div className="mt-4 flex items-center gap-2">
                        <Button type="button" onClick={acceptInvitation}>
                            Accept invitation
                        </Button>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
