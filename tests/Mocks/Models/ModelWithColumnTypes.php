<?php

declare(strict_types=1);

namespace Mocks\Models;

use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Model;
use Mocks\Types\JsonType;
use Mocks\Types\UpperCaseType;

#[PrimaryKey('id_model')]
class ModelWithColumnTypes extends Model
{
    public const string TABLE = 'model_with_column_types';

    /** @var list<string> */
    #[JsonType]
    public array $tags = [];

    /** @var array<string, mixed>|null */
    #[JsonType]
    public ?array $settings = null;

    #[UpperCaseType]
    public ?string $code = null;
}
