<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Support;

final class LanguageDetector
{
    public static function detect(string $text): string
    {
        $arabic = preg_match_all('/\p{Arabic}/u', $text);
        $latin = preg_match_all('/[a-zA-Z]/', $text);

        return $arabic >= $latin || ($arabic === 0 && $latin === 0) ? 'ar' : 'en';
    }
}
