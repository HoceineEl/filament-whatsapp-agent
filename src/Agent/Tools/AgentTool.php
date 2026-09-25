<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Agent\Tools;

use Carbon\CarbonImmutable;
use HoceineEl\WhatsAppAgent\Agent\AgentContext;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

abstract class AgentTool implements Tool
{
    public function __construct(protected readonly AgentContext $context) {}

    public function name(): string
    {
        return Str::snake(Str::beforeLast(class_basename(static::class), 'Tool'));
    }

    public function handle(Request $request): Stringable|string
    {
        $this->context->toolsUsed[] = $this->name();

        try {
            return $this->encode($this->run($request));
        } catch (Throwable $exception) {
            report($exception);

            return $this->encode(['ok' => false, 'error' => $exception->getMessage()]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    abstract protected function run(Request $request): array;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function encode(array $data): string
    {
        return (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function local(CarbonImmutable $instant): CarbonImmutable
    {
        return $instant->setTimezone($this->context->owner->agentTimezone())->locale($this->context->locale());
    }

    protected function parseLocal(string $value): CarbonImmutable
    {
        return CarbonImmutable::parse($value, $this->context->owner->agentTimezone());
    }

    protected function optionalString(Request $request, string $key): ?string
    {
        return $request->filled($key) ? (string) $request->string($key) : null;
    }
}
