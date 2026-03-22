import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Workspace Invitation',
        href: '#',
    },
];

export default function WorkspaceInvitationError({
    title,
    message,
}: {
    title: string;
    message: string;
}) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Invitation Error" />

            <div className="mx-auto w-full max-w-2xl p-4">
                <div className="rounded-xl border border-destructive/40 bg-destructive/5 p-6">
                    <Heading variant="small" title={title} description={message} />

                    <div className="mt-4">
                        <Button asChild>
                            <Link href={dashboard()}>Back to dashboard</Link>
                        </Button>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
