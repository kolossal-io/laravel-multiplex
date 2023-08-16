<?php

namespace Kolossal\Multiplex\Tests;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Kolossal\Multiplex\DataType;
use Kolossal\Multiplex\DataType\HandlerInterface;
use Kolossal\Multiplex\Tests\Mocks\Dummy;
use Kolossal\Multiplex\Tests\Mocks\SampleSerializable;
use stdClass;

/**
 * Data handler tests.
 *
 * @copyright Plank Multimedia Inc.
 *
 * @link https://github.com/plank/laravel-metable
 */
class DataTypeHandlerTest extends TestCase
{
    public static function handlerProvider()
    {
        $timestamp = '2017-01-01 00:00:00.000000+0000';
        $datetime = Carbon::createFromFormat('Y-m-d H:i:s.uO', $timestamp);

        $object = new stdClass();
        $object->foo = 'bar';
        $object->baz = 3;

        return [
            'array' => [
                new DataType\ArrayHandler,
                'array',
                ['foo' => ['bar'], 'baz'],
                [new stdClass()],
            ],
            'boolean' => [
                new DataType\BooleanHandler,
                'boolean',
                true,
                [1, 0, '', [], null],
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
            ],
            'float' => [
                new DataType\FloatHandler,
                'float',
                1.1,
                ['1.1', 1],
            ],
            'integer' => [
                new DataType\IntegerHandler,
                'integer',
                3,
                [1.1, '1'],
            ],
            'model' => [
                new DataType\ModelHandler,
                'model',
                new Dummy,
                [new stdClass()],
            ],
            'model collection' => [
                new DataType\ModelCollectionHandler,
                'collection',
                new Collection([new Dummy]),
                [collect()],
            ],
            'null' => [
                new DataType\NullHandler,
                'null',
                null,
                [0, '', 'null', [], false],
            ],
            'object' => [
                new DataType\ObjectHandler,
                'object',
                $object,
                [[]],
            ],
            'serializable' => [
                new DataType\SerializableHandler,
                'serializable',
                new SampleSerializable(['foo' => 'bar']),
                [],
            ],
            'string' => [
                new DataType\StringHandler,
                'string',
                'foo',
                [1, 1.1],
            ],
        ];
    }

    /**
     * @test
     *
     * @dataProvider handlerProvider
     */
    public function it_specifies_a_datatype_identifier(HandlerInterface $handler, $type)
    {
        $this->assertEquals($type, $handler->getDataType());
    }

    /**
     * @test
     *
     * @dataProvider handlerProvider
     */
    public function it_can_verify_compatibility(HandlerInterface $handler, $type, $value, $incompatible)
    {
        $this->assertTrue($handler->canHandleValue($value));

        foreach ($incompatible as $value) {
            $this->assertFalse($handler->canHandleValue($value));
        }
    }

    /**
     * @test
     *
     * @dataProvider handlerProvider
     */
    public function it_can_serialize_and_unserialize_values(HandlerInterface $handler, $type, $value, $incompatible, callable $closure = null)
    {
        $serialized = $handler->serializeValue($value);
        $unserialized = $handler->unserializeValue($serialized);

        if ($closure) {
            $this->assertTrue(call_user_func($closure, $unserialized));
        } else {
            $this->assertEquals($value, $unserialized);
        }
    }

    /**
     * @test
     *
     * @dataProvider handlerProvider
     */
    public function it_can_handle_null_values(HandlerInterface $handler)
    {
        $unserialized = $handler->unserializeValue(null);
        $this->assertNull($unserialized);
    }
}
