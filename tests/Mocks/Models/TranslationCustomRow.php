<?php

declare(strict_types=1);

namespace Mocks\Models;

use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\Model;

#[PrimaryKey('id_translation_custom_row')]
class TranslationCustomRow extends Model
{
    public const string TABLE = 'translation_custom_rows';

    #[ManyToOne(localKey: 'owner_ref')]
    public TranslationCustomParent $owner;

    public string $language;
    public string $title = '';
}
