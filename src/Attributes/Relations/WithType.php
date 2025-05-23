<?php

namespace Lsr\Orm\Attributes\Relations;

use Error;
use Lsr\Orm\Model;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;

trait WithType
{
    protected bool $nullable;

    /**
     * Get a class name for a property for a model relation
     *
     * @param  ReflectionProperty  $property
     *
     * @return object{class: class-string<Model>|Model, nullable: bool} Class name
     */
    public function getType(ReflectionProperty $property): object {
        if (!is_null($this->class)) {
            if (!isset($this->nullable)) {
                $this->nullable = false;
                if ($property->hasType()) {
                    $this->nullable = $property->getType()?->allowsNull() ?? false;
                }
            }
            return (object) ['class' => $this->class, 'nullable' => $this->nullable];
        }
        if ($property->hasType()) {
            /** @var ReflectionType $type */
            $type = $property->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                /** @var class-string<Model> $typeName */
                $typeName = $type->getName();
                $this->class = $typeName;
                $this->nullable = $type->allowsNull();
                return (object) ['class' => $this->class, 'nullable' => $this->nullable];
            }
            throw new Error(
                'Cannot create relation for a scalar type in Model ' . $this::class . ' and property ' . $property->getName()
            );
        }

        // TODO: Maybe add docblock parsing
        throw new Error(
            'Cannot create relation in Model ' . $this::class . ' and property ' . $property->getName(
            ) . ' - no type definition found'
        );
    }
}
