<?php

namespace Kolossal\Multiplex\DataType;

/**
 * Handle serialization of strings.
 *
 * @copyright Plank Multimedia Inc.
 *
 * @link https://github.com/plank/laravel-metable
 */
class StringHandler extends ScalarHandler
{
    /**
     * {@inheritdoc}
     */
    protected $type = 'string';
}
