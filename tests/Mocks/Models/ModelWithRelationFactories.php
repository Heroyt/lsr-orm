<?php

declare(strict_types=1);

namespace Mocks\Models;

use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToMany;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\Attributes\Relations\OneToMany;
use Lsr\Orm\Attributes\Relations\OneToOne;
use Lsr\Orm\LoadingType;
use Lsr\Orm\Model;
use Lsr\Orm\ModelCollection;

#[PrimaryKey('id')]
class ModelWithRelationFactories extends Model
{
    public const string TABLE = 'modelsWithRelationFactories';
    public static int $parentFactoryCallCount = 0;
    public static int $collectionFactoryCallCount = 0;
    public string $name;
    #[OneToOne(factoryMethod: 'findParent')]
    public ?ModelWithRelationFactories $parent;

    #[ManyToOne(factoryMethod: 'findParent')]
    public ?ModelWithRelationFactories $parent2;

    /** @var ModelCollection<ModelWithRelationFactories> */
    #[OneToMany(class: ModelWithRelationFactories::class, factoryMethod: 'findCollection')]
    public ModelCollection $oneToMany;

    /** @var ModelCollection<ModelWithRelationFactories> */
    #[ManyToMany(class: ModelWithRelationFactories::class, factoryMethod: 'findCollection')]
    public ModelCollection $manyToMany;

    #[OneToOne(factoryMethod: 'findParent', loadingType: LoadingType::EAGER)]
    public ?ModelWithRelationFactories $parentEager;

    #[ManyToOne(factoryMethod: 'findParent', loadingType: LoadingType::EAGER)]
    public ?ModelWithRelationFactories $parent2Eager;

    /** @var ModelCollection<ModelWithRelationFactories> */
    #[OneToMany(factoryMethod: 'findCollection', class: ModelWithRelationFactories::class, loadingType: LoadingType::EAGER)]
    public ModelCollection $oneToManyEager;

    /** @var ModelCollection<ModelWithRelationFactories> */
    #[ManyToMany(factoryMethod: 'findCollection', class: ModelWithRelationFactories::class, loadingType: LoadingType::EAGER)]
    public ModelCollection $manyToManyEager;


    public function findParent() : ?ModelWithRelationFactories {
        $model = new ModelWithRelationFactories();
        $model->id = 999;
        $model->name = 'parent';
        self::$parentFactoryCallCount++;
        return $model;
    }

    public function findCollection() : ModelCollection {
        $model = new ModelWithRelationFactories();
        $model->id = 9999;
        $model->name = 'collection';
        self::$collectionFactoryCallCount++;
        return new ModelCollection([$model->id => $model]);
    }
}
