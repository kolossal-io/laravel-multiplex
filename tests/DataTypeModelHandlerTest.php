<?php

use Kolossal\Multiplex\DataType;
use Kolossal\Multiplex\Tests\Mocks\Post;

it('can handle non existing models', function () {
    $model = Post::factory()->make();
    $handler = new DataType\ModelHandler;

    expect($handler->canHandleValue(new stdClass))->toBeFalse();
    expect($handler->canHandleValue($model))->toBeTrue();

    $serialized = $handler->serializeValue($model);
    $unserialized = $handler->unserializeValue($serialized);

    expect($serialized)->toEqual('Kolossal\Multiplex\Tests\Mocks\Post');
    expect($unserialized)->toBeInstanceOf(Post::class);
});

it('can handle existing models', function () {
    $model = Post::factory()->create();
    $handler = new DataType\ModelHandler;

    expect($handler->canHandleValue($model))->toBeTrue();

    $serialized = $handler->serializeValue($model);
    $unserialized = $handler->unserializeValue($serialized);

    expect($serialized)->toEqual('Kolossal\Multiplex\Tests\Mocks\Post#1');
    expect($unserialized->is($model))->toBeTrue();
});

it('will resolve null as null', function () {
    $handler = new DataType\ModelHandler;

    expect($handler->unserializeValue(null))->toBeNull();
});
