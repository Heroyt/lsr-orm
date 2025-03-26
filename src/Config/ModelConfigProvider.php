<?php

namespace Lsr\Orm\Config;

use BackedEnum;
use DateTimeInterface;
use Lsr\Helpers\Tools\Strings;
use Lsr\Logging\FsHelper;
use Lsr\Orm\Attributes\Factory;
use Lsr\Orm\Attributes\Hooks\AfterDelete;
use Lsr\Orm\Attributes\Hooks\AfterInsert;
use Lsr\Orm\Attributes\Hooks\AfterUpdate;
use Lsr\Orm\Attributes\Hooks\BeforeDelete;
use Lsr\Orm\Attributes\Hooks\BeforeInsert;
use Lsr\Orm\Attributes\Hooks\BeforeUpdate;
use Lsr\Orm\Attributes\Instantiate;
use Lsr\Orm\Attributes\NoDB;
use Lsr\Orm\Attributes\PrimaryKey;
use Lsr\Orm\Attributes\Relations\ManyToMany;
use Lsr\Orm\Attributes\Relations\ManyToOne;
use Lsr\Orm\Attributes\Relations\ModelRelation;
use Lsr\Orm\Attributes\Relations\OneToMany;
use Lsr\Orm\Attributes\Relations\OneToOne;
use Lsr\Orm\Interfaces\InsertExtendInterface;
use Lsr\Orm\Model;
use Lsr\Orm\ModelCollection;
use Lsr\Orm\ModelRepository;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use RuntimeException;
use stdClass;

/**
 * @phpstan-import-type PropertyConfig from ModelConfig
 */
trait ModelConfigProvider
{
    /**
     * Get model's primary key
     *
     * @return string Primary key's column name
     * @todo: Support mixed primary keys
     *
     */
    public static function getPrimaryKey() : string {
        // Prevent infinite loop due to cyclic relations
        if (!static::canUseConfig()) {
            return static::findPrimaryKey();
        }
        return static::getModelConfig()->primaryKey;
    }

    protected static function canUseConfig() : bool {
        if (isset(ModelRepository::$modelConfig[static::class]) || !isset(ModelRepository::$generatingConfig)) {
            return true;
        }
        if (file_exists(static::getCacheFileName())) {
            return true;
        }
        return false;
    }

    /**
     * Get generated config file path
     *
     * @return string Absolute path to the generated PHP file
     */
    protected static function getCacheFileName() : string {
        ModelRepository::$cacheFileName[static::class] ??= TMP_DIR.'models/'.static::getCacheClassName().'.php';
        return ModelRepository::$cacheFileName[static::class];
    }

    /**
     * Get generated config class name
     *
     * @return string
     */
    public static function getCacheClassName() : string {
        ModelRepository::$cacheClassName[static::class] ??= str_replace('\\', '_', static::class).'_Config';
        return ModelRepository::$cacheClassName[static::class];
    }

    /**
     * Find the class's primary key from the class attribute
     *
     * @return string
     */
    public static function findPrimaryKey() : string {
        if (!empty(ModelRepository::$primaryKeys[static::class])) {
            return ModelRepository::$primaryKeys[static::class];
        }
        $reflection = new ReflectionClass(static::class);

        $attributes = $reflection->getAttributes(PrimaryKey::class);
        if (!empty($attributes)) {
            /** @var ReflectionAttribute<ModelRelation> $attribute */
            $attribute = first($attributes);
            /** @var PrimaryKey $attr */
            $attr = $attribute->newInstance();
            ModelRepository::$primaryKeys[static::class] = $attr->column;
            return ModelRepository::$primaryKeys[static::class];
        }

        // Try to create a primary key name from model name
        $pascal = $reflection->getShortName();
        $camel = Strings::toCamelCase($reflection->getShortName());

        $snakeCase = Strings::toSnakeCase($reflection->getShortName());
        if (
            property_exists(static::class, 'id_'.$snakeCase) || property_exists(
                static::class,
                'id'.$pascal
            )
        ) {
            ModelRepository::$primaryKeys[static::class] = 'id_'.$snakeCase;
            return ModelRepository::$primaryKeys[static::class];
        }
        if (
            property_exists(static::class, $snakeCase.'_id') || property_exists(
                static::class,
                $camel.'Id'
            )
        ) {
            ModelRepository::$primaryKeys[static::class] = $snakeCase.'_id';
            return ModelRepository::$primaryKeys[static::class];
        }

        ModelRepository::$primaryKeys[static::class] = 'id';
        return ModelRepository::$primaryKeys[static::class];
    }

    /**
     * Get the Model's config cache object
     *
     * @return ModelConfig
     */
    public static function getModelConfig() : ModelConfig {
        if (isset(ModelRepository::$modelConfig[static::class])) {
            return ModelRepository::$modelConfig[static::class];
        }
        // Check cache
        if (!file_exists(static::getCacheFileName())) {
            static::createConfigModel();
        }

        /** @phpstan-ignore assign.propertyType */
        ModelRepository::$modelConfig[static::class] = require static::getCacheFileName();
        $config = ModelRepository::$modelConfig[static::class];
        assert($config instanceof ModelConfig);
        return $config;
    }

    /**
     * Create the config cache model and save it to the PHP cache file
     *
     * @post The cache file will be created.
     *
     * @return void
     */
    protected static function createConfigModel() : void {
        ModelRepository::$generatingConfig = static::class;
        $file = new PhpFile();
        $file->addComment('This is an autogenerated file containing model configuration of '.static::class)
             ->setStrictTypes();

        $class = $file->addClass(static::getCacheClassName());
        $class->setExtends(ModelConfig::class)->setFinal();

        $class->addProperty('primaryKey', static::findPrimaryKey())->setType('string');
        $class->addProperty('properties', static::findProperties())->setType('array');

        // Hooks
        $class->addProperty('beforeUpdate', static::findBeforeUpdate())->setType('array');
        $class->addProperty('afterUpdate', static::findAfterUpdate())->setType('array');
        $class->addProperty('beforeInsert', static::findBeforeInsert())->setType('array');
        $class->addProperty('afterInsert', static::findAfterInsert())->setType('array');
        $class->addProperty('beforeDelete', static::findBeforeDelete())->setType('array');
        $class->addProperty('afterDelete', static::findAfterDelete())->setType('array');

        // Factory
        $factory = static::findFactory();
        $class->addProperty(
            'factoryConfig',
            isset($factory) ? [
                'factoryClass'   => $factory->factoryClass,
                'defaultOptions' => $factory->defaultOptions,
            ] : null
        )->setType('array')->setNullable();

        ModelRepository::$generatingConfig = null;

        // Maybe create the cache directory
        $helper = FsHelper::getInstance();
        $helper->createDirRecursive($helper->extractPath(TMP_DIR.'models'));

        if (
            file_put_contents(
                static::getCacheFileName(),
                new PsrPrinter()->printFile($file)."\nreturn new ".$class->getName().';'
            ) === false
        ) {
            throw new RuntimeException('Cannot save file: '.static::getCacheFileName());
        }
    }

    /**
     * Find all Model's properties and relations that should be mapped from the DB
     *
     * @return array<non-empty-string, PropertyConfig>
     *
     * @phpstan-ignore return.type
     */
    protected static function findProperties() : array {
        $properties = [];
        foreach (static::getPropertyReflections(ReflectionProperty::IS_PUBLIC) as $property) {
            $propertyName = $property->getName();
            $properties[$propertyName] = [
                'name'         => $propertyName,
                'isPrimaryKey' => $propertyName === static::findPrimaryKey(),
                'allowsNull'   => false,
                'isBuiltin'    => false,
                'isExtend'     => false,
                'isEnum'       => false,
                'isDateTime'   => false,
                'instantiate'  => !empty($property->getAttributes(Instantiate::class)),
                'noDb'         => !empty($property->getAttributes(NoDB::class)),
                'type'         => null,
                'relation'     => null,
            ];
            if ($property->hasType()) {
                // Check enum and date values
                $type = $property->getType();
                if ($type instanceof ReflectionNamedType) {
                    $properties[$propertyName]['allowsNull'] = $type->allowsNull();
                    $properties[$propertyName]['type'] = $type->getName();
                    if ($type->isBuiltin()) {
                        $properties[$propertyName]['isBuiltin'] = true;
                        $properties[$propertyName]['type'] = $type->getName();
                    }
                    else {
                        $implements = class_implements($type->getName());
                        if (!is_array($implements)) {
                            $implements = [];
                        }
                        $properties[$propertyName]['isExtend'] = in_array(
                            InsertExtendInterface::class,
                            $implements,
                            true
                        );
                        $properties[$propertyName]['isEnum'] = in_array(
                            BackedEnum::class,
                            $implements,
                            true
                        );
                        $properties[$propertyName]['isDateTime'] = $type->getName() === DateTimeInterface::class
                            || in_array(
                                DateTimeInterface::class,
                                $implements,
                                true
                            );

                        // Auto instantiate collection properties
                        if (
                            $type->getName() === ModelCollection::class
                            || is_subclass_of($type->getName(), ModelCollection::class)
                        ) {
                            $properties[$propertyName]['instantiate'] = true;
                        }
                    }
                }
            }

            // Check relations
            $attributes = $property->getAttributes(ModelRelation::class, ReflectionAttribute::IS_INSTANCEOF);
            if (count($attributes) > 1) {
                throw new RuntimeException(
                    'Cannot have more than 1 relation attribute on a property: '.static::class.'::$'.$propertyName
                );
            }
            foreach ($attributes as $attribute) {
                /** @var ManyToOne|OneToMany|OneToOne|ManyToMany $attributeClass */
                $attributeClass = $attribute->newInstance();

                /** @var stdClass $info */
                $info = $attributeClass->getType($property);
                /** @var class-string<Model> $className */
                $className = $info->class;
                $factory = $className::getFactory();

                $foreignKey = $attributeClass->getForeignKey($className, static::class);
                $localKey = $attributeClass->getLocalKey($className, static::class);

                $properties[$propertyName]['relation'] = [
                    'type'        => $attributeClass::class,
                    'instance'    => serialize($attributeClass),
                    'class'       => $className,
                    'factory'     => isset($factory) ? $factory::class : null,
                    'foreignKey'  => $foreignKey,
                    'localKey'    => $localKey,
                    'loadingType' => $attributeClass->loadingType,
                    'factoryMethod' => $attributeClass->factoryMethod,
                ];
                if ($attributeClass->factoryMethod !== null && !static::getReflection()->hasMethod(
                        $attributeClass->factoryMethod
                    )) {
                    throw new RuntimeException(
                        sprintf(
                            'Factory method "%s" does not exists on class "%s" for the relation on property "%s".',
                            $attributeClass->factoryMethod,
                            static::class,
                            $propertyName
                        )
                    );
                }
            }
        }

        /** @phpstan-ignore return.type */
        return $properties;
    }

    /**
     * Get reflections for all the Model's properties
     *
     * @return ReflectionProperty[]
     */
    protected static function getPropertyReflections(?int $filter = null) : array {
        return static::getReflection()->getProperties($filter);
    }

    /**
     * Get all Model's properties
     *
     * @return array<string, PropertyConfig>
     */
    protected static function getProperties() : array {
        // Prevent infinite loop due to cyclic relations
        if (!static::canUseConfig()) {
            return static::findProperties();
        }
        return static::getModelConfig()->properties;
    }

    /**
     * Get a reflection class for the whole model
     *
     * @return ReflectionClass<Model>
     */
    protected static function getReflection() : ReflectionClass {
        if (!isset(ModelRepository::$reflections[static::class])) {
            ModelRepository::$reflections[static::class] = (new ReflectionClass(static::class));
        }
        return ModelRepository::$reflections[static::class];
    }

    /**
     * Get model's factory
     *
     * @return Factory|null
     */
    public static function getFactory() : ?Factory {
        // Prevent infinite loop due to cyclic relations
        if (!static::canUseConfig()) {
            return static::findFactory();
        }
        return static::getModelConfig()->factory;
    }

    /**
     * Find a model's factory
     *
     * @return Factory|null
     */
    protected static function findFactory() : ?Factory {
        if (!isset(ModelRepository::$factory[static::class])) {
            $attributes = static::getReflection()->getAttributes(Factory::class);
            if (empty($attributes)) {
                return null;
            }
            /** @var ReflectionAttribute<Factory> $attribute */
            $attribute = first($attributes);
            ModelRepository::$factory[static::class] = $attribute->newInstance();
        }
        return ModelRepository::$factory[static::class];
    }

    /**
     * @return non-empty-string[]
     */
    public static function findBeforeUpdate() : array {
        return static::findHooks(BeforeUpdate::class);
    }

    /**
     * @param  class-string  $hook
     * @return non-empty-string[]
     */
    protected static function findHooks(string $hook) : array {
        $hooks = [];
        $methods = static::getReflection()->getMethods();
        foreach ($methods as $method) {
            $attributes = $method->getAttributes($hook);
            if (count($attributes) > 0) {
                $hooks[] = $method->getName();
            }
        }
        return $hooks;
    }

    /**
     * @return non-empty-string[]
     */
    public static function findAfterUpdate() : array {
        return static::findHooks(AfterUpdate::class);
    }

    /**
     * @return non-empty-string[]
     */
    public static function findBeforeInsert() : array {
        return static::findHooks(BeforeInsert::class);
    }

    /**
     * @return non-empty-string[]
     */
    public static function findAfterInsert() : array {
        return static::findHooks(AfterInsert::class);
    }

    /**
     * @return non-empty-string[]
     */
    public static function findBeforeDelete() : array {
        return static::findHooks(BeforeDelete::class);
    }

    /**
     * @return non-empty-string[]
     */
    public static function findAfterDelete() : array {
        return static::findHooks(AfterDelete::class);
    }

    /**
     * Get a reflection class for a property
     *
     * @param  string  $name  Property name
     *
     * @return ReflectionProperty
     */
    public static function getPropertyReflection(string $name) : ReflectionProperty {
        return static::getReflection()->getProperty($name);
    }

    /**
     * @return non-empty-string[]
     */
    public static function getBeforeUpdate() : array {
        // Prevent infinite loop due to cyclic relations
        if (!static::canUseConfig()) {
            return static::findBeforeUpdate();
        }
        return static::getModelConfig()->beforeUpdate;
    }

    /**
     * @return non-empty-string[]
     */
    public static function getAfterUpdate() : array {
        // Prevent infinite loop due to cyclic relations
        if (!static::canUseConfig()) {
            return static::findAfterUpdate();
        }
        return static::getModelConfig()->afterUpdate;
    }

    /**
     * @return non-empty-string[]
     */
    public static function getBeforeInsert() : array {
        // Prevent infinite loop due to cyclic relations
        if (!static::canUseConfig()) {
            return static::findBeforeInsert();
        }
        return static::getModelConfig()->beforeInsert;
    }

    /**
     * @return non-empty-string[]
     */
    public static function getAfterInsert() : array {
        // Prevent infinite loop due to cyclic relations
        if (!static::canUseConfig()) {
            return static::findAfterInsert();
        }
        return static::getModelConfig()->afterInsert;
    }

    /**
     * @return non-empty-string[]
     */
    public static function getBeforeDelete() : array {
        // Prevent infinite loop due to cyclic relations
        if (!static::canUseConfig()) {
            return static::findBeforeDelete();
        }
        return static::getModelConfig()->beforeDelete;
    }

    /**
     * @return non-empty-string[]
     */
    public static function getAfterDelete() : array {
        // Prevent infinite loop due to cyclic relations
        if (!static::canUseConfig()) {
            return static::findAfterDelete();
        }
        return static::getModelConfig()->afterDelete;
    }
}
