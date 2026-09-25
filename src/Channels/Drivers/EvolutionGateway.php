<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Channels\Drivers;

use Carbon\CarbonImmutable;
use HoceineEl\WhatsAppAgent\Channels\Contracts\WhatsAppGateway;
use HoceineEl\WhatsAppAgent\Channels\Inbound\ConnectionUpdate;
use HoceineEl\WhatsAppAgent\Channels\Inbound\InboundMessage;
use HoceineEl\WhatsAppAgent\Channels\Inbound\OwnerReply;
use HoceineEl\WhatsAppAgent\Channels\Inbound\StatusUpdate;
use HoceineEl\WhatsAppAgent\Channels\Outbound\OutgoingMessage;
use HoceineEl\WhatsAppAgent\Channels\Outbound\SendResult;
use HoceineEl\WhatsAppAgent\Contracts\AgentOwner;
use HoceineEl\WhatsAppAgent\Enums\ConnectionStatus;
use HoceineEl\WhatsAppAgent\Enums\MessageStatus;
use HoceineEl\WhatsAppAgent\Enums\MessageType;
use HoceineEl\WhatsAppAgent\Enums\WhatsAppDriver;
use HoceineEl\WhatsAppAgent\Exceptions\WhatsAppException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Evolution API v2 (Baileys). Unofficial: no templates or native buttons, so choices are sent as numbered lines.
 *
 * @see https://doc.evolution-api.com/v2/api-reference
 */
class EvolutionGateway implements WhatsAppGateway
{
    public function send(AgentOwner $owner, OutgoingMessage $message): SendResult
    {
        if ($message->isVoice()) {
            $response = $this->client($owner)->post('/message/sendWhatsAppAudio/'.$owner->evolutionInstance(), [
                'number' => $message->to,
                'audio' => base64_encode((string) file_get_contents((string) $message->audioPath)),
                'delay' => (int) config('whatsapp-agent.evolution.typing_delay_ms'),
                'encoding' => $message->audioMime !== 'audio/ogg',
            ]);

            $this->ensureSuccessful($response, 'send voice note');

            return new SendResult($response->json('key.id'));
        }

        $response = $this->client($owner)->post('/message/sendText/'.$owner->evolutionInstance(), [
            'number' => $message->to,
            'text' => $message->textWithNumberedButtons(),
            'delay' => (int) config('whatsapp-agent.evolution.typing_delay_ms'),
            'presence' => 'composing',
            'linkPreview' => false,
        ]);

        $this->ensureSuccessful($response, 'send text');

        return new SendResult($response->json('key.id'));
    }

    public function downloadMedia(AgentOwner $owner, InboundMessage $message): ?array
    {
        $response = $this->client($owner)->post('/chat/getBase64FromMediaMessage/'.$owner->evolutionInstance(), [
            'message' => isset($message->raw['key'], $message->raw['message'])
                ? Arr::only($message->raw, ['key', 'message'])
                : ['key' => ['id' => $message->providerMessageId]],
            'convertToMp4' => false,
        ]);

        $base64 = $response->json('base64');

        return $response->successful() && is_string($base64)
            ? ['data' => (string) base64_decode($base64, true), 'mime' => (string) ($response->json('mimetype') ?? $message->mimeType ?? 'audio/ogg')]
            : null;
    }

    public function verifyWebhook(AgentOwner $owner, Request $request): bool
    {
        $expected = array_filter([$owner->webhook_token, $owner->credential('instance_token'), $owner->evolutionApiKey()]);
        $provided = array_filter([$request->header(config('whatsapp-agent.evolution.token_header')), $request->input('apikey')], 'is_string');

        foreach ($provided as $token) {
            foreach ($expected as $secret) {
                if (hash_equals((string) $secret, $token)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function parseWebhook(array $payload): array
    {
        $event = strtoupper(str_replace('.', '_', (string) ($payload['event'] ?? '')));
        $data = (array) ($payload['data'] ?? []);

        return match ($event) {
            'MESSAGES_UPSERT' => array_values(array_filter([$this->parseMessage($data)])),
            'MESSAGES_UPDATE' => $this->parseStatuses($data),
            'CONNECTION_UPDATE' => [new ConnectionUpdate(
                ConnectionStatus::fromEvolutionState((string) ($data['state'] ?? 'close')),
                $this->phoneFromJid((string) ($data['wuid'] ?? '')),
            )],
            default => [],
        };
    }

    public function ensureInstance(AgentOwner $owner): void
    {
        $this->fetchInstance($owner) !== null ? $this->setWebhook($owner) : $this->createInstance($owner);
    }

    /**
     * @return array<string, mixed>
     */
    public function createInstance(AgentOwner $owner): array
    {
        $token = $owner->credential('instance_token') ?: Str::random(32);

        $response = $this->client($owner)->post('/instance/create', [
            'instanceName' => $owner->evolutionInstance(),
            'token' => $token,
            'qrcode' => true,
            'integration' => 'WHATSAPP-BAILEYS',
            'rejectCall' => false,
            'groupsIgnore' => true,
            'alwaysOnline' => false,
            'readMessages' => true,
            'webhook' => $this->webhookPayload($owner),
        ]);

        if ($response->status() !== 403) {
            $this->ensureSuccessful($response, 'create instance');
        }

        $owner->update(['whatsapp_credentials' => array_merge($owner->whatsapp_credentials ?? [], [
            'instance' => $owner->evolutionInstance(),
            'instance_token' => $token,
        ])]);

        return (array) $response->json();
    }

    /**
     * @return array{base64: ?string, pairing_code: ?string}
     */
    public function connect(AgentOwner $owner, ?string $number = null): array
    {
        $response = $this->client($owner)->get('/instance/connect/'.$owner->evolutionInstance(), array_filter(['number' => $number]));

        $this->ensureSuccessful($response, 'connect instance');

        return [
            'base64' => $response->json('base64'),
            'pairing_code' => $response->json('pairingCode'),
        ];
    }

    public function connectionState(AgentOwner $owner): ConnectionStatus
    {
        $response = $this->client($owner)->get('/instance/connectionState/'.$owner->evolutionInstance());

        return $response->successful()
            ? ConnectionStatus::fromEvolutionState((string) $response->json('instance.state'))
            : ConnectionStatus::Disconnected;
    }

    public function linkedNumber(AgentOwner $owner): ?string
    {
        return $this->phoneFromJid((string) ($this->fetchInstance($owner)['ownerJid'] ?? ''));
    }

    public function serverVersion(AgentOwner $owner): string
    {
        $response = $this->client($owner)->get('/');

        $this->ensureSuccessful($response, 'reach server');

        return (string) ($response->json('version') ?? '?');
    }

    public function setWebhook(AgentOwner $owner): void
    {
        $response = $this->client($owner)->post('/webhook/set/'.$owner->evolutionInstance(), [
            'webhook' => $this->webhookPayload($owner),
        ]);

        $this->ensureSuccessful($response, 'set webhook');
    }

    public function logout(AgentOwner $owner): void
    {
        $this->client($owner)->delete('/instance/logout/'.$owner->evolutionInstance());
    }

    /**
     * @return list<string>
     */
    public function chatPhones(AgentOwner $owner): array
    {
        return $this->client($owner)
            ->post('/chat/findChats/'.$owner->evolutionInstance(), [])
            ->throw()
            ->collect()
            ->map(fn (mixed $chat): string => is_array($chat) ? (string) ($chat['remoteJid'] ?? '') : '')
            ->filter(fn (string $jid): bool => str_ends_with($jid, '@s.whatsapp.net'))
            ->map(fn (string $jid): ?string => $this->phoneFromJid($jid))
            ->filter(fn (?string $phone): bool => $phone !== null && $phone !== $owner->whatsapp_number)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchInstance(AgentOwner $owner): ?array
    {
        return $this->client($owner)
            ->get('/instance/fetchInstances', ['instanceName' => $owner->evolutionInstance()])
            ->collect()
            ->first(fn (mixed $instance): bool => is_array($instance) && ($instance['name'] ?? null) === $owner->evolutionInstance());
    }

    /**
     * @return array<string, mixed>
     */
    private function webhookPayload(AgentOwner $owner): array
    {
        return [
            'enabled' => true,
            'url' => $owner->webhookUrl(WhatsAppDriver::Evolution),
            'headers' => [config('whatsapp-agent.evolution.token_header') => $owner->webhook_token],
            'byEvents' => false,
            'base64' => false,
            'events' => config('whatsapp-agent.evolution.events'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function parseMessage(array $data): InboundMessage|OwnerReply|null
    {
        $key = (array) ($data['key'] ?? []);
        $jid = (string) ($key['remoteJid'] ?? '');

        if (str_ends_with($jid, '@g.us') || str_ends_with($jid, '@broadcast') || str_ends_with($jid, '@newsletter')) {
            return null;
        }

        if (str_ends_with($jid, '@lid')) {
            $jid = (string) ($key['remoteJidAlt'] ?? $key['senderPn'] ?? $data['senderPn'] ?? '');
        }

        $phone = $this->phoneFromJid($jid);
        $id = (string) ($key['id'] ?? '');

        if ($phone === null || $id === '') {
            return null;
        }

        $message = (array) ($data['message'] ?? []);
        $messageType = (string) ($data['messageType'] ?? array_key_first($message) ?? '');

        [$kind, $text, $buttonId, $mime] = match ($messageType) {
            'conversation' => [MessageType::Text, $message['conversation'] ?? '', null, null],
            'extendedTextMessage' => [MessageType::Text, $message['extendedTextMessage']['text'] ?? '', null, null],
            'buttonsResponseMessage' => [MessageType::Button, $message['buttonsResponseMessage']['selectedDisplayText'] ?? '', $message['buttonsResponseMessage']['selectedButtonId'] ?? null, null],
            'listResponseMessage' => [MessageType::Button, $message['listResponseMessage']['title'] ?? '', $message['listResponseMessage']['singleSelectReply']['selectedRowId'] ?? null, null],
            'templateButtonReplyMessage' => [MessageType::Button, $message['templateButtonReplyMessage']['selectedDisplayText'] ?? '', $message['templateButtonReplyMessage']['selectedId'] ?? null, null],
            'audioMessage' => [MessageType::Audio, null, null, $message['audioMessage']['mimetype'] ?? 'audio/ogg'],
            'imageMessage' => [MessageType::Image, $message['imageMessage']['caption'] ?? null, null, $message['imageMessage']['mimetype'] ?? null],
            'documentMessage' => [MessageType::Document, $message['documentMessage']['caption'] ?? null, null, $message['documentMessage']['mimetype'] ?? null],
            'documentWithCaptionMessage' => [
                MessageType::Document,
                $message['documentWithCaptionMessage']['message']['documentMessage']['caption'] ?? null,
                null,
                $message['documentWithCaptionMessage']['message']['documentMessage']['mimetype'] ?? null,
            ],
            'videoMessage' => [MessageType::Video, $message['videoMessage']['caption'] ?? null, null, $message['videoMessage']['mimetype'] ?? null],
            'locationMessage' => [MessageType::Location, trim(($message['locationMessage']['name'] ?? '').' '.($message['locationMessage']['degreesLatitude'] ?? '').','.($message['locationMessage']['degreesLongitude'] ?? '')), null, null],
            default => [MessageType::Unsupported, null, null, null],
        };

        $sentAt = isset($data['messageTimestamp']) ? CarbonImmutable::createFromTimestampUTC((int) $data['messageTimestamp']) : null;

        if (($key['fromMe'] ?? false) === true) {
            return new OwnerReply($id, $phone, $kind, $text, $sentAt);
        }

        return new InboundMessage(
            providerMessageId: $id,
            from: $phone,
            name: is_string($data['pushName'] ?? null) ? $data['pushName'] : null,
            type: $kind,
            text: $text,
            buttonId: $buttonId,
            mediaId: $kind->isMedia() ? $id : null,
            mimeType: $mime,
            sentAt: $sentAt,
            raw: $data,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<StatusUpdate>
     */
    private function parseStatuses(array $data): array
    {
        $updates = isset($data['keyId']) || isset($data['key']) ? [$data] : $data;

        return collect($updates)
            ->filter(fn (mixed $update): bool => is_array($update))
            ->map(function (array $update): ?StatusUpdate {
                $id = $update['keyId'] ?? $update['key']['id'] ?? null;
                $status = match (strtoupper((string) ($update['status'] ?? $update['update']['status'] ?? ''))) {
                    'SERVER_ACK', '1' => MessageStatus::Sent,
                    'DELIVERY_ACK', 'DELIVERED', '2' => MessageStatus::Delivered,
                    'READ', 'PLAYED', '3', '4' => MessageStatus::Read,
                    'ERROR', 'FAILED' => MessageStatus::Failed,
                    default => null,
                };

                return filled($id) && $status !== null ? new StatusUpdate((string) $id, $status) : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    private function phoneFromJid(string $jid): ?string
    {
        $number = (string) preg_replace('/\D/', '', Str::before(Str::before($jid, '@'), ':'));

        return strlen($number) >= 8 ? $number : null;
    }

    private function client(AgentOwner $owner): PendingRequest
    {
        return Http::baseUrl($owner->evolutionUrl())
            ->withHeaders(['apikey' => $owner->evolutionApiKey()])
            ->acceptJson()
            ->timeout((int) config('whatsapp-agent.http.timeout'))
            ->connectTimeout((int) config('whatsapp-agent.http.connect_timeout'))
            ->retry((int) config('whatsapp-agent.http.retries'), 300, throw: false);
    }

    private function ensureSuccessful(Response $response, string $action): void
    {
        if ($response->failed()) {
            $reason = $response->json('response.message') ?? $response->json('message') ?? $response->body();

            throw WhatsAppException::requestFailed('evolution', $action, is_array($reason) ? implode(', ', array_map('strval', $reason)) : (string) $reason);
        }
    }
}
