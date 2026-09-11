<?php

declare(strict_types=1);

namespace Lsr\Orm\Logging;

use Lsr\Logging\Interface\StorageInterface;
use Lsr\Logging\Logger;
use Lsr\Orm\Model;

/** @internal Adds identity to configured storage without replacing Logger's record dispatch. */
final class ModelLogger extends Logger
{
    /** @param class-string<Model> $modelClass */
    public function __construct(
        string $directory,
        private readonly string $modelClass,
        StorageInterface $storage,
    ) {
        parent::__construct($directory, $modelClass::TABLE, $storage);
    }

    /** @param array<string, mixed> $context */
    public function log($level, $message, array $context = []): void {
        $context['lsr.orm.model'] = $this->modelClass;
        $context['lsr.orm.table'] = $this->modelClass::TABLE;
        parent::log($level, $message, $context);
    }
}
