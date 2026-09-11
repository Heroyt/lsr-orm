<?php

declare(strict_types=1);

namespace Mocks\Models;

use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\Model;

#[PrimaryKey('id_translation_row')]
class TranslationRow extends Model
{
    public const string TABLE = 'translation_rows';

    #[ManyToOne]
    public TranslationParent $parent;

    public string $locale;
    public string $title = '';
    public ?string $body = null;
}
