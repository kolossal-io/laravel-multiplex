<?php

use Kolossal\Multiplex\DataType\ObjectHandler;
use Kolossal\Multiplex\Tests\Mocks\SampleSerializable;

it('will resolve null as null', function () {
    $handler = new ObjectHandler;

    expect($handler)->toBeInstanceOf(ObjectHandler::class);

    expect($handler->unserializeValue(null))->toBeNull();
});

it('can handle objects', function () {
    $handler = new ObjectHandler;

    expect($handler->canHandleValue((object) ['id' => 123]))->toBeTrue();
    expect($handler->canHandleValue(new SampleSerializable(['id' => 123])))->toBeTrue();
});

it('cannot handle other values', function () {
    $handler = new ObjectHandler;

    expect($handler->canHandleValue(['id' => 123]))->toBeFalse();
    expect($handler->canHandleValue(123))->toBeFalse();
    expect($handler->canHandleValue(false))->toBeFalse();
});

it('serializes value', function () {
    $handler = new ObjectHandler;

    expect($handler->serializeValue(new SampleSerializable(['id' => 123])))->toBe('{"data":{"id":123}}');
});

it('unserializes value', function () {
    $handler = new ObjectHandler;

    $value = $handler->unserializeValue('{"data":{"id":123}}');

    expect($value)->toBeObject();

    expect($value->data->id)->toBe(123);
});
