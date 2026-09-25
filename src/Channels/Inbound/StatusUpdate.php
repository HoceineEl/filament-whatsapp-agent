<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Channels\Inbound;

use HoceineEl\WhatsAppAgent\Enums\MessageStatus;

final readonly class StatusUpdate
{
    public function __construct(
        public string $providerMessageId,
        public MessageStatus $status,
        public ?string $error = null,
    ) {}
}
