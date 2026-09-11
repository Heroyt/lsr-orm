<?php

declare(strict_types=1);

namespace TestCases\Logging;

use Lsr\Logging\Interface\StorageInterface;
use Lsr\Logging\Logger;
use Lsr\Logging\LogLevel;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\DI\OrmExtension;
use Lsr\Orm\Logging\LsrModelLoggerProvider;
use Lsr\Orm\Logging\ModelLoggerProviderInterface;
use Lsr\Orm\Model;
use Lsr\Orm\ModelRepository;
use Nette\DI\Compiler;
use Nette\DI\Config\Loader;
use Nette\DI\Container;
use Nette\DI\ContainerLoader;
use Nette\DI\InvalidConfigurationException;
use Nette\Utils\FileSystem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use RuntimeException;
use stdClass;
use Stringable;

final class ModelLoggingTest extends TestCase
{
    private string $directory;

    protected function setUp(): void {
        ModelRepository::setLoggerProvider(null);
        $this->directory = TMP_DIR . 'orm-logging-' . bin2hex(random_bytes(6));
        FileSystem::createDir($this->directory);
    }

    protected function tearDown(): void {
        ModelRepository::setLoggerProvider(null);
        FileSystem::delete($this->directory);
        $file = LOG_DIR . 'models/' . LoggingModel::TABLE . '-' . date('Y-m-d') . '.log';
        if (is_file($file)) {
            unlink($file);
        }
    }

    public function test_standalone_logger_preserves_per_class_cache_path_and_record_context(): void {
        $model = new LoggingModel();
        $logger = $model->getLogger();
        self::assertSame($logger, ModelRepository::getLogger(LoggingModel::class));
        self::assertSame($logger, (new LoggingModel())->getLogger());
        self::assertNotSame($logger, ModelRepository::getLogger(OtherLoggingModel::class));

        $logger->info('standalone model', ['id' => 42]);
        $file = LOG_DIR . 'models/' . LoggingModel::TABLE . '-' . date('Y-m-d') . '.log';
        self::assertStringEndsWith('INFO: standalone model {"id":42}' . "\n", file_get_contents($file));
    }

    public function test_shared_storage_retains_class_and_table_identity_without_altering_other_context(): void {
        $storage = new RecordingModelStorage();
        ModelRepository::setLoggerProvider(new LsrModelLoggerProvider(storage: $storage));
        (new LoggingModel())->getLogger()->info('first', ['id' => 42, 'lsr.orm.model' => 'spoofed']);
        (new OtherLoggingModel())->getLogger()->warning('second');

        self::assertSame([
            ['info', 'first', [
                'id' => 42,
                'lsr.orm.model' => LoggingModel::class,
                'lsr.orm.table' => LoggingModel::TABLE,
            ]],
            ['warning', 'second', [
                'lsr.orm.model' => OtherLoggingModel::class,
                'lsr.orm.table' => OtherLoggingModel::TABLE,
            ]],
        ], $storage->records);
    }

    public function test_provider_switch_and_clear_preserve_acquired_model_logger_lifetime(): void {
        $oldModel = new LoggingModel();
        $oldLogger = $oldModel->getLogger();
        $waitingModel = new LoggingModel();
        $provider = new LsrModelLoggerProvider($this->directory);
        ModelRepository::setLoggerProvider($provider);
        $configured = $waitingModel->getLogger();
        self::assertNotSame($oldLogger, $configured);
        self::assertSame($oldLogger, $oldModel->getLogger());
        self::assertSame($configured, (new LoggingModel())->getLogger());
        self::assertSame($configured, $waitingModel->protectedLogger());

        ModelRepository::clearLoggers();
        $afterClear = (new LoggingModel())->getLogger();
        self::assertNotSame($configured, $afterClear);
        self::assertSame($configured, $waitingModel->getLogger());
        $afterClear->info('configured directory survives clear');
        self::assertStringContainsString(
            'INFO: configured directory survives clear',
            file_get_contents($this->directory . '/' . LoggingModel::TABLE . '-' . date('Y-m-d') . '.log'),
        );

        ModelRepository::setLoggerProvider(null);
        $fallback = (new LoggingModel())->getLogger();
        self::assertNotSame($afterClear, $fallback);
        $fallback->info('restored standalone');
        self::assertStringContainsString(
            'INFO: restored standalone',
            file_get_contents(LOG_DIR . 'models/' . LoggingModel::TABLE . '-' . date('Y-m-d') . '.log'),
        );
    }

    public function test_custom_provider_can_return_one_existing_logger_without_wrapping_or_rewriting_it(): void {
        $shared = new RecordingPsrModelLogger();
        ModelRepository::setLoggerProvider(new SharedModelLoggerProvider($shared));
        $model = new LoggingModel();
        $first = $model->getLogger();
        $second = (new OtherLoggingModel())->getLogger();
        self::assertSame($shared, $first);
        self::assertSame($shared, $second);
        self::assertSame($shared, $model->protectedLogger());
        ModelRepository::clearLoggers();
        self::assertSame($shared, ModelRepository::getLogger(LoggingModel::class));

        $exception = new RuntimeException('shared failure', 17);
        $first->error('shared failure', ['exception' => $exception, 'lsr.orm.model' => 'caller']);
        $second->debug('second model', ['id' => 42]);
        self::assertSame([
            ['error', 'shared failure', ['exception' => $exception, 'lsr.orm.model' => 'caller']],
            ['debug', 'second model', ['id' => 42]],
        ], $shared->records);
    }

    public function test_psr_provider_switch_and_cache_clear_preserve_acquired_instances(): void {
        $original = new RecordingPsrModelLogger();
        ModelRepository::setLoggerProvider(new SharedModelLoggerProvider($original));
        $retainedModel = new LoggingModel();
        self::assertSame($original, $retainedModel->getLogger());
        $waitingModel = new LoggingModel();

        ModelRepository::setLoggerProvider(new class implements ModelLoggerProviderInterface {
            public function getLogger(string $modelClass): LoggerInterface {
                return new RecordingPsrModelLogger();
            }
        });
        $replacement = $waitingModel->getLogger();
        self::assertInstanceOf(RecordingPsrModelLogger::class, $replacement);
        self::assertNotSame($original, $replacement);
        self::assertSame($replacement, (new LoggingModel())->getLogger());
        self::assertNotSame($replacement, (new OtherLoggingModel())->getLogger());
        self::assertSame($original, $retainedModel->protectedLogger());

        ModelRepository::clearLoggers();
        $afterClear = (new LoggingModel())->getLogger();
        self::assertInstanceOf(RecordingPsrModelLogger::class, $afterClear);
        self::assertNotSame($replacement, $afterClear);
        self::assertSame($afterClear, ModelRepository::getLogger(LoggingModel::class));
        self::assertSame($replacement, $waitingModel->getLogger());
        self::assertSame($replacement, $waitingModel->protectedLogger());
        self::assertSame($original, $retainedModel->getLogger());

        $retainedModel->getLogger()->info('retained');
        $waitingModel->getLogger()->warning('replacement');
        $afterClear->error('after clear');
        self::assertSame([['info', 'retained', []]], $original->records);
        self::assertSame([['warning', 'replacement', []]], $replacement->records);
        self::assertSame([['error', 'after clear', []]], $afterClear->records);
    }

    public function test_storage_failure_propagates_synchronously(): void {
        $failure = new RuntimeException('storage failed');
        $storage = new RecordingModelStorage();
        $storage->failure = $failure;
        ModelRepository::setLoggerProvider(new LsrModelLoggerProvider(storage: $storage));
        $this->expectExceptionObject($failure);
        (new LoggingModel())->getLogger()->error('not swallowed');
    }

    public function test_di_initialization_activates_storage_for_models_without_using_global_logger(): void {
        $waitingModel = new LoggingModel();
        $fallback = ModelRepository::getLogger(LoggingModel::class);
        $container = $this->compileNeon(<<<'NEON'
persistence:
    commands: false
    logging:
        storage: @model.storage
services:
    model.storage: TestCases\Logging\RecordingModelStorage
    logger: Lsr\Logging\Logger(%output%, global)
NEON);
        self::assertSame($fallback, ModelRepository::getLogger(LoggingModel::class));
        $container->initialize();
        self::assertSame($container->getService('logger'), $container->getByType(Logger::class));
        $waitingModel->getLogger()->info('after initialization');
        (new OtherLoggingModel())->getLogger()->debug('another model');
        $storage = $container->getService('model.storage');
        self::assertInstanceOf(RecordingModelStorage::class, $storage);
        self::assertSame(
            [LoggingModel::class, OtherLoggingModel::class],
            array_column(array_column($storage->records, 2), 'lsr.orm.model'),
        );
    }

    public function test_di_selects_custom_provider_even_when_commands_are_disabled(): void {
        $waitingModel = new LoggingModel();
        $fallback = ModelRepository::getLogger(LoggingModel::class);
        $container = $this->compileNeon(<<<'NEON'
persistence:
    commands: false
    logging:
        provider: @customProvider
services:
    logger: TestCases\Logging\RecordingPsrModelLogger
    customProvider: TestCases\Logging\SharedModelLoggerProvider(@logger)
NEON);
        self::assertSame($fallback, ModelRepository::getLogger(LoggingModel::class));
        $container->initialize();
        self::assertSame($container->getService('customProvider'), $container->getService('persistence.loggerProvider'));
        $shared = $container->getService('logger');
        self::assertInstanceOf(RecordingPsrModelLogger::class, $shared);
        self::assertSame($shared, $waitingModel->getLogger());
        self::assertSame($shared, $waitingModel->protectedLogger());
        self::assertSame($shared, (new OtherLoggingModel())->getLogger());
        $waitingModel->getLogger()->info('after initialization', ['id' => 42]);
        (new OtherLoggingModel())->getLogger()->warning('another model');
        self::assertSame([
            ['info', 'after initialization', ['id' => 42]],
            ['warning', 'another model', []],
        ], $shared->records);
    }

    /** @param array<string, mixed> $logging */
    #[DataProvider('invalidLogging')]
    public function test_rejects_invalid_logging_configuration(array $logging): void {
        $compiler = new Compiler();
        $compiler->addExtension('persistence', new OrmExtension());
        $compiler->addConfig([
            'persistence' => ['commands' => false, 'logging' => $logging],
            'services' => ['wrong' => stdClass::class],
        ]);
        $this->expectException(InvalidConfigurationException::class);
        $compiler->compile();
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidLogging(): iterable {
        yield 'provider plus directory' => [['provider' => '@wrong', 'directory' => '/logs/']];
        yield 'provider plus storage' => [['provider' => '@wrong', 'storage' => '@wrong']];
        yield 'wrong provider type' => [['provider' => '@wrong']];
        yield 'wrong storage type' => [['storage' => '@wrong']];
        yield 'provider is not a reference' => [['provider' => 'wrong']];
        yield 'storage is not a reference' => [['storage' => 'wrong']];
    }

    private function compileNeon(string $neon): Container {
        $file = $this->directory . '/config.neon';
        file_put_contents($file, $neon);
        $loader = new ContainerLoader($this->directory . '/container', true);
        $class = $loader->load(function (Compiler $compiler) use ($file): void {
            $compiler->addExtension('persistence', new OrmExtension());
            $compiler->addConfig((new Loader())->load($file));
            $compiler->addConfig(['parameters' => ['output' => $this->directory]]);
        }, $file);
        return new $class();
    }
}

#[PrimaryKey('id')]
class LoggingModel extends Model
{
    public const string TABLE = 'orm_logging_test';

    public function protectedLogger(): LoggerInterface {
        return $this->logger;
    }
}

// Two model classes may map the same table; shared storage must still distinguish them.
#[PrimaryKey('id')]
class OtherLoggingModel extends Model
{
    public const string TABLE = 'orm_logging_test';
}

final class RecordingModelStorage implements StorageInterface
{
    /** @var list<array{string, string, mixed}> */
    public array $records = [];
    public ?RuntimeException $failure = null;

    public function store(string|LogLevel $level, string $message, mixed $context = null): void {
        if ($this->failure !== null) {
            throw $this->failure;
        }
        $this->records[] = [$level instanceof LogLevel ? $level->value : $level, $message, $context];
    }
}

final readonly class SharedModelLoggerProvider implements ModelLoggerProviderInterface
{
    public function __construct(private LoggerInterface $logger) {
    }

    public function getLogger(string $modelClass): LoggerInterface {
        return $this->logger;
    }
}

final class RecordingPsrModelLogger extends AbstractLogger
{
    /** @var list<array{mixed, string, array<string, mixed>}> */
    public array $records = [];

    /** @param string|Stringable $message */
    public function log($level, $message, array $context = []): void {
        $this->records[] = [$level, (string) $message, $context];
    }
}
