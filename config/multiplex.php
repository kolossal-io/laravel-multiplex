<?php

return [
    /**
     * Model to use for Meta.
     */
    'model' => Kolossal\Multiplex\Meta::class,

    /**
     * Determine wethere packages migrations should be loaded automatically.
     * Disable this if you want to create your own migrations based on the ones
     * located in `database/migrations`.
     */
    'migrations' => true,

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
        Kolossal\Multiplex\DataType\BooleanHandler::class,
        Kolossal\Multiplex\DataType\NullHandler::class,
        Kolossal\Multiplex\DataType\IntegerHandler::class,
        Kolossal\Multiplex\DataType\FloatHandler::class,
        Kolossal\Multiplex\DataType\StringHandler::class,
        Kolossal\Multiplex\DataType\DateTimeHandler::class,
        Kolossal\Multiplex\DataType\DateHandler::class,
        Kolossal\Multiplex\DataType\ArrayHandler::class,
        Kolossal\Multiplex\DataType\ModelHandler::class,
        Kolossal\Multiplex\DataType\ModelCollectionHandler::class,
        Kolossal\Multiplex\DataType\SerializableHandler::class,
        Kolossal\Multiplex\DataType\ObjectHandler::class,
    ],
];
