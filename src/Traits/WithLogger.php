<?php

declare(strict_types=1);

namespace Lsr\Orm\Traits;

use Lsr\Orm\Attributes\JsonExclude;
use Lsr\Orm\ModelRepository;
use Psr\Log\LoggerInterface;

trait WithLogger
{
    #[JsonExclude]
    protected LoggerInterface $logger;

    /**
     * Get logger for this model type
     *
     * @return LoggerInterface
     */
    public function getLogger(): LoggerInterface {
        if ( ! isset($this->logger)) {
            $this->logger = ModelRepository::getLogger(static::class);
        }
        return $this->logger;
    }

}
