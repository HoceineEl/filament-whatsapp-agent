<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Channels\Inbound;

use Carbon\CarbonImmutable;
use HoceineEl\WhatsAppAgent\Enums\MessageType;

final readonly class OwnerReply
{
    public function __construct(
        public string $providerMessageId,
        public string $to,
        public MessageType $type,
        public ?string $text,
        public ?CarbonImmutable $sentAt = null,
    ) {}
}
