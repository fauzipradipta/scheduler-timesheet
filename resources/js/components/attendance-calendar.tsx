import { useForm } from '@inertiajs/react';
import type { SyntheticEvent } from 'react';
import { useMemo, useState } from 'react';
import { destroy, store } from '@/routes/attendance/entry';

export type AttendanceEntry = {
    id: string;
    clockedInAt: string;
    clockedOutAt: string | null;
    status: string;
    description: string;
};

type AttendanceCalendarProps = {
    entries: AttendanceEntry[];
};

const WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

/** The legend printed on the timesheet template itself. */
const STATUSES: Record<string, string> = {
    P: 'Present',
    S: 'Sick',
    V: 'Vacation',
    BT: 'Business Trip',
    PM: 'Permit',
    X: 'Not Working Anymore',
};

const PRESENT = 'P';

/**
 * The stored timestamps carry their own offset, so read the calendar day and the
 * wall clock straight off the string rather than shifting into the browser zone.
 */
function dayOf(isoDate: string): string {
    return isoDate.slice(0, 10);
}

function timeOf(isoDate: string): string {
    return isoDate.slice(11, 16);
}

function toIsoDay(date: Date): string {
    return [
        date.getFullYear(),
        String(date.getMonth() + 1).padStart(2, '0'),
        String(date.getDate()).padStart(2, '0'),
    ].join('-');
}

function durationOf(item: AttendanceEntry): number {
    if (!item.clockedOutAt || item.status !== PRESENT) {
        return 0;
    }

    return (
        new Date(item.clockedOutAt).getTime() -
        new Date(item.clockedInAt).getTime()
    );
}

function formatHours(milliseconds: number): string {
    const minutes = Math.round(milliseconds / 60000);

    return `${Math.floor(minutes / 60)}h ${String(minutes % 60).padStart(2, '0')}m`;
}

/**
 * Monday-first grid, padded so the first of the month lands under its weekday.
 */
function gridOf(month: Date): (Date | null)[] {
    const first = new Date(month.getFullYear(), month.getMonth(), 1);
    const days = new Date(
        month.getFullYear(),
        month.getMonth() + 1,
        0,
    ).getDate();
    const lead = (first.getDay() + 6) % 7;

    return [
        ...Array.from({ length: lead }, () => null),
        ...Array.from(
            { length: days },
            (_, index) =>
                new Date(month.getFullYear(), month.getMonth(), index + 1),
        ),
    ];
}

export default function AttendanceCalendar({
    entries,
}: AttendanceCalendarProps) {
    const [month, setMonth] = useState(() => {
        const today = new Date();

        return new Date(today.getFullYear(), today.getMonth(), 1);
    });
    const [selected, setSelected] = useState<string | null>(null);

    const byDay = useMemo(() => {
        const days = new Map<string, AttendanceEntry[]>();

        entries.forEach((item) => {
            const day = dayOf(item.clockedInAt);
            days.set(day, [...(days.get(day) ?? []), item]);
        });

        return days;
    }, [entries]);

    const form = useForm({
        date: '',
        status: PRESENT,
        startedAt: '09:00',
        endedAt: '17:00',
        description: '',
    });

    const removal = useForm({});

    const selectedEntries = selected ? (byDay.get(selected) ?? []) : [];

    const handleSelect = (day: string) => {
        setSelected(day);
        form.setData('date', day);
        form.clearErrors();
    };

    const handleSubmit = (event: SyntheticEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit(store(), {
            preserveScroll: true,
            onSuccess: () =>
                form.setData((current) => ({ ...current, description: '' })),
        });
    };

    const monthLabel = month.toLocaleDateString(undefined, {
        month: 'long',
        year: 'numeric',
    });

    return (
        <section className="rounded-lg bg-white p-6 shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0.16)] dark:bg-[#161615] dark:shadow-[inset_0px_0px_0px_1px_#fffaed2d]">
            <div className="flex flex-wrap items-center justify-between gap-4">
                <h2 className="font-medium">Calendar</h2>
                <div className="flex items-center gap-2">
                    <button
                        type="button"
                        aria-label="Previous month"
                        onClick={() =>
                            setMonth(
                                new Date(
                                    month.getFullYear(),
                                    month.getMonth() - 1,
                                    1,
                                ),
                            )
                        }
                        className="rounded-sm border border-[#e3e3e0] px-2 py-1 text-sm hover:border-[#1b1b18] dark:border-[#3E3E3A] dark:hover:border-[#EDEDEC]"
                    >
                        ‹
                    </button>
                    <span className="min-w-[9rem] text-center text-sm">
                        {monthLabel}
                    </span>
                    <button
                        type="button"
                        aria-label="Next month"
                        onClick={() =>
                            setMonth(
                                new Date(
                                    month.getFullYear(),
                                    month.getMonth() + 1,
                                    1,
                                ),
                            )
                        }
                        className="rounded-sm border border-[#e3e3e0] px-2 py-1 text-sm hover:border-[#1b1b18] dark:border-[#3E3E3A] dark:hover:border-[#EDEDEC]"
                    >
                        ›
                    </button>
                </div>
            </div>

            <div className="mt-4 grid grid-cols-7 gap-1">
                {WEEKDAYS.map((weekday) => (
                    <div
                        key={weekday}
                        className="pb-1 text-center text-xs text-[#706f6c] dark:text-[#A1A09A]"
                    >
                        {weekday}
                    </div>
                ))}

                {gridOf(month).map((date, index) => {
                    if (!date) {
                        return <div key={`pad-${index}`} />;
                    }

                    const day = toIsoDay(date);
                    const logged = byDay.get(day) ?? [];
                    const total = logged.reduce(
                        (sum, item) => sum + durationOf(item),
                        0,
                    );

                    return (
                        <button
                            key={day}
                            type="button"
                            onClick={() => handleSelect(day)}
                            className={`flex min-h-[3.5rem] flex-col items-start rounded-sm border p-1.5 text-left text-sm transition ${
                                selected === day
                                    ? 'border-[#1b1b18] dark:border-[#EDEDEC]'
                                    : 'border-[#e3e3e0] hover:border-[#a3a29e] dark:border-[#3E3E3A] dark:hover:border-[#706f6c]'
                            } ${
                                logged.length > 0
                                    ? 'bg-emerald-50 dark:bg-emerald-950/40'
                                    : ''
                            }`}
                        >
                            <span className="flex w-full items-baseline justify-between gap-1">
                                <span className="tabular-nums">
                                    {date.getDate()}
                                </span>
                                {logged.length > 0 && (
                                    <span className="text-[0.6rem] font-medium text-[#706f6c] dark:text-[#A1A09A]">
                                        {[
                                            ...new Set(
                                                logged.map(
                                                    (item) => item.status,
                                                ),
                                            ),
                                        ].join(' ')}
                                    </span>
                                )}
                            </span>
                            {total > 0 && (
                                <span className="mt-auto font-mono text-[0.65rem] text-emerald-700 tabular-nums dark:text-emerald-300">
                                    {formatHours(total)}
                                </span>
                            )}
                        </button>
                    );
                })}
            </div>

            {selected && (
                <div className="mt-6 border-t border-[#e3e3e0] pt-4 dark:border-[#3E3E3A]">
                    <h3 className="text-sm font-medium">
                        {new Date(`${selected}T00:00:00`).toLocaleDateString(
                            undefined,
                            {
                                weekday: 'long',
                                day: 'numeric',
                                month: 'long',
                                year: 'numeric',
                            },
                        )}
                    </h3>

                    {selectedEntries.length > 0 && (
                        <ul className="mt-3 flex flex-col divide-y divide-[#e3e3e0] dark:divide-[#3E3E3A]">
                            {selectedEntries.map((item) => (
                                <li
                                    key={item.id}
                                    className="flex flex-wrap items-start justify-between gap-x-4 gap-y-1 py-2 first:pt-0"
                                >
                                    <div className="min-w-0">
                                        <p className="text-sm">
                                            {item.status === PRESENT ? (
                                                <span className="font-mono tabular-nums">
                                                    {timeOf(item.clockedInAt)}
                                                    {' – '}
                                                    {item.clockedOutAt
                                                        ? timeOf(
                                                              item.clockedOutAt,
                                                          )
                                                        : '…'}
                                                </span>
                                            ) : (
                                                <span>
                                                    {STATUSES[item.status] ??
                                                        item.status}
                                                </span>
                                            )}
                                        </p>
                                        {item.description && (
                                            <p className="mt-0.5 text-sm break-words text-[#706f6c] dark:text-[#A1A09A]">
                                                {item.description}
                                            </p>
                                        )}
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            removal.submit(destroy(item.id), {
                                                preserveScroll: true,
                                            })
                                        }
                                        className="text-sm text-[#706f6c] underline hover:text-[#f53003] dark:text-[#A1A09A] dark:hover:text-[#FF4433]"
                                    >
                                        Remove
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}

                    <form
                        onSubmit={handleSubmit}
                        className="mt-4 flex flex-col gap-3"
                    >
                        <div className="flex flex-wrap gap-3">
                            <label className="flex flex-col gap-1 text-sm">
                                Status
                                <select
                                    value={form.data.status}
                                    onChange={(event) =>
                                        form.setData(
                                            'status',
                                            event.target.value,
                                        )
                                    }
                                    className="rounded-md border border-[#e3e3e0] bg-transparent px-3 py-2 text-sm outline-none focus:border-[#1b1b18] dark:border-[#3E3E3A] dark:bg-[#161615] dark:focus:border-[#EDEDEC]"
                                >
                                    {Object.entries(STATUSES).map(
                                        ([code, label]) => (
                                            <option key={code} value={code}>
                                                {code} — {label}
                                            </option>
                                        ),
                                    )}
                                </select>
                            </label>

                            <label
                                className={`flex flex-col gap-1 text-sm ${form.data.status === PRESENT ? '' : 'hidden'}`}
                            >
                                Start
                                <input
                                    type="time"
                                    value={form.data.startedAt}
                                    onChange={(event) =>
                                        form.setData(
                                            'startedAt',
                                            event.target.value,
                                        )
                                    }
                                    className="rounded-md border border-[#e3e3e0] bg-transparent px-3 py-2 text-sm outline-none focus:border-[#1b1b18] dark:border-[#3E3E3A] dark:focus:border-[#EDEDEC]"
                                />
                            </label>
                            <label
                                className={`flex flex-col gap-1 text-sm ${form.data.status === PRESENT ? '' : 'hidden'}`}
                            >
                                End
                                <input
                                    type="time"
                                    value={form.data.endedAt}
                                    onChange={(event) =>
                                        form.setData(
                                            'endedAt',
                                            event.target.value,
                                        )
                                    }
                                    className="rounded-md border border-[#e3e3e0] bg-transparent px-3 py-2 text-sm outline-none focus:border-[#1b1b18] dark:border-[#3E3E3A] dark:focus:border-[#EDEDEC]"
                                />
                            </label>
                        </div>

                        <input
                            type="text"
                            value={form.data.description}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
                            maxLength={1000}
                            placeholder="Activity / remark"
                            className="w-full rounded-md border border-[#e3e3e0] bg-transparent px-3 py-2 text-sm outline-none placeholder:text-[#a3a29e] focus:border-[#1b1b18] dark:border-[#3E3E3A] dark:focus:border-[#EDEDEC]"
                        />

                        {Object.values(form.errors).map((message) => (
                            <p
                                key={message}
                                className="text-sm text-[#f53003] dark:text-[#FF4433]"
                            >
                                {message}
                            </p>
                        ))}

                        <button
                            type="submit"
                            disabled={form.processing}
                            className="self-start rounded-sm border border-black bg-[#1b1b18] px-5 py-2 text-sm leading-normal text-white hover:bg-black disabled:cursor-not-allowed disabled:opacity-50 dark:border-[#eeeeec] dark:bg-[#eeeeec] dark:text-[#1C1C1A] dark:hover:bg-white"
                        >
                            {form.processing ? 'Saving…' : 'Add entry'}
                        </button>
                    </form>
                </div>
            )}
        </section>
    );
}
