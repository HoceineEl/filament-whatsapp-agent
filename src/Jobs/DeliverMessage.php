<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Jobs;

use HoceineEl\WhatsAppAgent\Channels\Outbound\OutgoingMessage;
use HoceineEl\WhatsAppAgent\Channels\WhatsAppManager;
use HoceineEl\WhatsAppAgent\Enums\MessageStatus;
use HoceineEl\WhatsAppAgent\Enums\MessageType;
use HoceineEl\WhatsAppAgent\Enums\WhatsAppDriver;
use HoceineEl\WhatsAppAgent\Messaging\VoiceSynthesizer;
use HoceineEl\WhatsAppAgent\WhatsAppAgent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DeliverMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public function __construct(public Model $message)
    {
        $this->onQueue(config('whatsapp-agent.queue'));
    }

    public function handle(WhatsAppManager $whatsapp, VoiceSynthesizer $voices): void
    {
        $message = $this->message->loadMissing('conversation.contact', 'owner');

        WhatsAppAgent::within($message->owner, fn () => $this->deliver($message, $whatsapp, $voices));
    }

    private function deliver(Model $message, WhatsAppManager $whatsapp, VoiceSynthesizer $voices): void
    {

        if ($message->status !== MessageStatus::Queued) {
            return;
        }

        $gateway = $message->conversation->contact->isSandbox()
            ? $whatsapp->driver(WhatsAppDriver::Simulator)
            : $whatsapp->for($message->owner);

        $voice = $message->type === MessageType::Audio && ! $message->conversation->contact->isSandbox() ? $this->voice($message, $voices) : null;

        $result = $gateway->send($message->owner, new OutgoingMessage(
            to: $message->conversation->contact->phone,
            text: (string) $message->body,
            buttons: $message->payload['buttons'] ?? [],
            template: $message->payload['template'] ?? null,
            audioPath: $voice ? Storage::disk('local')->path($voice['path']) : null,
            audioMime: $voice['mime'] ?? null,
        ));

        $message->forceFill([
            'status' => MessageStatus::Sent,
            'provider_message_id' => $result->providerMessageId,
            'sent_at' => now(),
            'error' => null,
        ])->save();
    }

    /**
     * @return array{path: string, mime: string}|null
     */
    private function voice(Model $message, VoiceSynthesizer $voices): ?array
    {
        try {
            return $voices->forMessage($message);
        } catch (Throwable $exception) {
            Log::warning('Voice reply failed, sending text instead', ['message_id' => $message->getKey(), 'error' => $exception->getMessage()]);
            $message->forceFill(['type' => MessageType::Text])->save();

            return null;
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::warning('WhatsApp delivery failed', ['message_id' => $this->message->getKey(), 'error' => $exception->getMessage()]);

        $this->message->forceFill([
            'status' => MessageStatus::Failed,
            'error' => mb_substr($exception->getMessage(), 0, 250),
        ])->save();
    }
}
