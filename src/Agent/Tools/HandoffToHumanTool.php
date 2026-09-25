<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Agent\Tools;

use HoceineEl\WhatsAppAgent\Agent\AgentContext;
use HoceineEl\WhatsAppAgent\Messaging\HandoffService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class HandoffToHumanTool extends AgentTool
{
    public function __construct(AgentContext $context, private readonly HandoffService $handoff)
    {
        parent::__construct($context);
    }

    public function description(): Stringable|string
    {
        return 'Pause the assistant and alert the team to reply personally: the customer asks for a person, complains, needs a decision you cannot make '
            .'(refunds, discounts, special requests), has an emergency, or asks anything you cannot answer from the provided information.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'reason' => $schema->string()->description('One short line for the team, in the business language.')->required(),
        ];
    }

    protected function run(Request $request): array
    {
        $this->handoff->handOff($this->context->conversation, (string) $request->string('reason'));
        $this->context->handedOff = true;

        return ['ok' => true, 'instruction' => 'Tell the customer a team member will reply here shortly. Do not promise an exact time.'];
    }
}
