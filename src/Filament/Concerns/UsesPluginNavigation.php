<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Filament\Concerns;

use HoceineEl\WhatsAppAgent\WhatsAppAgentPlugin;

trait UsesPluginNavigation
{
    abstract protected static function pluginKey(): string;

    public static function getNavigationGroup(): ?string
    {
        return rescue(fn (): ?string => WhatsAppAgentPlugin::get()->navigationGroupFor(static::pluginKey()), report: false)
            ?? parent::getNavigationGroup();
    }

    public static function getNavigationSort(): ?int
    {
        return rescue(fn (): ?int => WhatsAppAgentPlugin::get()->navigationSortFor(static::pluginKey()), report: false)
            ?? parent::getNavigationSort();
    }
}
