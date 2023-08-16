<?php

namespace Kolossal\Multiplex\Exceptions;

use Exception;

/**
 * Data Type registry exception.
 *
 * @copyright Plank Multimedia Inc.
 *
 * @link https://github.com/plank/laravel-metable
 */
final class DataTypeException extends Exception
{
    public static function handlerNotFound(string $type): self
    {
        return new self("Meta handler not found for type identifier '{$type}'");
    }

    public static function handlerNotFoundForValue(mixed $value): self
    {
        $type = is_object($value) ? get_class($value) : gettype($value);

        return new self("Meta handler not found for value of type '{$type}'");
    }
}
