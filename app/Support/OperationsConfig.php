<?php

namespace App\Support;

use DateTimeZone;
use InvalidArgumentException;

/**
 * Fails fast on a misconfigured environment. A wrong timezone or shift
 * definition would silently put work on the wrong operational date, so the
 * application refuses to boot with a message saying exactly what to fix.
 */
class OperationsConfig
{
    /**
     * @return array<int, string> Problems found; empty when the configuration is valid.
     */
    public static function problems(): array
    {
        $problems = [];
        $timezone = config('operations.timezone');

        if (! is_string($timezone) || ! in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            $problems[] = sprintf('OPERATIONS_TIMEZONE "%s" is not a valid timezone identifier (for example Africa/Accra).', is_scalar($timezone) ? $timezone : gettype($timezone));
        }

        $carryOver = config('operations.carry_over_days');
        if (! is_int($carryOver) || $carryOver < 0 || $carryOver > 90) {
            $problems[] = 'OPERATIONS_CARRY_OVER_DAYS must be a whole number between 0 and 90.';
        }

        $maxRange = config('operations.max_report_range_days');
        if (! is_int($maxRange) || $maxRange < 1 || $maxRange > 3660) {
            $problems[] = 'OPERATIONS_MAX_REPORT_RANGE_DAYS must be a whole number between 1 and 3660.';
        }

        $shifts = config('operations.shifts');
        if (! is_array($shifts) || $shifts === []) {
            $problems[] = 'At least one shift must be defined in config/operations.php.';
        } else {
            foreach ($shifts as $key => $shift) {
                foreach (['start', 'end'] as $edge) {
                    if (! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) ($shift[$edge] ?? ''))) {
                        $problems[] = "Shift \"{$key}\" needs a {$edge} time in HH:MM format.";
                    }
                }

                if (blank($shift['label'] ?? null)) {
                    $problems[] = "Shift \"{$key}\" needs a label.";
                }
            }
        }

        return $problems;
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function assertValid(): void
    {
        if ($problems = static::problems()) {
            throw new InvalidArgumentException('Invalid operations configuration: '.implode(' ', $problems));
        }
    }
}
