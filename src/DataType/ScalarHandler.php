<?php

namespace Kolossal\Meta\DataType;

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
        return gettype($value) == $this->type;
    }

    /**
     * {@inheritdoc}
     */
    public function serializeValue($value): string
    {
        settype($value, 'string');

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function unserializeValue(string $value)
    {
        settype($value, $this->type);

        return $value;
    }
}
