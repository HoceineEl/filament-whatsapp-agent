<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Messaging;

use HoceineEl\WhatsAppAgent\Channels\Outbound\OutgoingMessage;
use HoceineEl\WhatsAppAgent\Channels\WhatsAppManager;
use HoceineEl\WhatsAppAgent\Contracts\AgentOwner;
use HoceineEl\WhatsAppAgent\WhatsAppAgent;
use Illuminate\Support\Facades\Log;
use Throwable;

class OwnerNotifier
{
    public function __construct(private readonly WhatsAppManager $whatsapp) {}

    /**
     * Sends a private WhatsApp note to the owner's personal number (kept out of the customer inbox).
     */
    public function notify(AgentOwner $owner, string $text): bool
    {
        if (blank($owner->agentOwnerWhatsApp())) {
            return false;
        }

        try {
            $this->whatsapp->for($owner)->send($owner, new OutgoingMessage(to: (string) $owner->agentOwnerWhatsApp(), text: $text));

            return true;
        } catch (Throwable $exception) {
            Log::warning('Owner notification failed', [WhatsAppAgent::ownerKey() => $owner->getKey(), 'error' => $exception->getMessage()]);

            return false;
        }
    }
}
