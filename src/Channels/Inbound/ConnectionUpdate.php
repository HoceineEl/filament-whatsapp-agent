<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Channels\Inbound;

use HoceineEl\WhatsAppAgent\Enums\ConnectionStatus;

final readonly class ConnectionUpdate
{
    public function __construct(
        public ConnectionStatus $status,
        public ?string $number = null,
    ) {}
}
