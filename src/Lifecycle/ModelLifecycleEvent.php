<?php

declare(strict_types=1);

namespace Lsr\Orm\Lifecycle;

use Lsr\Orm\Model;

final readonly class ModelLifecycleEvent
{
    public const string MUTATION = 'mutation';
    public const string QUERY = 'query';
    public const string HYDRATION = 'hydration';

    public const string INSERT = 'insert';
    public const string UPDATE = 'update';
    public const string DELETE = 'delete';
    public const string EXISTS = 'exists';
    public const string COUNT = 'count';
    public const string FIRST = 'first';
    public const string GET = 'get';
    public const string FETCH = 'fetch';
    public const string HYDRATE = 'hydrate';

    public const string SUCCESS = 'success';
    public const string FAILURE = 'failure';
    public const string ERROR = 'error';

    /**
     * @param class-string<Model> $modelClass
     */
    public function __construct(
        public string $category,
        public string $operation,
        public string $outcome,
        public float $durationSeconds,
        public string $modelClass,
        public ?int $resultCount = null,
        public ?string $errorType = null,
    ) {
    }
}
