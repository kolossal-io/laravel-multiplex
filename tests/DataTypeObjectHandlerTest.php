<?php

namespace Kolossal\Multiplex\Tests;

use Kolossal\Multiplex\DataType\ObjectHandler;
use Kolossal\Multiplex\Tests\Mocks\SampleSerializable;
use PHPUnit\Framework\Attributes\Test;

final class DataTypeObjectHandlerTest extends TestCase
{
    /** @test */
    public function it_will_resolve_null_as_null(): void
    {
        $handler = new ObjectHandler;

        $this->assertInstanceOf(ObjectHandler::class, $handler);

        $this->assertNull($handler->unserializeValue(null));
    }

    /** @test */
    public function it_can_handle_objects(): void
    {
        $handler = new ObjectHandler;

        $this->assertTrue($handler->canHandleValue((object) ['id' => 123]));
        $this->assertTrue($handler->canHandleValue(new SampleSerializable(['id' => 123])));
    }

    /** @test */
    public function it_cannot_handle_other_values(): void
    {
        $handler = new ObjectHandler;

        $this->assertFalse($handler->canHandleValue(['id' => 123]));
        $this->assertFalse($handler->canHandleValue(123));
        $this->assertFalse($handler->canHandleValue(false));
    }

    /** @test */
    public function it_serializes_value(): void
    {
        $handler = new ObjectHandler;

        $this->assertSame(
            '{"data":{"id":123}}',
            $handler->serializeValue(new SampleSerializable(['id' => 123]))
        );
    }

    /** @test */
    public function it_unserializes_value(): void
    {
        $handler = new ObjectHandler;

        $value = $handler->unserializeValue('{"data":{"id":123}}');

        $this->assertIsObject($value);

        $this->assertSame(123, $value->data->id);
    }
}
