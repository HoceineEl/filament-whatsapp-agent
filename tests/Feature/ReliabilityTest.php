<?php

declare(strict_types=1);

use HoceineEl\WhatsAppAgent\Agent\AgentRuntime;
use HoceineEl\WhatsAppAgent\Agent\AssistantAgent;
use HoceineEl\WhatsAppAgent\Channels\Inbound\InboundMessage;
use HoceineEl\WhatsAppAgent\Enums\ConversationStatus;
use HoceineEl\WhatsAppAgent\Enums\MessageAuthor;
use HoceineEl\WhatsAppAgent\Enums\MessageType;
use HoceineEl\WhatsAppAgent\Jobs\ProcessInboundMessage;
use HoceineEl\WhatsAppAgent\Messaging\MessageSender;
use HoceineEl\WhatsAppAgent\Messaging\WebhookProcessor;
use HoceineEl\WhatsAppAgent\Models\Message;
use Illuminate\Support\Facades\Queue;

beforeEach(fn () => Queue::fake());

it('never answers a customer who opted out until they opt back in', function () {
    AssistantAgent::fake(['Welcome back!']);
    $store = store();
    $runtime = app(AgentRuntime::class);

    $runtime->respond(customerSays($store, 'Stop.'));

    expect($runtime->respond(customerSays($store, 'Do you have milk?')))->toBeNull();

    $runtime->respond(customerSays($store, 'START'));

    expect($runtime->respond(customerSays($store, 'Do you have milk?'))->body)->toBe('Welcome back!');
});

it('does not answer twice when a retried job finds a reply already sent', function () {
    AssistantAgent::fake(['First answer', 'Second answer']);
    $inbound = customerSays(store(), 'Do you deliver?');
    $runtime = app(AgentRuntime::class);

    $runtime->respond($inbound);

    expect($runtime->respond($inbound))->toBeNull()
        ->and(Message::query()->outbound()->pluck('body')->all())->toBe(['First answer']);
});

it('answers a burst of messages once, on the latest one', function () {
    AssistantAgent::fake(['One answer for both']);
    $store = store();
    $first = customerSays($store, 'Hi');
    $second = customerSays($store, 'Do you sell eggs?');
    $runtime = app(AgentRuntime::class);

    expect($runtime->respond($first))->toBeNull()
        ->and($runtime->respond($second)->body)->toBe('One answer for both');
});

it('queues the reply job on the configured queue', function () {
    config(['whatsapp-agent.queue' => 'whatsapp']);

    app(WebhookProcessor::class)->inbound(store(), new InboundMessage('wamid.q1', '971501234567', 'Sara', MessageType::Text, 'hello'));

    Queue::assertPushedOn('whatsapp', ProcessInboundMessage::class);
});

it('ignores a message the provider delivers twice', function () {
    $store = store();
    $event = new InboundMessage('wamid.same', '971501234567', 'Sara', MessageType::Text, 'hello');

    app(WebhookProcessor::class)->inbound($store, $event);
    app(WebhookProcessor::class)->inbound($store, $event);

    expect(Message::query()->count())->toBe(1);
    Queue::assertPushed(ProcessInboundMessage::class, 1);
});

it('drops messages older than the allowed age after an outage', function () {
    $old = new InboundMessage('wamid.old', '971501234567', 'Sara', MessageType::Text, 'hello', sentAt: now()->subHour()->toImmutable());

    expect(app(WebhookProcessor::class)->inbound(store(), $old))->toBeNull()
        ->and(Message::query()->count())->toBe(0);
});

it('hands off with a fallback reply when the ai fails, and resumes on the next message', function () {
    $store = store();
    $runtime = app(AgentRuntime::class);

    AssistantAgent::fake(fn () => throw new RuntimeException('Gemini is down'));
    $fallback = $runtime->respond(customerSays($store, 'Where is my order?'));
    $conversation = $fallback->conversation->refresh();

    expect($fallback->author)->toBe(MessageAuthor::System)
        ->and($conversation->status)->toBe(ConversationStatus::NeedsHuman);

    AssistantAgent::fake(['It ships today.']);

    expect($runtime->respond(customerSays($store, 'Any news?'))->body)->toBe('It ships today.')
        ->and($conversation->refresh()->status)->toBe(ConversationStatus::Bot);
});

it('stops the ai after the daily reply limit and hands the chat to the team', function () {
    $store = store(['settings' => ['max_ai_replies_per_day' => 1]]);
    $runtime = app(AgentRuntime::class);
    AssistantAgent::fake(['Sure, what size?', 'should not be sent']);

    $runtime->respond(customerSays($store, 'I want shoes'));
    $reply = $runtime->respond(customerSays($store, 'Size 42'));

    expect($reply->author)->toBe(MessageAuthor::System)
        ->and($reply->conversation->refresh()->status)->toBe(ConversationStatus::NeedsHuman);
});

it('stays silent while the team handles the chat', function () {
    AssistantAgent::fake(fn () => throw new RuntimeException('AI should not run'));
    $inbound = customerSays(store(), 'hello');
    $inbound->conversation->forceFill(['status' => ConversationStatus::Human])->save();

    expect(app(AgentRuntime::class)->respond($inbound))->toBeNull();
});

it('splits long replies into whatsapp sized bubbles on paragraph breaks', function () {
    $chunks = MessageSender::chunks(str_repeat('a', 900)."\n\n".str_repeat('b', 900), 1000);

    expect($chunks)->toBe([str_repeat('a', 900), str_repeat('b', 900)])
        ->and(MessageSender::chunks(str_repeat('c', 2500), 1000))->toHaveCount(3)
        ->and(MessageSender::chunks('   '))->toBe([]);
});
