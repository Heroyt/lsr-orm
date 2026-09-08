<?php

declare(strict_types=1);

namespace Lsr\Orm\Lifecycle;

use Lsr\Orm\Model;

interface ModelLifecycleHookInterface
{
    public function captures(string $category): bool;

    /**
     * @param class-string<Model> $modelClass
     */
    public function begin(
        string $category,
        string $operation,
        string $modelClass,
    ): ModelLifecycleScopeInterface;
}
