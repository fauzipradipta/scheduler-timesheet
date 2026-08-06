import { Link, useForm } from '@inertiajs/react';
import type { SyntheticEvent } from 'react';
import { store } from '@/actions/App/Http/Controllers/Auth/AuthenticatedSessionController';
import { create as registerPage } from '@/actions/App/Http/Controllers/Auth/RegisteredUserController';
import AuthLayout from '@/components/auth-layout';
import TextField from '@/components/text-field';

export default function Login() {
    const { data, setData, submit, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const handleSubmit = (event: SyntheticEvent<HTMLFormElement>) => {
        event.preventDefault();

        submit(store(), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <AuthLayout
            title="Log in"
            description="Sign in to reach your attendance log."
        >
            <form onSubmit={handleSubmit} className="flex flex-col gap-4">
                <TextField
                    id="email"
                    type="email"
                    label="Email"
                    value={data.email}
                    onChange={(event) => setData('email', event.target.value)}
                    autoComplete="username"
                    autoFocus
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
                    autoComplete="current-password"
                    required
                    error={errors.password}
                />

                <label className="flex items-center gap-2 text-sm text-[#706f6c] dark:text-[#A1A09A]">
                    <input
                        type="checkbox"
                        checked={data.remember}
                        onChange={(event) =>
                            setData('remember', event.target.checked)
                        }
                        className="rounded border-[#e3e3e0] dark:border-[#3E3E3A]"
                    />
                    Remember me
                </label>

                <button
                    type="submit"
                    disabled={processing}
                    className="rounded-sm border border-black bg-[#1b1b18] px-5 py-2 text-sm leading-normal text-white hover:bg-black disabled:cursor-not-allowed disabled:opacity-50 dark:border-[#eeeeec] dark:bg-[#eeeeec] dark:text-[#1C1C1A] dark:hover:bg-white"
                >
                    {processing ? 'Logging in…' : 'Log in'}
                </button>
            </form>

            <p className="mt-4 text-sm text-[#706f6c] dark:text-[#A1A09A]">
                No account yet?{' '}
                <Link
                    href={registerPage()}
                    className="font-medium text-[#1b1b18] underline underline-offset-4 dark:text-[#EDEDEC]"
                >
                    Register
                </Link>
            </p>
        </AuthLayout>
    );
}
