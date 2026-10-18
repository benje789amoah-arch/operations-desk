<?php

namespace App\Console\Commands;

use App\Services\AuditLogger;
use Illuminate\Console\Command;

/**
 * Recomputes the audit log's hash chain. Suitable for monitoring: exits
 * non-zero when an event was altered or removed after it was written.
 */
class VerifyAuditChain extends Command
{
    protected $signature = 'operations:verify-audit-chain';

    protected $description = 'Verify the tamper-evident hash chain of the audit log';

    public function handle(AuditLogger $audit): int
    {
        $result = $audit->verifyChain();

        if ($result['valid']) {
            $this->info("Audit chain intact: {$result['checked']} event(s) verified.");

            return self::SUCCESS;
        }

        $this->error("Audit chain broken at event #{$result['broken_at']}: {$result['reason']}");

        return self::FAILURE;
    }
}
