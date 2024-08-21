<?php

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Kolossal\Multiplex\DataType;
use Kolossal\Multiplex\DataType\HandlerInterface;
use Kolossal\Multiplex\Tests\Mocks\Dummy;
use Kolossal\Multiplex\Tests\Mocks\SampleSerializable;

dataset('handlerProvider', function () {
    $timestamp = '2017-01-01 00:00:00.000000+0000';
    $datetime = Carbon::createFromFormat('Y-m-d H:i:s.uO', $timestamp);

    $object = new stdClass;
    $object->foo = 'bar';
    $object->baz = 3;

    return [
        'array' => [
            new DataType\ArrayHandler,
            'array',
            ['foo' => ['bar'], 'baz'],
            [new stdClass],
            null,
        ],
        'boolean' => [
            new DataType\BooleanHandler,
            'boolean',
            true,
            [1, 0, '', [], null],
            null,
        ],
        'date' => [
            new DataType\DateHandler,
            'date',
            '2017-01-01',
            [2017, Carbon::parse('2017-01-01')],
            fn (Carbon $value) => $value->isSameDay('2017-01-01'),
        ],
        'datetime' => [
            new DataType\DateTimeHandler,
            'datetime',
            $datetime,
            [2017, '2017-01-01'],
            null,
        ],
        'float' => [
            new DataType\FloatHandler,
            'float',
            1.1,
            ['1.1', 1],
            null,
        ],
        'integer' => [
            new DataType\IntegerHandler,
            'integer',
            3,
            [1.1, '1'],
            null,
        ],
        'model' => [
            new DataType\ModelHandler,
            'model',
            new Dummy,
            [new stdClass],
            null,
        ],
        'model collection' => [
            new DataType\ModelCollectionHandler,
            'collection',
            new Collection([new Dummy]),
            [collect()],
            null,
        ],
        'null' => [
            new DataType\NullHandler,
            'null',
            null,
            [0, '', 'null', [], false],
            null,
        ],
        'object' => [
            new DataType\ObjectHandler,
            'object',
            $object,
            [[]],
            null,
        ],
        'serializable' => [
            new DataType\SerializableHandler,
            'serializable',
            new SampleSerializable(['foo' => 'bar']),
            [],
            null,
        ],
        'string' => [
            new DataType\StringHandler,
            'string',
            'foo',
            [1, 1.1],
            null,
        ],
    ];
});

it('specifies a datatype identifier', function (HandlerInterface $handler, $type, $value, $incompatible, mixed $closure = null) {
    expect($handler->getDataType())->toEqual($type);
})->with('handlerProvider');

it('can verify compatibility', function (HandlerInterface $handler, $type, $value, $incompatible, mixed $closure) {
    expect($handler->canHandleValue($value))->toBeTrue();

    foreach ($incompatible as $value) {
        expect($handler->canHandleValue($value))->toBeFalse();
    }
})->with('handlerProvider');

it('can serialize and unserialize values', function (HandlerInterface $handler, $type, $value, $incompatible, mixed $closure = null) {
    $serialized = $handler->serializeValue($value);
    $unserialized = $handler->unserializeValue($serialized);

    if ($closure) {
        expect(call_user_func($closure, $unserialized))->toBeTrue();
    } else {
        expect($unserialized)->toEqual($value);
    }
})->with('handlerProvider');

it('can handle null values', function (HandlerInterface $handler, $type, $value, $incompatible, mixed $closure = null) {
    $unserialized = $handler->unserializeValue(null);
    expect($unserialized)->toBeNull();
})->with('handlerProvider');
