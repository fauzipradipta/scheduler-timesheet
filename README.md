# Scheduler Timesheet

A time clock and monthly timesheet for a single team. People clock in and out
through the browser, mark the days they were sick or away, and export the month
as a filled `.xlsx` that matches the company template.

## What it does

- **Clock in and out**, with an optional note on what was worked on. Only one
  shift can be running at a time.
- **Mark whole days** that have no hours against them, using the template's own
  legend: present, sick, vacation, business trip, permit, and no longer
  working.
- **See the month on a calendar**, with Indonesian public holidays and joint
  leave (*cuti bersama*) already on it.
- **Export the month** as an `.xlsx` filled into the timesheet template each
  user uploads, so the logo, header, totals, and signature blocks stay exactly
  as the template author wrote them. A template must keep the standard layout:
  the employee details in `B4` to `B7`, the period in `B8`, one day per row
  from row 11 to 41 (date, start, end, total, the status columns `E` to `J`,
  and the remark in `K`), and the signature name and dates in rows 54 and 55.
- **Fill the template's own columns.** Any heading a template adds in row 9 or
  10 to the right of `K` (such as Project Name or Project Code) becomes a field
  on the clock and calendar forms, and its values land in that column.
- **Keep a profile.** Name, NTT ID, project, and division are edited on the
  profile page and written into the header of every export.
- **Import a timesheet** back in, from `.xlsx` or CSV, replacing the stored
  entries. An `.xlsx` brings its added columns back with it.

Holidays come from Google's public Indonesian calendar feed. The dates cannot
be computed, because they mix the Hijri, Saka, Chinese lunar, and Christian
calendars, and the government fixes each year's list by decree. The whole feed
is fetched once and cached for 30 days.

## Stack

Laravel 13 on PHP 8.3+, Inertia 3 with React 19 and Tailwind 4, built by Vite.
Routes reach the frontend as typed functions through Laravel Wayfinder. Tests
are written in Pest 5, static analysis is Larastan, and formatting is Pint on
the PHP side and Prettier on the JavaScript side.

Data lives in SQLite by default, including the cache, session, and queue
tables. Nothing here needs Redis or a separate database server.

## Requirements

- PHP 8.3 or newer, with the `sqlite3`, `xml`, `zip`, `curl`, and `mbstring`
  extensions
- Composer
- Node 22

## Getting started

```bash
git clone https://github.com/fauzipradipta/scheduler-timesheet.git
cd scheduler-timesheet
composer setup
```

`composer setup` installs both dependency trees, writes a `.env`, generates an
application key, runs the migrations, and builds the frontend.

Then start everything at once:

```bash
composer dev
```

That runs the PHP server, a queue listener, and the Vite dev server together.
Open http://localhost:8000 and register the first account.

## Working on it

```bash
composer dev          # serve, queue, and Vite together
composer test         # Pint, Larastan, and the Pest suite
composer ci:check     # everything above, plus the frontend checks
vendor/bin/pint       # format PHP
npm run format        # format JavaScript with Prettier
npm run lint          # fix what ESLint can fix
```

Tests run against an in-memory SQLite database, so they need no setup and
leave nothing behind.

If a change to the frontend does not show up, the Vite build is stale. Run
`npm run dev` or `npm run build`.

## How it is laid out

| Path | What lives there |
| --- | --- |
| `app/Http/Controllers/AttendanceController.php` | Clock in and out, day entries, import, export |
| `app/Support/HolidayCalendar.php` | The Indonesian holiday feed, parsed and cached |
| `resources/js/pages` | Inertia pages, one file per screen |
| `app/Http/Controllers/TimesheetTemplateController.php` | Upload and removal of each user's template |
| `app/Rules/FollowsTimesheetLayout.php` | The layout check an uploaded template must pass |
| `app/Support/Timesheets/TemplateColumns.php` | Finds the columns a template adds after the remark |
| `app/Http/Controllers/ProfileController.php` | The profile page and password change |
| `deploy/` | VPS provisioning and release scripts |

Routes are all in `routes/web.php`. There is no `routes/api.php`.

## Deploying

The `deploy/` directory holds everything needed for an Ubuntu VPS.

Once, on a fresh server:

```bash
sudo bash deploy/provision.sh yourdomain.com
```

That installs PHP, nginx, Composer, and Node, clones the app to
`/var/www/scheduler-timesheet`, writes a production `.env`, installs the nginx
site, and runs the first release. It finishes by telling you to point DNS at
the box and issue a certificate with certbot.

For every release after that:

```bash
cd /var/www/scheduler-timesheet && sudo -u www-data bash deploy/deploy.sh
```

The script resets to `origin/master`, so anything edited directly on the
server is discarded and anything not pushed does not ship. It prints the
PHP-FPM reload command to finish with.

Two things worth knowing. Compiled assets are not in git, so the deploy has to
build them or new pages will 500. And the database is a SQLite file inside the
project directory, which the deploy leaves alone but which nothing backs up, so
copy `database/database.sqlite` somewhere off the server on a schedule.

## Known rough edges

**Password reset hands out a new password on the strength of an email address
alone.** There is no emailed link and nothing confirms the requester owns the
mailbox, so anyone who knows a registered address can take that account over.
Attempts are throttled to three per address and IP every 15 minutes, which
slows an attacker down but does not stop one who already knows a real address.
This is fine on a private network and is not safe facing the internet.
