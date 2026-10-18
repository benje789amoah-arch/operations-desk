<?php

namespace App\Exceptions;

use App\Models\ActivityInstance;
use RuntimeException;

/**
 * Thrown when someone saves an update based on an outdated view of an
 * activity, i.e. another person recorded an update in the meantime.
 */
class StaleActivityUpdateException extends RuntimeException
{
    public function __construct(public readonly ActivityInstance $current)
    {
        $latest = $current->latestUpdate;

        parent::__construct($latest
            ? sprintf('%s recorded an update at %s while you were editing. Review it below, then submit yours again if it is still needed.', $latest->updater_name, ops_time($latest->created_at))
            : 'This activity changed while you were editing. Review the latest state, then submit again.');
    }
}
