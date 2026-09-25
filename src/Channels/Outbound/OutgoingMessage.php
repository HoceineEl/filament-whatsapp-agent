<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Channels\Outbound;

final readonly class OutgoingMessage
{
    /**
     * @param  list<array{id: string, title: string}>  $buttons
     * @param  array{name: string, language: string, parameters: list<string>}|null  $template
     */
    public function __construct(
        public string $to,
        public string $text,
        public array $buttons = [],
        public ?array $template = null,
        public ?string $audioPath = null,
        public ?string $audioMime = null,
    ) {}

    public function isVoice(): bool
    {
        return $this->audioPath !== null;
    }

    /**
     * Numbered fallback for channels without native reply buttons.
     */
    public function textWithNumberedButtons(): string
    {
        if ($this->buttons === []) {
            return $this->text;
        }

        $lines = collect($this->buttons)->values()->map(fn (array $button, int $index): string => ($index + 1).'. '.$button['title']);

        return $this->text."\n\n".$lines->implode("\n");
    }
}
