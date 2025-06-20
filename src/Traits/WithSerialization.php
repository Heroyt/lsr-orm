<?php
declare(strict_types=1);

namespace Lsr\Orm\Traits;

use Lsr\Orm\Attributes;
use Lsr\Orm\Attributes\JsonExclude;
use ReflectionProperty;

trait WithSerialization
{

    /**
     * Specify data, which should be serialized to JSON.
     *
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return array<string, mixed> data, which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     */
    public function jsonSerialize() : array {
        $data = [];
        $reflection = new \ReflectionClass($this);

        // Find all public properties of the class
        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $propertyName = $property->getName();

            // Skip properties that are not serializable
            if (
                !$property->isInitialized($this)
                || !empty($property->getAttributes(JsonExclude::class))
            ) {
                continue;
            }

            // Handle serialization alias
            $aliasAttributes = $property->getAttributes(Attributes\SerializationAlias::class);
            if (!empty($aliasAttributes)) {
                $alias = $aliasAttributes[0]->newInstance()->alias;
                if ($alias !== '') {
                    $propertyName = $alias;
                }
            }


            $data[$propertyName] = $property->getValue($this);
        }

        // Find methods that should extend serialization
        foreach ($reflection->getMethods() as $method) {
            if (empty($method->getAttributes(Attributes\ExtendsSerialization::class))) {
                continue;
            }

            // Validate that the method has a array parameter and returns an array
            $params = $method->getParameters();
            if (
                count($params) !== 1
                || $params[0]->getType() === null
                || $params[0]->getType()->getName() !== 'array'
            ) {
                throw new \RuntimeException(
                    sprintf(
                        'Method %s::%s must have exactly one array parameter.',
                        $reflection->getName(),
                        $method->getName()
                    )
                );
            }
            $returnType = $method->getReturnType();
            if ($returnType === null 
                || !($returnType instanceof \ReflectionNamedType) 
                || $returnType->getName() !== 'array') {
                throw new \RuntimeException(
                    sprintf(
                        'Method %s::%s must return an array.',
                        $reflection->getName(),
                        $method->getName()
                    )
                );
            }

            // Call the method and merge its result into the data array
            $data = $method->invoke($this, $data);
        }

        return $data;
    }

}