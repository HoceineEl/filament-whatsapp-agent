<?php

return [
    'authors' => [
        'customer' => 'Client',
        'bot' => 'Assistant',
        'staff' => 'Équipe',
        'system' => 'Automatique',
    ],
    'handoff' => [
        'ai_failed' => 'L\'assistant n\'a pas pu répondre automatiquement',
        'daily_limit' => 'Discussion inhabituellement longue aujourd\'hui : l\'assistant s\'est mis en pause pour économiser l\'usage de l\'IA',
        'notification_title' => ':name attend une réponse de l\'équipe',
        'open' => 'Ouvrir la conversation',
        'owner_phone' => 'Vous avez répondu depuis votre téléphone, l\'assistant s\'est donc retiré de cette discussion.',
        'owner_whatsapp' => '🙋 Un client attend votre réponse
:name (:phone)
Motif : :reason
Ouvrez la boîte de réception pour répondre.',
    ],
    'message_statuses' => [
        'received' => 'Reçu',
        'queued' => 'Envoi en cours',
        'sent' => 'Envoyé',
        'delivered' => 'Distribué',
        'read' => 'Lu',
        'failed' => 'Échec',
    ],
    'statuses' => [
        'bot' => 'L\'assistant répond',
        'needs_human' => 'Réponse requise',
        'human' => 'L\'équipe répond',
    ],
];
