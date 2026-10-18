<?php

namespace App\Enums;

/**
 * Desk roles. Abilities for each role are defined as gates in
 * AppServiceProvider and enforced on the server for every request.
 */
enum Role: string
{
    case Administrator = 'administrator';
    case Supervisor = 'supervisor';
    case Support = 'support';

    public function label(): string
    {
        return match ($this) {
            self::Administrator => 'Administrator',
            self::Supervisor => 'Support Supervisor',
            self::Support => 'Support Team Member',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Administrator => 'Manages personnel, activity definitions and system configuration. Full access to records, reports and the audit log.',
            self::Supervisor => 'Records updates, assigns and schedules activities, reviews team activity and exports management reports.',
            self::Support => 'Records activity updates and remarks, reviews handovers and runs operational reports.',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $role) => [$role->value => $role->label()])->all();
    }
}
