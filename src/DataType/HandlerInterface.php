<?php

namespace Kolossal\Multiplex\DataType;

/**
 * Provides means to serialize and unserialize values of different data types.
 *
 * @copyright Plank Multimedia Inc.
 *
 * @link https://github.com/plank/laravel-metable
 */
interface HandlerInterface
{
    /**
     * Return the identifier for the data type being handled.
     */
    public function getDataType(): string;

    /**
     * Determine if the value is of the correct type for this handler.
     *
     * @param  mixed  $value
     */
    public function canHandleValue($value): bool;

    /**
     * Convert the value to a string, so that it can be stored in the database.
     *
     * @param  mixed  $value
     */
    public function serializeValue($value): string;

    /**
     * Convert a serialized string back to its original value.
     *
     * @param  string  $serializedValue
     * @return mixed
     */
    public function unserializeValue(?string $serializedValue);
}
