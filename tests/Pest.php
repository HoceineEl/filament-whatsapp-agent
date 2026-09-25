<?php

declare(strict_types=1);

use HoceineEl\WhatsAppAgent\Channels\Inbound\InboundMessage;
use HoceineEl\WhatsAppAgent\Enums\MessageType;
use HoceineEl\WhatsAppAgent\Messaging\ConversationRecorder;
use HoceineEl\WhatsAppAgent\Models\Message;
use HoceineEl\WhatsAppAgent\Tests\Fixtures\Store;
use HoceineEl\WhatsAppAgent\Tests\Fixtures\StoreProfile;
use HoceineEl\WhatsAppAgent\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(TestCase::class, RefreshDatabase::class)->in(__DIR__);

uses()->afterEach(fn () => StoreProfile::$blocked = null)->in('Feature');

function store(array $attributes = []): Store
{
    return Store::create(['name' => 'Corner Shop', 'slug' => 'corner-shop', ...$attributes]);
}

function customerSays(Store $store, string $text, string $phone = '971501234567'): Message
{
    return app(ConversationRecorder::class)->recordInbound($store, new InboundMessage(
        providerMessageId: 'wamid.'.Str::random(12),
        from: $phone,
        name: 'Sara',
        type: MessageType::Text,
        text: $text,
    ));
}
