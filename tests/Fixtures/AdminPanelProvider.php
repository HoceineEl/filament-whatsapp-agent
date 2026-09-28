<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Tests\Fixtures;

use Filament\Panel;
use Filament\PanelProvider;
use HoceineEl\WhatsAppAgent\WhatsAppAgentPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel->id('app')->path('app')->default()->plugin(WhatsAppAgentPlugin::make());
    }
}
