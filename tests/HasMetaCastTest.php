<?php

use Carbon\Carbon;
use Kolossal\Multiplex\Tests\Mocks\Post;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('can force type casts', function () {
    $model = getModel();

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

    expect($model->foo)->toBe(123.0);
    expect($model->int)->toBe(123);
    expect($model->str)->toBe('123');
    expect($model->bool)->toBe(false);
    expect($model->date->isSameDay(Carbon::create(2020, 1, 1)))->toBeTrue();
    expect($model->datetime->equalTo(Carbon::create(2020, 1, 1)))->toBeTrue();
    expect($model->dbl)->toBe(123.000);
});

it('will handle null values', function () {
    $model = getModel();

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

    expect($model->foo)->toBeNull();
    expect($model->int)->toBeNull();
    expect($model->str)->toBeNull();
    expect($model->bool)->toBeNull();
    expect($model->date)->toBeNull();
    expect($model->datetime)->toBeNull();
    expect($model->dbl)->toBeNull();
});

it('will handle falsy values', function () {
    $model = getModel();

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

    expect($model->foo)->toBe(0);
    expect($model->int)->toBe(0);
    expect($model->str)->toBe('0');
    expect($model->bool)->toBe(false);
    expect($model->date->format('Y-m-d'))->toBe('1970-01-01');
    expect($model->datetime->format('Y-m-d H:i:s'))->toBe('1970-01-01 00:00:00');
    expect($model->dbl)->toBe(0.0);
});

it('will handle empty values', function () {
    $model = getModel();

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

    expect($model->foo)->toBe('');
    expect($model->int)->toBe(0);
    expect($model->str)->toBe('');
    expect($model->bool)->toBe(false);
    expect($model->date?->format('Y-m-d'))->toBeNull();
    expect($model->datetime?->format('Y-m-d H:i:s'))->toBeNull();
    expect($model->dbl)->toBe(0.0);
});

function getModel(): Post
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
