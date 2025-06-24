<?php
declare(strict_types=1);

namespace Lsr\Orm\Traits;

use Lsr\Orm\Attributes\JsonExclude;

trait Cacheable
{
    /** @var non-empty-string[] Static tags to add to all cache records for this model. */
    public const    array CACHE_TAGS = [];

    /** @var non-empty-string[] Dynamic tags to add to cache records for this model instance */
    #[JsonExclude]
    protected array $cacheTags = [];

    /**
     * @return non-empty-string[]
     */
    protected function getCacheTags() : array {
        return array_merge(
            ['models', $this::TABLE, $this::TABLE.'/'.$this->id],
            $this::CACHE_TAGS,
            $this->cacheTags,
        );
    }
}