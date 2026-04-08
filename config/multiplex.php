<?php

use Kolossal\Multiplex\DataType\ArrayHandler;
use Kolossal\Multiplex\DataType\BooleanHandler;
use Kolossal\Multiplex\DataType\DateHandler;
use Kolossal\Multiplex\DataType\DateTimeHandler;
use Kolossal\Multiplex\DataType\EnumHandler;
use Kolossal\Multiplex\DataType\FloatHandler;
use Kolossal\Multiplex\DataType\IntegerHandler;
use Kolossal\Multiplex\DataType\ModelCollectionHandler;
use Kolossal\Multiplex\DataType\ModelHandler;
use Kolossal\Multiplex\DataType\NullHandler;
use Kolossal\Multiplex\DataType\ObjectHandler;
use Kolossal\Multiplex\DataType\SerializableHandler;
use Kolossal\Multiplex\DataType\StringHandler;
use Kolossal\Multiplex\Meta;

return [
    /**
     * Model to use for Meta.
     */
    'model' => Meta::class,

    /**
     * Determine wethere packages migrations should be loaded automatically.
     * Disable this if you want to create your own migrations based on the ones
     * located in `database/migrations`.
     */
    'migrations' => true,

    /**
     * The type of primary key your models using the `HasMeta` trait are using.
     * Must be one of `integer`, `uuid` or `ulid`.
     * ATTENTION: This must be changed before running the database migrations.
     */
    'morph_type' => env('MULTIPLEX_MORPH_TYPE', 'integer'),

    /**
     * List of handlers for recognized data types.
     *
     * Handlers will be evaluated in order, so a value will be handled
     * by the first appropriate handler in the list.
     *
     * @copyright Plank Multimedia Inc.
     *
     * @link https://github.com/plank/laravel-metable
     */
    'datatypes' => [
        BooleanHandler::class,
        NullHandler::class,
        IntegerHandler::class,
        FloatHandler::class,
        StringHandler::class,
        DateTimeHandler::class,
        DateHandler::class,
        ArrayHandler::class,
        EnumHandler::class,
        ModelHandler::class,
        ModelCollectionHandler::class,
        SerializableHandler::class,
        ObjectHandler::class,
    ],
];
