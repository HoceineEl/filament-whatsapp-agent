<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Messaging;

use HoceineEl\WhatsAppAgent\Channels\Inbound\InboundMessage;
use HoceineEl\WhatsAppAgent\Contracts\AgentOwner;
use HoceineEl\WhatsAppAgent\Enums\ConversationStatus;
use HoceineEl\WhatsAppAgent\Enums\MessageAuthor;
use HoceineEl\WhatsAppAgent\Enums\MessageDirection;
use HoceineEl\WhatsAppAgent\Enums\MessageStatus;
use HoceineEl\WhatsAppAgent\Support\LanguageDetector;
use HoceineEl\WhatsAppAgent\WhatsAppAgent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ConversationRecorder
{
    /**
     * Persists an inbound message once; returns null when the provider already delivered it.
     */
    public function recordInbound(AgentOwner $owner, InboundMessage $inbound): ?Model
    {
        $exists = WhatsAppAgent::messageModel()::query()->forOwner($owner)->where('provider_message_id', $inbound->providerMessageId)->exists();

        if ($exists) {
            return null;
        }

        try {
            return DB::transaction(function () use ($owner, $inbound): Model {
                $customer = $this->customer($owner, $inbound->from, $inbound->name, $inbound->text);
                $conversation = $this->conversation($customer);

                $message = WhatsAppAgent::messageModel()::create([
                    WhatsAppAgent::ownerKey() => $owner->getKey(),
                    'conversation_id' => $conversation->getKey(),
                    'direction' => MessageDirection::Inbound,
                    'author' => MessageAuthor::Customer,
                    'type' => $inbound->type,
                    'body' => $inbound->text,
                    'payload' => array_filter([
                        'button_id' => $inbound->buttonId,
                        'media_id' => $inbound->mediaId,
                        'mime_type' => $inbound->mimeType,
                        'media_message' => $inbound->type->isMedia() && isset($inbound->raw['key'], $inbound->raw['message']) ? Arr::only($inbound->raw, ['key', 'message']) : null,
                    ]),
                    'provider_message_id' => $inbound->providerMessageId,
                    'status' => MessageStatus::Received,
                    'sent_at' => $inbound->sentAt ?? now(),
                ]);

                $conversation->forceFill([
                    'last_message_at' => now(),
                    'last_inbound_at' => now(),
                    'unread_count' => $conversation->unread_count + 1,
                ])->save();

                return $message;
            });
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }

    public function customer(AgentOwner $owner, string $phone, ?string $name = null, ?string $text = null): Model
    {
        $customer = WhatsAppAgent::contactModel()::withTrashed()->forOwner($owner)->withPhone($phone)->first()
            ?? new (WhatsAppAgent::contactModel())([WhatsAppAgent::ownerKey() => $owner->getKey(), 'phone' => WhatsAppAgent::contactModel()::normalizePhone($phone)]);

        if (method_exists($customer, 'trashed') && $customer->trashed()) {
            $customer->restore();
        }

        $customer->forceFill([
            'name' => $customer->name ?: $this->cleanName($name),
            'locale' => filled($text) ? LanguageDetector::detect((string) $text) : ($customer->locale ?? $owner->agentLocale()),
            'last_seen_at' => now(),
        ])->save();

        return $customer;
    }

    public function conversation(Model $customer): Model
    {
        return WhatsAppAgent::conversationModel()::query()->forOwner($customer->ownerId())->firstOrCreate(
            [WhatsAppAgent::contactKey() => $customer->getKey()],
            [WhatsAppAgent::ownerKey() => $customer->ownerId(), 'status' => ConversationStatus::Bot],
        );
    }

    private function cleanName(?string $name): ?string
    {
        $name = trim((string) preg_replace('/[^\p{L}\p{M}\s\'\.-]/u', '', (string) $name));

        return mb_strlen($name) >= 2 ? mb_substr($name, 0, 80) : null;
    }
}
