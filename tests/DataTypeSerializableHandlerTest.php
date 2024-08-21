<?php

use Kolossal\Multiplex\DataType\SerializableHandler;
use Kolossal\Multiplex\Tests\Mocks\Dummy;
use Kolossal\Multiplex\Tests\Mocks\SampleSerializable;

it('will resolve null as null', function () {
    $handler = new SerializableHandler;

    expect($handler)->toBeInstanceOf(SerializableHandler::class);

    expect($handler->unserializeValue(null))->toBeNull();
});

it('can handle serializable values', function () {
    $handler = new SerializableHandler;

    expect($handler->canHandleValue(new SampleSerializable(['id' => 123])))->toBeTrue();
});

it('cannot handle other values', function () {
    $handler = new SerializableHandler;

    expect($handler->canHandleValue(new Dummy))->toBeFalse();
    expect($handler->canHandleValue(123))->toBeFalse();
    expect($handler->canHandleValue(false))->toBeFalse();
    expect($handler->canHandleValue(['id' => 123]))->toBeFalse();
    expect($handler->canHandleValue((object) ['id' => 123]))->toBeFalse();
});

it('serializes value', function () {
    $handler = new SerializableHandler;

    expect($handler->serializeValue(new SampleSerializable(['id' => 123])))->toBe('O:49:"Kolossal\Multiplex\Tests\Mocks\SampleSerializable":1:{s:2:"id";i:123;}');
});

it('unserializes value', function () {
    $handler = new SerializableHandler;

    $value = $handler->unserializeValue('O:49:"Kolossal\Multiplex\Tests\Mocks\SampleSerializable":1:{s:2:"id";i:123;}');

    expect($value)->toBeInstanceOf(SampleSerializable::class);

    expect($value->data['id'])->toBe(123);
});
