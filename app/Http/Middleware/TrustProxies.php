<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Symfony\Component\HttpFoundation\Request;

class TrustProxies extends Middleware
{
    /**
     * Trust all proxies (Apache reverse proxy on same host).
     *
     * @var array<int, string>|string|null
     */
    protected $proxies = '*';

    /**
     * Use all X-Forwarded-* headers (proto, host, port, etc.).
     *
     * @var int
     */
    protected $headers = Request::HEADER_FORWARDED
        | Request::HEADER_X_FORWARDED_FOR
        | Request::HEADER_X_FORWARDED_HOST
        | Request::HEADER_X_FORWARDED_PORT
        | Request::HEADER_X_FORWARDED_PROTO
        | Request::HEADER_X_FORWARDED_PREFIX;
}

