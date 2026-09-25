<?php

declare(strict_types=1);

namespace HoceineEl\WhatsAppAgent\Commands;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'make:whatsapp-agent-profile')]
class MakeAgentProfileCommand extends GeneratorCommand
{
    protected $name = 'make:whatsapp-agent-profile';

    protected $description = 'Create the class that plugs your business into the WhatsApp agent';

    protected $type = 'Agent profile';

    public function handle(): ?bool
    {
        if (parent::handle() === false) {
            return false;
        }

        $this->components->info("Set it in config/whatsapp-agent.php: 'profile' => {$this->qualifyClass($this->getNameInput())}::class,");

        return null;
    }

    protected function getStub(): string
    {
        return __DIR__.'/../../stubs/agent-profile.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Ai';
    }
}
