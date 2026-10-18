<?php

namespace App\Enums;

use Carbon\CarbonInterface;

/**
 * How often an activity definition produces a daily instance.
 *
 * - Daily:    every calendar day.
 * - Weekdays: Monday to Friday.
 * - Weekly:   on the ISO weekdays listed in recurrence_days (1 = Monday ... 7 = Sunday).
 * - Monthly:  on the day of the month in recurrence_days[0]; months that are
 *             shorter run it on their last day instead of skipping it.
 * - AdHoc:    never generated automatically; a supervisor schedules it for a date.
 */
enum Recurrence: string
{
    case Daily = 'daily';
    case Weekdays = 'weekdays';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case AdHoc = 'adhoc';

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Daily',
            self::Weekdays => 'Weekdays',
            self::Weekly => 'Weekly',
            self::Monthly => 'Monthly',
            self::AdHoc => 'Ad-hoc',
        };
    }

    /**
     * @param  array<int, int>  $days
     */
    public function appliesOn(CarbonInterface $date, array $days = []): bool
    {
        return match ($this) {
            self::Daily => true,
            self::Weekdays => $date->isWeekday(),
            self::Weekly => in_array($date->isoWeekday(), array_map('intval', $days), true),
            self::Monthly => $this->monthlyDayMatches($date, (int) ($days[0] ?? 1)),
            self::AdHoc => false,
        };
    }

    /**
     * @param  array<int, int>  $days
     */
    public function describe(array $days = []): string
    {
        return match ($this) {
            self::Weekly => 'Weekly on '.collect($days)->sort()->map(fn ($day) => self::weekdayName((int) $day))->join(', ', ' and '),
            self::Monthly => 'Monthly on day '.((int) ($days[0] ?? 1)),
            default => $this->label(),
        };
    }

    public static function weekdayName(int $isoDay): string
    {
        return ['', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'][$isoDay] ?? '?';
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $recurrence) => [$recurrence->value => $recurrence->label()])->all();
    }

    private function monthlyDayMatches(CarbonInterface $date, int $dayOfMonth): bool
    {
        return $date->day === min($dayOfMonth, $date->daysInMonth);
    }
}
