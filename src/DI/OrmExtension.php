<?php

declare(strict_types=1);

namespace Lsr\Orm\DI;

use Lsr\Logging\Interface\StorageInterface;
use Lsr\Orm\Commands\OrmCacheCleanCommand;
use Lsr\Orm\Logging\LsrModelLoggerProvider;
use Lsr\Orm\Logging\ModelLoggerProviderInterface;
use Lsr\Orm\ModelRepository;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\Reference;
use Nette\DI\InvalidConfigurationException;
use Nette\PhpGenerator\ClassType;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Symfony\Component\Console\Command\Command;

/**
 * @property-read object{
 *     commands: bool,
 *     logging: object{provider: ?string, storage: ?string, directory: ?string}
 * } $config
 */
final class OrmExtension extends CompilerExtension
{
    public function getConfigSchema(): Schema {
        return Expect::structure([
            'commands' => Expect::bool()->default(true),
            'logging' => Expect::structure([
                'provider' => Expect::string()->pattern('@[a-zA-Z0-9_.-]+')->nullable(),
                'storage' => Expect::string()->pattern('@[a-zA-Z0-9_.-]+')->nullable(),
                'directory' => Expect::string()->nullable(),
            ]),
        ]);
    }

    public function loadConfiguration(): void {
        $logging = $this->config->logging;
        if ($logging->provider !== null && ($logging->storage !== null || $logging->directory !== null)) {
            throw new InvalidConfigurationException(
                'ORM logging.provider cannot be combined with logging.storage or logging.directory.',
            );
        }

        $provider = $this->getContainerBuilder()
            ->addDefinition($this->prefix('loggerProvider'))
            ->setType(ModelLoggerProviderInterface::class)
            ->setAutowired(false);
        if ($logging->provider !== null) {
            $provider->setFactory(new Reference(substr($logging->provider, 1)));
        } else {
            $provider->setFactory(LsrModelLoggerProvider::class, [
                'directory' => $logging->directory,
                'storage' => $logging->storage === null ? null : new Reference(substr($logging->storage, 1)),
            ]);
        }

        if ( ! $this->config->commands || ! class_exists(Command::class)) {
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

    public function beforeCompile(): void {
        $builder = $this->getContainerBuilder();
        $builder->resolve();
        foreach ([
            'provider' => ModelLoggerProviderInterface::class,
            'storage' => StorageInterface::class,
        ] as $option => $type) {
            $reference = $this->config->logging->$option;
            if ($reference === null) {
                continue;
            }
            $serviceType = $builder->getDefinition(substr($reference, 1))->getType();
            if ($serviceType === null || ! is_a($serviceType, $type, true)) {
                throw new InvalidConfigurationException('ORM logging.' . $option . ' must reference a ' . $type . ' service.');
            }
        }
    }

    public function afterCompile(ClassType $class): void {
        $this->initialization->addBody(
            '\\' . ModelRepository::class . '::setLoggerProvider($this->getService(?));',
            [$this->prefix('loggerProvider')],
        );
    }
}
