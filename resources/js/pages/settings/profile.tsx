import { Transition } from '@headlessui/react';
import { Form, Head, Link, usePage } from '@inertiajs/react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/delete-user';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
import type { Activity, BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Profile settings',
        href: edit(),
    },
];

export default function Profile({
    mustVerifyEmail,
    status,
    activities,
}: {
    mustVerifyEmail: boolean;
    status?: string;
    activities: Activity[];
}) {
    const { auth } = usePage().props;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Profile settings" />

            <h1 className="sr-only">Profile settings</h1>

            <SettingsLayout>
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="Profile information"
                        description="Update your avatar, name, role and bio"
                    />

                    <Form
                        {...ProfileController.update.form()}
                        options={{
                            preserveScroll: true,
                        }}
                        className="space-y-6"
                    >
                        {({ processing, recentlySuccessful, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="avatar">Avatar</Label>

                                    <Input
                                        id="avatar"
                                        type="file"
                                        className="mt-1 block w-full"
                                        name="avatar"
                                        accept="image/*"
                                    />

                                    <InputError
                                        className="mt-2"
                                        message={errors.avatar}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="name">Name</Label>

                                    <Input
                                        id="name"
                                        className="mt-1 block w-full"
                                        defaultValue={auth.user.name}
                                        name="name"
                                        required
                                        autoComplete="name"
                                        placeholder="Full name"
                                    />

                                    <InputError
                                        className="mt-2"
                                        message={errors.name}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="profile_role">Role</Label>

                                    <Input
                                        id="profile_role"
                                        className="mt-1 block w-full"
                                        defaultValue={auth.user.profile_role as string | undefined}
                                        name="profile_role"
                                        autoComplete="organization-title"
                                        placeholder="Engineering Manager"
                                    />

                                    <InputError
                                        className="mt-2"
                                        message={errors.profile_role}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="bio">Bio</Label>

                                    <textarea
                                        id="bio"
                                        className="mt-1 min-h-24 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs"
                                        defaultValue={auth.user.bio as string | undefined}
                                        name="bio"
                                        placeholder="Tell your team what you focus on."
                                    />

                                    <InputError
                                        className="mt-2"
                                        message={errors.bio}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="email">Email address</Label>

                                    <Input
                                        id="email"
                                        type="email"
                                        className="mt-1 block w-full"
                                        defaultValue={auth.user.email}
                                        name="email"
                                        required
                                        autoComplete="username"
                                        placeholder="Email address"
                                    />

                                    <InputError
                                        className="mt-2"
                                        message={errors.email}
                                    />
                                </div>

                                {mustVerifyEmail &&
                                    auth.user.email_verified_at === null && (
                                        <div>
                                            <p className="-mt-4 text-sm text-muted-foreground">
                                                Your email address is
                                                unverified.{' '}
                                                <Link
                                                    href={send()}
                                                    as="button"
                                                    className="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                                                >
                                                    Click here to resend the
                                                    verification email.
                                                </Link>
                                            </p>

                                            {status ===
                                                'verification-link-sent' && (
                                                <div className="mt-2 text-sm font-medium text-green-600">
                                                    A new verification link has
                                                    been sent to your email
                                                    address.
                                                </div>
                                            )}
                                        </div>
                                    )}

                                <div className="flex items-center gap-4">
                                    <Button
                                        disabled={processing}
                                        data-test="update-profile-button"
                                    >
                                        Save
                                    </Button>

                                    <Transition
                                        show={recentlySuccessful}
                                        enter="transition ease-in-out"
                                        enterFrom="opacity-0"
                                        leave="transition ease-in-out"
                                        leaveTo="opacity-0"
                                    >
                                        <p className="text-sm text-neutral-600">
                                            Saved
                                        </p>
                                    </Transition>
                                </div>
                            </>
                        )}
                    </Form>
                </div>

                <div className="space-y-4">
                    <Heading
                        variant="small"
                        title="Activity history"
                        description="Recent account and workspace actions"
                    />

                    <div className="space-y-2 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        {activities.length > 0 ? (
                            activities.map((activity) => (
                                <div key={activity.id} className="flex items-start justify-between gap-4 border-b border-border/70 py-2 last:border-b-0">
                                    <div>
                                        <p className="text-sm font-medium">{activity.description}</p>
                                        <p className="text-xs text-muted-foreground">{activity.action}</p>
                                    </div>
                                    <p className="shrink-0 text-xs text-muted-foreground">{new Date(activity.created_at).toLocaleString()}</p>
                                </div>
                            ))
                        ) : (
                            <p className="text-sm text-muted-foreground">No activity yet.</p>
                        )}
                    </div>
                </div>

                <DeleteUser />
            </SettingsLayout>
        </AppLayout>
    );
}
