<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Concerns;

use HoceineEl\WhatsAppAgent\Enums\ConversationStatus;
use HoceineEl\WhatsAppAgent\WhatsAppAgent;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Expects: status, handoff_reason, handed_off_at, assigned_user_id, unread_count, last_message_at and last_inbound_at.
 *
 * @mixin Model
 */
trait IsAgentConversation
{
    public function initializeIsAgentConversation(): void
    {
        $this->mergeCasts([
            'status' => ConversationStatus::class,
            'handed_off_at' => 'datetime',
            'last_message_at' => 'datetime',
            'last_inbound_at' => 'datetime',
            'unread_count' => 'integer',
        ]);
        $this->attributes['status'] ??= ConversationStatus::Bot->value;
        $this->attributes['unread_count'] ??= 0;
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
    public function contact(): BelongsTo
    {
        $relation = $this->belongsTo(WhatsAppAgent::contactModel(), WhatsAppAgent::contactKey());

        return method_exists($relation->getRelated(), 'bootSoftDeletes') ? $relation->withTrashed() : $relation;
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(WhatsAppAgent::userModel(), 'assigned_user_id');
    }

    /**
     * @return HasMany<Model, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(WhatsAppAgent::messageModel(), 'conversation_id');
    }

    /**
     * @return HasOne<Model, $this>
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(WhatsAppAgent::messageModel(), 'conversation_id')->latestOfMany();
    }

    #[Scope]
    protected function forOwner(Builder $query, mixed $owner): void
    {
        $query->where($query->qualifyColumn(WhatsAppAgent::ownerKey()), is_object($owner) ? $owner->getKey() : $owner);
    }

    #[Scope]
    protected function withStatus(Builder $query, ConversationStatus ...$statuses): void
    {
        $query->whereIn('status', $statuses);
    }

    #[Scope]
    protected function waitingForHuman(Builder $query): void
    {
        $query->where('status', ConversationStatus::NeedsHuman);
    }

    #[Scope]
    protected function real(Builder $query): void
    {
        $query->whereHas('contact', fn (Builder $inner) => $inner->real());
    }

    #[Scope]
    protected function notPersonal(Builder $query): void
    {
        $query->where(fn (Builder $visible) => $visible
            ->whereHas('contact', fn (Builder $inner) => $inner->notPersonal())
            ->orWhere(fn (Builder $engaged) => $engaged
                ->whereHas('contact', fn (Builder $inner) => $inner->notMuted())
                ->whereHas('messages', fn (Builder $inner) => $inner->outbound())));
    }

    #[Scope]
    protected function recent(Builder $query): void
    {
        $query->orderByDesc('last_message_at')->orderByDesc('id');
    }

    public function withinServiceWindow(): bool
    {
        return $this->last_inbound_at !== null && $this->last_inbound_at->greaterThan(now()->subHours(24));
    }

    public function ownerId(): int
    {
        return (int) $this->getAttribute(WhatsAppAgent::ownerKey());
    }
}
