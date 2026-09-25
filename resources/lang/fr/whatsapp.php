<?php

return [
    'connection' => [
        'disconnected' => 'Non connecté',
        'connecting' => 'En attente du scan',
        'connected' => 'Connecté',
    ],
    'drivers' => [
        'cloud' => [
            'label' => 'WhatsApp Cloud API (officielle)',
            'description' => 'L\'API officielle de Meta : numéro professionnel vérifié, boutons de réponse et modèles de rappel approuvés.',
        ],
        'evolution' => [
            'label' => 'Connecter votre WhatsApp par QR',
            'description' => 'Scannez un QR code avec votre numéro WhatsApp Business actuel. Prêt en une minute, sans validation Meta.',
        ],
        'simulator' => [
            'label' => 'Mode test',
            'description' => 'Aucun vrai message n\'est envoyé. Essayez l\'assistant depuis le Bac à sable.',
        ],
    ],
];
