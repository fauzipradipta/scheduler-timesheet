import { Link, useForm } from '@inertiajs/react';
import type { SyntheticEvent } from 'react';
import { create as loginPage } from '@/actions/App/Http/Controllers/Auth/AuthenticatedSessionController';
import { store } from '@/actions/App/Http/Controllers/Auth/RegisteredUserController';
import AuthLayout from '@/components/auth-layout';
import TextField from '@/components/text-field';

export default function Register() {
    const { data, setData, submit, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const handleSubmit = (event: SyntheticEvent<HTMLFormElement>) => {
        event.preventDefault();

        submit(store(), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <AuthLayout
            title="Register"
            description="Create an account to start tracking your hours."
        >
            <form onSubmit={handleSubmit} className="flex flex-col gap-4">
                <TextField
                    id="name"
                    type="text"
                    label="Name"
                    value={data.name}
                    onChange={(event) => setData('name', event.target.value)}
                    autoComplete="name"
                    autoFocus
                    required
                    error={errors.name}
                />

                <TextField
                    id="email"
                    type="email"
                    label="Email"
                    value={data.email}
                    onChange={(event) => setData('email', event.target.value)}
                    autoComplete="username"
                    required
                    error={errors.email}
                />

                <TextField
                    id="password"
                    type="password"
                    label="Password"
                    value={data.password}
                    onChange={(event) =>
                        setData('password', event.target.value)
                    }
                    autoComplete="new-password"
                    required
                    error={errors.password}
                />

                <TextField
                    id="password_confirmation"
                    type="password"
                    label="Confirm password"
                    value={data.password_confirmation}
                    onChange={(event) =>
                        setData('password_confirmation', event.target.value)
                    }
                    autoComplete="new-password"
                    required
                    error={errors.password_confirmation}
                />

                <button
                    type="submit"
                    disabled={processing}
                    className="rounded-sm border border-black bg-[#1b1b18] px-5 py-2 text-sm leading-normal text-white hover:bg-black disabled:cursor-not-allowed disabled:opacity-50 dark:border-[#eeeeec] dark:bg-[#eeeeec] dark:text-[#1C1C1A] dark:hover:bg-white"
                >
                    {processing ? 'Creating account…' : 'Create account'}
                </button>
            </form>

            <p className="mt-4 text-sm text-[#706f6c] dark:text-[#A1A09A]">
                Already registered?{' '}
                <Link
                    href={loginPage()}
                    className="font-medium text-[#1b1b18] underline underline-offset-4 dark:text-[#EDEDEC]"
                >
                    Log in
                </Link>
            </p>
        </AuthLayout>
    );
}
