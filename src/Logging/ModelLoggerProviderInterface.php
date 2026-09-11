<?php

declare(strict_types=1);

namespace Lsr\Orm\Logging;

use Lsr\Logging\Logger;
use Lsr\Orm\Model;

interface ModelLoggerProviderInterface
{
    /** @param class-string<Model> $modelClass */
    public function getLogger(string $modelClass): Logger;
}
