<?php

namespace App\Models\Concerns;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Stores operational_date as a plain "Y-m-d" string on every database driver,
 * so equality and range filters compare dates directly and can use indexes.
 * Reading returns a CarbonImmutable at midnight; only its calendar date is
 * meaningful (use OpsClock::date() to place it in the operational timezone).
 */
trait HasOperationalDate
{
    protected function operationalDate(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? CarbonImmutable::createFromFormat('Y-m-d', substr($value, 0, 10))->startOfDay() : null,
            set: fn (DateTimeInterface|string $value) => $value instanceof DateTimeInterface ? $value->format('Y-m-d') : substr($value, 0, 10),
        );
    }
}
