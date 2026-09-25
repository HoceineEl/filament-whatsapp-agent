<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Agent\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class SaveCustomerNameTool extends AgentTool
{
    public function description(): Stringable|string
    {
        return "Save the customer's name when they tell you it.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->required(),
        ];
    }

    protected function run(Request $request): array
    {
        $name = mb_substr(trim((string) $request->string('name')), 0, 80);

        if (mb_strlen($name) < 2) {
            return ['ok' => false, 'error' => 'Name too short.'];
        }

        $this->context->contact->update(['name' => $name]);

        return ['ok' => true, 'name' => $name];
    }
}
