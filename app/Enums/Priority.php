<?php

namespace App\Enums;

enum Priority: string
{
    case Normal = 'normal';
    case High = 'high';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal',
            self::High => 'High',
            self::Critical => 'Critical',
        };
    }

    /**
     * Lower numbers sort first in attention and handover lists.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Critical => 0,
            self::High => 1,
            self::Normal => 2,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $priority) => [$priority->value => $priority->label()])->all();
    }
}
