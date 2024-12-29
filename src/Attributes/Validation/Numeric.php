<?php

namespace Lsr\Orm\Attributes\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class Numeric implements Validator
{

    public function validateValue(mixed $value, string | object $class, string $property) : void {
        if (empty($value) || !is_numeric($value)) {
            throw ValidationException::createWithValue(
              'Property '.(is_string($class) ? $class :
                $class::class).'::'.$property.' must be numeric (string, int or float). (value: %s)',
              $value,
            );
        }
    }
}