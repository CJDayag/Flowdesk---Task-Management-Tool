import { Head, useForm, usePage } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { edit as editAppearance } from '@/routes/appearance';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Appearance settings',
        href: editAppearance(),
    },
];

export default function Appearance() {
    const { auth } = usePage().props;
    const compactForm = useForm({
        compact_view: Boolean(auth.user.compact_view),
    });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Appearance settings" />

            <h1 className="sr-only">Appearance settings</h1>

            <SettingsLayout>
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="Appearance settings"
                        description="Update your account's appearance settings"
                    />
                    <AppearanceTabs />

                    <div className="space-y-3 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <Heading
                            variant="small"
                            title="UI density"
                            description="Compact view reduces header height and tightens main content spacing."
                        />

                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={compactForm.data.compact_view}
                                onChange={(event) => compactForm.setData('compact_view', event.target.checked)}
                            />
                            Use compact view
                        </label>

                        <Button
                            type="button"
                            onClick={() => compactForm.patch('/settings/preferences', {
                                preserveScroll: true,
                            })}
                            disabled={compactForm.processing}
                        >
                            Save UI preference
                        </Button>
                    </div>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
