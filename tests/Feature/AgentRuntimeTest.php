<?php

declare(strict_types=1);

use HoceineEl\WhatsAppAgent\Agent\AgentContext;
use HoceineEl\WhatsAppAgent\Agent\AgentRuntime;
use HoceineEl\WhatsAppAgent\Agent\AssistantAgent;
use HoceineEl\WhatsAppAgent\Agent\PromptBuilder;
use HoceineEl\WhatsAppAgent\Enums\ConversationStatus;
use HoceineEl\WhatsAppAgent\Enums\MessageAuthor;
use HoceineEl\WhatsAppAgent\Models\Message;
use HoceineEl\WhatsAppAgent\Tests\Fixtures\StoreProfile;
use HoceineEl\WhatsAppAgent\WhatsAppAgent;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Responses\Data\ToolCall;

beforeEach(fn () => Queue::fake());

it('answers with the ai and records the tools it used', function () {
    AssistantAgent::fake([
        new ToolCall('c1', 'check_stock', ['product' => 'milk']),
        'Yes, milk is in stock.',
    ]);

    $reply = app(AgentRuntime::class)->respond(customerSays(store(), 'Do you have milk?'));

    expect($reply->body)->toBe('Yes, milk is in stock.')
        ->and($reply->author)->toBe(MessageAuthor::Bot)
        ->and($reply->meta['tools'])->toBe(['check_stock'])
        ->and(WhatsAppAgent::toolSummary($reply->meta['tools']))->toBe('Checked stock');
});

it('builds the prompt from the profile knowledge and customer facts', function () {
    $context = AgentContext::for(customerSays(store(), 'hi')->conversation);

    $prompt = app(PromptBuilder::class)->build($context);

    expect($prompt)->toContain('Opening hours: 9am to 9pm')
        ->toContain('Loyalty points: 120')
        ->toContain('Corner Shop')
        ->and(strpos($prompt, 'Opening hours'))->toBeLessThan(strpos($prompt, '## Right now'));
});

it('hands the chat to the team when the profile blocks replies', function () {
    StoreProfile::$blocked = 'Plan expired';
    AssistantAgent::fake(['should not be sent']);

    $message = customerSays(store(), 'hello');
    app(AgentRuntime::class)->respond($message);

    expect($message->conversation->refresh()->status)->toBe(ConversationStatus::NeedsHuman)
        ->and($message->conversation->handoff_reason)->toBe('Plan expired')
        ->and(Message::query()->outbound()->count())->toBe(0);
});

it('lets the profile handle a message before the ai', function () {
    AssistantAgent::fake(fn () => throw new RuntimeException('AI should not run'));

    expect(app(AgentRuntime::class)->respond(customerSays(store(), 'PING')))->toBeNull();
});

it('stops and restarts on opt out keywords', function () {
    $store = store();

    app(AgentRuntime::class)->respond(customerSays($store, 'STOP'));
    $contact = Message::query()->inbound()->first()->conversation->contact;

    expect($contact->refresh()->isOptedOut())->toBeTrue();
});

it('stays quiet on a closing thank you', function () {
    AssistantAgent::fake(['Anything else I can help with today']);
    $store = store();
    app(AgentRuntime::class)->respond(customerSays($store, 'What time do you open?'));

    AssistantAgent::fake(fn () => throw new RuntimeException('AI should not run'));

    expect(app(AgentRuntime::class)->respond(customerSays($store, 'thanks')))->toBeNull();
});
