<?php

declare(strict_types=1);

namespace Lsr\Orm\Logging;

use Lsr\Orm\Model;
use Psr\Log\LoggerInterface;

interface ModelLoggerProviderInterface
{
    /** @param class-string<Model> $modelClass */
    public function getLogger(string $modelClass): LoggerInterface;
}
