<?php

/*
|--------------------------------------------------------------------------
| Npontu Operations Desk
|--------------------------------------------------------------------------
|
| Operational rules for the Applications Support desk. Timestamps are always
| stored in UTC (config/app.php keeps 'timezone' => 'UTC'); the timezone set
| here is used to decide what "today" means for the team and to display times.
|
*/

return [

    /*
    | The team's operational timezone. An activity's "operational date" and the
    | boundaries of every reporting day are calculated in this timezone.
    | Ghana observes GMT (UTC+0) all year, so Africa/Accra is the default.
    */
    'timezone' => env('OPERATIONS_TIMEZONE', 'Africa/Accra'),

    /*
    | Shift windows used to label handovers and to filter the handover journal.
    | Times are local to the operational timezone. A shift whose end is not
    | after its start (for example 16:00 to 00:00) runs to the next midnight.
    */
    'shifts' => [
        'night' => ['label' => 'Night shift', 'start' => '00:00', 'end' => '08:00'],
        'morning' => ['label' => 'Morning shift', 'start' => '08:00', 'end' => '16:00'],
        'evening' => ['label' => 'Evening shift', 'start' => '16:00', 'end' => '00:00'],
    ],

    /*
    | Pending instances from this many previous operational days are carried
    | into today's "Requires attention" list and the handover centre.
    */
    'carry_over_days' => (int) env('OPERATIONS_CARRY_OVER_DAYS', 7),

    /*
    | Upper bound for a single report query, in days.
    */
    'max_report_range_days' => (int) env('OPERATIONS_MAX_REPORT_RANGE_DAYS', 366),

    /*
    | Minimum length of a remark. Remarks are mandatory on every update so the
    | next person on the desk always has an explanation to work from.
    */
    'remark_min_length' => 10,

    /*
    | Seed clearly-labelled demonstration accounts and records. Only honoured in
    | the local and testing environments unless explicitly enabled.
    */
    'seed_demo_data' => (bool) env('OPERATIONS_SEED_DEMO_DATA', false),

];
