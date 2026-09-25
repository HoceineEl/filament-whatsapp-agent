<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Messaging;

use HoceineEl\WhatsAppAgent\Enums\MessageAuthor;
use HoceineEl\WhatsAppAgent\Enums\MessageDirection;
use HoceineEl\WhatsAppAgent\Enums\MessageStatus;
use HoceineEl\WhatsAppAgent\Enums\MessageType;
use HoceineEl\WhatsAppAgent\Jobs\DeliverMessage;
use HoceineEl\WhatsAppAgent\WhatsAppAgent;
use Illuminate\Database\Eloquent\Model;

class MessageSender
{
    /**
     * @param  list<array{id: string, title: string}>  $buttons
     * @param  array{name: string, language: string, parameters: list<string>}|null  $template  used when the 24h window is closed on drivers that need it
     * @param  array<string, mixed>  $meta
     */
    public function send(
        Model $conversation,
        string $text,
        MessageAuthor $author = MessageAuthor::Bot,
        array $buttons = [],
        ?array $template = null,
        ?Model $user = null,
        array $meta = [],
        bool $asVoice = false,
    ): Model {
        $owner = $conversation->owner;
        $useTemplate = $template !== null
            && $owner->whatsapp_driver->requiresTemplatesOutsideWindow()
            && ! $conversation->withinServiceWindow();

        $message = WhatsAppAgent::messageModel()::create([
            WhatsAppAgent::ownerKey() => $conversation->ownerId(),
            'conversation_id' => $conversation->getKey(),
            'direction' => MessageDirection::Outbound,
            'author' => $author,
            'type' => match (true) {
                $useTemplate => MessageType::Template,
                $buttons !== [] => MessageType::Button,
                $asVoice => MessageType::Audio,
                default => MessageType::Text,
            },
            'body' => trim($text),
            'payload' => array_filter([
                'buttons' => $buttons,
                'template' => $useTemplate ? $template : null,
            ]),
            'meta' => $meta ?: null,
            'status' => MessageStatus::Queued,
            'user_id' => $user?->getKey(),
        ]);

        $conversation->forceFill([
            'last_message_at' => now(),
            'unread_count' => $author === MessageAuthor::Staff ? 0 : $conversation->unread_count,
        ])->save();

        $conversation->contact->isSandbox()
            ? DeliverMessage::dispatchSync($message)
            : DeliverMessage::dispatch($message);

        return $message;
    }

    /**
     * Splits long assistant replies on paragraph breaks so WhatsApp shows them as natural chat bubbles.
     *
     * @return list<string>
     */
    public static function chunks(string $text, int $limit = 1500): array
    {
        $text = trim($text);

        if (mb_strlen($text) <= $limit) {
            return $text === '' ? [] : [$text];
        }

        $chunks = [];
        $current = '';

        foreach (preg_split("/\n{2,}/u", $text) ?: [] as $paragraph) {
            if ($current !== '' && mb_strlen($current) + mb_strlen($paragraph) + 2 > $limit) {
                $chunks[] = $current;
                $current = '';
            }

            $current = $current === '' ? $paragraph : $current."\n\n".$paragraph;

            while (mb_strlen($current) > $limit) {
                $chunks[] = mb_substr($current, 0, $limit);
                $current = mb_substr($current, $limit);
            }
        }

        if (trim($current) !== '') {
            $chunks[] = $current;
        }

        return array_values(array_map('trim', $chunks));
    }
}
