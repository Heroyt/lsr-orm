<?php

/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace Lsr\Orm\Exceptions;

class ValidationException extends \RuntimeException
{
    public static function createWithValue(string $message, mixed $value): ValidationException {
        try {
            $formatted = json_encode($value, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $formatted = print_r($value, true);
        }
        return new self(sprintf($message, $formatted));
    }
}
