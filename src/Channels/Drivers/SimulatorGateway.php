<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Channels\Drivers;

use HoceineEl\WhatsAppAgent\Channels\Contracts\WhatsAppGateway;
use HoceineEl\WhatsAppAgent\Channels\Inbound\InboundMessage;
use HoceineEl\WhatsAppAgent\Channels\Outbound\OutgoingMessage;
use HoceineEl\WhatsAppAgent\Channels\Outbound\SendResult;
use HoceineEl\WhatsAppAgent\Contracts\AgentOwner;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Delivers nothing: messages live only in the inbox. Powers the playground, demos and tests.
 */
class SimulatorGateway implements WhatsAppGateway
{
    /** @var list<OutgoingMessage> */
    public array $sent = [];

    public function send(AgentOwner $owner, OutgoingMessage $message): SendResult
    {
        $this->sent[] = $message;

        return new SendResult('sim_'.Str::ulid());
    }

    public function downloadMedia(AgentOwner $owner, InboundMessage $message): ?array
    {
        return null;
    }

    public function verifyWebhook(AgentOwner $owner, Request $request): bool
    {
        return false;
    }

    public function parseWebhook(array $payload): array
    {
        return [];
    }
}
