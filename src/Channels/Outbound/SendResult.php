<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Channels\Outbound;

final readonly class SendResult
{
    public function __construct(public ?string $providerMessageId) {}
}
