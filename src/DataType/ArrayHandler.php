<?php

namespace Kolossal\Multiplex\DataType;

/**
 * Handle serialization of arrays.
 *
 * @copyright Plank Multimedia Inc.
 *
 * @link https://github.com/plank/laravel-metable
 */
class ArrayHandler implements HandlerInterface
{
    /**
     * {@inheritdoc}
     */
    public function getDataType(): string
    {
        return 'array';
    }

    /**
     * {@inheritdoc}
     */
    public function canHandleValue($value): bool
    {
        return is_array($value);
    }

    /**
     * {@inheritdoc}
     */
    public function serializeValue($value): string
    {
        return json_encode($value) ?: '';
    }

    /**
     * {@inheritdoc}
     */
    public function unserializeValue(?string $value)
    {
        if (is_null($value)) {
            return $value;
        }

        return json_decode($value, true);
    }
}
