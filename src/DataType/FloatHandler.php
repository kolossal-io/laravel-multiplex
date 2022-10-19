<?php

namespace Kolossal\Multiplex\DataType;

/**
 * Handle serialization of floats.
 *
 * @copyright Plank Multimedia Inc.
 *
 * @link https://github.com/plank/laravel-metable
 */
class FloatHandler extends ScalarHandler
{
    /**
     * {@inheritdoc}
     */
    protected $type = 'double';

    /**
     * {@inheritdoc}
     */
    public function getDataType(): string
    {
        return 'float';
    }
}
