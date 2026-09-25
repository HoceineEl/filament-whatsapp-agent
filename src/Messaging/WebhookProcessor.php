<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Messaging;

use Carbon\CarbonInterface;
use HoceineEl\WhatsAppAgent\Channels\Inbound\ConnectionUpdate;
use HoceineEl\WhatsAppAgent\Channels\Inbound\InboundMessage;
use HoceineEl\WhatsAppAgent\Channels\Inbound\OwnerReply;
use HoceineEl\WhatsAppAgent\Channels\Inbound\StatusUpdate;
use HoceineEl\WhatsAppAgent\Contracts\AgentOwner;
use HoceineEl\WhatsAppAgent\Enums\ConnectionStatus;
use HoceineEl\WhatsAppAgent\Enums\MessageAuthor;
use HoceineEl\WhatsAppAgent\Enums\MessageDirection;
use HoceineEl\WhatsAppAgent\Enums\MessageStatus;
use HoceineEl\WhatsAppAgent\Enums\MessageType;
use HoceineEl\WhatsAppAgent\Jobs\ProcessInboundMessage;
use HoceineEl\WhatsAppAgent\WhatsAppAgent;
use Illuminate\Database\Eloquent\Model;

class WebhookProcessor
{
    public function __construct(
        private readonly ConversationRecorder $recorder,
        private readonly HandoffService $handoff,
    ) {}

    /**
     * @param  list<InboundMessage|OwnerReply|StatusUpdate|ConnectionUpdate>  $events
     */
    public function process(AgentOwner $owner, array $events): void
    {
        foreach ($events as $event) {
            match (true) {
                $event instanceof InboundMessage => $this->inbound($owner, $event),
                $event instanceof OwnerReply => $this->ownerReply($owner, $event),
                $event instanceof StatusUpdate => $this->status($owner, $event),
                $event instanceof ConnectionUpdate => $this->connection($owner, $event),
            };
        }
    }

    public function inbound(AgentOwner $owner, InboundMessage $event): ?Model
    {
        if ($this->isStale($event->sentAt)) {
            return null;
        }

        $message = $this->recorder->recordInbound($owner, $event);

        if ($message !== null) {
            ProcessInboundMessage::dispatch($message)->delay(now()->addSeconds((int) config('whatsapp-agent.reply_debounce_seconds')));
        }

        return $message;
    }

    /**
     * The owner typed on the phone itself: log it in the inbox and let the assistant step back in that chat.
     */
    private function ownerReply(AgentOwner $owner, OwnerReply $event): void
    {
        $conversation = WhatsAppAgent::contactModel()::query()->forOwner($owner)->withPhone($event->to)->first()?->conversation;

        if ($conversation === null || $this->isStale($event->sentAt) || $this->isOurOwnEcho($conversation, $event)) {
            return;
        }

        WhatsAppAgent::messageModel()::create([
            WhatsAppAgent::ownerKey() => $owner->getKey(),
            'conversation_id' => $conversation->getKey(),
            'direction' => MessageDirection::Outbound,
            'author' => MessageAuthor::Staff,
            'type' => $event->type,
            'body' => $event->text,
            'meta' => ['source' => 'owner_phone'],
            'status' => MessageStatus::Sent,
            'provider_message_id' => $event->providerMessageId,
            'sent_at' => $event->sentAt ?? now(),
        ]);

        $this->handoff->pauseForOwnerPhone($conversation);
    }

    private function isOurOwnEcho(Model $conversation, OwnerReply $event): bool
    {
        $recent = WhatsAppAgent::messageModel()::query()
            ->forOwner($conversation->ownerId())
            ->whereBelongsTo($conversation)
            ->outbound()
            ->where('created_at', '>=', now()->subMinutes(3))
            ->get(['provider_message_id', 'body', 'type']);

        return $recent->contains(function (Model $message) use ($event): bool {
            if ($message->provider_message_id === $event->providerMessageId) {
                return true;
            }

            if ($event->type === MessageType::Audio && $message->type === MessageType::Audio) {
                return true;
            }

            return filled($event->text) && trim((string) $message->body) === trim((string) $event->text);
        });
    }

    private function isStale(?CarbonInterface $sentAt): bool
    {
        return (bool) $sentAt?->lt(now()->subMinutes((int) config('whatsapp-agent.max_inbound_age_minutes')));
    }

    private function status(AgentOwner $owner, StatusUpdate $event): void
    {
        $message = WhatsAppAgent::messageModel()::query()->forOwner($owner)->outbound()->where('provider_message_id', $event->providerMessageId)->first();

        if ($message === null || ($event->status !== MessageStatus::Failed && $event->status->rank() <= $message->status->rank())) {
            return;
        }

        $message->forceFill(['status' => $event->status, 'error' => $event->error])->save();
    }

    private function connection(AgentOwner $owner, ConnectionUpdate $event): void
    {
        $owner->forceFill([
            'whatsapp_status' => $event->status,
            'whatsapp_number' => $event->status === ConnectionStatus::Connected ? ($event->number ?? $owner->whatsapp_number) : $owner->whatsapp_number,
        ])->save();
    }
}
