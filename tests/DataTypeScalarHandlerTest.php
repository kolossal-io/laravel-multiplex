<?php

namespace Kolossal\Multiplex\Tests;

use Exception;
use Kolossal\Multiplex\DataType\ScalarHandler;
use Kolossal\Multiplex\DataType\StringHandler;
use PHPUnit\Framework\Attributes\Test;

final class DataTypeScalarHandlerTest extends TestCase
{
    #[Test]
    public function it_will_throw_an_exception_for_invalid_values(): void
    {
        $handler = new StringHandler;

        $this->assertInstanceOf(ScalarHandler::class, $handler);

        $this->assertSame('Test', $handler->serializeValue('Test'));
        $this->assertSame('', $handler->serializeValue(null));
        $this->assertSame('145.12', $handler->serializeValue(145.12));
        $this->assertSame('145', $handler->serializeValue(145));
        $this->assertSame('1', $handler->serializeValue(true));
        $this->assertSame('', $handler->serializeValue(false));
        $this->assertStringStartsWith('Resource id #', $handler->serializeValue(stream_context_create()));

        $this->expectException(Exception::class);

        $handler->serializeValue([1, 2, 3]);
    }
}
