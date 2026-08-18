<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The Indonesian days off, read from Google's public holiday calendar.
 *
 * The dates cannot be computed: they mix the Hijri, Saka, Chinese lunar and
 * Christian calendars, and the government fixes each year's list - the cuti
 * bersama especially - by decree. One request covers every year the feed
 * holds, so the whole feed is fetched and cached under a single key.
 *
 * @phpstan-type Holiday array{name: string, joint: bool, tentative: bool}
 */
class HolidayCalendar
{
    private const FEED = 'https://calendar.google.com/calendar/ical/id.indonesian%23holiday%40group.v.calendar.google.com/public/basic.ics';

    private const CACHE_KEY = 'holidays:id:v1';

    private const FRESH_FOR_DAYS = 30;

    /**
     * A feed that is down must not be asked again on every page view.
     */
    private const RETRY_AFTER_MINUTES = 15;

    /**
     * The feed's own word for a day that is celebrated but still worked,
     * such as the first of Ramadan or new year's eve.
     */
    private const OBSERVANCE = 'Perayaan';

    /**
     * The feed titles joint leave "Cuti Bersama <the holiday it bridges>".
     */
    private const JOINT_PREFIX = 'Cuti Bersama';

    /**
     * The feed's marker for a date the government has not confirmed yet.
     */
    private const TENTATIVE = '(belum pasti)';

    /**
     * The days off of one year, keyed by date.
     *
     * @return array<string, Holiday>
     */
    public function forYear(int $year): array
    {
        return array_filter(
            $this->all(),
            fn (string $date): bool => str_starts_with($date, $year.'-'),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * Every year the feed holds, keyed by date.
     *
     * @return array<string, Holiday>
     */
    public function all(): array
    {
        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached)) {
            return $cached;
        }

        $holidays = $this->fetch();

        /** An empty read means the feed failed, so it is only held briefly. */
        Cache::put(self::CACHE_KEY, $holidays, $holidays === []
            ? now()->addMinutes(self::RETRY_AFTER_MINUTES)
            : now()->addDays(self::FRESH_FOR_DAYS));

        return $holidays;
    }

    /**
     * A feed that cannot be reached leaves the calendar without holidays
     * rather than without a page.
     *
     * @return array<string, Holiday>
     */
    private function fetch(): array
    {
        try {
            $response = Http::timeout(8)->retry(2, 250, throw: false)->get(self::FEED);
        } catch (Throwable) {
            return [];
        }

        return $response->successful() ? $this->parse($response->body()) : [];
    }

    /**
     * Read the feed's events, keeping only the days that are actually off.
     *
     * @return array<string, Holiday>
     */
    public function parse(string $calendar): array
    {
        /** iCalendar folds a long line onto the next one behind a space. */
        $unfolded = (string) preg_replace("/\r?\n[ \t]/", '', $calendar);

        if (preg_match_all('/BEGIN:VEVENT(.*?)END:VEVENT/s', $unfolded, $events) === false) {
            return [];
        }

        $holidays = [];

        foreach ($events[1] as $event) {
            $holiday = $this->holidayOf($event);

            if ($holiday === null) {
                continue;
            }

            [$date, $entry] = $holiday;

            /** A national holiday outranks any joint leave sharing its day. */
            if (isset($holidays[$date]) && ! $holidays[$date]['joint']) {
                continue;
            }

            $holidays[$date] = $entry;
        }

        ksort($holidays);

        return $holidays;
    }

    /**
     * @return array{string, Holiday}|null
     */
    private function holidayOf(string $event): ?array
    {
        if (preg_match('/DTSTART;VALUE=DATE:(\d{4})(\d{2})(\d{2})/', $event, $start) !== 1) {
            return null;
        }

        if (preg_match('/\nSUMMARY[^:\n]*:(.*)/', $event, $summary) !== 1) {
            return null;
        }

        $description = preg_match('/\nDESCRIPTION[^:\n]*:(.*)/', $event, $matched) === 1
            ? $this->unescape($matched[1])
            : '';

        if (str_starts_with($description, self::OBSERVANCE)) {
            return null;
        }

        $name = $this->unescape($summary[1]);

        return [
            implode('-', [$start[1], $start[2], $start[3]]),
            [
                'name' => trim(str_replace(self::TENTATIVE, '', $name)),
                'joint' => str_starts_with($name, self::JOINT_PREFIX),
                'tentative' => str_contains($name, self::TENTATIVE),
            ],
        ];
    }

    /**
     * iCalendar escapes the commas, semicolons and newlines inside a value.
     */
    private function unescape(string $value): string
    {
        return trim(str_replace(
            ['\,', '\;', '\n', '\N'],
            [',', ';', "\n", "\n"],
            trim($value),
        ));
    }
}
