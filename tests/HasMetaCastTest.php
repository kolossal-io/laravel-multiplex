<?php

namespace Kolossal\Multiplex\Tests;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kolossal\Multiplex\Tests\Mocks\Post;
use PHPUnit\Framework\Attributes\Test;

final class HasMetaCastTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_force_type_casts(): void
    {
        $model = $this->getModel();

        $model->forceFill([
            'foo' => 123.0,
            'int' => '123',
            'str' => 123.0,
            'bool' => '0',
            'date' => '2020-01-01',
            'datetime' => '2020-01-01',
            'dbl' => 123,
        ])->save();

        $this->assertDatabaseHas('meta', ['key' => 'foo', 'type' => 'float']);
        $this->assertDatabaseHas('meta', ['key' => 'int', 'type' => 'integer']);
        $this->assertDatabaseHas('meta', ['key' => 'str', 'type' => 'string']);
        $this->assertDatabaseHas('meta', ['key' => 'bool', 'type' => 'boolean']);
        $this->assertDatabaseHas('meta', ['key' => 'date', 'type' => 'date']);
        $this->assertDatabaseHas('meta', ['key' => 'datetime', 'type' => 'datetime']);
        $this->assertDatabaseHas('meta', ['key' => 'dbl', 'type' => 'float']);

        $model->refresh();

        $this->assertSame(123.0, $model->foo);
        $this->assertSame(123, $model->int);
        $this->assertSame('123', $model->str);
        $this->assertSame(false, $model->bool);
        $this->assertTrue($model->date->isSameDay(Carbon::create(2020, 1, 1)));
        $this->assertTrue($model->datetime->equalTo(Carbon::create(2020, 1, 1)));
        $this->assertSame(123.000, $model->dbl);
    }

    /** @test */
    public function it_will_handle_null_values(): void
    {
        $model = $this->getModel();

        $model->forceFill([
            'foo' => null,
            'int' => null,
            'str' => null,
            'bool' => null,
            'date' => null,
            'datetime' => null,
            'dbl' => null,
        ])->save();

        $this->assertDatabaseHas('meta', ['key' => 'foo', 'type' => 'null', 'value' => null]);
        $this->assertDatabaseHas('meta', ['key' => 'int', 'type' => 'integer', 'value' => null]);
        $this->assertDatabaseHas('meta', ['key' => 'str', 'type' => 'string', 'value' => null]);
        $this->assertDatabaseHas('meta', ['key' => 'bool', 'type' => 'boolean', 'value' => null]);
        $this->assertDatabaseHas('meta', ['key' => 'date', 'type' => 'date', 'value' => null]);
        $this->assertDatabaseHas('meta', ['key' => 'datetime', 'type' => 'datetime', 'value' => null]);
        $this->assertDatabaseHas('meta', ['key' => 'dbl', 'type' => 'float', 'value' => null]);

        $model->refresh();

        $this->assertNull($model->foo);
        $this->assertNull($model->int);
        $this->assertNull($model->str);
        $this->assertNull($model->bool);
        $this->assertNull($model->date);
        $this->assertNull($model->datetime);
        $this->assertNull($model->dbl);
    }

    /** @test */
    public function it_will_handle_falsy_values(): void
    {
        $model = $this->getModel();

        $model->forceFill([
            'foo' => 0,
            'int' => 0,
            'str' => 0,
            'bool' => 0,
            'date' => 0,
            'datetime' => 0,
            'dbl' => 0,
        ])->save();

        $model->refresh();

        $this->assertSame(0, $model->foo);
        $this->assertSame(0, $model->int);
        $this->assertSame('0', $model->str);
        $this->assertSame(false, $model->bool);
        $this->assertSame('1970-01-01', $model->date->format('Y-m-d'));
        $this->assertSame('1970-01-01 00:00:00', $model->datetime->format('Y-m-d H:i:s'));
        $this->assertSame(0.0, $model->dbl);
    }

    /** @test */
    public function it_will_handle_empty_values(): void
    {
        $model = $this->getModel();

        $model->forceFill([
            'foo' => '',
            'int' => '',
            'str' => '',
            'bool' => '',
            'date' => '',
            'datetime' => '',
            'dbl' => '',
        ])->save();

        $model->refresh();

        $this->assertSame('', $model->foo);
        $this->assertSame(0, $model->int);
        $this->assertSame('', $model->str);
        $this->assertSame(false, $model->bool);
        $this->assertNull($model->date?->format('Y-m-d'));
        $this->assertNull($model->datetime?->format('Y-m-d H:i:s'));
        $this->assertSame(0.0, $model->dbl);
    }

    protected function getModel(): Post
    {
        $model = Post::factory()->create();

        $model->metaKeys([
            'foo',
            'int' => 'integer',
            'str' => 'string',
            'bool' => 'boolean',
            'date' => 'date',
            'datetime' => 'datetime',
            'dbl' => 'float',
        ]);

        return $model;
    }
}
