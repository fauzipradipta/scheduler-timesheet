import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';

type AuthLayoutProps = {
    title: string;
    description: string;
    children: ReactNode;
};

/**
 * The chrome shared by the login and register pages.
 */
export default function AuthLayout({
    title,
    description,
    children,
}: AuthLayoutProps) {
    return (
        <>
            <Head title={title} />
            <div className="flex min-h-screen items-center justify-center bg-[#FDFDFC] px-6 py-10 text-[#1b1b18] dark:bg-[#0a0a0a] dark:text-[#EDEDEC]">
                <div className="w-full max-w-sm">
                    <header>
                        <p className="text-xs font-medium tracking-[0.2em] text-[#706f6c] uppercase dark:text-[#A1A09A]">
                            Time clock
                        </p>
                        <h1 className="mt-1 text-2xl font-medium">{title}</h1>
                        <p className="mt-2 text-sm text-[#706f6c] dark:text-[#A1A09A]">
                            {description}
                        </p>
                    </header>

                    <section className="mt-6 rounded-lg bg-white p-6 shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0.16)] dark:bg-[#161615] dark:shadow-[inset_0px_0px_0px_1px_#fffaed2d]">
                        {children}
                    </section>
                </div>
            </div>
        </>
    );
}
