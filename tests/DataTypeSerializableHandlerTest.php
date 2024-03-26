<?php

namespace Kolossal\Multiplex\Tests;

use Kolossal\Multiplex\DataType\SerializableHandler;
use Kolossal\Multiplex\Tests\Mocks\Dummy;
use Kolossal\Multiplex\Tests\Mocks\SampleSerializable;
use PHPUnit\Framework\Attributes\Test;

final class DataTypeSerializableHandlerTest extends TestCase
{
    /** @test */
    public function it_will_resolve_null_as_null(): void
    {
        $handler = new SerializableHandler;

        $this->assertInstanceOf(SerializableHandler::class, $handler);

        $this->assertNull($handler->unserializeValue(null));
    }

    /** @test */
    public function it_can_handle_serializable_values(): void
    {
        $handler = new SerializableHandler;

        $this->assertTrue($handler->canHandleValue(new SampleSerializable(['id' => 123])));
    }

    /** @test */
    public function it_cannot_handle_other_values(): void
    {
        $handler = new SerializableHandler;

        $this->assertFalse($handler->canHandleValue(new Dummy));
        $this->assertFalse($handler->canHandleValue(123));
        $this->assertFalse($handler->canHandleValue(false));
        $this->assertFalse($handler->canHandleValue(['id' => 123]));
        $this->assertFalse($handler->canHandleValue((object) ['id' => 123]));
    }

    /** @test */
    public function it_serializes_value(): void
    {
        $handler = new SerializableHandler;

        $this->assertSame(
            'O:49:"Kolossal\Multiplex\Tests\Mocks\SampleSerializable":1:{s:2:"id";i:123;}',
            $handler->serializeValue(new SampleSerializable(['id' => 123]))
        );
    }

    /** @test */
    public function it_unserializes_value(): void
    {
        $handler = new SerializableHandler;

        $value = $handler->unserializeValue('O:49:"Kolossal\Multiplex\Tests\Mocks\SampleSerializable":1:{s:2:"id";i:123;}');

        $this->assertInstanceOf(SampleSerializable::class, $value);

        $this->assertSame(123, $value->data['id']);
    }
}
