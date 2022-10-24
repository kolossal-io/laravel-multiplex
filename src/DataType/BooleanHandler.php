<?php

namespace Kolossal\Multiplex\DataType;

/**
 * Handle serialization of booleans.
 *
 * @copyright Plank Multimedia Inc.
 *
 * @link https://github.com/plank/laravel-metable
 */
class BooleanHandler extends ScalarHandler
{
    /**
     * {@inheritdoc}
     */
    protected $type = 'boolean';

    /**
     * {@inheritdoc}
     */
    public function serializeValue($value): string
    {
        return $value ? '1' : '0';
    }
}
