<?php

declare(strict_types=1);

namespace Lsr\Orm\Attributes\Relations;

use Attribute;
use InvalidArgumentException;
use Lsr\Orm\Attributes\NoDB;
use Lsr\Orm\Attributes\Transform;
use Lsr\Orm\Config\ModelConfig;
use Lsr\Orm\LoadingType;
use Lsr\Orm\Model;
use Lsr\Orm\TranslationCollection;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

/** @phpstan-import-type RelationConfig from ModelConfig */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Translations extends ModelRelation
{
    /** @param class-string<Model> $class */
    public function __construct(
        public readonly string $class,
        public readonly string $mappedBy,
        public readonly string $localeProperty = 'locale',
    ) {
    }

    /**
     * @param class-string<Model> $parentClass
     * @return RelationConfig
     * @internal
     */
    public function getConfig(string $parentClass, ReflectionProperty $property): array {
        $type = $property->getType();
        if (
            ! $type instanceof ReflectionNamedType
            || $type->getName() !== TranslationCollection::class
            || $type->allowsNull()
            || ! $property->isPublic()
            || $property->isStatic()
            || $property->isReadOnly()
            || $property->isPrivateSet()
            || $property->isVirtual()
            || $property->getHooks() !== []
        ) {
            throw new InvalidArgumentException('Translations requires a public, writable TranslationCollection property.');
        }
        $child = new ReflectionClass($this->class);
        if ( ! $child->isSubclassOf(Model::class)) {
            throw new InvalidArgumentException('A translation class must extend Model.');
        }
        if ( ! $child->isInstantiable() || $child->isAbstract()) {
            throw new InvalidArgumentException('A translation class must be instantiable.');
        }
        if ( ! $child->hasProperty($this->mappedBy) || ! $child->hasProperty($this->localeProperty)) {
            throw new InvalidArgumentException('Translation parent and locale properties must exist.');
        }
        $parent = $child->getProperty($this->mappedBy);
        $locale = $child->getProperty($this->localeProperty);
        foreach ([$parent, $locale] as $field) {
            if (
                ! $field->isPublic() || $field->isStatic() || $field->isReadOnly()
                || $field->isPrivateSet() || $field->isProtectedSet()
                || $field->isVirtual() || $field->getHooks() !== []
                || $field->getAttributes(NoDB::class) !== []
                || $field->getAttributes(Transform::class, ReflectionAttribute::IS_INSTANCEOF) !== []
            ) {
                throw new InvalidArgumentException('Translation keys must be ordinary writable persisted properties.');
            }
        }
        $parentType = $parent->getType();
        $localeType = $locale->getType();
        if (
            ! $parentType instanceof ReflectionNamedType || $parentType->allowsNull()
            || ! is_a($parentClass, $parentType->getName(), true)
            || ! $localeType instanceof ReflectionNamedType || $localeType->getName() !== 'string'
            || $localeType->allowsNull() || $locale->getAttributes(ModelRelation::class, ReflectionAttribute::IS_INSTANCEOF) !== []
        ) {
            throw new InvalidArgumentException('Translations requires a non-null parent model and string locale.');
        }
        $attributes = $parent->getAttributes(ModelRelation::class, ReflectionAttribute::IS_INSTANCEOF);
        if (count($attributes) !== 1 || $attributes[0]->getName() !== ManyToOne::class) {
            throw new InvalidArgumentException('The translation parent must be a ManyToOne relation.');
        }
        $relation = $attributes[0]->newInstance();
        assert($relation instanceof ManyToOne);
        if (
            $relation->factoryMethod !== null
            || ($relation->class !== null && $relation->class !== $parentType->getName())
            || $relation->getForeignKey($parentClass, $this->class) !== $parentClass::findPrimaryKey()
        ) {
            throw new InvalidArgumentException('The translation parent relation must reference its primary key without a factory.');
        }
        return [
            'type' => self::class,
            'instance' => serialize($this),
            'class' => $this->class,
            'factory' => null,
            'foreignKey' => $relation->getLocalKey($parentClass, $this->class),
            'localKey' => $parentClass::findPrimaryKey(),
            'loadingType' => LoadingType::LAZY,
            'factoryMethod' => null,
            'mappedBy' => $this->mappedBy,
            'localeProperty' => $this->localeProperty,
        ];
    }
}
