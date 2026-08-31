<?php

declare(strict_types=1);

namespace TestCases\Commands;

use Lsr\Orm\Commands\OrmCacheCleanCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class OrmCacheCleanCommandTest extends TestCase
{
    private string $directory;

    protected function setUp(): void {
        $this->directory = TMP_DIR . 'orm-cache-command-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0777, true);
    }

    protected function tearDown(): void {
        $files = glob($this->directory . '/*');
        if ($files !== false) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    public function testClearsOrmCacheFiles(): void {
        file_put_contents($this->directory . '/model-config.php', '<?php return [];');
        file_put_contents($this->directory . '/model-config.php.lock', '');
        file_put_contents($this->directory . '/model-config.php.meta', '');
        $unrelatedFile = $this->directory . '/keep.txt';
        file_put_contents($unrelatedFile, '');

        $tester = new CommandTester(new OrmCacheCleanCommand());
        $code = $tester->execute([
            '--directory' => [$this->directory],
        ]);

        self::assertSame(Command::SUCCESS, $code);
        self::assertFileDoesNotExist($this->directory . '/model-config.php');
        self::assertFileDoesNotExist($this->directory . '/model-config.php.lock');
        self::assertFileDoesNotExist($this->directory . '/model-config.php.meta');
        self::assertFileExists($unrelatedFile);
        self::assertStringContainsString('ORM cache cleared successfully.', $tester->getDisplay());
    }

    public function testRejectsRootFrameworkTempDirectory(): void {
        $tester = new CommandTester(new OrmCacheCleanCommand(DIRECTORY_SEPARATOR));

        $code = $tester->execute([]);

        self::assertSame(Command::INVALID, $code);
        self::assertStringContainsString('Specify at least one --directory', $tester->getDisplay());
    }
}
