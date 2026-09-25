<?php

return [
    'authors' => [
        'customer' => 'Customer',
        'bot' => 'Assistant',
        'staff' => 'Team',
        'system' => 'Automatic',
    ],
    'handoff' => [
        'ai_failed' => 'The assistant could not answer automatically',
        'daily_limit' => 'Unusually long chat today: the assistant paused to save AI usage',
        'notification_title' => ':name needs a reply from the team',
        'open' => 'Open conversation',
        'owner_phone' => 'You replied from your phone, so the assistant stepped back in this chat.',
        'owner_whatsapp' => '🙋 A customer needs your reply
:name (:phone)
Reason: :reason
Open the inbox to answer.',
    ],
    'message_statuses' => [
        'received' => 'Received',
        'queued' => 'Sending',
        'sent' => 'Sent',
        'delivered' => 'Delivered',
        'read' => 'Read',
        'failed' => 'Failed',
    ],
    'statuses' => [
        'bot' => 'Assistant replying',
        'needs_human' => 'Needs a reply',
        'human' => 'Team replying',
    ],
];
