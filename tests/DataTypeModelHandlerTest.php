<?php

namespace Kolossal\Multiplex\Tests;

use Kolossal\Multiplex\DataType;
use Kolossal\Multiplex\Tests\Mocks\Post;
use PHPUnit\Framework\Attributes\Test;
use stdClass;

final class DataTypeModelHandlerTest extends TestCase
{
    /** @test */
    public function it_can_handle_non_existing_models(): void
    {
        $model = Post::factory()->make();
        $handler = new DataType\ModelHandler;

        $this->assertFalse($handler->canHandleValue(new stdClass));
        $this->assertTrue($handler->canHandleValue($model));

        $serialized = $handler->serializeValue($model);
        $unserialized = $handler->unserializeValue($serialized);

        $this->assertEquals('Kolossal\Multiplex\Tests\Mocks\Post', $serialized);
        $this->assertInstanceOf(Post::class, $unserialized);
    }

    /** @test */
    public function it_can_handle_existing_models(): void
    {
        $model = Post::factory()->create();
        $handler = new DataType\ModelHandler;

        $this->assertTrue($handler->canHandleValue($model));

        $serialized = $handler->serializeValue($model);
        $unserialized = $handler->unserializeValue($serialized);

        $this->assertEquals('Kolossal\Multiplex\Tests\Mocks\Post#1', $serialized);
        $this->assertTrue($unserialized->is($model));
    }

    /** @test */
    public function it_will_resolve_null_as_null(): void
    {
        $handler = new DataType\ModelHandler;

        $this->assertNull($handler->unserializeValue(null));
    }
}
