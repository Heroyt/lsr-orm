<?php

declare(strict_types=1);

namespace Lsr\Orm\Commands;

use Lsr\Orm\ModelRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'orm:cache:clean', description: 'Clean ORM model cache.', aliases: ['orm:cache:clear'])]
final class OrmCacheCleanCommand extends Command
{
    public function __construct(private readonly ?string $frameworkTempDirectory = null) {
        parent::__construct();
    }

    protected function configure(): void {
        $this->addOption(
            'directory',
            'd',
            InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
            'ORM model cache directory.',
            $this->getDefaultDirectories(),
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        /** @var string[] $directories */
        $directories = $input->getOption('directory');
        if ($directories === []) {
            $output->writeln(
                '<error>No safe ORM cache directory is available. Specify at least one --directory.</error>',
            );
            return Command::INVALID;
        }

        $this->clearRuntimeCache();

        $success = true;
        foreach (array_unique($directories) as $directory) {
            $directory = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR;

            if ( ! is_dir($directory)) {
                $output->writeln(
                    "<comment>Directory {$directory} does not exist.</comment>",
                    OutputInterface::VERBOSITY_VERBOSE,
                );
                continue;
            }

            $files = glob($directory . '*.php');
            if ($files === false) {
                $output->writeln("<error>Failed to read directory {$directory}.</error>");
                $success = false;
                continue;
            }

            foreach (['*.php.lock', '*.php.meta'] as $pattern) {
                $matches = glob($directory . $pattern);
                if ($matches !== false) {
                    $files = array_merge($files, $matches);
                }
            }

            $removed = 0;
            foreach (array_unique($files) as $file) {
                if ( ! is_file($file)) {
                    $output->writeln(
                        "<comment>Skipped {$file} - not a file.</comment>",
                        OutputInterface::VERBOSITY_DEBUG,
                    );
                    continue;
                }
                if ( ! unlink($file)) {
                    $output->writeln("<error>Failed to delete file {$file}.</error>");
                    $success = false;
                    continue;
                }
                $output->writeln("<comment>Removed {$file}.</comment>", OutputInterface::VERBOSITY_DEBUG);
                $removed++;
            }

            $output->writeln(
                "<comment>Removed {$removed} files from {$directory}.</comment>",
                OutputInterface::VERBOSITY_VERBOSE,
            );
        }

        if ( ! $success) {
            $output->writeln(
                '<error>Some errors occurred while clearing ORM cache. Please check the messages above.</error>',
            );
            return Command::FAILURE;
        }

        $output->writeln('<info>ORM cache cleared successfully.</info>');
        return Command::SUCCESS;
    }

    private function clearRuntimeCache(): void {
        ModelRepository::$cacheFileName = [];
        ModelRepository::$cacheClassName = [];
        ModelRepository::$modelConfig = [];
        ModelRepository::$primaryKeys = [];
        ModelRepository::$reflections = [];
        ModelRepository::$factory = [];
        ModelRepository::$generatingConfig = null;
        ModelRepository::clearInstances();
        ModelRepository::clearLoggers();
    }

    /**
     * @return string[]
     */
    private function getDefaultDirectories(): array {
        if ($this->frameworkTempDirectory !== null) {
            $configuredDirectory = $this->frameworkTempDirectory;
        } elseif (defined('TMP_DIR')) {
            $configuredDirectory = (string) constant('TMP_DIR');
        } else {
            return [];
        }

        $configuredDirectory = trim($configuredDirectory);
        $tempDirectory = rtrim($configuredDirectory, '/\\');
        if (
            $configuredDirectory === ''
            || dirname($configuredDirectory) === $configuredDirectory
            || $tempDirectory === ''
            || preg_match('~^[A-Za-z]:$~D', $tempDirectory) === 1
        ) {
            return [];
        }

        return [$tempDirectory . DIRECTORY_SEPARATOR . 'models'];
    }
}
