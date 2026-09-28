<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Throwable;

class HttpRetry
{
    /**
     * Only network drops, rate limits and gateway errors are worth another try; a rejected payload fails the same way twice.
     */
    public static function shouldRetry(Throwable $exception): bool
    {
        return $exception instanceof ConnectionException
            || ($exception instanceof RequestException && in_array($exception->response->status(), [408, 429, 502, 503, 504], true));
    }
}
