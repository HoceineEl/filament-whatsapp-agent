<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Enums;

use Filament\Support\Contracts\HasLabel;

enum AssistantGender: string implements HasLabel
{
    case Female = 'female';
    case Male = 'male';

    public function getLabel(): string
    {
        return __("whatsapp-agent::assistant.genders.{$this->value}");
    }

    public function defaultName(?string $locale = null): string
    {
        return __("whatsapp-agent::assistant.default_names.{$this->value}", locale: $locale);
    }

    public function defaultVoice(): AssistantVoice
    {
        return match ($this) {
            self::Female => AssistantVoice::Sulafat,
            self::Male => AssistantVoice::Achird,
        };
    }

    public function promptWord(): string
    {
        return match ($this) {
            self::Female => 'woman',
            self::Male => 'man',
        };
    }
}
