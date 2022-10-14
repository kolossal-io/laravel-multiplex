<?php

namespace Kolossal\Meta\DataType;

/**
 * Handle serialization of booleans.
 *
 * @copyright Plank Multimedia Inc.
 * @link https://github.com/plank/laravel-metable
 */
class BooleanHandler extends ScalarHandler
{
    /**
     * {@inheritdoc}
     */
    protected $type = 'boolean';
}
