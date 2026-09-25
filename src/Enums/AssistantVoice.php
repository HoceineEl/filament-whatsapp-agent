<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Enums;

use Filament\Support\Contracts\HasLabel;

enum AssistantVoice: string implements HasLabel
{
    case Sulafat = 'Sulafat';
    case Kore = 'Kore';
    case Aoede = 'Aoede';
    case Leda = 'Leda';
    case Achird = 'Achird';
    case Charon = 'Charon';
    case Orus = 'Orus';
    case Algieba = 'Algieba';

    public function gender(): AssistantGender
    {
        return match ($this) {
            self::Sulafat, self::Kore, self::Aoede, self::Leda => AssistantGender::Female,
            self::Achird, self::Charon, self::Orus, self::Algieba => AssistantGender::Male,
        };
    }

    public function getLabel(): string
    {
        return $this->value.' · '.__('whatsapp-agent::assistant.voice_styles.'.strtolower($this->value));
    }

    /**
     * @return array<string, string>
     */
    public static function optionsFor(AssistantGender $gender): array
    {
        return collect(self::cases())
            ->filter(fn (self $voice): bool => $voice->gender() === $gender)
            ->mapWithKeys(fn (self $voice): array => [$voice->value => $voice->getLabel()])
            ->all();
    }
}
