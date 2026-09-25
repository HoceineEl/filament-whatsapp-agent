<?php

return [
    'connection' => [
        'disconnected' => 'Not connected',
        'connecting' => 'Waiting for scan',
        'connected' => 'Connected',
    ],
    'drivers' => [
        'cloud' => [
            'label' => 'WhatsApp Cloud API (official)',
            'description' => 'Meta\'s official API: verified business number, reply buttons and approved reminder templates.',
        ],
        'evolution' => [
            'label' => 'Link your WhatsApp by QR',
            'description' => 'Scan a QR code with your current WhatsApp Business number. Ready in one minute, no Meta approval.',
        ],
        'simulator' => [
            'label' => 'Test mode',
            'description' => 'No real messages are sent. Try the assistant from the Playground.',
        ],
    ],
];
