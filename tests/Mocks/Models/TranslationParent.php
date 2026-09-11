<?php

declare(strict_types=1);

namespace Mocks\Models;

use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\Translations;
use Lsr\Orm\Model;
use Lsr\Orm\TranslationCollection;

#[PrimaryKey('id_translation_parent')]
class TranslationParent extends Model
{
    public const string TABLE = 'translation_parents';

    public string $name = '';

    /** @var TranslationCollection<TranslationRow> */
    #[Translations(class: TranslationRow::class, mappedBy: 'parent', localeProperty: 'locale')]
    public TranslationCollection $translations;
}
