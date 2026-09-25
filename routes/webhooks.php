<?php

declare(strict_types=1);

use HoceineEl\WhatsAppAgent\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix(config('whatsapp-agent.routes.prefix'))
    ->middleware(config('whatsapp-agent.routes.middleware'))
    ->group(function (): void {
        Route::get('cloud/{token}', [WhatsAppWebhookController::class, 'verify'])->name('webhooks.whatsapp.verify');
        Route::post('{driver}/{token}', [WhatsAppWebhookController::class, 'receive'])
            ->whereIn('driver', ['cloud', 'evolution'])
            ->name('webhooks.whatsapp');
    });
