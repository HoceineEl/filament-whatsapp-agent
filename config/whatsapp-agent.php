<?php

declare(strict_types=1);
use HoceineEl\WhatsAppAgent\Models\Contact;
use HoceineEl\WhatsAppAgent\Models\Conversation;
use HoceineEl\WhatsAppAgent\Models\Message;

return [
    // Your AgentProfile subclass: tools, prompt knowledge, reply gate and inbox panel.
    'profile' => null,

    'models' => [
        'owner' => null,
        'contact' => Contact::class,
        'conversation' => Conversation::class,
        'message' => Message::class,
        'user' => 'App\\Models\\User',
    ],

    'panel' => 'app',

    'columns' => [
        'owner' => 'business_id',
        'contact' => 'contact_id',
    ],

    'tables' => [
        'contacts' => 'whatsapp_contacts',
        'conversations' => 'whatsapp_conversations',
        'messages' => 'whatsapp_messages',
    ],

    'ai' => [
        'model' => env('WHATSAPP_AGENT_AI_MODEL', 'gemini-3.1-flash-lite'),
        'fallback_model' => env('WHATSAPP_AGENT_AI_FALLBACK_MODEL', 'gemini-3.6-flash'),
        'thinking_level' => env('WHATSAPP_AGENT_THINKING_LEVEL', 'low'),
        'history_messages' => 10,
        'max_tokens' => 1024,
    ],

    'voice' => [
        'model' => env('WHATSAPP_AGENT_VOICE_MODEL', 'gemini-3.1-flash-tts-preview'),
        'ffmpeg' => env('WHATSAPP_AGENT_FFMPEG', 'ffmpeg'),
        'max_characters' => 900,
    ],

    'screening' => [
        'model' => env('WHATSAPP_AGENT_SCREENING_MODEL', 'gemini-3.1-flash-lite'),
    ],

    'reply_debounce_seconds' => (int) env('WHATSAPP_AGENT_REPLY_DEBOUNCE_SECONDS', 6),
    'max_inbound_age_minutes' => (int) env('WHATSAPP_AGENT_MAX_INBOUND_AGE_MINUTES', 10),

    'defaults' => [
        'assistant_enabled' => true,
        'voice_replies_enabled' => true,
        'screen_new_contacts' => true,
        'assistant_gender' => 'female',
        'assistant_voice' => null,
        'assistant_name' => null,
        'assistant_tone' => 'warm',
        'assistant_instructions' => null,
        'reply_language' => 'auto',
        'max_ai_replies_per_day' => 60,
    ],

    'webhook_base_url' => env('WHATSAPP_WEBHOOK_BASE_URL'),

    'cloud' => [
        'graph_url' => env('WHATSAPP_CLOUD_GRAPH_URL', 'https://graph.facebook.com'),
        'version' => env('WHATSAPP_CLOUD_VERSION', 'v23.0'),
        'app_secret' => env('WHATSAPP_CLOUD_APP_SECRET'),
        'verify_token' => env('WHATSAPP_CLOUD_VERIFY_TOKEN'),
    ],

    'evolution' => [
        'url' => env('EVOLUTION_API_URL', 'http://localhost:8080'),
        'api_key' => env('EVOLUTION_API_KEY'),
        'instance_prefix' => env('EVOLUTION_INSTANCE_PREFIX', 'wa_'),
        'token_header' => env('EVOLUTION_TOKEN_HEADER', 'X-WhatsApp-Agent-Token'),
        'typing_delay_ms' => (int) env('EVOLUTION_TYPING_DELAY_MS', 1200),
        'events' => ['MESSAGES_UPSERT', 'MESSAGES_UPDATE', 'CONNECTION_UPDATE', 'QRCODE_UPDATED'],
    ],

    'http' => [
        'timeout' => 20,
        'connect_timeout' => 5,
        'retries' => 2,
    ],

    'max_text_length' => 4000,
    'queue' => env('WHATSAPP_QUEUE', 'default'),
    'routes' => [
        'prefix' => 'webhooks/whatsapp',
        'middleware' => ['api', 'throttle:600,1'],
    ],
];
