<?php

use App\Support\OpsClock;

/*
|--------------------------------------------------------------------------
| View formatting helpers
|--------------------------------------------------------------------------
|
| Stored timestamps are UTC. These helpers convert them to the operational
| timezone for display so templates never format raw UTC values by mistake.
|
*/

if (! function_exists('ops_time')) {
    function ops_time(?DateTimeInterface $moment, bool $seconds = false): string
    {
        return OpsClock::local($moment)?->format($seconds ? 'H:i:s' : 'H:i') ?? '—';
    }
}

if (! function_exists('ops_datetime')) {
    function ops_datetime(?DateTimeInterface $moment, bool $seconds = false): string
    {
        return OpsClock::local($moment)?->format($seconds ? 'd M Y, H:i:s' : 'd M Y, H:i') ?? '—';
    }
}

if (! function_exists('ops_date')) {
    /**
     * Format a calendar date (e.g. an operational_date). Not for timestamps:
     * use ops_format() for those so they are converted to local time first.
     */
    function ops_date(DateTimeInterface|string|null $date, string $format = 'D, d M Y'): string
    {
        return $date ? OpsClock::date($date)->format($format) : '—';
    }
}

if (! function_exists('ops_format')) {
    /**
     * Format a stored UTC timestamp in the operational timezone.
     */
    function ops_format(?DateTimeInterface $moment, string $format): string
    {
        return OpsClock::local($moment)?->format($format) ?? '—';
    }
}

if (! function_exists('ops_is_today')) {
    function ops_is_today(?DateTimeInterface $moment): bool
    {
        return $moment !== null && OpsClock::local($moment)->isSameDay(OpsClock::now());
    }
}

if (! function_exists('ops_tz')) {
    function ops_tz(): string
    {
        return OpsClock::timezoneAbbreviation();
    }
}

if (! function_exists('ops_relative')) {
    function ops_relative(?DateTimeInterface $moment): string
    {
        return $moment ? OpsClock::local($moment)->diffForHumans() : '—';
    }
}
