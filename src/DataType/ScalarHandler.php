<?php

namespace Kolossal\Multiplex\DataType;

use Exception;

/**
 * Handle serialization of scalar values.
 *
 * @copyright Plank Multimedia Inc.
 *
 * @link https://github.com/plank/laravel-metable
 */
abstract class ScalarHandler implements HandlerInterface
{
    /**
     * The name of the scalar data type.
     *
     * @var string
     */
    protected $type;

    /**
     * {@inheritdoc}
     */
    public function getDataType(): string
    {
        return $this->type;
    }

    /**
     * {@inheritdoc}
     */
    public function canHandleValue($value): bool
    {
        return gettype($value) === $this->type;
    }

    /**
     * {@inheritdoc}
     */
    public function serializeValue($value): string
    {
        if (!is_null($value) && !is_bool($value) && !is_float($value) && !is_int($value) && !is_resource($value) && !is_string($value)) {
            throw new Exception('Invalid value passed as scalar value. Use a boolean, float, int, resource, string value or null.');
        }

        return strval($value);
    }

    /**
     * {@inheritdoc}
     */
    public function unserializeValue(?string $value): mixed
    {
        if (is_null($value)) {
            return $value;
        }

        settype($value, $this->type);

        return $value;
    }
}
