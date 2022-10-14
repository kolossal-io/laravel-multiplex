<?php

namespace Kolossal\Meta\DataType;

/**
 * Handle serialization of plain objects.
 *
 * @copyright Plank Multimedia Inc.
 *
 * @link https://github.com/plank/laravel-metable
 */
class ObjectHandler implements HandlerInterface
{
    /**
     * {@inheritdoc}
     */
    public function getDataType(): string
    {
        return 'object';
    }

    /**
     * {@inheritdoc}
     */
    public function canHandleValue($value): bool
    {
        return is_object($value);
    }

    /**
     * {@inheritdoc}
     */
    public function serializeValue($value): string
    {
        return json_encode($value);
    }

    /**
     * {@inheritdoc}
     */
    public function unserializeValue(string $value)
    {
        return json_decode($value, false);
    }
}
