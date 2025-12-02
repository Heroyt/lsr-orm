<?php
declare(strict_types=1);

namespace Lsr\Orm\Attributes\Transforms;

use Attribute;
use Lsr\Orm\Attributes\Transform;
use Lsr\Orm\Model;

/**
 * Clamps a numeric value to a min and/or max on save and load
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Clamp extends Transform
{

    public function __construct(
        public float|int|null $min = null,
        public float|int|null $max = null,
        public bool           $onSave = true,
        public bool           $onLoad = true,
    )
    {
        parent::__construct();
    }

    public function transformSave(mixed $value, Model $model): mixed
    {
        if (!$this->onSave || (!is_int($value) && !is_float($value))) {
            return $value;
        }
        return $this->clampValue($value);
    }

    protected function clampValue(int|float $value): int|float
    {
        if ($this->min !== null && $value < $this->min) {
            return $this->min;
        }
        if ($this->max !== null && $value > $this->max) {
            return $this->max;
        }
        return $value;
    }

    public function transformLoad(mixed $value, Model $model): mixed
    {
        if (!$this->onLoad || (!is_int($value) && !is_float($value))) {
            return $value;
        }
        return $this->clampValue($value);
    }


}