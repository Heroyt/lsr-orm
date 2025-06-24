<?php
declare(strict_types=1);

namespace Lsr\Orm\Traits;

use Lsr\Logging\Logger;
use Lsr\Orm\Attributes\JsonExclude;
use Lsr\Orm\ModelRepository;

trait WithLogger
{
    #[JsonExclude]
    protected Logger $logger;

    /**
     * Get logger for this model type
     *
     * @return Logger
     */
    public function getLogger() : Logger {
        if (!isset($this->logger)) {
            $this->logger = ModelRepository::getLogger(static::class);
        }
        return $this->logger;
    }

}