<?php

declare(strict_types=1);

namespace Lsr\Orm\DI;

use Lsr\Orm\Commands\OrmCacheCleanCommand;
use Nette\DI\CompilerExtension;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Symfony\Component\Console\Command\Command;

/**
 * @property-read object{commands: bool} $config
 */
final class OrmExtension extends CompilerExtension
{
    public function getConfigSchema(): Schema {
        return Expect::structure([
            'commands' => Expect::bool()->default(true),
        ]);
    }

    public function loadConfiguration(): void {
        if (!$this->config->commands || !class_exists(Command::class)) {
            return;
        }

        $this->getContainerBuilder()
            ->addDefinition($this->prefix('commands.cache_clean'))
            ->setFactory(OrmCacheCleanCommand::class)
            ->setTags([
                'lsr' => true,
                'orm' => true,
                'cache' => true,
                'console.command' => true,
                'command' => true,
            ]);
    }
}
