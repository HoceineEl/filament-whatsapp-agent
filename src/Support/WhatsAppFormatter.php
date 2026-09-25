<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Support;

/**
 * Converts model Markdown into WhatsApp's own formatting (*bold*, _italic_, plain bullets).
 */
final class WhatsAppFormatter
{
    public static function format(string $text): string
    {
        $text = str_replace("\r\n", "\n", trim($text));
        $text = (string) preg_replace('/\*\*(.+?)\*\*/us', '*$1*', $text);
        $text = (string) preg_replace('/__(.+?)__/us', '_$1_', $text);
        $text = (string) preg_replace('/^#{1,6}\s*(.+)$/mu', '*$1*', $text);
        $text = (string) preg_replace('/^\s*[-•]\s+/mu', '• ', $text);
        $text = (string) preg_replace('/\[([^\]]+)\]\((https?:\/\/[^)]+)\)/u', '$1: $2', $text);
        $text = (string) preg_replace("/[ \t]+\n/u", "\n", $text);

        return (string) preg_replace("/\n{3,}/u", "\n\n", $text);
    }
}
