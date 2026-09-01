<?php

declare(strict_types=1);

namespace TestCases\Models;

use Lsr\Orm\Lifecycle\ModelLifecycleEvent;
use Lsr\Orm\Lifecycle\ModelLifecycleScopeInterface;
use Lsr\Orm\Model;
use RuntimeException;

final class RecordingModelLifecycleScope implements ModelLifecycleScopeInterface
{
    private int $startedAt;

    /**
     * @param class-string<Model> $modelClass
     */
    public function __construct(
        private readonly RecordingModelLifecycleHook $hook,
        private readonly string $category,
        private readonly string $operation,
        private readonly string $modelClass,
    ) {
        $this->startedAt = (int) hrtime(true);
    }

    public function complete(
        string $outcome,
        ?int $resultCount = null,
        ?string $errorType = null,
    ): void {
        if ($this->hook->fail) {
            throw new RuntimeException('Lifecycle hook failure.');
        }
        $this->hook->events[] = new ModelLifecycleEvent(
            $this->category,
            $this->operation,
            $outcome,
            max(0.0, ((int) hrtime(true) - $this->startedAt) / 1_000_000_000),
            $this->modelClass,
            $resultCount,
            $errorType,
        );
    }
}
