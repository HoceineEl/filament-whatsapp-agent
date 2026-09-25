<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Exceptions;

use RuntimeException;

class WhatsAppException extends RuntimeException
{
    public static function requestFailed(string $driver, string $action, string $reason): self
    {
        return new self("WhatsApp [{$driver}] could not {$action}: {$reason}");
    }

    public static function notConfigured(string $driver): self
    {
        return new self("WhatsApp [{$driver}] is not configured for this business.");
    }
}
