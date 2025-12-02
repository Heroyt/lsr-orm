<?php
declare(strict_types=1);

namespace Lsr\Orm\Attributes\Transforms;

use Attribute;
use Lsr\Orm\Attributes\Transform;
use Lsr\Orm\Model;

/**
 * Truncate a string to a max length on save and load
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Truncate extends Transform
{

    public function __construct(
        public int    $maxLength,
        public string $suffix = '',
        public bool   $onSave = true,
        public bool   $onLoad = true,
    )
    {
        parent::__construct();
    }

    public function transformSave(mixed $value, Model $model): mixed
    {
        if (!$this->onSave || !is_string($value)) {
            return $value;
        }
        return $this->truncate($value);
    }

    protected function truncate(string $value): string
    {
        if (mb_strlen($value) <= $this->maxLength) {
            return $value;
        }
        $suffixLength = mb_strlen($this->suffix);
        return mb_rtrim(mb_substr($value, 0, $this->maxLength - $suffixLength)) . $this->suffix;
    }

    public function transformLoad(mixed $value, Model $model): mixed
    {
        if (!$this->onLoad || !is_string($value)) {
            return $value;
        }
        return $this->truncate($value);
    }


}