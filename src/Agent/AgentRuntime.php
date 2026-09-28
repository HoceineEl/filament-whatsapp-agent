<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Agent;

use HoceineEl\WhatsAppAgent\Channels\Inbound\InboundMessage;
use HoceineEl\WhatsAppAgent\Channels\WhatsAppManager;
use HoceineEl\WhatsAppAgent\Enums\MessageAuthor;
use HoceineEl\WhatsAppAgent\Enums\MessageType;
use HoceineEl\WhatsAppAgent\Enums\PersonalSource;
use HoceineEl\WhatsAppAgent\Messaging\HandoffService;
use HoceineEl\WhatsAppAgent\Messaging\MessageSender;
use HoceineEl\WhatsAppAgent\Screening\MessageScreenerAgent;
use HoceineEl\WhatsAppAgent\Support\WhatsAppFormatter;
use HoceineEl\WhatsAppAgent\WhatsAppAgent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Exceptions\FailoverableException;
use Laravel\Ai\Files\Audio;
use Laravel\Ai\Files\Document;
use Laravel\Ai\Files\Image;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message as AiMessage;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Responses\Data\TextUsage;
use Throwable;

class AgentRuntime
{
    private const array OPT_OUT = ['stop', 'unsubscribe', 'إيقاف', 'ايقاف', 'إلغاء الاشتراك', 'الغاء الاشتراك'];

    private const array OPT_IN = ['start', 'subscribe', 'اشتراك', 'تفعيل'];

    private const array ACKNOWLEDGEMENTS = [
        'ok', 'okay', 'okey', 'k', 'kk', 'thanks', 'thank', 'you', 'thx', 'ty', 'great', 'perfect', 'cool', 'noted', 'merci', 'bye',
        'تمام', 'شكرا', 'مشكور', 'مشكوره', 'يعطيك', 'العافيه', 'الله', 'تسلم', 'تسلمي', 'تسلمين', 'يسلمو', 'جزاك', 'خير', 'اوكي', 'اوك',
        'طيب', 'حاضر', 'ان', 'شاء', 'ماشي', 'زين', 'ممتاز', 'الف', 'حلو', 'اوكيه', 'يعطيكم',
    ];

    public function __construct(
        private readonly MessageSender $sender,
        private readonly HandoffService $handoff,
        private readonly WhatsAppManager $whatsapp,
    ) {}

    public function respond(Model $inbound, bool $finalAttempt = true): ?Model
    {
        $inbound->loadMissing('conversation.owner', 'conversation.contact');
        $conversation = $inbound->conversation;
        $owner = $conversation->owner;

        if (
            ! $owner->agentIsActive()
            || ! $inbound->isInbound()
            || $this->hasNewerInbound($inbound)
            || $this->alreadyAnswered($inbound)
        ) {
            return null;
        }

        $context = AgentContext::for($conversation);

        if ($context->contact->isMuted() || $this->screenedAsPersonal($inbound, $context)) {
            return null;
        }

        $keyword = mb_strtolower(trim((string) $inbound->body, " \t\n\r\0\x0B.!?؟"));

        if (in_array($keyword, self::OPT_OUT, true)) {
            $context->contact->update(['opted_out_at' => now()]);

            return $this->sender->send($conversation, __('whatsapp-agent::assistant.opted_out', locale: $context->locale()));
        }

        if (in_array($keyword, self::OPT_IN, true) && $context->contact->isOptedOut()) {
            $context->contact->update(['opted_out_at' => null]);

            return $this->sender->send($conversation, __('whatsapp-agent::assistant.opted_in', locale: $context->locale()));
        }

        if ($context->contact->isOptedOut()) {
            return null;
        }

        if (WhatsAppAgent::intercept($inbound, $context)) {
            return null;
        }

        $this->handoff->resumeAfterAiFailure($conversation);

        if (! $conversation->status->botReplies() || ! $owner->agentSetting('assistant_enabled', true)) {
            return null;
        }

        if (! $context->contact->isSandbox() && filled($reason = WhatsAppAgent::blockedReason($owner, $context->contact))) {
            $this->handoff->handOff($conversation, $reason);

            return null;
        }

        if ($this->isClosingAcknowledgement($inbound)) {
            return null;
        }

        if ($this->pendingInbound($inbound)->every(fn (Model $message): bool => $message->type === MessageType::Video && blank($message->body))) {
            return $this->sender->send($conversation, __('whatsapp-agent::assistant.video_unsupported', locale: $context->locale()));
        }

        return $this->answerWithAi($inbound, $context, $finalAttempt);
    }

    private function answerWithAi(Model $inbound, AgentContext $context, bool $finalAttempt): ?Model
    {
        $conversation = $context->conversation;

        if ($this->reachedDailyLimit($conversation)) {
            $this->handoff->handOff($conversation, __('whatsapp-agent::conversations.handoff.daily_limit', locale: $context->owner->agentLocale()));

            return $this->sender->send($conversation, __('whatsapp-agent::assistant.fallback', locale: $context->locale()), MessageAuthor::System);
        }

        $pending = $this->pendingInbound($inbound);
        $history = $this->history($inbound, $pending);
        $context->replyByVoice = $context->owner->agentSetting('voice_replies_enabled')
            && ($context->contact->prefers_voice || $pending->contains(fn (Model $message): bool => $message->type === MessageType::Audio));

        try {
            $response = (new AssistantAgent($context, $history))->prompt(
                $this->promptText($pending),
                attachments: $this->attachments($pending, $context),
            );
        } catch (Throwable $exception) {
            if ($exception instanceof FailoverableException && ! $finalAttempt) {
                throw $exception;
            }

            Log::error('AgentRuntime AI failed', ['conversation_id' => $conversation->getKey(), 'error' => $exception->getMessage()]);
            $this->handoff->handOff($conversation, __('whatsapp-agent::conversations.handoff.ai_failed', locale: $context->owner->agentLocale()));

            return $this->sender->send($conversation, __('whatsapp-agent::assistant.fallback', locale: $context->locale()), MessageAuthor::System);
        }

        if ($context->mayBePersonal && trim((string) $response->text) === PromptBuilder::SKIP) {
            return null;
        }

        $usage = $response->usage;
        $meta = [
            'model' => config('whatsapp-agent.ai.model'),
            'tools' => array_values(array_unique($context->toolsUsed)),
            'touched' => array_map(fn (array $ids): array => array_values(array_unique($ids)), $context->touchedRecords),
            'tokens' => [
                'input' => $usage->inputTokens,
                'cached' => $usage instanceof TextUsage ? $usage->cacheReadInputTokens : null,
                'output' => $usage->outputTokens,
            ],
        ];

        $reply = WhatsAppFormatter::format((string) $response->text);

        if ($context->replyByVoice && filled($reply)) {
            return $this->sender->send($conversation, $reply, meta: $meta, asVoice: true);
        }

        $last = null;

        foreach (MessageSender::chunks($reply) as $chunk) {
            $last = $this->sender->send($conversation, $chunk, meta: $meta);
            $meta = [];
        }

        return $last;
    }

    /**
     * Shared personal/business phone: new numbers are checked once, saved contacts on every new exchange, always with the cheapest model.
     */
    private function screenedAsPersonal(Model $inbound, AgentContext $context): bool
    {
        if (! $context->owner->agentSetting('screen_new_contacts') || $context->contact->isSandbox()) {
            return false;
        }

        return $context->contact->isPersonal()
            ? $this->screenKnownContact($inbound, $context)
            : $this->screenNewContact($inbound, $context);
    }

    private function screenKnownContact(Model $inbound, AgentContext $context): bool
    {
        $recentlyAnswered = $this->conversationMessages($inbound->ownerId(), $inbound->conversation_id)
            ->outbound()
            ->byAuthor(MessageAuthor::Bot)
            ->where('created_at', '>=', now()->subMinutes(30))
            ->exists();

        if ($recentlyAnswered) {
            return false;
        }

        $text = $this->pendingInbound($inbound)->where('type', MessageType::Text)->pluck('body')->filter()->implode("\n");

        if ($text === '') {
            $context->mayBePersonal = true;

            return false;
        }

        return rescue(fn (): bool => (new MessageScreenerAgent($context->owner, knownContact: true))->isPersonal($text), false, report: false);
    }

    private function screenNewContact(Model $inbound, AgentContext $context): bool
    {
        $customer = $context->contact;

        if ($customer->screened_at !== null || $inbound->type !== MessageType::Text || blank($inbound->body)) {
            return false;
        }

        $hasHistory = WhatsAppAgent::hasHistory($customer)
            || $this->conversationMessages($inbound->ownerId(), $inbound->conversation_id)->outbound()->exists();

        if ($hasHistory) {
            return false;
        }

        $personal = rescue(fn (): bool => (new MessageScreenerAgent($context->owner))->isPersonal((string) $inbound->body), false, report: false);

        if ($personal) {
            $customer->markPersonal(PersonalSource::Screening);
        } else {
            $customer->update(['screened_at' => now()]);
        }

        return $personal;
    }

    private function reachedDailyLimit(Model $conversation): bool
    {
        $limit = (int) $conversation->owner->agentSetting('max_ai_replies_per_day');

        return $limit > 0 && $this->conversationMessages($conversation->ownerId(), $conversation->getKey())
            ->outbound()
            ->byAuthor(MessageAuthor::Bot)
            ->where('created_at', '>=', now()->subDay())
            ->count() >= $limit;
    }

    private function hasNewerInbound(Model $inbound): bool
    {
        return $this->messagesAfter($inbound)->inbound()->exists();
    }

    /**
     * A retried job must not answer twice when an earlier attempt already sent part of the reply.
     */
    private function alreadyAnswered(Model $inbound): bool
    {
        return $this->messagesAfter($inbound)->outbound()->exists();
    }

    private function messagesAfter(Model $message): Builder
    {
        return $this->conversationMessages($message->ownerId(), $message->conversation_id)
            ->where('id', '>', $message->getKey());
    }

    /**
     * "ok", "thanks", 👍 or a sticker after a reply that asked nothing needs no answer, so it costs no AI call.
     */
    private function isClosingAcknowledgement(Model $inbound): bool
    {
        $lastReply = $this->conversationMessages($inbound->ownerId(), $inbound->conversation_id)
            ->outbound()
            ->latest('id')
            ->first(['author', 'body']);

        if ($lastReply?->author !== MessageAuthor::Bot || blank($lastReply->body) || Str::contains((string) $lastReply->body, ['?', '؟'])) {
            return false;
        }

        return $this->pendingInbound($inbound)->every(fn (Model $message): bool => $message->type === MessageType::Unsupported
            || ($message->type === MessageType::Text && $this->isAcknowledgement((string) $message->body)));
    }

    private function isAcknowledgement(string $text): bool
    {
        $withoutDiacritics = mb_strtolower((string) preg_replace('/\p{Mn}/u', '', $text));
        $normalized = strtr($withoutDiacritics, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ة' => 'ه', 'ى' => 'ي', 'ـ' => '']);
        $words = preg_split('/[^\p{L}]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return count($words) <= 6 && array_diff($words, self::ACKNOWLEDGEMENTS) === [];
    }

    /**
     * @return Builder<Model>
     */
    private function conversationMessages(int|string $ownerId, int|string $conversationId): Builder
    {
        return WhatsAppAgent::messageModel()::query()->forOwner($ownerId)->where('conversation_id', $conversationId);
    }

    /**
     * Inbound messages the customer sent since our last reply, answered together as one turn.
     *
     * @return Collection<int, Model>
     */
    private function pendingInbound(Model $inbound): Collection
    {
        $lastOutboundId = $this->conversationMessages($inbound->ownerId(), $inbound->conversation_id)
            ->outbound()
            ->where('id', '<', $inbound->getKey())
            ->max('id') ?? 0;

        return $this->conversationMessages($inbound->ownerId(), $inbound->conversation_id)
            ->inbound()
            ->where('id', '>', $lastOutboundId)
            ->where('id', '<=', $inbound->getKey())
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, Model>  $pending
     * @return list<AiMessage>
     */
    private function history(Model $inbound, Collection $pending): array
    {
        return $this->conversationMessages($inbound->ownerId(), $inbound->conversation_id)
            ->where('id', '<', $pending->first()?->getKey() ?? $inbound->getKey())
            ->latest('id')
            ->limit((int) config('whatsapp-agent.ai.history_messages'))
            ->get()
            ->reverse()
            ->filter(fn (Model $message): bool => filled($message->body) || $message->isInbound())
            ->map(function (Model $message): AiMessage {
                if (! $message->isInbound()) {
                    return new AssistantMessage((string) $message->body);
                }

                return new UserMessage($message->type === MessageType::Audio ? '[earlier voice note]' : $this->promptText(collect([$message])));
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Model>  $pending
     */
    private function promptText(Collection $pending): string
    {
        return $pending
            ->map(fn (Model $message): string => match ($message->type) {
                MessageType::Audio => '[voice note attached — listen, understand it and reply to its content]',
                MessageType::Image => trim('[photo attached] '.$message->body),
                MessageType::Document => $this->isReadableDocument($message)
                    ? trim('[document attached — read it and use it to help] '.$message->body)
                    : trim('[document you cannot open — ask for a PDF or a photo of it] '.$message->body),
                MessageType::Video => trim('[video — you cannot watch videos; briefly ask for a photo or a short description] '.$message->body),
                MessageType::Location => '[shared location] '.$message->body,
                MessageType::Unsupported => '[unsupported message type]',
                default => (string) $message->body,
            })
            ->implode("\n");
    }

    /**
     * @param  Collection<int, Model>  $pending
     * @return list<mixed>
     */
    private function attachments(Collection $pending, AgentContext $context): array
    {
        return $pending
            ->filter(fn (Model $message): bool => in_array($message->type, [MessageType::Audio, MessageType::Image], true) || $this->isReadableDocument($message))
            ->map(function (Model $message) use ($context): mixed {
                $media = $this->media($message, $context);

                if ($media === null || strlen($media['data']) > 15 * 1024 * 1024) {
                    return null;
                }

                $data = base64_encode($media['data']);

                return match (true) {
                    $message->type === MessageType::Audio => Audio::fromBase64($data, $this->audioMime($media['mime'])),
                    $message->type === MessageType::Document && $media['mime'] === 'application/pdf' => Document::fromBase64($data, $media['mime']),
                    default => Image::fromBase64($data, $media['mime']),
                };
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * PDFs and scanned pages Gemini can read directly; Word files and the like are only mentioned.
     */
    private function isReadableDocument(Model $message): bool
    {
        $mime = (string) ($message->payload['mime_type'] ?? '');

        return $message->type === MessageType::Document && ($mime === 'application/pdf' || str_starts_with($mime, 'image/'));
    }

    /**
     * @return array{data: string, mime: string}|null
     */
    private function media(Model $message, AgentContext $context): ?array
    {
        if (filled($path = $message->payload['path'] ?? null) && Storage::disk('local')->exists($path)) {
            return ['data' => (string) Storage::disk('local')->get($path), 'mime' => (string) ($message->payload['mime_type'] ?? 'audio/ogg')];
        }

        $media = rescue(fn (): ?array => $this->whatsapp->for($context->owner)->downloadMedia($context->owner, new InboundMessage(
            providerMessageId: (string) $message->provider_message_id,
            from: $context->contact->phone,
            name: null,
            type: $message->type,
            text: $message->body,
            mediaId: $message->payload['media_id'] ?? null,
            mimeType: $message->payload['mime_type'] ?? null,
            raw: $message->payload['media_message'] ?? [],
        )), report: false);

        if ($media !== null) {
            $extension = match (true) {
                $message->type === MessageType::Audio => 'ogg',
                $media['mime'] === 'application/pdf' => 'pdf',
                default => 'jpg',
            };
            $path = "media/{$message->ownerId()}/{$message->getKey()}.{$extension}";
            Storage::disk('local')->put($path, $media['data']);
            $message->update(['payload' => array_merge($message->payload ?? [], ['path' => $path, 'mime_type' => $media['mime']])]);
        }

        return $media;
    }

    private function audioMime(string $mime): string
    {
        return str_starts_with($mime, 'audio/ogg') ? 'audio/ogg' : $mime;
    }
}
