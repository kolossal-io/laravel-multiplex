<?php

namespace Kolossal\Meta\DataType;

/**
 * Handle serialization of integers.
 *
 * @copyright Plank Multimedia Inc.
 * @link https://github.com/plank/laravel-metable
 */
class IntegerHandler extends ScalarHandler
{
    /**
     * {@inheritdoc}
     */
    protected $type = 'integer';
}
