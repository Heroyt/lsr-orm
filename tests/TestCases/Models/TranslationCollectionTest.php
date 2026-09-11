<?php

declare(strict_types=1);

namespace TestCases\Models;

use Dibi\Event;
use InvalidArgumentException;
use LogicException;
use Lsr\Db\DB;
use Lsr\Orm\Exceptions\ModelNotFoundException;
use Lsr\Orm\ModelRepository;
use Mocks\Models\TranslationCustomParent;
use Mocks\Models\TranslationCustomRow;
use Mocks\Models\TranslationParent;
use Mocks\Models\TranslationRow;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TranslationCollectionTest extends TestCase
{
    use DbHelpers;

    private int $selects = 0;

    protected function setUp(): void {
        parent::setUp();
        $this->initDb('dbTranslationCollection');
        DB::getConnection()->query('PRAGMA foreign_keys = ON');
        DB::getConnection()->query(
            'CREATE TABLE IF NOT EXISTS translation_parents (
                id_translation_parent INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL
            )',
        );
        DB::getConnection()->query(
            'CREATE TABLE IF NOT EXISTS translation_rows (
                id_translation_row INTEGER PRIMARY KEY AUTOINCREMENT,
                id_translation_parent INTEGER NOT NULL REFERENCES translation_parents(id_translation_parent) ON DELETE CASCADE,
                locale TEXT NOT NULL,
                title TEXT NOT NULL,
                body TEXT NULL,
                UNIQUE(id_translation_parent, locale)
            )',
        );
        DB::getConnection()->query(
            'CREATE TABLE IF NOT EXISTS translation_custom_parents (
                id_translation_custom_parent INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL
            )',
        );
        DB::getConnection()->query(
            'CREATE TABLE IF NOT EXISTS translation_custom_rows (
                id_translation_custom_row INTEGER PRIMARY KEY AUTOINCREMENT,
                owner_ref INTEGER NOT NULL REFERENCES translation_custom_parents(id_translation_custom_parent) ON DELETE CASCADE,
                language TEXT NOT NULL,
                title TEXT NOT NULL,
                UNIQUE(owner_ref, language)
            )',
        );
        DB::delete(TranslationParent::TABLE, ['1 = 1']);
        DB::delete(TranslationCustomParent::TABLE, ['1 = 1']);
        DB::getConnection()->connection->onEvent[] = function (Event $event): void {
            if ($event->type === Event::SELECT) {
                $this->selects++;
            }
        };
    }

    protected function tearDown(): void {
        ModelRepository::clearInstances();
        $this->cleanupDb();
        parent::tearDown();
    }

    public function test_exact_lookup_and_ordered_fallback_return_one_writable_row(): void {
        $parent = $this->parent();
        $english = $this->translation($parent, 'en', 'English title', 'English body');
        $czech = $this->translation($parent, 'cs', '', null);

        self::assertNull($parent->translations->find('de'));
        self::assertSame($czech, $parent->translations->find('cs'));
        self::assertSame($czech, $parent->translations->resolve(['de', 'cs', 'en']));
        self::assertSame('', $czech->title);
        self::assertNull($czech->body);
        self::assertSame($english, $parent->translations->resolve(['en', 'cs']));
        self::assertNull($parent->translations->resolve(['de', 'fr']));
        self::assertNull($parent->translations->resolve([]));

        $resolved = $parent->translations->resolve(['de', 'cs', 'en']);
        self::assertNotNull($resolved);
        self::assertSame('cs', $resolved->locale);
        $resolved->title = 'Updated Czech';
        self::assertTrue($resolved->save());
        ModelRepository::clearInstances();
        self::assertSame('Updated Czech', TranslationParent::get($parent->id)->translations->find('cs')?->title);
        self::assertSame('English title', TranslationParent::get($parent->id)->translations->find('en')?->title);
        self::assertNull(TranslationParent::get($parent->id)->translations->find('de'));
    }

    public function test_locale_keys_are_not_normalized(): void {
        $parent = $this->parent();
        $upper = $this->translation($parent, 'EN', 'Upper');
        $spaced = $this->translation($parent, ' en ', 'Spaced');

        self::assertNull($parent->translations->find('en'));
        self::assertSame($upper, $parent->translations->find('EN'));
        self::assertSame($spaced, $parent->translations->find(' en '));
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidLocales(): iterable {
        foreach (['find', 'create', 'resolve'] as $operation) {
            yield $operation . ' empty' => [$operation, ''];
            yield $operation . ' whitespace' => [$operation, " \t\n"];
        }
    }

    #[DataProvider('invalidLocales')]
    public function test_empty_locale_is_rejected(string $operation, string $locale): void {
        $translations = $this->parent()->translations;
        $this->expectException(InvalidArgumentException::class);
        if ($operation === 'resolve') {
            $translations->resolve([$locale]);
            return;
        }
        $translations->$operation($locale);
    }

    public function test_unsaved_parent_lookup_does_not_query_or_allow_creation(): void {
        $parent = new TranslationParent();
        $this->selects = 0;
        self::assertNull($parent->translations->find('en'));
        self::assertNull($parent->translations->resolve(['cs', 'en']));
        self::assertSame(0, $this->selects);

        $this->expectException(LogicException::class);
        $parent->translations->create('en');
    }

    public function test_creation_requires_explicit_child_save_and_parent_never_saves_translations(): void {
        $parent = $this->parent();
        $collection = $parent->translations;
        self::assertNull($collection->find('en'));
        $draft = $collection->create('en');
        $draft->title = 'Draft';
        self::assertNull($draft->id);
        self::assertNull($collection->find('en'));

        $parent->name = 'Changed parent';
        self::assertTrue($parent->save());
        self::assertSame(0, DB::select(TranslationRow::TABLE, '*')->count(cache: false));
        self::assertTrue($draft->save());
        self::assertSame($parent->id, $draft->parent->id);
        self::assertSame('en', $draft->locale);
        self::assertSame($draft, $collection->find('en'));

        $draft->title = 'Unsaved edit';
        $parent->name = 'Another parent change';
        self::assertTrue($parent->save());
        self::assertSame(
            'Draft',
            DB::select(TranslationRow::TABLE, 'title')->where('id_translation_row = %i', $draft->id)->fetchSingle(cache: false),
        );
        self::assertSame(
            $parent->id,
            DB::select(TranslationRow::TABLE, 'id_translation_parent')->where('id_translation_row = %i', $draft->id)->fetchSingle(cache: false),
        );
    }

    public function test_creating_an_already_persisted_locale_is_rejected(): void {
        $parent = $this->parent();
        $this->translation($parent, 'en', 'Existing');
        $this->expectException(LogicException::class);
        $parent->translations->create('en');
    }

    public function test_database_uniqueness_closes_the_gap_between_create_and_save(): void {
        $parent = $this->parent();
        $first = $parent->translations->create('en');
        $second = $parent->translations->create('en');
        $first->title = 'Winner';
        $second->title = 'Duplicate';

        self::assertTrue($first->save());
        self::assertFalse($second->save());
        self::assertSame(1, DB::select(TranslationRow::TABLE, '*')->count(cache: false));
        self::assertSame('Winner', $parent->translations->find('en')?->title);
        $otherParent = $this->parent('Other');
        self::assertTrue($otherParent->translations->create('en')->save());
    }

    public function test_ordinary_child_insert_update_and_delete_invalidate_cached_hits_and_misses(): void {
        $parent = $this->parent();
        $retained = $parent->translations;
        self::assertNull($retained->find('en'));
        $this->selects = 0;
        self::assertNull($retained->find('en'));
        self::assertSame(0, $this->selects);

        $row = new TranslationRow();
        $row->parent = $parent;
        $row->locale = 'en';
        $row->title = 'Inserted outside collection';
        self::assertTrue($row->save());
        self::assertSame($row, $retained->find('en'));

        $anotherInstance = new TranslationRow($row->id);
        $anotherInstance->title = 'Updated outside collection';
        self::assertTrue($anotherInstance->save());
        self::assertSame('Updated outside collection', $retained->find('en')?->title);
        self::assertTrue($anotherInstance->delete());
        self::assertNull($retained->find('en'));
    }

    public function test_moving_a_row_invalidates_old_and_new_parent_and_locale_lookups(): void {
        $source = $this->parent('Source');
        $target = $this->parent('Target');
        $row = $this->translation($source, 'en', 'Moving');
        $oldCollection = $source->translations;
        $newCollection = $target->translations;
        self::assertSame($row, $oldCollection->find('en'));
        self::assertNull($oldCollection->find('cs'));
        self::assertNull($newCollection->find('en'));
        self::assertNull($newCollection->find('cs'));

        $row->locale = 'cs';
        self::assertTrue($row->save());
        self::assertNull($oldCollection->find('en'));
        self::assertSame($row, $oldCollection->find('cs'));

        $row->parent = $target;
        $row->locale = 'en';
        self::assertTrue($row->save());
        self::assertNull($oldCollection->find('cs'));
        self::assertNull($oldCollection->find('en'));
        self::assertSame($row, $newCollection->find('en'));
        self::assertNull($newCollection->find('cs'));
        ModelRepository::clearInstances();
        self::assertNull(TranslationParent::get($source->id)->translations->find('cs'));
        self::assertSame('Moving', TranslationParent::get($target->id)->translations->find('en')?->title);
    }

    public function test_parent_deletion_cascades_and_invalidates_retained_children(): void {
        $parent = $this->parent();
        $row = $this->translation($parent, 'en', 'Owned');
        $rowId = $row->id;
        $collection = $parent->translations;
        self::assertSame($row, $collection->find('en'));
        self::assertTrue($parent->delete());
        self::assertSame(0, DB::select(TranslationRow::TABLE, '*')->count(cache: false));
        self::assertNull($collection->find('en'));

        $this->expectException(ModelNotFoundException::class);
        TranslationRow::get($rowId);
    }

    public function test_batch_loading_merges_locales_without_changing_pagination_or_count(): void {
        $parents = [];
        for ($i = 0; $i < 4; $i++) {
            $parent = $this->parent('Parent ' . $i);
            $parents[] = $parent;
            $this->translation($parent, 'en', 'English ' . $i);
            if ($i !== 2) {
                $this->translation($parent, 'cs', 'Czech ' . $i);
            }
        }
        ModelRepository::clearInstances();
        $this->selects = 0;
        self::assertSame(4, TranslationParent::query()->withTranslations(['en'])->count(cache: false));
        self::assertSame(1, $this->selects);

        $this->selects = 0;
        $page = array_values(
            TranslationParent::query()
                ->orderBy('id_translation_parent')->asc()->offset(1)->limit(2)
                ->withTranslations(['en'])->withTranslations(['cs', 'en'])
                ->get(cache: false),
        );
        self::assertSame([$parents[1]->id, $parents[2]->id], array_map(static fn (TranslationParent $parent): ?int => $parent->id, $page));
        self::assertSame(2, $this->selects);
        $this->selects = 0;
        self::assertSame('English 1', $page[0]->translations->find('en')?->title);
        self::assertSame('Czech 1', $page[0]->translations->resolve(['cs', 'en'])?->title);
        self::assertNull($page[1]->translations->find('cs'));
        self::assertSame('English 2', $page[1]->translations->resolve(['cs', 'en'])?->title);
        self::assertSame(0, $this->selects);
    }

    public function test_custom_mapping_works_with_first_preload_and_explicit_save(): void {
        $parent = new TranslationCustomParent();
        $parent->name = 'Custom';
        self::assertTrue($parent->save());
        $row = $parent->texts->create('cs');
        $row->title = 'Custom text';
        self::assertTrue($row->save());
        self::assertSame($parent->id, $row->owner->id);
        self::assertSame('cs', $row->language);
        ModelRepository::clearInstances();

        $this->selects = 0;
        $loaded = TranslationCustomParent::query()->withTranslations(['cs', 'en'], 'texts')->first(cache: false);
        self::assertNotNull($loaded);
        self::assertSame($parent->id, $loaded->id);
        self::assertSame(2, $this->selects);
        $this->selects = 0;
        self::assertSame('Custom text', $loaded->texts->find('cs')?->title);
        self::assertNull($loaded->texts->find('en'));
        self::assertSame('cs', $loaded->texts->resolve(['en', 'cs'])?->language);
        self::assertSame(0, $this->selects);
        self::assertSame(
            $parent->id,
            DB::select(TranslationCustomRow::TABLE, 'owner_ref')->where('id_translation_custom_row = %i', $row->id)->fetchSingle(cache: false),
        );
    }

    public function test_empty_batch_result_does_not_query_translations(): void {
        $this->parent();
        $this->selects = 0;
        self::assertNull(TranslationParent::query()->where('1 = 0')->withTranslations(['en'])->first(cache: false));
        self::assertSame(1, $this->selects);
    }

    public function test_serialization_omits_unloaded_and_loaded_translations_without_queries(): void {
        $parent = $this->parent('Serialized');
        $this->translation($parent, 'en', 'Not serialized');
        ModelRepository::clearInstances();
        $parent = TranslationParent::get($parent->id);
        $this->selects = 0;
        $unloaded = $parent->jsonSerialize();
        self::assertArrayNotHasKey('translations', $unloaded);
        self::assertSame('Serialized', $unloaded['name']);
        self::assertSame(0, $this->selects);

        self::assertNotNull($parent->translations->find('en'));
        $this->selects = 0;
        $loaded = $parent->jsonSerialize();
        self::assertSame($unloaded, $loaded);
        self::assertSame(0, $this->selects);
    }

    public function test_clear_instances_refreshes_externally_retained_collection_hits_and_misses(): void {
        $parent = $this->parent();
        $this->translation($parent, 'en', 'Before external update');
        $collection = $parent->translations;
        self::assertSame('Before external update', $collection->find('en')?->title);
        self::assertNull($collection->find('cs'));

        DB::update(TranslationRow::TABLE, ['title' => 'External update'], ['id_translation_parent = %i', $parent->id]);
        DB::insert(TranslationRow::TABLE, [
            'id_translation_parent' => $parent->id,
            'locale' => 'cs',
            'title' => 'External insert',
            'body' => null,
        ]);
        ModelRepository::clearInstances(TranslationRow::class);
        self::assertSame('External update', $collection->find('en')?->title);
        self::assertSame('External insert', $collection->find('cs')?->title);

        DB::delete(TranslationRow::TABLE, ['id_translation_parent = %i', $parent->id]);
        ModelRepository::clearInstances();
        self::assertNull($collection->find('en'));
        self::assertNull($collection->find('cs'));
    }

    private function parent(string $name = 'Parent'): TranslationParent {
        $parent = new TranslationParent();
        $parent->name = $name;
        self::assertTrue($parent->save());
        return $parent;
    }

    private function translation(TranslationParent $parent, string $locale, string $title, ?string $body = null): TranslationRow {
        $row = $parent->translations->create($locale);
        $row->title = $title;
        $row->body = $body;
        self::assertTrue($row->save());
        return $row;
    }
}
