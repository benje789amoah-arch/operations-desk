<?php

namespace App\Enums;

/**
 * The status a team member can record against a daily activity instance.
 * An instance that has never been updated is still "pending" in storage;
 * the UI distinguishes it as "Not updated" using its latest_update_id.
 */
enum ActivityStatus: string
{
    case Pending = 'pending';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Done => 'Done',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $status) => [$status->value => $status->label()])->all();
    }
}
