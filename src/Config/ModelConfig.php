<?php

namespace Lsr\Orm\Config;

use Lsr\Orm\Attributes\Factory;
use Lsr\Orm\Attributes\Relations\ModelRelation;
use Lsr\Orm\Interfaces\FactoryInterface;
use Lsr\Orm\LoadingType;
use Lsr\Orm\Model;

/**
 * @phpstan-type FactoryConfig array{
 *     factoryClass: class-string<FactoryInterface<Model>>,
 *     defaultOptions: array<string,mixed>
 * }
 * @phpstan-type RelationConfig array{
 *     type:class-string<ModelRelation>,
 *     instance: string,
 *     class: class-string<Model>,
 *     factory: class-string|null,
 *     foreignKey: string,
 *     localKey: string,
 *     loadingType: LoadingType,
 *     factoryMethod: string|null
 * }
 * @phpstan-type PropertyConfig array{
 *     name:string,
 *     isPrimaryKey: bool,
 *     allowsNull: bool,
 *     isBuiltin: bool,
 *     isExtend: bool,
 *     isEnum: bool,
 *     isDateTime: bool,
 *     instantiate: bool,
 *     noDb: bool,
 *     type: class-string|string,
 *     relation:null|RelationConfig,
 *     isVirtual?: bool,
 * }
 */
abstract class ModelConfig
{
    public string $primaryKey;

    /** @var FactoryConfig|null */
    public ?array $factoryConfig = null;

    /** @var array<non-empty-string, PropertyConfig> */
    public array $properties = [];

    /** @var non-empty-string[] */
    public array $beforeUpdate = [];

    /** @var non-empty-string[] */
    public array $afterUpdate = [];

    /** @var non-empty-string[] */
    public array $beforeInsert = [];

    /** @var non-empty-string[] */
    public array $afterInsert = [];

    /** @var non-empty-string[] */
    public array $beforeDelete = [];

    /** @var non-empty-string[] */
    public array $afterDelete = [];

    /** @var list<callable(int $id):void> */
    public array $afterExternalUpdate = [];

    public ?Factory $factory {
        get {
            if (!isset($this->factoryConfig)) {
                return null;
            }

            $this->factory ??= new Factory(
                $this->factoryConfig['factoryClass'],
                $this->factoryConfig['defaultOptions']
            );
            return $this->factory;
        }
    }
}
