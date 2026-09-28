<?php

declare(strict_types=1);

use HoceineEl\WhatsAppAgent\Channels\Inbound\InboundMessage;
use HoceineEl\WhatsAppAgent\Contracts\AgentOwner;
use HoceineEl\WhatsAppAgent\Enums\MessageType;
use HoceineEl\WhatsAppAgent\Messaging\ConversationRecorder;
use HoceineEl\WhatsAppAgent\Models\Message;
use HoceineEl\WhatsAppAgent\Tests\Fixtures\Store;
use HoceineEl\WhatsAppAgent\Tests\Fixtures\StoreProfile;
use HoceineEl\WhatsAppAgent\Tests\TestCase;
use Illuminate\Support\Str;

uses(TestCase::class)->in(__DIR__);

uses()->afterEach(fn () => StoreProfile::$blocked = null)->in('Feature');

function store(array $attributes = []): Store
{
    return Store::create(['name' => 'Corner Shop', 'slug' => 'corner-shop', ...$attributes]);
}

function customerSays(AgentOwner $store, string $text, string $phone = '971501234567'): Message
{
    return app(ConversationRecorder::class)->recordInbound($store, new InboundMessage(
        providerMessageId: 'wamid.'.Str::random(12),
        from: $phone,
        name: 'Sara',
        type: MessageType::Text,
        text: $text,
    ));
}

function postEvolution($store, array $payload, ?string $token = null)
{
    return test()->postJson(
        route('webhooks.whatsapp', ['driver' => 'evolution', 'token' => $store->webhook_token]),
        $payload,
        [config('whatsapp-agent.evolution.token_header') => $token ?? $store->webhook_token],
    );
}

function evolutionText(string $text): array
{
    return ['event' => 'messages.upsert', 'instance' => 'wa_corner-shop', 'data' => [
        'key' => ['remoteJid' => '971501234567@s.whatsapp.net', 'fromMe' => false, 'id' => 'EV1'],
        'pushName' => 'Sara',
        'message' => ['conversation' => $text],
        'messageType' => 'conversation',
        'messageTimestamp' => now()->timestamp,
    ]];
}
