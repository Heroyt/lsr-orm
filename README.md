# LSR ORM

`lsr/orm` is the Laser framework's attribute-driven ORM on top of LSR database, cache, serialization and object validation. It provides models, queries, collections, relationships and generated model metadata. Its namespace is `Lsr\Orm`.

## Requirements

- PHP `>=8.4`.
- Nette DI `^3.2`, LSR logging `^0.3.2`, cache/serializer `^0.3`, DB `^0.3.1` and object-validation `^0.3.4`.
- No PHP extensions are declared directly; install the extensions required by the database driver and other dependencies. See [composer.json](composer.json).
- A configured `Lsr\Db\Connection` with its cache and mapper services, initialized through `Lsr\Db\DB::init()`, and application-owned database tables.
- `TMP_DIR` and, for default file logging, `LOG_DIR` constants with trailing directory separators and writable locations. Model metadata is generated under `TMP_DIR . 'models/'`; default model loggers write under `LOG_DIR . 'models/'`.
- Optional `lsr/console` integration to load the `orm:cache:clean` command into the framework console.

## Installation

```sh
composer require lsr/orm
```

## Defining a model

Model classes extend `Lsr\Orm\Model`, specify the table name and describe their fields with typed public properties. Set the primary-key column explicitly when it is not `id`:

```php
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Model;

#[PrimaryKey('id_article')]
class Article extends Model
{
    public const string TABLE = 'articles';

    public string $title;
}
```

This definition assumes an application-provisioned `articles` table with `id_article` and `title` columns. Declaring the class does not create the table. The inherited `$id` property represents the primary key; do not duplicate it as an ordinary persisted property.

After database/cache initialization and filesystem setup, use `Article::get($id)` to fetch by primary key, `Article::query()` for a model query, and `save()` on an instance to insert or update it. `save()` validates the model and returns a boolean result. Missing rows and invalid data have distinct ORM exceptions; handle them at the application boundary rather than assuming every fetch succeeds.

The example follows [`Model`](src/Model.php), [`ModelConfigProvider`](src/Config/ModelConfigProvider.php) and the concrete [test model definitions](tests/Mocks/Models). Persistence behavior is implemented in [`ModelSave`](src/Traits/ModelSave.php) and retrieval in [`ModelFetch`](src/Traits/ModelFetch.php).

## Column types

**Requires an installed `lsr/orm` newer than `0.4.0`.**

A column type describes how one property is converted to and from its database column. Use it when
a column's storage format differs from the property's PHP type - a value object, a JSON document, an
encrypted or a spatial column - instead of assigning driver-specific values to a `mixed` property.

Extend [`Lsr\Orm\Attributes\ColumnType`](src/Attributes/ColumnType.php), mark the concrete class as
an attribute and place it on the property. A type is reusable: the same class can be declared on any
number of properties and models.

```php
use Attribute;
use Dibi\Expression;
use Lsr\Orm\Attributes\ColumnType;
use Lsr\Orm\Model;

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class PointType extends ColumnType
{
    public function __construct(public int $srid = 4326) {
    }

    public function toDatabase(mixed $value, Model $model) : mixed {
        assert($value === null || $value instanceof Point);
        return $value === null
            ? null
            : new Expression('ST_GeomFromText(%s, %i)', $value->toWkt(), $this->srid);
    }

    public function fromDatabase(mixed $value, Model $model) : mixed {
        return is_string($value) ? Point::fromWkt($value) : null;
    }
}
```

```php
#[PrimaryKey('id_place')]
class Place extends Model
{
    public const string TABLE = 'places';

    #[PointType(srid: 4326)]
    public ?Point $position = null;
}
```

`$place->save()` now writes `position = ST_GeomFromText('POINT(14.1475 49.3088)', 4326)`, and
hydration converts the stored column back through `fromDatabase()`.

### Rules

- A property may declare **at most one** column type; a second one is a configuration error thrown
  while generating the model config.
- The type owns the whole conversion of its column. Built-in enum, date-time, scalar-cast and
  array/object handling is skipped, so a typed property may hold any PHP value - including `array`
  and `object` properties, which are otherwise not persisted.
- Both directions also receive `null`; the type decides what a missing value means.
- `#[Transform]` attributes still run, always on the PHP-side value: on save before `toDatabase()`,
  on load after `fromDatabase()`.
- Column types are part of the generated model config. Clear it (`orm:cache:clean`, or remove
  `TMP_DIR/models`) after adding or removing one.

### Value conversion vs. SQL conversion

`toDatabase()` may return either a bound value or SQL:

- `null` or a scalar is bound as a query parameter.
- A dibi `Expression` or `Literal` is rendered as SQL - `new Expression('ST_GeomFromText(%s, %i)',
  $wkt, 4326)`, `new Expression('%bin', $binary)` for a binary column, `new Literal('now()')`. Writes
  containing an expression are always translated by dibi; they transparently bypass the ORM's native
  PDO fast path, which only binds scalars.

There is no read-side SQL wrapper: model queries always select `*`, and models are hydrated from
rows produced by arbitrary queries, so `fromDatabase()` receives the column exactly as the driver
returns it. When a column is unreadable in its stored form (for example a geometry that the driver
returns as WKB), either parse that representation in `fromDatabase()` or keep the readable copy in
companion columns.

## Owned content translations (since 0.3.23)

Use `#[Translations]` for database-backed multilingual content owned by a model, such as product descriptions. This is opt-in: existing `OneToMany`, `ManyToOne` and other ordinary relations retain their behavior. Both models extend ordinary `Model`; no special translatable parent or translation base class is required.

This feature requires an installed **`lsr/orm` 0.3.23 or newer**. Older installed versions do not provide the attribute, collection or query method. Check the application's lock file and installed source before using them; updating these docs or installing the [ORM skill](https://github.com/Heroyt/lsr-skills/blob/master/skills/lsr-orm/SKILL.md) does not upgrade an application or establish package publication.

### Declare the parent and translation row

```php
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\Attributes\Relations\Translations;
use Lsr\Orm\Model;
use Lsr\Orm\TranslationCollection;

#[PrimaryKey('id_product')]
final class Product extends Model
{
    public const string TABLE = 'products';

    public string $sku;

    /** @var TranslationCollection<ProductTranslation> */
    #[Translations(
        class: ProductTranslation::class,
        mappedBy: 'product',
        localeProperty: 'locale',
    )]
    public TranslationCollection $translations;
}

#[PrimaryKey('id_product_translation')]
final class ProductTranslation extends Model
{
    public const string TABLE = 'product_translations';

    #[ManyToOne(foreignKey: 'id_product', localKey: 'product_id')]
    public Product $product;

    public string $locale;
    public string $title;
    public ?string $description = null;
}
```

`mappedBy` names the child's **PHP parent property**, not its FK column; `localeProperty` names its public non-null `string` property. The parent property must be public and non-null `TranslationCollection`, with its child type documented for static analysis. The ORM initializes it from generated metadata: do not construct it yourself or also declare it as an ordinary `OneToMany`. All content fields are ordinary typed child properties.

Use an instantiable child class. The collection and both child key properties must be writable, non-static properties without PHP property hooks; readonly/virtual keys are unsupported. The child parent/locale keys must be persisted normally, without `NoDB` or `Transform`. The parent key must have exactly one `ManyToOne` referencing the parent's primary key, without a relation factory; if its `class` argument is supplied, it must match the declared parent-property type. Unsupported declarations fail metadata generation with `InvalidArgumentException`.

The collection property may have another name, such as `$localizedContent`; use that name in property access and the `withTranslations()` `property` argument. Regenerate model metadata after adding or changing these declarations. Existing generated configurations without translation relations remain compatible, but cannot discover newly added declarations until regenerated.

### Own the schema in application migrations

The attribute does not create or migrate tables. Each translation row needs an integer surrogate primary key, a non-null parent FK and locale column, **`UNIQUE (parent FK, locale)`**, and a real FK with **`ON DELETE CASCADE`**. For the models above, this is an illustrative SQLite schema:

```sql
PRAGMA foreign_keys = ON;

CREATE TABLE products (
    id_product INTEGER PRIMARY KEY AUTOINCREMENT,
    sku TEXT NOT NULL
);

CREATE TABLE product_translations (
    id_product_translation INTEGER PRIMARY KEY AUTOINCREMENT,
    product_id INTEGER NOT NULL,
    locale TEXT COLLATE BINARY NOT NULL,
    title TEXT NOT NULL,
    description TEXT NULL,
    UNIQUE (product_id, locale),
    FOREIGN KEY (product_id) REFERENCES products (id_product) ON DELETE CASCADE
);
```

Adapt column types, identity syntax and FK enforcement to the application's database. The ORM does not substitute a composite key for the child's integer ID, enforce this schema on deployment, or emulate missing cascades. Enable SQLite FK enforcement on every relevant connection; verify equivalent enforcement in other engines.

Locale keys are explicit application data: there is no ambient locale, language negotiation, case folding, trimming, or underscore/hyphen conversion. Empty and whitespace-only locale arguments are rejected; other strings are preserved exactly, including any surrounding whitespace. Choose one canonical representation at the application boundary and use it consistently for stored rows, lookups, fallback lists and batch loading. Database equality and the unique index must match those keys: use a suitable case-sensitive/binary collation and check the engine's whitespace/padding rules rather than allowing `en-GB` and `en-gb` to compare equal in SQL but differ in memory. Store the source/default locale as another translation row; there is no special source-language field on the parent.

### Read with fallback; edit an exact locale

```php
$product = Product::get($id);

$english = $product->translations->find('en-GB'); // ProductTranslation|null
$display = $product->translations->resolve(['fr-FR', 'en-GB']);

$view = $display === null ? null : [
    'locale' => $display->locale,
    'title' => $display->title,
    'description' => $display->description,
];
```

- `find(string $locale): ?T` returns the exact persisted row or `null`; it returns `null` for an unsaved parent.
- `resolve(array $locales): ?T` returns the first existing **whole row** in the supplied order, or `null`. An empty list resolves to `null`. A row whose description is `null` still wins; missing fields are never filled from another locale.
- Resolution returns the actual writable child model, including its actual locale, not a synthetic merged object. Do not use it to select an editing target: saving a fallback row changes that fallback language, not the requested language. Authorize editing independently of locale selection.

Create/save the parent first, then explicitly save each exact translation:

```php
$product = new Product();
$product->sku = 'example-001';
if (!$product->save()) {
    throw new RuntimeException('Could not save product.');
}

$locale = 'en-GB'; // Validated application locale, not an implicit global value.
$translation = $product->translations->find($locale)
    ?? $product->translations->create($locale);
$translation->title = 'Example product';
$translation->description = 'Administrator-authored content.';
if (!$translation->save()) {
    throw new RuntimeException('Could not save product translation.');
}
```

`create(string $locale): T` constructs an **unsaved** child and assigns its parent and locale; it does not insert, save the parent, or reserve a locale. Fill required content fields before saving. Lookups describe persisted rows, not pending drafts; retain the returned draft yourself. Calling the parent's `save()` never saves translations, including previously loaded/edited children. Ordinary relation synchronization never detaches translation children or nulls their non-null parent FK.

Handle these failures at the application boundary:

| Operation | Failure contract |
| --- | --- |
| Locale lookup, resolution, creation or preload with an empty/whitespace-only locale | `InvalidArgumentException`; validate user input and the application's supported-locale policy. Locale lists must contain strings. |
| `create()` before the parent is persisted | `LogicException`; save the parent successfully first. |
| `create()` for an already persisted locale | `LogicException`; use exact `find()` when editing. |
| `withTranslations()` names a property that is not a declared translation relation | `InvalidArgumentException`; use the actual declared property and regenerated metadata. |
| Saving invalid child data | Normal ORM/object-validation failures, including `Lsr\ObjectValidation\Exceptions\ValidationException`; creation does not bypass validation. |
| `save()` / `delete()` returns `false`, or persistence throws | Treat the write as failed under the existing ORM/driver contract; do not report success. The unique constraint remains authoritative if concurrent writers race after `find()`/`create()`. |

To delete one language, find its exact row, call its `delete()`, and check the boolean result. Deleting the parent uses the database's owned `ON DELETE CASCADE` and invalidates translation lookups/child instances in the current process; database cascades do not execute each child's PHP delete hooks. Multi-row atomicity and exception/rollback handling remain application responsibilities under the installed ORM/DB transaction behavior; this feature does not redesign `save()` transaction semantics.

### Batch only the locales needed

```php
$products = Product::query()
    ->withTranslations(['fr-FR'])
    ->withTranslations(['en-GB'])
    ->get();

foreach ($products as $product) {
    $display = $product->translations->resolve(['fr-FR', 'en-GB']);
    // Render $display, or the application's explicit no-translation state.
}

$first = Product::query()->withTranslations(['en-GB'])->first();
```

`ModelQuery::withTranslations(array $locales, string $property = 'translations'): static` batches child loading for the returned parents on `get()` and `first()`, including remembered missing locales. Repeated calls for the same property merge the requested locales. Reads use one child query per bounded batch/relation, not an unconditional single query for every result size; the current batch limits are 200 parent IDs and 100 locale keys. For another property name, call `->withTranslations(['en-GB'], property: 'localizedContent')`. This is preload configuration, not a parent filter, fallback selection, or locale sort: `count()` is unchanged, and the caller still supplies resolution order. Locales outside the preload are loaded on demand.

### Serialization and cache lifecycle

`Model::jsonSerialize()` omits translation relations by default, even if loaded. Build an explicit DTO/view payload such as `$view` above when exposing a selected row; batch loading must not unexpectedly expand JSON or expose every locale. Other serializer integrations and custom serialization remain application-owned.

Translation exact/batch reads bypass persistent DB query caching (`fetchAll(cache: false)`). Collections cache hits and misses in process memory. Successful child ORM insert/update/delete clears affected lookup state; moving a persisted row to another parent or locale clears both its old and new lookup state. Parent deletion clears owned child state. `ModelRepository::clearInstances()` also clears translation lookup caches, including collections still retained outside the repository.

These are **in-process lifecycle guarantees**, not cross-process refresh or a raw-write observer. `clearInstances()` does not mutate an already retained child object's content into a fresh row. Do not retain models across requests/jobs; clear the repository at each lifecycle boundary and refetch. Direct SQL, bulk/raw writes and external writers bypass ORM mutation handling: explicitly clear in-process instance/translation state and refetch after those writes. Ordinary `Model::query()` result caches retain their existing application invalidation contract; `withTranslations()` respects the parent query's normal `get()`/`first()` cache setting but always bypasses DB caching for child reads. Invalidate any separately cached ordinary queries affected by a raw write as well.

### Content is not a UI text catalog

This relation stores user/administrator-authored content. Application UI strings still belong to gettext/PO/MO or the optional NEON/text-catalog pipeline; do not store their semantic keys or compile these content rows into a competing UI catalog. Locale selection and fallback policy remain application-owned. See the [localization skill](https://github.com/Heroyt/lsr-skills/blob/master/skills/lsr-localization/SKILL.md) and [text-catalog skill](https://github.com/Heroyt/lsr-skills/blob/master/skills/lsr-text-catalog/SKILL.md) for those separate responsibilities.

## Integration and metadata lifecycle

The ORM does not bootstrap a connection by itself. The [database test helper](tests/TestCases/Models/DbHelpers.php) demonstrates `DB::init(new Connection(...))` with a cache, mapper and SQLite configuration; adapt the service construction and schema management to the application's database rather than copying the test database lifecycle. [tests/bootstrap.php](tests/bootstrap.php) illustrates the required filesystem constants.

- [`Attributes`](src/Attributes) contains relationship, mapping, serialization and lifecycle declarations. [`ModelCollection`](src/ModelCollection.php) represents typed model collections.
- [`ModelRepository`](src/ModelRepository.php) holds loaded model instances and generated configuration state in process memory. Long-running applications must manage this state at appropriate lifecycle boundaries; `ModelRepository::clearInstances()` clears the instance registry.
- Model metadata is generated as PHP files under the model cache directory. When model declarations change, invalidate the generated metadata as part of deployment; do not treat it as application source.
- [`Lsr\Orm\DI\OrmExtension`](src/DI/OrmExtension.php) registers `orm:cache:clean` when `commands` is enabled and Symfony Console is available, and selects the model logger provider during container initialization. It does not wire the database/cache stack. The [command implementation](src/Commands/OrmCacheCleanCommand.php) is the reference for cache-cleaning integration.

## Model logging

**Published patch API: `lsr/orm 0.3.25`.** Configurable model logging requires `lsr/logging ^0.3.2`. The published `0.3.25` patch retains concrete `Lsr\Logging\Logger` return types and the protected `Logger $logger` property; its custom providers must return an LSR logger.

**Unreleased minor API: `lsr/orm 0.4`.** `Model::getLogger()`, `ModelRepository::getLogger()`, the inherited protected `$logger` property, and [`ModelLoggerProviderInterface::getLogger()`](src/Logging/ModelLoggerProviderInterface.php) expose `Psr\Log\LoggerInterface`. A custom provider may return any PSR-3 implementation directly, including an existing shared logger:

```php
use Psr\Log\LoggerInterface;
use Lsr\Orm\Logging\ModelLoggerProviderInterface;
use Lsr\Orm\Model;

final readonly class SharedModelLoggerProvider implements ModelLoggerProviderInterface
{
    public function __construct(private LoggerInterface $logger) {}

    /** @param class-string<Model> $modelClass */
    public function getLogger(string $modelClass): LoggerInterface
    {
        return $this->logger;
    }
}
```

The ORM returns the provider's exact object without wrapping it or modifying its records. A custom provider owns model identity/routing if needed; built-in model/table context enrichment is not applied to custom PSR loggers. The built-in provider still returns a concrete LSR logger and retains its covariant `Logger` return type.

### Migrating to unreleased 0.4

- Audit callers and injected types before adopting `0.4`; the published `0.3.25` contract does not accept providers declaring a generic `LoggerInterface` return. Existing providers returning concrete `Logger` remain valid on `0.4` through covariance.
- `exception()` and `logDb()` are **not removed** from `Lsr\Logging\Logger`, but they are not PSR methods and are no longer guaranteed by a model getter. Use them only when explicitly holding a concrete LSR logger, not when accepting an arbitrary model logger.
- PHP property types are invariant: subclasses that redeclare the inherited protected logger property must change its type to `LoggerInterface` or remove the redundant declaration. A getter override returning concrete `Logger` is covariant, but must actually guarantee that concrete result rather than blindly returning the generic parent getter.
- When replacing `exception($exception)` at callers, retain the existing two-record sequence rather than merging it into one structured exception record:

  ```php
  $logger = $model->getLogger();
  $logger->error('Thrown Exception (' . $exception->getCode() . '): ' . $exception->getMessage());
  $logger->debug($exception->getTraceAsString());
  ```

  Preserve any surrounding messages, contexts and record order as well. Internal ORM logging already emits this error/debug pair unchanged; storage exceptions still propagate synchronously. Audit concrete-only `logDb()` calls separately and preserve their record behavior when migrating them to PSR calls.

Application adoption is explicit: these contracts are unreleased and existing applications should stay on their published dependency constraints and locks until their logger callers are migrated.

### Defaults and lifetime

Without DI or custom configuration, the repository lazily creates one logger per model class using `new Logger(LOG_DIR . 'models/', $modelClass::TABLE)`. Existing filenames, message levels/content and context remain unchanged. The unrelated application `@logger` is never selected implicitly.

[`LsrModelLoggerProvider`](src/Logging/LsrModelLoggerProvider.php) accepts `?string $directory = null` and `?Lsr\Logging\Interface\StorageInterface $storage = null`. A directory replaces the complete model log directory, not its parent. `LOG_DIR` is evaluated only at first default logger lookup, not when compiling or initializing DI. With explicit storage, the storage owns output destinations and no `LOG_DIR` is needed; the optional directory is only the LSR logger's path argument.

`ModelRepository` owns the per-class logger cache; the built-in provider does not keep a duplicate cache. Direct provider calls construct loggers rather than caching them. `ModelRepository::setLoggerProvider(?ModelLoggerProviderInterface $provider): void` selects the provider and clears the repository logger cache; `null` restores standalone defaults. `clearLoggers()` clears only that cache, retaining the provider. A custom provider may return the same shared logger again after a clear.

Model instances that already acquired a logger retain it, including protected-property access by subclasses. This preserves existing instance-lifetime behavior. Instances created earlier but not yet accessing a logger use the currently selected provider. Changing providers or clearing the cache does not rewrite already-retained logger references. Initialize the container before model logging, and do not retain models across application lifecycle boundaries.

### Nette DI configuration

```neon
extensions:
    orm: Lsr\Orm\DI\OrmExtension

orm:
    logging:
        provider: null
        storage: null
        directory: null
```

The extension always exposes non-autowired `@orm.loggerProvider` (or `<extension>.loggerProvider` under another extension name), including when `commands: false`. Normal `Container::initialize()` installs it in `ModelRepository`; merely compiling or constructing a raw container does not. Model logging during earlier extension initialization or during provider construction still precedes this activation, so keep it after container initialization. The static repository is process-wide: initializing another container selects its provider for subsequent lookups.

`provider` and `storage` accept native named `@service` references. To use the custom provider above:

```neon
services:
    sharedModelProvider: SharedModelLoggerProvider(@logger)

orm:
    logging:
        provider: @sharedModelProvider
```

A non-null `provider` cannot be combined with non-null `storage` or `directory`; conflicting settings and incompatible service types are rejected rather than silently ignored.

### Shared stacks and OpenTelemetry

For the built-in provider, `logging.storage` replaces the default per-table file storage with an explicitly selected base storage. It may be a shared stack:

```neon
services:
    modelFormatter: Lsr\Logging\Formatter\LegacyFormatter
    modelFile: Lsr\Logging\Storage\DailyLogStorage(%modelLogDir%, models, @modelFormatter)
    modelStack: Lsr\Logging\Storage\StackStorage([@modelFile, @otel.logging.storage])

orm:
    logging:
        storage: @modelStack
```

Here `%modelLogDir%` is an application-defined writable directory; `@otel.logging.storage` must be supplied by an enabled `Lsr\Otel\DI\OtelExtension` registered as `otel`. This OTEL example requires the compatible optional OTEL package and logging `^0.3.4`; base-storage injection itself uses APIs available since logging `0.3.2`, now the ORM runtime minimum. To export only to OTEL, select `storage: @otel.logging.storage` directly.

Only the configured-storage path adds authoritative `lsr.orm.model` (fully qualified model class) and `lsr.orm.table` context keys, replacing caller values for those reserved keys while preserving other context. Even two classes mapped to one table remain distinguishable. An internal LSR Logger subclass adds this context then uses the normal parent logging pipeline; record-aware storage on logging `0.3.4+` also retains the table as `lsr.logger.name`. Stack exception policy, filtering, ordering and flush behavior remain those of the configured logging/telemetry services.

Dynamic model loggers are not DI logger services, so OTEL `autoWire` cannot discover them. **Explicitly select an OTEL-containing base storage** rather than relying on `autoWire` to instrument every model. This opt-in shared storage does not automatically retain the old per-table file destinations: put the desired file destinations in the stack, or supply a custom provider when routing must vary by model (`0.3.25` requires concrete LSR results; unreleased `0.4` accepts any PSR logger).

## Development

CI runs the complete suite on PHP 8.4 and 8.5 with `sqlite3` and `pdo_sqlite`; no MySQL or Redis server is needed. Install the PHP extensions listed in [.github/workflows/ci.yml](.github/workflows/ci.yml), then run:

```sh
composer install --prefer-dist --no-interaction --no-progress
composer cs
vendor/bin/phpstan analyse --no-progress
vendor/bin/phpunit --no-coverage
```

The checkout must be writable: the bootstrap creates `tests/tmp/` and `tests/logs/`, and tests create/remove SQLite databases and generated model metadata. CI sets PHP's memory limit to 1 GB, matching `composer test`; use the same limit locally. `composer test` additionally enables Xdebug coverage mode. See [phpunit.xml](phpunit.xml) and [phpstan.neon](phpstan.neon).

Run `composer cs` to check PHP coding style and `composer cs:fix` (or `composer cbf`) to apply fixes with PHP CS Fixer. The rules and source paths are defined in [.php-cs-fixer.php](.php-cs-fixer.php).

## AI coding assistance

See [LSR Skills](https://github.com/Heroyt/lsr-skills) for AI agent skills for working with the LSR framework.

## License

Licensed under the [MIT License](LICENSE).
