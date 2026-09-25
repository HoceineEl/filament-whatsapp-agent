<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Concerns;

use HoceineEl\WhatsAppAgent\Enums\AssistantGender;
use HoceineEl\WhatsAppAgent\Enums\AssistantVoice;
use HoceineEl\WhatsAppAgent\Enums\ConnectionStatus;
use HoceineEl\WhatsAppAgent\Enums\EvolutionServer;
use HoceineEl\WhatsAppAgent\Enums\ReplyLanguage;
use HoceineEl\WhatsAppAgent\Enums\WhatsAppDriver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * For the owner model. Expects the columns whatsapp_driver, whatsapp_credentials, whatsapp_status, whatsapp_number, webhook_token and owner_whatsapp.
 *
 * @mixin Model
 */
trait HasWhatsAppAgent
{
    public static function bootHasWhatsAppAgent(): void
    {
        static::creating(function (self $owner): void {
            $owner->webhook_token ??= Str::random(40);
        });
    }

    public function initializeHasWhatsAppAgent(): void
    {
        $this->mergeCasts([
            'whatsapp_driver' => WhatsAppDriver::class,
            'whatsapp_credentials' => 'encrypted:array',
            'whatsapp_status' => ConnectionStatus::class,
        ]);
        $this->hidden = array_values(array_unique([...$this->hidden, 'whatsapp_credentials']));

        if ($this->getFillable() !== []) {
            $this->mergeFillable(['owner_whatsapp', 'whatsapp_driver', 'whatsapp_credentials', 'whatsapp_status', 'whatsapp_number', 'webhook_token']);
        }

        $this->attributes['whatsapp_driver'] ??= WhatsAppDriver::Simulator->value;
        $this->attributes['whatsapp_status'] ??= ConnectionStatus::Disconnected->value;
    }

    public function credential(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->whatsapp_credentials ?? [], $key, $default);
    }

    public function webhookUrl(?WhatsAppDriver $driver = null): string
    {
        $parameters = ['driver' => ($driver ?? $this->whatsapp_driver)->value, 'token' => $this->webhook_token];
        $base = config('whatsapp-agent.webhook_base_url');

        return filled($base)
            ? rtrim((string) $base, '/').route('webhooks.whatsapp', $parameters, absolute: false)
            : route('webhooks.whatsapp', $parameters);
    }

    public function evolutionInstance(): string
    {
        return (string) ($this->credential('instance') ?: config('whatsapp-agent.evolution.instance_prefix').($this->slug ?? $this->getKey()));
    }

    public function evolutionServer(): EvolutionServer
    {
        return EvolutionServer::tryFrom((string) $this->credential('server')) ?? EvolutionServer::Platform;
    }

    public function evolutionUrl(): string
    {
        $url = $this->evolutionServer() === EvolutionServer::Custom ? $this->credential('api_url') : null;

        return rtrim((string) ($url ?: config('whatsapp-agent.evolution.url')), '/');
    }

    public function evolutionApiKey(): string
    {
        $key = $this->evolutionServer() === EvolutionServer::Custom ? $this->credential('api_key') : null;

        return (string) ($key ?: config('whatsapp-agent.evolution.api_key'));
    }

    public function agentSetting(string $key, mixed $default = null): mixed
    {
        $default ??= config("whatsapp-agent.defaults.{$key}");

        return method_exists($this, 'setting')
            ? $this->setting($key, $default)
            : Arr::get((array) ($this->settings ?? []), $key, $default);
    }

    public function saveAgentSettings(array $settings): void
    {
        $this->update(['settings' => array_merge((array) ($this->settings ?? []), $settings)]);
    }

    public function agentName(): string
    {
        return (string) $this->name;
    }

    public function agentDescription(): string
    {
        return $this->agentName();
    }

    public function agentRole(): string
    {
        return 'assistant';
    }

    public function agentDuties(): string
    {
        return 'You answer customers 24/7 using the tools, and answer questions using ONLY the facts below.';
    }

    public function agentLocale(): string
    {
        return (string) ($this->locale ?? app()->getLocale());
    }

    public function agentTimezone(): string
    {
        return (string) ($this->timezone ?? config('app.timezone'));
    }

    public function agentIsActive(): bool
    {
        return (bool) ($this->is_active ?? true);
    }

    public function agentOwnerWhatsApp(): ?string
    {
        return $this->owner_whatsapp ?? null;
    }

    public function agentNotifiables(): iterable
    {
        return method_exists($this, 'users') ? $this->users : [];
    }

    public function assistantName(?string $locale = null): string
    {
        $name = $this->agentSetting('assistant_name');

        return filled($name) ? (string) $name : $this->assistantGender()->defaultName($locale ?? $this->agentLocale());
    }

    public function assistantGender(): AssistantGender
    {
        return AssistantGender::tryFrom((string) $this->agentSetting('assistant_gender')) ?? AssistantGender::Female;
    }

    public function assistantVoice(): AssistantVoice
    {
        $voice = AssistantVoice::tryFrom((string) $this->agentSetting('assistant_voice'));

        return $voice?->gender() === $this->assistantGender() ? $voice : $this->assistantGender()->defaultVoice();
    }

    public function replyLanguage(): ReplyLanguage
    {
        return ReplyLanguage::tryFrom((string) $this->agentSetting('reply_language')) ?? ReplyLanguage::Auto;
    }
}
