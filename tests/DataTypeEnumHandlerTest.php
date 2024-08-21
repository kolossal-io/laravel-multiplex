<?php

use Kolossal\Multiplex\DataType\EnumHandler;
use Kolossal\Multiplex\Tests\Mocks\BackedEnum;
use Kolossal\Multiplex\Tests\Mocks\Enum;
use Kolossal\Multiplex\Tests\Mocks\Post;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('can serialize backed enums', function () {
    $handler = new EnumHandler;

    expect($handler->canHandleValue(BackedEnum::One))->toBeTrue();

    expect($handler->serializeValue(BackedEnum::One))->toEqual('Kolossal\Multiplex\Tests\Mocks\BackedEnum::one');
    expect($handler->serializeValue(BackedEnum::Two))->toEqual('Kolossal\Multiplex\Tests\Mocks\BackedEnum::two');
    expect($handler->serializeValue(BackedEnum::Three))->toEqual('Kolossal\Multiplex\Tests\Mocks\BackedEnum::three');
});

it('cannot serialize basic enums', function () {
    $handler = new EnumHandler;

    expect($handler->canHandleValue(Enum::One))->toBeFalse();
    expect($handler->serializeValue(Enum::One))->toBe('');
});

it('cannot unserialize null', function () {
    $handler = new EnumHandler;

    expect($handler->unserializeValue(null))->toBeNull();
});

it('cannot unserialize invalid value', function () {
    $handler = new EnumHandler;

    expect($handler->unserializeValue('Kolossal\Multiplex\Tests\Mocks\BackedEnum'))->toBeNull();
});

it('cannot unserialize not existing enums', function () {
    $handler = new EnumHandler;

    expect($handler->unserializeValue('Kolossal\Multiplex\Tests\Mocks\InvalidEnum::one'))->toBeNull();
});

it('cannot unserialize non enum classes', function () {
    $handler = new EnumHandler;

    expect($handler->unserializeValue('Kolossal\Multiplex\Tests\Mocks\Dummy::one'))->toBeNull();
});

it('can unserialize backed enums', function () {
    $handler = new EnumHandler;

    $enum = $handler->unserializeValue('Kolossal\Multiplex\Tests\Mocks\BackedEnum::three');

    expect($enum)->toBe(BackedEnum::Three);
    $this->assertNotSame(BackedEnum::One, $enum);
});

it('cannot unserialize invalid values', function () {
    $handler = new EnumHandler;

    $enum = $handler->unserializeValue('Kolossal\Multiplex\Tests\Mocks\BackedEnum::four');

    expect($enum)->toBeNull();
});

it('cannot unserialize basic enums', function () {
    $handler = new EnumHandler;

    $enum = $handler->unserializeValue('Kolossal\Multiplex\Tests\Mocks\Enum::three');

    expect($enum)->toBeNull();
});

it('will handle backed enum value', function () {
    $model = Post::factory()->create();

    $model->saveMeta('enum_test', BackedEnum::Two);

    $this->assertDatabaseHas('meta', [
        'key' => 'enum_test',
        'value' => 'Kolossal\Multiplex\Tests\Mocks\BackedEnum::two',
        'type' => 'enum',
    ]);

    expect(Post::first()->enum_test)->toBe(BackedEnum::Two);
});

it('will not handle basic enum value', function () {
    $model = Post::factory()->create();

    $model->saveMeta('enum_test', Enum::Two);

    $this->assertDatabaseHas('meta', [
        'key' => 'enum_test',
        'value' => '',
        'type' => 'object',
    ]);

    expect(Post::first()->enum_test)->toBeNull();
});
