<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Channels\Drivers;

use Carbon\CarbonImmutable;
use HoceineEl\WhatsAppAgent\Channels\Contracts\WhatsAppGateway;
use HoceineEl\WhatsAppAgent\Channels\Inbound\InboundMessage;
use HoceineEl\WhatsAppAgent\Channels\Inbound\StatusUpdate;
use HoceineEl\WhatsAppAgent\Channels\Outbound\OutgoingMessage;
use HoceineEl\WhatsAppAgent\Channels\Outbound\SendResult;
use HoceineEl\WhatsAppAgent\Contracts\AgentOwner;
use HoceineEl\WhatsAppAgent\Enums\MessageStatus;
use HoceineEl\WhatsAppAgent\Enums\MessageType;
use HoceineEl\WhatsAppAgent\Exceptions\WhatsAppException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Meta WhatsApp Cloud API.
 *
 * @see https://developers.facebook.com/docs/whatsapp/cloud-api
 */
class CloudApiGateway implements WhatsAppGateway
{
    public function send(AgentOwner $owner, OutgoingMessage $message): SendResult
    {
        $response = $this->client($owner)->post($this->endpoint($owner, 'messages'), [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $message->to,
            ...($message->isVoice() ? ['type' => 'audio', 'audio' => ['id' => $this->uploadMedia($owner, $message)]] : $this->body($message)),
        ]);

        $this->ensureSuccessful($response, 'send message');

        return new SendResult($response->json('messages.0.id'));
    }

    public function downloadMedia(AgentOwner $owner, InboundMessage $message): ?array
    {
        if (blank($message->mediaId)) {
            return null;
        }

        $meta = $this->client($owner)->get($this->graphUrl()."/{$message->mediaId}");

        if ($meta->failed() || blank($meta->json('url'))) {
            return null;
        }

        $file = $this->client($owner)->get((string) $meta->json('url'));

        return $file->successful()
            ? ['data' => $file->body(), 'mime' => (string) ($meta->json('mime_type') ?? $message->mimeType ?? 'application/octet-stream')]
            : null;
    }

    public function verifyWebhook(AgentOwner $owner, Request $request): bool
    {
        $secret = (string) ($owner->credential('app_secret') ?: config('whatsapp-agent.cloud.app_secret'));
        $signature = (string) $request->header('X-Hub-Signature-256');

        if ($secret === '' || ! str_starts_with($signature, 'sha256=')) {
            return false;
        }

        return hash_equals('sha256='.hash_hmac('sha256', $request->getContent(), $secret), $signature);
    }

    public function parseWebhook(array $payload): array
    {
        $events = [];

        foreach ((array) ($payload['entry'] ?? []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                $value = (array) ($change['value'] ?? []);
                $names = collect((array) ($value['contacts'] ?? []))->mapWithKeys(fn (array $contact): array => [(string) ($contact['wa_id'] ?? '') => $contact['profile']['name'] ?? null]);

                foreach ((array) ($value['messages'] ?? []) as $message) {
                    $events[] = $this->parseMessage((array) $message, $names->get((string) ($message['from'] ?? '')));
                }

                foreach ((array) ($value['statuses'] ?? []) as $status) {
                    $mapped = match ($status['status'] ?? null) {
                        'sent' => MessageStatus::Sent,
                        'delivered' => MessageStatus::Delivered,
                        'read' => MessageStatus::Read,
                        'failed' => MessageStatus::Failed,
                        default => null,
                    };

                    if ($mapped !== null && filled($status['id'] ?? null)) {
                        $events[] = new StatusUpdate((string) $status['id'], $mapped, $status['errors'][0]['title'] ?? null);
                    }
                }
            }
        }

        return $events;
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function parseMessage(array $message, ?string $name): InboundMessage
    {
        $type = (string) ($message['type'] ?? 'unsupported');

        [$kind, $text, $buttonId, $mediaId, $mime] = match ($type) {
            'text' => [MessageType::Text, $message['text']['body'] ?? '', null, null, null],
            'interactive' => [
                MessageType::Button,
                $message['interactive']['button_reply']['title'] ?? $message['interactive']['list_reply']['title'] ?? '',
                $message['interactive']['button_reply']['id'] ?? $message['interactive']['list_reply']['id'] ?? null,
                null,
                null,
            ],
            'button' => [MessageType::Button, $message['button']['text'] ?? '', $message['button']['payload'] ?? null, null, null],
            'audio' => [MessageType::Audio, null, null, $message['audio']['id'] ?? null, $message['audio']['mime_type'] ?? null],
            'image' => [MessageType::Image, $message['image']['caption'] ?? null, null, $message['image']['id'] ?? null, $message['image']['mime_type'] ?? null],
            'document' => [MessageType::Document, $message['document']['caption'] ?? null, null, $message['document']['id'] ?? null, $message['document']['mime_type'] ?? null],
            'video' => [MessageType::Video, $message['video']['caption'] ?? null, null, null, $message['video']['mime_type'] ?? null],
            'location' => [MessageType::Location, trim(($message['location']['name'] ?? '').' '.($message['location']['latitude'] ?? '').','.($message['location']['longitude'] ?? '')), null, null, null],
            default => [MessageType::Unsupported, null, null, null, null],
        };

        return new InboundMessage(
            providerMessageId: (string) ($message['id'] ?? Str::uuid()),
            from: (string) ($message['from'] ?? ''),
            name: $name,
            type: $kind,
            text: $text,
            buttonId: $buttonId,
            mediaId: $mediaId,
            mimeType: $mime,
            sentAt: isset($message['timestamp']) ? CarbonImmutable::createFromTimestampUTC((int) $message['timestamp']) : null,
            raw: $message,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function body(OutgoingMessage $message): array
    {
        if ($message->template !== null) {
            return [
                'type' => 'template',
                'template' => [
                    'name' => $message->template['name'],
                    'language' => ['code' => $message->template['language']],
                    'components' => [[
                        'type' => 'body',
                        'parameters' => array_map(fn (string $value): array => ['type' => 'text', 'text' => $value], $message->template['parameters']),
                    ]],
                ],
            ];
        }

        if ($message->buttons !== []) {
            return [
                'type' => 'interactive',
                'interactive' => [
                    'type' => 'button',
                    'body' => ['text' => Str::limit($message->text, 1024, '…')],
                    'action' => [
                        'buttons' => collect($message->buttons)->take(3)->map(fn (array $button): array => [
                            'type' => 'reply',
                            'reply' => ['id' => $button['id'], 'title' => Str::limit($button['title'], 20, '')],
                        ])->values()->all(),
                    ],
                ],
            ];
        }

        return ['type' => 'text', 'text' => ['preview_url' => false, 'body' => $message->text]];
    }

    private function uploadMedia(AgentOwner $owner, OutgoingMessage $message): string
    {
        $response = $this->client($owner)
            ->attach('file', (string) file_get_contents((string) $message->audioPath), basename((string) $message->audioPath), ['Content-Type' => (string) $message->audioMime])
            ->post($this->endpoint($owner, 'media'), ['messaging_product' => 'whatsapp', 'type' => (string) $message->audioMime]);

        $this->ensureSuccessful($response, 'upload media');

        return (string) $response->json('id');
    }

    private function client(AgentOwner $owner): PendingRequest
    {
        return Http::withToken((string) $owner->credential('access_token'))
            ->acceptJson()
            ->timeout((int) config('whatsapp-agent.http.timeout'))
            ->connectTimeout((int) config('whatsapp-agent.http.connect_timeout'))
            ->retry((int) config('whatsapp-agent.http.retries'), 300, throw: false);
    }

    private function endpoint(AgentOwner $owner, string $path): string
    {
        return $this->graphUrl().'/'.$owner->credential('phone_number_id').'/'.$path;
    }

    private function graphUrl(): string
    {
        return rtrim((string) config('whatsapp-agent.cloud.graph_url'), '/').'/'.config('whatsapp-agent.cloud.version');
    }

    private function ensureSuccessful(Response $response, string $action): void
    {
        if ($response->failed()) {
            throw WhatsAppException::requestFailed('cloud', $action, (string) ($response->json('error.message') ?? $response->body()));
        }
    }
}
