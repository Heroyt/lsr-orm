<?php
declare(strict_types=1);

namespace Lsr\Orm\Attributes\Transforms;

use Attribute;
use Lsr\Orm\Attributes\Transform;
use Lsr\Orm\Model;

/**
 * Asserts that the string is trimmed of whitespace on save and load
 *
 * @see https://www.php.net/manual/en/function.trim.php
 * @see https://www.php.net/manual/en/function.ltrim.php
 * @see https://www.php.net/manual/en/function.rtrim.php
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Trim extends Transform
{

    public const int RIGHT = 1;
    public const int LEFT = 2;
    public const int BOTH = 3;

    public function __construct(
        public string $characters = " \t\n\r\0\x0B",
        public int    $mode = self::BOTH,
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
        return $this->trim($value);
    }

    protected function trim(string $value): string
    {
        return match (true) {
            (bool)($this->mode & self::BOTH) => trim($value, $this->characters),
            (bool)($this->mode & self::LEFT) => ltrim($value, $this->characters),
            (bool)($this->mode & self::RIGHT) => rtrim($value, $this->characters),
            default => $value,
        };
    }

    public function transformLoad(mixed $value, Model $model): mixed
    {
        if (!$this->onLoad || !is_string($value)) {
            return $value;
        }
        return $this->trim($value);
    }

}