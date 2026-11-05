import { Head, Link, useForm } from '@inertiajs/react';
import type { ReactNode, SyntheticEvent } from 'react';
import TextField from '@/components/text-field';
import { index as attendancePage } from '@/routes/attendance';
import { update } from '@/routes/profile';
import { update as updatePassword } from '@/routes/profile/password';

type Profile = {
    name: string;
    email: string;
    ntt_id: string;
    project_name: string;
    division: string;
};

type ProfileProps = {
    profile: Profile;
};

function Card({
    title,
    description,
    children,
}: {
    title: string;
    description: string;
    children: ReactNode;
}) {
    return (
        <section className="rounded-lg bg-white p-6 shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0.16)] dark:bg-[#161615] dark:shadow-[inset_0px_0px_0px_1px_#fffaed2d]">
            <h2 className="font-medium">{title}</h2>
            <p className="mt-1 text-sm text-[#706f6c] dark:text-[#A1A09A]">
                {description}
            </p>
            <div className="mt-4">{children}</div>
        </section>
    );
}

function SubmitRow({
    processing,
    saved,
}: {
    processing: boolean;
    saved: boolean;
}) {
    return (
        <div className="flex items-center gap-3">
            <button
                type="submit"
                disabled={processing}
                className="rounded-sm border border-black bg-[#1b1b18] px-5 py-2 text-sm leading-normal text-white hover:bg-black disabled:cursor-not-allowed disabled:opacity-50 dark:border-[#eeeeec] dark:bg-[#eeeeec] dark:text-[#1C1C1A] dark:hover:bg-white"
            >
                {processing ? 'Saving…' : 'Save'}
            </button>
            {saved && (
                <p className="text-sm text-[#706f6c] dark:text-[#A1A09A]">
                    Saved.
                </p>
            )}
        </div>
    );
}

export default function ProfilePage({ profile }: ProfileProps) {
    const details = useForm<Profile>(profile);
    const password = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const handleDetails = (event: SyntheticEvent<HTMLFormElement>) => {
        event.preventDefault();

        details.submit(update(), { preserveScroll: true });
    };

    const handlePassword = (event: SyntheticEvent<HTMLFormElement>) => {
        event.preventDefault();

        password.submit(updatePassword(), {
            preserveScroll: true,
            onFinish: () => password.reset(),
        });
    };

    return (
        <>
            <Head title="Profile" />
            <div className="min-h-screen bg-[#FDFDFC] px-6 py-10 text-[#1b1b18] dark:bg-[#0a0a0a] dark:text-[#EDEDEC]">
                <div className="mx-auto flex w-full max-w-2xl flex-col gap-6">
                    <header className="flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <p className="text-xs font-medium tracking-[0.2em] text-[#706f6c] uppercase dark:text-[#A1A09A]">
                                Time clock
                            </p>
                            <h1 className="mt-1 text-2xl font-medium">
                                Profile
                            </h1>
                        </div>
                        <Link
                            href={attendancePage()}
                            className="rounded-sm border border-[#e3e3e0] px-3 py-1 text-sm hover:border-[#1b1b18] dark:border-[#3E3E3A] dark:hover:border-[#EDEDEC]"
                        >
                            Back to attendance
                        </Link>
                    </header>

                    <Card
                        title="Details"
                        description="Your name, NTT ID, project and division are written into the header of every timesheet you download."
                    >
                        <form
                            onSubmit={handleDetails}
                            className="flex flex-col gap-4"
                        >
                            <TextField
                                id="name"
                                type="text"
                                label="Name"
                                value={details.data.name}
                                onChange={(event) =>
                                    details.setData('name', event.target.value)
                                }
                                autoComplete="name"
                                required
                                error={details.errors.name}
                            />
                            <TextField
                                id="email"
                                type="email"
                                label="Email"
                                value={details.data.email}
                                onChange={(event) =>
                                    details.setData('email', event.target.value)
                                }
                                autoComplete="username"
                                required
                                error={details.errors.email}
                            />
                            <TextField
                                id="ntt_id"
                                type="text"
                                label="NTT ID"
                                value={details.data.ntt_id}
                                onChange={(event) =>
                                    details.setData(
                                        'ntt_id',
                                        event.target.value,
                                    )
                                }
                                required
                                error={details.errors.ntt_id}
                            />
                            <TextField
                                id="project_name"
                                type="text"
                                label="Project name"
                                value={details.data.project_name}
                                onChange={(event) =>
                                    details.setData(
                                        'project_name',
                                        event.target.value,
                                    )
                                }
                                required
                                error={details.errors.project_name}
                            />
                            <TextField
                                id="division"
                                type="text"
                                label="Division"
                                value={details.data.division}
                                onChange={(event) =>
                                    details.setData(
                                        'division',
                                        event.target.value,
                                    )
                                }
                                required
                                error={details.errors.division}
                            />
                            <SubmitRow
                                processing={details.processing}
                                saved={details.recentlySuccessful}
                            />
                        </form>
                    </Card>

                    <Card
                        title="Password"
                        description="Enter your current password to choose a new one."
                    >
                        <form
                            onSubmit={handlePassword}
                            className="flex flex-col gap-4"
                        >
                            <TextField
                                id="current_password"
                                type="password"
                                label="Current password"
                                value={password.data.current_password}
                                onChange={(event) =>
                                    password.setData(
                                        'current_password',
                                        event.target.value,
                                    )
                                }
                                autoComplete="current-password"
                                required
                                error={password.errors.current_password}
                            />
                            <TextField
                                id="password"
                                type="password"
                                label="New password"
                                value={password.data.password}
                                onChange={(event) =>
                                    password.setData(
                                        'password',
                                        event.target.value,
                                    )
                                }
                                autoComplete="new-password"
                                required
                                error={password.errors.password}
                            />
                            <TextField
                                id="password_confirmation"
                                type="password"
                                label="Confirm new password"
                                value={password.data.password_confirmation}
                                onChange={(event) =>
                                    password.setData(
                                        'password_confirmation',
                                        event.target.value,
                                    )
                                }
                                autoComplete="new-password"
                                required
                                error={password.errors.password_confirmation}
                            />
                            <SubmitRow
                                processing={password.processing}
                                saved={password.recentlySuccessful}
                            />
                        </form>
                    </Card>
                </div>
            </div>
        </>
    );
}
