<?php

declare(strict_types=1);

namespace Mocks\Models;

use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\Translations;
use Lsr\Orm\Model;
use Lsr\Orm\TranslationCollection;

#[PrimaryKey('id_translation_custom_parent')]
class TranslationCustomParent extends Model
{
    public const string TABLE = 'translation_custom_parents';

    public string $name = '';

    /** @var TranslationCollection<TranslationCustomRow> */
    #[Translations(class: TranslationCustomRow::class, mappedBy: 'owner', localeProperty: 'language')]
    public TranslationCollection $texts;
}
