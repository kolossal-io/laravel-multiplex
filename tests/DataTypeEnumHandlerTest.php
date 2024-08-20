<?php

namespace Kolossal\Multiplex\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Kolossal\Multiplex\DataType\EnumHandler;
use Kolossal\Multiplex\Tests\Mocks\BackedEnum;
use Kolossal\Multiplex\Tests\Mocks\Enum;
use Kolossal\Multiplex\Tests\Mocks\Post;
use PHPUnit\Framework\Attributes\Test;

final class DataTypeEnumHandlerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     * @requires PHP 8.1
     * */
    public function it_can_serialize_backed_enums(): void
    {
        $handler = new EnumHandler;

        $this->assertTrue($handler->canHandleValue(BackedEnum::One));

        $this->assertEquals('Kolossal\Multiplex\Tests\Mocks\BackedEnum::one', $handler->serializeValue(BackedEnum::One));
        $this->assertEquals('Kolossal\Multiplex\Tests\Mocks\BackedEnum::two', $handler->serializeValue(BackedEnum::Two));
        $this->assertEquals('Kolossal\Multiplex\Tests\Mocks\BackedEnum::three', $handler->serializeValue(BackedEnum::Three));
    }

    /**
     * @test
     * @requires PHP 8.1
     * */
    public function it_cannot_serialize_basic_enums(): void
    {
        $handler = new EnumHandler;

        $this->assertFalse($handler->canHandleValue(Enum::One));
        $this->assertSame('', $handler->serializeValue(Enum::One));
    }

    /**
     * @test
     * @requires PHP 8.1
     * */
    public function it_cannot_unserialize_null(): void
    {
        $handler = new EnumHandler;

        $this->assertNull($handler->unserializeValue(null));
    }

    /**
     * @test
     * @requires PHP 8.1
     * */
    public function it_cannot_unserialize_invalid_value(): void
    {
        $handler = new EnumHandler;

        $this->assertNull($handler->unserializeValue('Kolossal\Multiplex\Tests\Mocks\BackedEnum'));
    }

    /**
     * @test
     * @requires PHP 8.1
     * */
    public function it_cannot_unserialize_not_existing_enums(): void
    {
        $handler = new EnumHandler;

        $this->assertNull($handler->unserializeValue('Kolossal\Multiplex\Tests\Mocks\InvalidEnum::one'));
    }

    /**
     * @test
     * @requires PHP 8.1
     * */
    public function it_cannot_unserialize_non_enum_classes(): void
    {
        $handler = new EnumHandler;

        $this->assertNull($handler->unserializeValue('Kolossal\Multiplex\Tests\Mocks\Dummy::one'));
    }

    /**
     * @test
     * @requires PHP 8.1
     * */
    public function it_can_unserialize_backed_enums(): void
    {
        $handler = new EnumHandler;

        $enum = $handler->unserializeValue('Kolossal\Multiplex\Tests\Mocks\BackedEnum::three');

        $this->assertSame(BackedEnum::Three, $enum);
        $this->assertNotSame(BackedEnum::One, $enum);
    }

    /**
     * @test
     * @requires PHP 8.1
     * */
    public function it_cannot_unserialize_invalid_values(): void
    {
        $handler = new EnumHandler;

        $enum = $handler->unserializeValue('Kolossal\Multiplex\Tests\Mocks\BackedEnum::four');

        $this->assertNull($enum);
    }

    /**
     * @test
     * @requires PHP 8.1
     * */
    public function it_cannot_unserialize_basic_enums(): void
    {
        $handler = new EnumHandler;

        $enum = $handler->unserializeValue('Kolossal\Multiplex\Tests\Mocks\Enum::three');

        $this->assertNull($enum);
    }

    /**
     * @test
     * @requires PHP 8.1
     * */
    public function it_will_handle_backed_enum_value(): void
    {
        $model = Post::factory()->create();

        $model->saveMeta('enum_test', BackedEnum::Two);

        $this->assertDatabaseHas('meta', [
            'key' => 'enum_test',
            'value' => 'Kolossal\Multiplex\Tests\Mocks\BackedEnum::two',
            'type' => 'enum',
        ]);

        $this->assertSame(BackedEnum::Two, Post::first()->enum_test);
    }

    /**
     * @test
     * @requires PHP 8.1
     * */
    public function it_will_not_handle_basic_enum_value(): void
    {
        $model = Post::factory()->create();

        $model->saveMeta('enum_test', Enum::Two);

        $this->assertDatabaseHas('meta', [
            'key' => 'enum_test',
            'value' => '',
            'type' => 'object',
        ]);

        $this->assertNull(Post::first()->enum_test);
    }
}
