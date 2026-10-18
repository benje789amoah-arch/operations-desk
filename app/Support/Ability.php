<?php

namespace App\Support;

use App\Enums\Role;

/**
 * The roles-and-permissions matrix. Every ability is registered as a Gate in
 * AppServiceProvider and checked on the server (routes, components, actions).
 * The personnel page renders this same matrix, so documentation and
 * enforcement cannot disagree.
 */
final class Ability
{
    public const RECORD_UPDATES = 'record-updates';

    public const SUBMIT_HANDOVER = 'submit-handover';

    public const ASSIGN_ACTIVITIES = 'assign-activities';

    public const SCHEDULE_ACTIVITIES = 'schedule-activities';

    public const MANAGE_DEFINITIONS = 'manage-definitions';

    public const VIEW_MANAGEMENT_REPORTS = 'view-management-reports';

    public const VIEW_TEAM_ACTIVITY = 'view-team-activity';

    public const EXPORT_REPORTS = 'export-reports';

    public const MANAGE_PERSONNEL = 'manage-personnel';

    public const VIEW_AUDIT_LOG = 'view-audit-log';

    /**
     * @return array<string, array{label: string, roles: array<int, Role>}>
     */
    public static function matrix(): array
    {
        return [
            self::RECORD_UPDATES => [
                'label' => 'Record Done/Pending updates and remarks',
                'roles' => [Role::Administrator, Role::Supervisor, Role::Support],
            ],
            self::SUBMIT_HANDOVER => [
                'label' => 'Submit and acknowledge shift handovers',
                'roles' => [Role::Administrator, Role::Supervisor, Role::Support],
            ],
            self::ASSIGN_ACTIVITIES => [
                'label' => 'Assign or reassign the responsible person',
                'roles' => [Role::Administrator, Role::Supervisor],
            ],
            self::SCHEDULE_ACTIVITIES => [
                'label' => 'Schedule ad-hoc activity instances',
                'roles' => [Role::Administrator, Role::Supervisor],
            ],
            self::VIEW_TEAM_ACTIVITY => [
                'label' => 'Run the personnel report for any team member',
                'roles' => [Role::Administrator, Role::Supervisor],
            ],
            self::VIEW_MANAGEMENT_REPORTS => [
                'label' => 'Completion trend (management) report',
                'roles' => [Role::Administrator, Role::Supervisor],
            ],
            self::EXPORT_REPORTS => [
                'label' => 'Export reports to CSV',
                'roles' => [Role::Administrator, Role::Supervisor],
            ],
            self::MANAGE_DEFINITIONS => [
                'label' => 'Create, edit and archive activity definitions',
                'roles' => [Role::Administrator],
            ],
            self::MANAGE_PERSONNEL => [
                'label' => 'Manage personnel accounts and roles',
                'roles' => [Role::Administrator],
            ],
            self::VIEW_AUDIT_LOG => [
                'label' => 'View and export the system audit log',
                'roles' => [Role::Administrator],
            ],
        ];
    }

    /**
     * @return array<int, Role>
     */
    public static function rolesFor(string $ability): array
    {
        return self::matrix()[$ability]['roles'] ?? [];
    }
}
