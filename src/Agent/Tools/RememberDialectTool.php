<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Agent\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;
use Stringable;

class RememberDialectTool extends AgentTool
{
    public function description(): Stringable|string
    {
        return 'Save the language and dialect the customer speaks (e.g. "Moroccan Darija", "Saudi Najdi Arabic", "Egyptian Arabic", "Modern Standard Arabic", "English"). Call it once you can tell, or if they clearly switch.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'dialect' => $schema->string()->required(),
        ];
    }

    protected function run(Request $request): array
    {
        $dialect = Str::limit(trim($request->string('dialect')->toString()), 60, '');

        $this->context->contact->update(['dialect' => $dialect]);

        return ['ok' => true, 'dialect' => $dialect];
    }
}
