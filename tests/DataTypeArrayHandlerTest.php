<?php

use Kolossal\Multiplex\DataType\ArrayHandler;

it('will resolve null as null', function () {
    $handler = new ArrayHandler;

    expect($handler)->toBeInstanceOf(ArrayHandler::class);

    expect($handler->unserializeValue(null))->toBeNull();
});

it('can handle arrays', function () {
    $handler = new ArrayHandler;

    expect($handler->canHandleValue(['id' => 123]))->toBeTrue();
});

it('cannot handle other values', function () {
    $handler = new ArrayHandler;

    expect($handler->canHandleValue((object) ['id' => 123]))->toBeFalse();
    expect($handler->canHandleValue(123))->toBeFalse();
    expect($handler->canHandleValue(false))->toBeFalse();
});

it('serializes value', function () {
    $handler = new ArrayHandler;

    expect($handler->serializeValue(['id' => 123]))->toBe('{"id":123}');
});

it('unserializes value', function () {
    $handler = new ArrayHandler;

    $value = $handler->unserializeValue('{"id":123}');

    expect($value)->toBeArray();

    expect($value['id'])->toBe(123);
});
