<?php

declare(strict_types=1);

namespace TestCases\Models;

use InvalidArgumentException;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\Attributes\Relations\Translations;
use Lsr\Orm\Model;
use Lsr\Orm\ModelRepository;
use Lsr\Orm\TranslationCollection;
use PHPUnit\Framework\TestCase;

final class TranslationMappingTest extends TestCase
{
    public function test_inaccessible_locale_is_rejected_when_constructing_parent(): void {
        $this->expectException(InvalidArgumentException::class);
        try {
            new RestrictedTranslationParent();
        } finally {
            ModelRepository::$generatingConfig = null;
        }
    }
}

class RestrictedTranslationParent extends Model
{
    /** @var TranslationCollection<RestrictedTranslationRow> */
    #[Translations(class: RestrictedTranslationRow::class, mappedBy: 'parent')]
    public TranslationCollection $translations;
}

class RestrictedTranslationRow extends Model
{
    #[ManyToOne]
    public RestrictedTranslationParent $parent;

    public private(set) string $locale;
}
