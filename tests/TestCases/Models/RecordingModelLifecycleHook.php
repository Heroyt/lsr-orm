<?php

declare(strict_types=1);

namespace TestCases\Models;

use Lsr\Orm\Lifecycle\ModelLifecycleEvent;
use Lsr\Orm\Lifecycle\ModelLifecycleHookInterface;
use Lsr\Orm\Lifecycle\ModelLifecycleScopeInterface;
use Lsr\Orm\Model;

final class RecordingModelLifecycleHook implements ModelLifecycleHookInterface
{
    /** @var list<ModelLifecycleEvent> */
    public array $events = [];

    /** @param list<string> $categories */
    public function __construct(
        public array $categories,
        public bool $fail = false,
    ) {
    }

    public function captures(string $category): bool {
        return in_array($category, $this->categories, true);
    }

    /**
     * @param class-string<Model> $modelClass
     */
    public function begin(
        string $category,
        string $operation,
        string $modelClass,
    ): ModelLifecycleScopeInterface {
        return new RecordingModelLifecycleScope($this, $category, $operation, $modelClass);
    }
}
