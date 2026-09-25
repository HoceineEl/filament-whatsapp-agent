<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Agent\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class SetReplyFormatTool extends AgentTool
{
    public function description(): Stringable|string
    {
        return 'Switch how you reply to this customer: "voice" sends your replies as WhatsApp voice notes, "text" as written messages. Remembered for future chats.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'format' => $schema->string()->enum(['voice', 'text'])->required(),
        ];
    }

    protected function run(Request $request): array
    {
        if (! $this->context->owner->agentSetting('voice_replies_enabled')) {
            return ['ok' => false, 'error' => 'Voice replies are turned off for this business. Keep replying in text.'];
        }

        $voice = $request->string('format')->toString() === 'voice';

        $this->context->contact->update(['prefers_voice' => $voice]);
        $this->context->replyByVoice = $voice;

        return ['ok' => true, 'format' => $voice ? 'voice' : 'text'];
    }
}
