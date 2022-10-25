<?php

namespace Kolossal\Multiplex\DataType;

use Illuminate\Database\Eloquent\Model;

/**
 * Handle serialization of Eloquent Models.
 *
 * @copyright Plank Multimedia Inc.
 *
 * @link https://github.com/plank/laravel-metable
 */
class ModelHandler implements HandlerInterface
{
    /**
     * {@inheritdoc}
     */
    public function getDataType(): string
    {
        return 'model';
    }

    /**
     * {@inheritdoc}
     */
    public function canHandleValue($value): bool
    {
        return $value instanceof Model;
    }

    /**
     * {@inheritdoc}
     */
    public function serializeValue($value): string
    {
        if ($value->exists) {
            return get_class($value) . '#' . $value->getKey();
        }

        return get_class($value);
    }

    /**
     * {@inheritdoc}
     */
    public function unserializeValue(?string $value)
    {
        if (is_null($value)) {
            return $value;
        }

        // Return blank instances.
        if (strpos($value, '#') === false) {
            return new $value();
        }

        // Fetch specific instances.
        [$class, $id] = explode('#', $value);

        return (new $class())->findOrFail($id);
    }
}
