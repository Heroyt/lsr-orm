<?php
declare(strict_types=1);

namespace Lsr\Orm\Attributes;

use Lsr\Orm\Model;

/**
 * Transform property before saving to database and after fetching from database
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
readonly class Transform
{

    public function __construct(
        public string|null|\Closure $save = null,
        public string|null|\Closure $load = null,
    )
    {
    }

    public function transformSave(mixed $value, Model $model): mixed
    {
        if ($this->save === null) {
            return $value;
        }
        return $this->callTransform($this->save, $value, $model);
    }

    protected function callTransform(string|\Closure $transform, mixed $value, Model $model): mixed
    {
        if (is_string($transform)) {
            if (!method_exists($model, $transform)) {
                return $value;
            }
            return $model->$transform($value);
        }
        return $transform($value);
    }

    public function transformLoad(mixed $value, Model $model): mixed
    {
        if ($this->load === null) {
            return $value;
        }
        return $this->callTransform($this->load, $value, $model);
    }
}