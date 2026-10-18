<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use DateTimeInterface;

/**
 * Single place for the desk's time rules.
 *
 * - Every timestamp is stored in UTC (config('app.timezone') is UTC).
 * - "Today", operational dates and report day boundaries are calculated in
 *   config('operations.timezone').
 * - An operational day runs from local midnight (inclusive) to the next local
 *   midnight (exclusive); dayBounds() returns that window as UTC instants.
 */
class OpsClock
{
    public static function timezone(): string
    {
        return config('operations.timezone', 'UTC');
    }

    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(static::timezone());
    }

    public static function today(): CarbonImmutable
    {
        return static::now()->startOfDay();
    }

    /**
     * Parse a Y-m-d string (or date) as a local operational date at midnight.
     */
    public static function date(string|DateTimeInterface|null $date): CarbonImmutable
    {
        if ($date instanceof DateTimeInterface) {
            return CarbonImmutable::parse($date->format('Y-m-d'), static::timezone())->startOfDay();
        }

        return $date ? CarbonImmutable::parse($date, static::timezone())->startOfDay() : static::today();
    }

    /**
     * Accepts user input and falls back to today when it is not a valid Y-m-d date.
     */
    public static function safeDate(?string $date): CarbonImmutable
    {
        if (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
            return static::date($date);
        }

        return static::today();
    }

    /**
     * The UTC window [start, end) covering one local operational date.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function dayBounds(string|DateTimeInterface $date): array
    {
        $start = static::date($date);

        return [$start->utc(), $start->addDay()->utc()];
    }

    /**
     * The UTC window [start, end) covering an inclusive range of local dates.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function rangeBounds(string|DateTimeInterface $from, string|DateTimeInterface $to): array
    {
        return [static::date($from)->utc(), static::date($to)->addDay()->utc()];
    }

    /**
     * Combine a local date and an "H:i" or "H:i:s" time into a UTC instant.
     */
    public static function at(string|DateTimeInterface $date, string $time): CarbonImmutable
    {
        return CarbonImmutable::parse(static::date($date)->format('Y-m-d').' '.$time, static::timezone())->utc();
    }

    public static function local(?DateTimeInterface $moment): ?CarbonImmutable
    {
        return $moment ? CarbonImmutable::instance($moment)->setTimezone(static::timezone()) : null;
    }

    /**
     * @return array<int, CarbonImmutable>
     */
    public static function datesBetween(string|DateTimeInterface $from, string|DateTimeInterface $to): array
    {
        return collect(CarbonPeriod::create(static::date($from), static::date($to)))
            ->map(fn (CarbonInterface $date) => CarbonImmutable::instance($date))
            ->all();
    }

    /**
     * The configured shift that contains the given moment (default: now).
     *
     * @return array{key: string, label: string, start: string, end: string}|null
     */
    public static function currentShift(?DateTimeInterface $moment = null): ?array
    {
        $local = static::local($moment ?? static::now());
        $minutes = $local->hour * 60 + $local->minute;

        foreach (config('operations.shifts', []) as $key => $shift) {
            [$start, $end] = [static::minutes($shift['start']), static::minutes($shift['end'])];
            $inside = $end > $start ? ($minutes >= $start && $minutes < $end) : ($minutes >= $start || $minutes < $end);

            if ($inside) {
                return ['key' => $key] + $shift;
            }
        }

        return null;
    }

    /**
     * The UTC window [start, end) of a configured shift on a local date.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function shiftBounds(string|DateTimeInterface $date, string $shiftKey): array
    {
        $shift = config("operations.shifts.{$shiftKey}");
        $start = static::at($date, $shift['start']);
        $end = static::at($date, $shift['end']);

        return [$start, $end->lessThanOrEqualTo($start) ? $end->addDay() : $end];
    }

    public static function timezoneAbbreviation(): string
    {
        return static::now()->format('T');
    }

    private static function minutes(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return $hours * 60 + $minutes;
    }
}
