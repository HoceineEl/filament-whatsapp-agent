<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Channels\Inbound;

use Carbon\CarbonImmutable;
use HoceineEl\WhatsAppAgent\Enums\MessageType;

final readonly class InboundMessage
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $providerMessageId,
        public string $from,
        public ?string $name,
        public MessageType $type,
        public ?string $text,
        public ?string $buttonId = null,
        public ?string $mediaId = null,
        public ?string $mimeType = null,
        public ?CarbonImmutable $sentAt = null,
        public array $raw = [],
    ) {}
}
