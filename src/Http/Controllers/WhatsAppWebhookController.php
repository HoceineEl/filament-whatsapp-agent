<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Http\Controllers;

use HoceineEl\WhatsAppAgent\Channels\WhatsAppManager;
use HoceineEl\WhatsAppAgent\Contracts\AgentOwner;
use HoceineEl\WhatsAppAgent\Enums\WhatsAppDriver;
use HoceineEl\WhatsAppAgent\Messaging\WebhookProcessor;
use HoceineEl\WhatsAppAgent\WhatsAppAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request, string $token): Response
    {
        $owner = $this->owner($token);
        $expected = (string) ($owner?->credential('verify_token') ?: config('whatsapp-agent.cloud.verify_token'));

        abort_unless(
            $owner !== null
            && $request->query('hub_mode') === 'subscribe'
            && $expected !== ''
            && hash_equals($expected, (string) $request->query('hub_verify_token')),
            403,
        );

        return response((string) $request->query('hub_challenge'), 200, ['Content-Type' => 'text/plain']);
    }

    public function receive(Request $request, string $driver, string $token, WhatsAppManager $whatsapp, WebhookProcessor $processor): JsonResponse
    {
        $owner = $this->owner($token);
        $driver = WhatsAppDriver::from($driver);

        if ($owner === null || $owner->whatsapp_driver !== $driver) {
            return response()->json(['ok' => true]);
        }

        $gateway = $whatsapp->driver($driver);

        if (! $gateway->verifyWebhook($owner, $request)) {
            Log::warning('Rejected WhatsApp webhook', [WhatsAppAgent::ownerKey() => $owner->getKey(), 'driver' => $driver->value, 'ip' => $request->ip()]);

            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        WhatsAppAgent::within($owner, fn () => $processor->process($owner, $gateway->parseWebhook((array) $request->json()->all())));

        return response()->json(['ok' => true]);
    }

    private function owner(string $token): ?AgentOwner
    {
        $owner = WhatsAppAgent::ownerModel()::query()->where('webhook_token', $token)->first();

        return $owner?->agentIsActive() ? $owner : null;
    }
}
