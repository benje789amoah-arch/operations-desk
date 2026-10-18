<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;

/**
 * Reads trusted proxies from config at request time (so it works with a
 * cached configuration). Set TRUSTED_PROXIES when running behind a load
 * balancer so HTTPS detection and audit-log IP addresses are correct.
 */
class TrustProxies extends Middleware
{
    protected function proxies()
    {
        $proxies = config('app.trusted_proxies');

        return match (true) {
            blank($proxies) => null,
            $proxies === '*' => '*',
            default => array_map('trim', explode(',', $proxies)),
        };
    }
}
