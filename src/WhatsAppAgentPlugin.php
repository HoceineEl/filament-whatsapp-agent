<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent;

use Filament\Contracts\Plugin;
use Filament\Panel;
use HoceineEl\WhatsAppAgent\Filament\Pages\Inbox;
use HoceineEl\WhatsAppAgent\Filament\Pages\Playground;
use HoceineEl\WhatsAppAgent\Filament\Pages\WhatsAppConnection;

class WhatsAppAgentPlugin implements Plugin
{
    /** @var array<string, string|null> */
    private array $groups = ['inbox' => null, 'playground' => null, 'connection' => null];

    /** @var array<string, int|null> */
    private array $sorts = ['inbox' => null, 'playground' => null, 'connection' => null];

    /** @var list<class-string> */
    private array $pages = [Inbox::class, Playground::class, WhatsAppConnection::class];

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static */
        return filament(app(static::class)->getId());
    }

    public function getId(): string
    {
        return 'whatsapp-agent';
    }

    /**
     * @param  array<string, string|null>  $groups  keys: inbox, playground, connection
     */
    public function navigationGroups(array $groups): static
    {
        $this->groups = [...$this->groups, ...$groups];

        return $this;
    }

    /**
     * @param  array<string, int|null>  $sorts  keys: inbox, playground, connection
     */
    public function navigationSorts(array $sorts): static
    {
        $this->sorts = [...$this->sorts, ...$sorts];

        return $this;
    }

    /**
     * @param  list<class-string>  $pages  replace a page with your own subclass, or leave one out
     */
    public function pages(array $pages): static
    {
        $this->pages = $pages;

        return $this;
    }

    public function navigationGroupFor(string $page): ?string
    {
        return $this->groups[$page] ?? null;
    }

    public function navigationSortFor(string $page): ?int
    {
        return $this->sorts[$page] ?? null;
    }

    public function register(Panel $panel): void
    {
        $panel->pages($this->pages);
    }

    public function boot(Panel $panel): void {}
}
