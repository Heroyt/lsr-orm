<?php
declare(strict_types=1);

namespace Lsr\Orm\Attributes\Transforms;

use Attribute;
use Lsr\Orm\Attributes\Transform;
use Lsr\Orm\Model;

/**
 * Pad a string to a certain length on save and load
 *
 * @see https://www.php.net/manual/en/function.str-pad.php
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Pad extends Transform
{

    public function __construct(
        public int    $length,
        public string $padString = " ",
        public int    $mode = STR_PAD_RIGHT,
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
        return str_pad($value, $this->length, $this->padString, $this->mode);
    }

    public function transformLoad(mixed $value, Model $model): mixed
    {
        if (!$this->onLoad || !is_string($value)) {
            return $value;
        }
        return str_pad($value, $this->length, $this->padString, $this->mode);
    }

}