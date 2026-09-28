<?php

declare(strict_types=1);

use HoceineEl\WhatsAppAgent\Channels\Inbound\OwnerReply;
use HoceineEl\WhatsAppAgent\Enums\ConversationStatus;
use HoceineEl\WhatsAppAgent\Enums\MessageAuthor;
use HoceineEl\WhatsAppAgent\Enums\MessageStatus;
use HoceineEl\WhatsAppAgent\Enums\MessageType;
use HoceineEl\WhatsAppAgent\Enums\WhatsAppDriver;
use HoceineEl\WhatsAppAgent\Exceptions\WhatsAppException;
use HoceineEl\WhatsAppAgent\Jobs\DeliverMessage;
use HoceineEl\WhatsAppAgent\Messaging\MessageSender;
use HoceineEl\WhatsAppAgent\Messaging\WebhookProcessor;
use HoceineEl\WhatsAppAgent\Models\Message;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    config(['whatsapp-agent.evolution.url' => 'https://evo.test', 'whatsapp-agent.evolution.api_key' => 'key', 'whatsapp-agent.evolution.typing_delay_ms' => 0]);
});

function queuedReply(string $text = 'Your order is on the way'): Message
{
    $inbound = customerSays(store(['whatsapp_driver' => WhatsAppDriver::Evolution]), 'Where is my order?');

    return app(MessageSender::class)->send($inbound->conversation, $text);
}

it('delivers a queued reply through evolution and stores the provider id', function () {
    Http::fake(['evo.test/message/sendText/*' => Http::response(['key' => ['id' => 'EVO-OUT-1']])]);
    $reply = queuedReply();

    app()->call([new DeliverMessage($reply), 'handle']);

    expect($reply->refresh()->status)->toBe(MessageStatus::Sent)
        ->and($reply->provider_message_id)->toBe('EVO-OUT-1');
    Http::assertSent(fn ($request) => $request['number'] === '971501234567' && $request['text'] === 'Your order is on the way');
});

it('does not retry a request whatsapp rejected', function () {
    Http::fake(['evo.test/*' => Http::response(['message' => 'invalid number'], 400)]);
    $reply = queuedReply();

    expect(fn () => app()->call([new DeliverMessage($reply), 'handle']))
        ->toThrow(WhatsAppException::class);

    Http::assertSentCount(1);
});

it('retries once when the gateway is briefly unavailable', function () {
    Http::fakeSequence('evo.test/*')->push('busy', 503)->push(['key' => ['id' => 'EVO-OUT-2']]);
    $reply = queuedReply();

    app()->call([new DeliverMessage($reply), 'handle']);

    expect($reply->refresh()->provider_message_id)->toBe('EVO-OUT-2');
    Http::assertSentCount(2);
});

it('marks a reply failed with the reason once every attempt is used', function () {
    $reply = queuedReply();

    (new DeliverMessage($reply))->failed(new RuntimeException('Number is not on WhatsApp'));

    expect($reply->refresh()->status)->toBe(MessageStatus::Failed)
        ->and($reply->error)->toBe('Number is not on WhatsApp');
});

it('pauses the assistant when the owner answers from their phone', function () {
    $inbound = customerSays($store = store(['whatsapp_driver' => WhatsAppDriver::Evolution]), 'Is the blue one available?');

    app(WebhookProcessor::class)->process($store, [new OwnerReply('OWNER-1', '971501234567', MessageType::Text, 'Yes, I keep one for you', now()->toImmutable())]);

    $conversation = $inbound->conversation->refresh();

    expect($conversation->status)->toBe(ConversationStatus::Human)
        ->and(Message::query()->outbound()->sole()->author)->toBe(MessageAuthor::Staff);
});

it('does not mistake its own sent reply for the owner typing', function () {
    $reply = queuedReply('We open at 9');
    $store = $reply->conversation->owner;

    app(WebhookProcessor::class)->process($store, [new OwnerReply('ECHO-1', '971501234567', MessageType::Text, 'We open at 9', now()->toImmutable())]);

    expect($reply->conversation->refresh()->status)->toBe(ConversationStatus::Bot)
        ->and(Message::query()->outbound()->count())->toBe(1);
});
