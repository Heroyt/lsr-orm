<?php

declare(strict_types=1);

namespace TestCases\Commands;

use Lsr\Orm\DI\OrmExtension;
use Nette\DI\Compiler;
use PHPUnit\Framework\TestCase;

final class OrmCommandRegistrationTest extends TestCase
{
    public function testDeclaresCommandWhenConsoleSupportIsAvailable(): void {
        $compiler = new Compiler();
        $compiler->addExtension('orm', new OrmExtension());
        $compiler->processExtensions();

        $definition = $compiler->getContainerBuilder()->getDefinition('orm.commands.cache_clean');
        self::assertTrue($definition->getTag('console.command'));
    }

    public function testCommandDeclarationCanBeDisabled(): void {
        $compiler = new Compiler();
        $compiler->addExtension('orm', new OrmExtension());
        $compiler->addConfig([
            'orm' => [
                'commands' => false,
            ],
        ]);
        $compiler->processExtensions();

        self::assertFalse($compiler->getContainerBuilder()->hasDefinition('orm.commands.cache_clean'));
    }
}
