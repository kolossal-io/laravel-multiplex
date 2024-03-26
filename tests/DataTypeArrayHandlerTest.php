<?php

namespace Kolossal\Multiplex\Tests;

use Kolossal\Multiplex\DataType\ArrayHandler;
use PHPUnit\Framework\Attributes\Test;

final class DataTypeArrayHandlerTest extends TestCase
{
    /** @test */
    public function it_will_resolve_null_as_null(): void
    {
        $handler = new ArrayHandler;

        $this->assertInstanceOf(ArrayHandler::class, $handler);

        $this->assertNull($handler->unserializeValue(null));
    }

    /** @test */
    public function it_can_handle_arrays(): void
    {
        $handler = new ArrayHandler;

        $this->assertTrue($handler->canHandleValue(['id' => 123]));
    }

    /** @test */
    public function it_cannot_handle_other_values(): void
    {
        $handler = new ArrayHandler;

        $this->assertFalse($handler->canHandleValue((object) ['id' => 123]));
        $this->assertFalse($handler->canHandleValue(123));
        $this->assertFalse($handler->canHandleValue(false));
    }

    /** @test */
    public function it_serializes_value(): void
    {
        $handler = new ArrayHandler;

        $this->assertSame(
            '{"id":123}',
            $handler->serializeValue(['id' => 123])
        );
    }

    /** @test */
    public function it_unserializes_value(): void
    {
        $handler = new ArrayHandler;

        $value = $handler->unserializeValue('{"id":123}');

        $this->assertIsArray($value);

        $this->assertSame(123, $value['id']);
    }
}
