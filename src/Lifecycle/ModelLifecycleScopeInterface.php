<?php

declare(strict_types=1);

namespace Lsr\Orm\Lifecycle;

interface ModelLifecycleScopeInterface
{
    public function complete(
        string $outcome,
        ?int $resultCount = null,
        ?string $errorType = null,
    ): void;
}
