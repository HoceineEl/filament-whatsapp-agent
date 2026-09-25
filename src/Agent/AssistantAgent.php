<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Agent;

use HoceineEl\WhatsAppAgent\Agent\Tools\HandoffToHumanTool;
use HoceineEl\WhatsAppAgent\Agent\Tools\RememberDialectTool;
use HoceineEl\WhatsAppAgent\Agent\Tools\SaveCustomerNameTool;
use HoceineEl\WhatsAppAgent\Agent\Tools\SetReplyFormatTool;
use HoceineEl\WhatsAppAgent\WhatsAppAgent;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Stringable;

#[MaxSteps(8)]
#[MaxTokens(1024)]
#[Temperature(0.3)]
#[Timeout(60)]
class AssistantAgent implements Agent, Conversational, HasProviderOptions, HasTools
{
    use Promptable;

    /**
     * @param  list<Message>  $history
     */
    public function __construct(
        public readonly AgentContext $context,
        public readonly array $history = [],
    ) {}

    /**
     * Primary model first; rate limits, overloads and quota errors fail over to the lighter model.
     *
     * @return array<string, string>
     */
    public function provider(): array
    {
        return array_filter([
            'gemini' => (string) config('whatsapp-agent.ai.model'),
            'gemini-fallback' => (string) config('whatsapp-agent.ai.fallback_model'),
        ]);
    }

    public function providerOptions(Lab|string $provider): array
    {
        return ['thinking_level' => config('whatsapp-agent.ai.thinking_level')];
    }

    public function instructions(): Stringable|string
    {
        return app(PromptBuilder::class)->build($this->context);
    }

    public function messages(): iterable
    {
        return $this->history;
    }

    public function tools(): iterable
    {
        return collect(WhatsAppAgent::resolveTools($this->context))
            ->merge([SaveCustomerNameTool::class, SetReplyFormatTool::class, RememberDialectTool::class, HandoffToHumanTool::class])
            ->map(fn (object|string $tool): object => is_string($tool) ? app($tool, ['context' => $this->context]) : $tool)
            ->all();
    }
}
