<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Tests\Fixtures;

use HoceineEl\WhatsAppAgent\Agent\Tools\AgentTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

class CheckStockTool extends AgentTool
{
    public function description(): Stringable|string
    {
        return 'Check if a product is in stock.';
    }

    public function schema(JsonSchema $schema): array
    {
        return ['product' => $schema->string()->required()];
    }

    protected function run(Request $request): array
    {
        return ['ok' => true, 'product' => (string) $request->string('product'), 'in_stock' => true];
    }
}
