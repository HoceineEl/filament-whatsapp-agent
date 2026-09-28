<?php

declare(strict_types=1);

use HoceineEl\WhatsAppAgent\Enums\WhatsAppDriver;
use HoceineEl\WhatsAppAgent\Jobs\ProcessInboundMessage;
use HoceineEl\WhatsAppAgent\Models\Contact;
use HoceineEl\WhatsAppAgent\Models\Message;
use Illuminate\Support\Facades\Queue;

it('records an inbound evolution message and queues the reply', function () {
    Queue::fake();
    $store = store(['whatsapp_driver' => WhatsAppDriver::Evolution]);

    postEvolution($store, evolutionText('Are you open today?'))->assertOk();

    expect(Message::query()->forOwner($store)->sole()->body)->toBe('Are you open today?')
        ->and(Contact::query()->forOwner($store)->sole()->only(['phone', 'name']))->toBe(['phone' => '971501234567', 'name' => 'Sara']);

    Queue::assertPushed(ProcessInboundMessage::class);
});

it('rejects a webhook without the owner token', function () {
    $store = store(['whatsapp_driver' => WhatsAppDriver::Evolution]);

    postEvolution($store, evolutionText('hi'), 'wrong')->assertUnauthorized();

    expect(Message::query()->count())->toBe(0);
});

it('ignores webhooks for an unknown token', function () {
    test()->postJson(route('webhooks.whatsapp', ['driver' => 'evolution', 'token' => 'nope']), evolutionText('hi'))->assertOk();

    expect(Message::query()->count())->toBe(0);
});
