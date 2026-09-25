<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Channels\Contracts;

use HoceineEl\WhatsAppAgent\Channels\Inbound\ConnectionUpdate;
use HoceineEl\WhatsAppAgent\Channels\Inbound\InboundMessage;
use HoceineEl\WhatsAppAgent\Channels\Inbound\OwnerReply;
use HoceineEl\WhatsAppAgent\Channels\Inbound\StatusUpdate;
use HoceineEl\WhatsAppAgent\Channels\Outbound\OutgoingMessage;
use HoceineEl\WhatsAppAgent\Channels\Outbound\SendResult;
use HoceineEl\WhatsAppAgent\Contracts\AgentOwner;
use Illuminate\Http\Request;

interface WhatsAppGateway
{
    public function send(AgentOwner $owner, OutgoingMessage $message): SendResult;

    /**
     * @return array{data: string, mime: string}|null
     */
    public function downloadMedia(AgentOwner $owner, InboundMessage $message): ?array;

    public function verifyWebhook(AgentOwner $owner, Request $request): bool;

    /**
     * @param  array<string, mixed>  $payload
     * @return list<InboundMessage|OwnerReply|StatusUpdate|ConnectionUpdate>
     */
    public function parseWebhook(array $payload): array;
}
