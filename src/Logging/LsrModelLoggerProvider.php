<?php

declare(strict_types=1);

namespace Lsr\Orm\Logging;

use Lsr\Logging\Interface\StorageInterface;
use Lsr\Logging\Logger;

/** ModelRepository owns the logger cache; this provider only constructs loggers. */
final readonly class LsrModelLoggerProvider implements ModelLoggerProviderInterface
{
    public function __construct(
        private ?string $directory = null,
        private ?StorageInterface $storage = null,
    ) {
    }

    public function getLogger(string $modelClass): Logger {
        if ($this->storage === null) {
            return new Logger($this->directory ?? LOG_DIR . 'models/', $modelClass::TABLE);
        }

        return new ModelLogger(
            $this->directory ?? '',
            $modelClass,
            $this->storage,
        );
    }
}
