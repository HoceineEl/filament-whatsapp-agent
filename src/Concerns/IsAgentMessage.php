<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Concerns;

use HoceineEl\WhatsAppAgent\Enums\MessageAuthor;
use HoceineEl\WhatsAppAgent\Enums\MessageDirection;
use HoceineEl\WhatsAppAgent\Enums\MessageStatus;
use HoceineEl\WhatsAppAgent\Enums\MessageType;
use HoceineEl\WhatsAppAgent\WhatsAppAgent;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Expects: conversation_id, direction, author, type, body, payload, meta, provider_message_id, status, error, user_id and sent_at.
 *
 * @mixin Model
 */
trait IsAgentMessage
{
    public function initializeIsAgentMessage(): void
    {
        $this->mergeCasts([
            'direction' => MessageDirection::class,
            'author' => MessageAuthor::class,
            'type' => MessageType::class,
            'status' => MessageStatus::class,
            'payload' => 'array',
            'meta' => 'array',
            'sent_at' => 'datetime',
        ]);
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(WhatsAppAgent::ownerModel(), WhatsAppAgent::ownerKey());
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsAppAgent::conversationModel(), 'conversation_id');
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(WhatsAppAgent::userModel(), 'user_id');
    }

    #[Scope]
    protected function forOwner(Builder $query, mixed $owner): void
    {
        $query->where($query->qualifyColumn(WhatsAppAgent::ownerKey()), is_object($owner) ? $owner->getKey() : $owner);
    }

    #[Scope]
    protected function inbound(Builder $query): void
    {
        $query->where('direction', MessageDirection::Inbound);
    }

    #[Scope]
    protected function outbound(Builder $query): void
    {
        $query->where('direction', MessageDirection::Outbound);
    }

    #[Scope]
    protected function byAuthor(Builder $query, MessageAuthor $author): void
    {
        $query->where('author', $author);
    }

    #[Scope]
    protected function createdBetween(Builder $query, \DateTimeInterface $from, \DateTimeInterface $until): void
    {
        $query->where('created_at', '>=', $from)->where('created_at', '<', $until);
    }

    public function isInbound(): bool
    {
        return $this->direction === MessageDirection::Inbound;
    }

    public function ownerId(): int
    {
        return (int) $this->getAttribute(WhatsAppAgent::ownerKey());
    }
}
