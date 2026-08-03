import { Head, useForm } from '@inertiajs/react';
import type { SyntheticEvent } from 'react';
import { useEffect, useMemo, useState } from 'react';
import { store } from '@/routes/attendance';

type AttendanceEntry = {
    id: string;
    clockedInAt: string;
    clockedOutAt: string | null;
    description: string;
};

type AttendanceProps = {
    entries: AttendanceEntry[];
    activeEntry: AttendanceEntry | null;
};

function formatTime(isoDate: string): string {
    return new Date(isoDate).toLocaleTimeString(undefined, {
        hour: '2-digit',
        minute: '2-digit',
    });
}

function formatDate(isoDate: string): string {
    return new Date(isoDate).toLocaleDateString(undefined, {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
    });
}

function formatDuration(milliseconds: number): string {
    const totalSeconds = Math.max(0, Math.floor(milliseconds / 1000));

    return [
        Math.floor(totalSeconds / 3600),
        Math.floor((totalSeconds % 3600) / 60),
        totalSeconds % 60,
    ]
        .map((part) => String(part).padStart(2, '0'))
        .join(':');
}

function entryDuration(entry: AttendanceEntry, now: number): number {
    const clockedInAt = new Date(entry.clockedInAt).getTime();
    const clockedOutAt = entry.clockedOutAt
        ? new Date(entry.clockedOutAt).getTime()
        : now;

    return clockedOutAt - clockedInAt;
}

export default function Attendance({ entries, activeEntry }: AttendanceProps) {
    const [now, setNow] = useState(() => Date.now());
    const { data, setData, transform, submit, processing, errors, reset } =
        useForm({
            action: activeEntry ? 'clock-out' : 'clock-in',
            description: '',
        });

    useEffect(() => {
        const ticker = window.setInterval(() => setNow(Date.now()), 1000);

        return () => window.clearInterval(ticker);
    }, []);

    const todayTotal = useMemo(() => {
        const startOfToday = new Date().setHours(0, 0, 0, 0);

        return entries
            .filter(
                (entry) =>
                    new Date(entry.clockedInAt).getTime() >= startOfToday,
            )
            .reduce((total, entry) => total + entryDuration(entry, now), 0);
    }, [entries, now]);

    const handleSubmit = (event: SyntheticEvent<HTMLFormElement>) => {
        event.preventDefault();

        transform((formData) => ({
            ...formData,
            action: activeEntry ? 'clock-out' : 'clock-in',
        }));

        submit(store(), {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    return (
        <>
            <Head title="Attendance" />
            <div className="min-h-screen bg-[#FDFDFC] px-6 py-10 text-[#1b1b18] dark:bg-[#0a0a0a] dark:text-[#EDEDEC]">
                <div className="mx-auto flex w-full max-w-2xl flex-col gap-6">
                    <header className="flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <p className="text-xs font-medium tracking-[0.2em] text-[#706f6c] uppercase dark:text-[#A1A09A]">
                                Time clock
                            </p>
                            <h1 className="mt-1 text-2xl font-medium">
                                Attendance
                            </h1>
                        </div>
                        <p className="font-mono text-3xl tabular-nums">
                            {new Date(now).toLocaleTimeString()}
                        </p>
                    </header>

                    <section className="rounded-lg bg-white p-6 shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0.16)] dark:bg-[#161615] dark:shadow-[inset_0px_0px_0px_1px_#fffaed2d]">
                        <div className="flex flex-wrap items-center justify-between gap-4">
                            <span
                                className={`inline-flex items-center gap-2 rounded-full px-3 py-1 text-sm font-medium ${
                                    activeEntry
                                        ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                                        : 'bg-[#f5f5f4] text-[#706f6c] dark:bg-[#232322] dark:text-[#A1A09A]'
                                }`}
                            >
                                <span
                                    className={`h-2 w-2 rounded-full ${
                                        activeEntry
                                            ? 'animate-pulse bg-emerald-500'
                                            : 'bg-[#c9c8c4] dark:bg-[#57564f]'
                                    }`}
                                />
                                {activeEntry ? 'Clocked in' : 'Clocked out'}
                            </span>

                            <div className="text-right">
                                <p className="font-mono text-2xl tabular-nums">
                                    {formatDuration(
                                        activeEntry
                                            ? entryDuration(activeEntry, now)
                                            : 0,
                                    )}
                                </p>
                                <p className="text-xs text-[#706f6c] dark:text-[#A1A09A]">
                                    {activeEntry
                                        ? `Since ${formatTime(activeEntry.clockedInAt)}`
                                        : 'Not running'}
                                </p>
                            </div>
                        </div>

                        {activeEntry?.description && (
                            <p className="mt-4 border-l-2 border-[#e3e3e0] pl-3 text-sm text-[#706f6c] dark:border-[#3E3E3A] dark:text-[#A1A09A]">
                                {activeEntry.description}
                            </p>
                        )}

                        <form
                            onSubmit={handleSubmit}
                            className="mt-6 flex flex-col gap-3"
                        >
                            <label
                                htmlFor="description"
                                className="text-sm font-medium"
                            >
                                Description
                            </label>
                            <textarea
                                id="description"
                                value={data.description}
                                onChange={(event) =>
                                    setData('description', event.target.value)
                                }
                                rows={3}
                                maxLength={1000}
                                placeholder={
                                    activeEntry
                                        ? 'What did you work on? (leave blank to keep the current note)'
                                        : 'What are you working on?'
                                }
                                className="w-full resize-y rounded-md border border-[#e3e3e0] bg-transparent px-3 py-2 text-sm outline-none placeholder:text-[#a3a29e] focus:border-[#1b1b18] dark:border-[#3E3E3A] dark:focus:border-[#EDEDEC]"
                            />
                            {errors.description && (
                                <p className="text-sm text-[#f53003] dark:text-[#FF4433]">
                                    {errors.description}
                                </p>
                            )}
                            {errors.action && (
                                <p className="text-sm text-[#f53003] dark:text-[#FF4433]">
                                    {errors.action}
                                </p>
                            )}

                            <button
                                type="submit"
                                disabled={processing}
                                className="rounded-sm border border-black bg-[#1b1b18] px-5 py-2 text-sm leading-normal text-white hover:bg-black disabled:cursor-not-allowed disabled:opacity-50 dark:border-[#eeeeec] dark:bg-[#eeeeec] dark:text-[#1C1C1A] dark:hover:bg-white"
                            >
                                {processing
                                    ? 'Saving…'
                                    : activeEntry
                                      ? 'Clock out'
                                      : 'Clock in'}
                            </button>
                        </form>
                    </section>

                    <section className="rounded-lg bg-white p-6 shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0.16)] dark:bg-[#161615] dark:shadow-[inset_0px_0px_0px_1px_#fffaed2d]">
                        <div className="flex items-baseline justify-between gap-4">
                            <h2 className="font-medium">History</h2>
                            <p className="text-sm text-[#706f6c] dark:text-[#A1A09A]">
                                Today:{' '}
                                <span className="font-mono tabular-nums">
                                    {formatDuration(todayTotal)}
                                </span>
                            </p>
                        </div>

                        {entries.length === 0 ? (
                            <p className="mt-4 text-sm text-[#706f6c] dark:text-[#A1A09A]">
                                No entries yet. Clock in to start tracking.
                            </p>
                        ) : (
                            <ul className="mt-4 flex flex-col divide-y divide-[#e3e3e0] dark:divide-[#3E3E3A]">
                                {entries.map((entry) => (
                                    <li
                                        key={entry.id}
                                        className="flex flex-wrap items-start justify-between gap-x-4 gap-y-1 py-3 first:pt-0 last:pb-0"
                                    >
                                        <div className="min-w-0">
                                            <p className="text-sm">
                                                {formatDate(entry.clockedInAt)}
                                                <span className="mx-2 text-[#c9c8c4] dark:text-[#57564f]">
                                                    ·
                                                </span>
                                                <span className="font-mono tabular-nums">
                                                    {formatTime(
                                                        entry.clockedInAt,
                                                    )}
                                                    {' – '}
                                                    {entry.clockedOutAt
                                                        ? formatTime(
                                                              entry.clockedOutAt,
                                                          )
                                                        : '…'}
                                                </span>
                                            </p>
                                            {entry.description && (
                                                <p className="mt-1 text-sm break-words text-[#706f6c] dark:text-[#A1A09A]">
                                                    {entry.description}
                                                </p>
                                            )}
                                        </div>
                                        <p className="font-mono text-sm tabular-nums">
                                            {formatDuration(
                                                entryDuration(entry, now),
                                            )}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                </div>
            </div>
        </>
    );
}
