<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Messaging;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Audio;

class VoiceSynthesizer
{
    /**
     * @return array{path: string, mime: string}
     */
    public function forMessage(Model $message): array
    {
        $existing = $message->payload['voice_path'] ?? null;

        if (is_string($existing) && Storage::disk('local')->exists($existing)) {
            return ['path' => $existing, 'mime' => (string) ($message->payload['voice_mime'] ?? 'audio/ogg')];
        }

        $message->loadMissing('owner', 'conversation.contact');
        $gender = $message->owner->assistantGender()->promptWord();
        $dialect = $message->conversation?->contact?->dialect;
        $accent = $dialect ? "a natural {$dialect} accent" : 'the same language and dialect as the text';

        $wav = Audio::of($this->speakable((string) $message->body))
            ->voice($message->owner->assistantVoice()->value)
            ->instructions("Speak as a warm, friendly {$gender} {$message->owner->agentRole()}, in {$accent}, clearly and at a relaxed pace.")
            ->timeout(60)
            ->generate('gemini', (string) config('whatsapp-agent.voice.model'))
            ->content();

        $base = "voice/{$message->ownerId()}/{$message->getKey()}";
        Storage::disk('local')->put("{$base}.wav", $wav);

        $voice = $this->toOpus(Storage::disk('local')->path("{$base}.wav"), Storage::disk('local')->path("{$base}.ogg"))
            ? ['path' => "{$base}.ogg", 'mime' => 'audio/ogg']
            : ['path' => "{$base}.wav", 'mime' => 'audio/wav'];

        $message->update(['payload' => array_merge($message->payload ?? [], ['voice_path' => $voice['path'], 'voice_mime' => $voice['mime']])]);

        return $voice;
    }

    public function speakable(string $text): string
    {
        $spoken = collect(preg_split('/\R/u', $text) ?: [])
            ->map(fn (string $line): string => trim((string) preg_replace(['/[*_~`]|https?:\/\/\S+|\p{Extended_Pictographic}/u', '/^[•\-]\s*/u'], '', trim($line))))
            ->filter()
            ->implode("\n");

        return Str::limit($spoken, (int) config('whatsapp-agent.voice.max_characters'), '');
    }

    private function toOpus(string $from, string $to): bool
    {
        $result = Process::timeout(60)->run([
            (string) config('whatsapp-agent.voice.ffmpeg'), '-y', '-loglevel', 'error', '-i', $from,
            '-c:a', 'libopus', '-b:a', '32k', '-ac', '1', '-ar', '48000', '-application', 'voip', $to,
        ]);

        return $result->successful() && is_file($to) && filesize($to) > 0;
    }
}
