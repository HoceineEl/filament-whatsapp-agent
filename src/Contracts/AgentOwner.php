<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Contracts;

use HoceineEl\WhatsAppAgent\Enums\AssistantGender;
use HoceineEl\WhatsAppAgent\Enums\AssistantVoice;
use HoceineEl\WhatsAppAgent\Enums\EvolutionServer;
use HoceineEl\WhatsAppAgent\Enums\ReplyLanguage;
use HoceineEl\WhatsAppAgent\Enums\WhatsAppDriver;
use Illuminate\Database\Eloquent\Model;

/**
 * The model that owns a WhatsApp number (a business, a store, a team). Most of it comes from the HasWhatsAppAgent trait.
 *
 * @property WhatsAppDriver $whatsapp_driver
 */
interface AgentOwner
{
    public function getKey();

    public function credential(string $key, mixed $default = null): mixed;

    public function webhookUrl(?WhatsAppDriver $driver = null): string;

    public function evolutionInstance(): string;

    public function evolutionServer(): EvolutionServer;

    public function evolutionUrl(): string;

    public function evolutionApiKey(): string;

    public function agentSetting(string $key, mixed $default = null): mixed;

    /**
     * @param  array<string, mixed>  $settings
     */
    public function saveAgentSettings(array $settings): void;

    public function agentName(): string;

    /**
     * One line the assistant and the screener use to understand the business, e.g. "a dental clinic in Riyadh, Saudi Arabia".
     */
    public function agentDescription(): string;

    /**
     * What the assistant is called in the prompt, e.g. "receptionist" or "sales assistant".
     */
    public function agentRole(): string;

    /**
     * What the assistant does for customers, appended to the role line.
     */
    public function agentDuties(): string;

    public function agentLocale(): string;

    public function agentTimezone(): string;

    public function agentIsActive(): bool;

    public function agentOwnerWhatsApp(): ?string;

    /**
     * @return iterable<Model>
     */
    public function agentNotifiables(): iterable;

    public function assistantName(?string $locale = null): string;

    public function assistantGender(): AssistantGender;

    public function assistantVoice(): AssistantVoice;

    public function replyLanguage(): ReplyLanguage;
}
