<?php

declare(strict_types=1);

namespace Lsr\Orm\Lifecycle;

interface ModelLifecycleHookInterface
{
    public function captures(string $category): bool;

    /**
     * @param class-string<\Lsr\Orm\Model> $modelClass
     */
    public function begin(
        string $category,
        string $operation,
        string $modelClass,
    ): ModelLifecycleScopeInterface;
}
