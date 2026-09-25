<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Concerns;

use HoceineEl\WhatsAppAgent\Enums\PersonalSource;
use HoceineEl\WhatsAppAgent\WhatsAppAgent;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * For the model that stores WhatsApp contacts. Expects: phone, name, locale, dialect, prefers_voice, opted_out_at,
 * personal_at, personal_source, screened_at and last_seen_at.
 *
 * @mixin Model
 */
trait IsAgentContact
{
    public const string SANDBOX_PREFIX = '9990';

    public static function bootIsAgentContact(): void
    {
        static::saving(function (self $contact): void {
            if ($contact->isDirty('personal_at')) {
                $contact->personal_source = $contact->isPersonal() ? ($contact->personal_source ?? PersonalSource::Owner) : null;
            }
        });
    }

    public function initializeIsAgentContact(): void
    {
        $this->mergeCasts([
            'prefers_voice' => 'boolean',
            'opted_out_at' => 'datetime',
            'personal_at' => 'datetime',
            'personal_source' => PersonalSource::class,
            'screened_at' => 'datetime',
            'last_seen_at' => 'datetime',
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
     * @return HasOne<Model, $this>
     */
    public function conversation(): HasOne
    {
        return $this->hasOne(WhatsAppAgent::conversationModel(), WhatsAppAgent::contactKey());
    }

    #[Scope]
    protected function forOwner(Builder $query, mixed $owner): void
    {
        $query->where($query->qualifyColumn(WhatsAppAgent::ownerKey()), is_object($owner) ? $owner->getKey() : $owner);
    }

    #[Scope]
    protected function reachable(Builder $query): void
    {
        $query->whereNull('opted_out_at');
    }

    #[Scope]
    protected function notMuted(Builder $query): void
    {
        $query->where(fn (Builder $inner) => $inner->whereNull('personal_source')->orWhere('personal_source', '!=', PersonalSource::Owner));
    }

    #[Scope]
    protected function notPersonal(Builder $query): void
    {
        $query->whereNull('personal_at');
    }

    #[Scope]
    protected function real(Builder $query): void
    {
        $query->where('phone', 'not like', self::SANDBOX_PREFIX.'%');
    }

    #[Scope]
    protected function withPhone(Builder $query, string $phone): void
    {
        $query->where('phone', self::normalizePhone($phone));
    }

    public static function normalizePhone(string $phone): string
    {
        $digits = (string) preg_replace('/\D/', '', strtr($phone, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']));

        return str_starts_with($digits, '00') ? substr($digits, 2) : $digits;
    }

    public static function sandboxPhone(int $ownerId, int $userId): string
    {
        return self::SANDBOX_PREFIX.$ownerId.'0'.$userId;
    }

    public function isSandbox(): bool
    {
        return str_starts_with((string) $this->phone, self::SANDBOX_PREFIX);
    }

    public function isMuted(): bool
    {
        return $this->personal_source === PersonalSource::Owner;
    }

    public function isPersonal(): bool
    {
        return $this->personal_at !== null;
    }

    public function markPersonal(PersonalSource $source): void
    {
        $this->fill(['personal_at' => now(), 'personal_source' => $source, 'screened_at' => $this->screened_at ?? now()])->save();
    }

    public function isOptedOut(): bool
    {
        return $this->opted_out_at !== null;
    }

    public function displayName(): string
    {
        return $this->name ?: '+'.$this->phone;
    }

    public function ownerId(): int
    {
        return (int) $this->getAttribute(WhatsAppAgent::ownerKey());
    }
}
