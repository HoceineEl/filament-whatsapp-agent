<?php

declare(strict_types=1);

use HoceineEl\WhatsAppAgent\Enums\MessageAuthor;
use HoceineEl\WhatsAppAgent\Enums\MessageDirection;
use HoceineEl\WhatsAppAgent\Enums\MessageStatus;
use HoceineEl\WhatsAppAgent\Enums\MessageType;
use HoceineEl\WhatsAppAgent\Enums\WhatsAppDriver;
use HoceineEl\WhatsAppAgent\Models\Message;
use HoceineEl\WhatsAppAgent\WhatsAppAgent;
use Illuminate\Support\Facades\Queue;

beforeEach(fn () => Queue::fake());

function cloudStore()
{
    return store(['whatsapp_driver' => WhatsAppDriver::Cloud, 'whatsapp_credentials' => ['app_secret' => 'meta-secret', 'verify_token' => 'verify-me']]);
}

function postCloud($store, array $payload, string $secret = 'meta-secret')
{
    $body = json_encode($payload);

    return test()->call('POST', route('webhooks.whatsapp', ['driver' => 'cloud', 'token' => $store->webhook_token]), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, $secret),
    ], $body);
}

function cloudPayload(array $value): array
{
    return ['object' => 'whatsapp_business_account', 'entry' => [['changes' => [['field' => 'messages', 'value' => $value]]]]];
}

it('records a signed cloud api message with the sender name', function () {
    $store = cloudStore();

    postCloud($store, cloudPayload([
        'contacts' => [['wa_id' => '212600000000', 'profile' => ['name' => 'Youssef']]],
        'messages' => [['id' => 'wamid.C1', 'from' => '212600000000', 'timestamp' => (string) now()->timestamp, 'type' => 'text', 'text' => ['body' => 'Salam']]],
    ]))->assertOk();

    $message = Message::query()->forOwner($store)->sole();

    expect($message->body)->toBe('Salam')
        ->and($message->conversation->contact->name)->toBe('Youssef');
});

it('rejects a cloud webhook with a forged signature', function () {
    postCloud(cloudStore(), cloudPayload(['messages' => [['id' => 'wamid.X', 'from' => '1', 'type' => 'text', 'text' => ['body' => 'hi']]]]), 'wrong-secret')
        ->assertUnauthorized();

    expect(Message::query()->count())->toBe(0);
});

it('answers the meta verification challenge only with the right token', function () {
    $store = cloudStore();
    $url = route('webhooks.whatsapp.verify', ['token' => $store->webhook_token]);

    $this->get($url.'?hub_mode=subscribe&hub_verify_token=verify-me&hub_challenge=1234')->assertOk()->assertSee('1234');
    $this->get($url.'?hub_mode=subscribe&hub_verify_token=nope&hub_challenge=1234')->assertForbidden();
});

it('keeps delivery receipts moving forward only', function () {
    $store = cloudStore();
    $message = customerSays($store, 'hi');
    $reply = Message::create([
        WhatsAppAgent::ownerKey() => $store->getKey(),
        'conversation_id' => $message->conversation_id,
        'direction' => MessageDirection::Outbound,
        'author' => MessageAuthor::Bot,
        'type' => MessageType::Text,
        'body' => 'Hello!',
        'status' => MessageStatus::Sent,
        'provider_message_id' => 'wamid.OUT',
    ]);

    postCloud($store, cloudPayload(['statuses' => [['id' => 'wamid.OUT', 'status' => 'read']]]))->assertOk();
    postCloud($store, cloudPayload(['statuses' => [['id' => 'wamid.OUT', 'status' => 'delivered']]]))->assertOk();

    expect($reply->refresh()->status)->toBe(MessageStatus::Read);
});

it('parses voice notes, photos and button replies from the cloud api', function () {
    $store = cloudStore();

    postCloud($store, cloudPayload(['messages' => [
        ['id' => 'wamid.A', 'from' => '212600000000', 'type' => 'audio', 'audio' => ['id' => 'media-1', 'mime_type' => 'audio/ogg; codecs=opus']],
        ['id' => 'wamid.I', 'from' => '212600000000', 'type' => 'image', 'image' => ['id' => 'media-2', 'caption' => 'this one', 'mime_type' => 'image/jpeg']],
        ['id' => 'wamid.B', 'from' => '212600000000', 'type' => 'interactive', 'interactive' => ['button_reply' => ['id' => 'yes', 'title' => 'Yes please']]],
    ]]))->assertOk();

    $messages = Message::query()->orderBy('id')->get();

    expect($messages->pluck('type')->all())->toBe([MessageType::Audio, MessageType::Image, MessageType::Button])
        ->and($messages[0]->payload['media_id'])->toBe('media-1')
        ->and($messages[1]->body)->toBe('this one')
        ->and($messages[2]->payload['button_id'])->toBe('yes');
});
