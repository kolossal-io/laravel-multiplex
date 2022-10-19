<?php

namespace Kolossal\Multiplex\DataType;

use Serializable;

/**
 * Handle serialization of Serializable objects.
 *
 * @copyright Plank Multimedia Inc.
 *
 * @link https://github.com/plank/laravel-metable
 */
class SerializableHandler implements HandlerInterface
{
    /**
     * {@inheritdoc}
     */
    public function getDataType(): string
    {
        return 'serializable';
    }

    /**
     * {@inheritdoc}
     */
    public function canHandleValue($value): bool
    {
        return $value instanceof Serializable;
    }

    /**
     * {@inheritdoc}
     */
    public function serializeValue($value): string
    {
        return serialize($value);
    }

    /**
     * {@inheritdoc}
     */
    public function unserializeValue(string $value)
    {
        return unserialize($value);
    }
}
