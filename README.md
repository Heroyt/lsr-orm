# LSR ORM

`lsr/orm` is the Laser framework's attribute-driven ORM on top of LSR database, cache, serialization and object validation. It provides models, queries, collections, relationships and generated model metadata. Its namespace is `Lsr\Orm`.

## Requirements

- PHP `>=8.4`.
- Nette DI `^3.2`, LSR logging/cache/serializer `^0.3`, DB `^0.3.1` and object-validation `^0.3.4`.
- No PHP extensions are declared directly; install the extensions required by the database driver and other dependencies. See [composer.json](composer.json).
- A configured `Lsr\Db\Connection` with its cache and mapper services, initialized through `Lsr\Db\DB::init()`, and application-owned database tables.
- `TMP_DIR` and `LOG_DIR` constants with trailing directory separators and writable locations. Model metadata is generated under `TMP_DIR . 'models/'`; model loggers write under `LOG_DIR . 'models/'`.
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

## Integration and metadata lifecycle

The ORM does not bootstrap a connection by itself. The [database test helper](tests/TestCases/Models/DbHelpers.php) demonstrates `DB::init(new Connection(...))` with a cache, mapper and SQLite configuration; adapt the service construction and schema management to the application's database rather than copying the test database lifecycle. [tests/bootstrap.php](tests/bootstrap.php) illustrates the required filesystem constants.

- [`Attributes`](src/Attributes) contains relationship, mapping, serialization and lifecycle declarations. [`ModelCollection`](src/ModelCollection.php) represents typed model collections.
- [`ModelRepository`](src/ModelRepository.php) holds loaded model instances and generated configuration state in process memory. Long-running applications must manage this state at appropriate lifecycle boundaries; `ModelRepository::clearInstances()` clears the instance registry.
- Model metadata is generated as PHP files under the model cache directory. When model declarations change, invalidate the generated metadata as part of deployment; do not treat it as application source.
- [`Lsr\Orm\DI\OrmExtension`](src/DI/OrmExtension.php) registers `orm:cache:clean` when `commands` is enabled and Symfony Console is available. It does not wire the database/cache stack. The [command implementation](src/Commands/OrmCacheCleanCommand.php) is the reference for cache-cleaning integration.

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
