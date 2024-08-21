<?php

use Illuminate\Database\Eloquent\Collection;
use Kolossal\Multiplex\DataType;
use Kolossal\Multiplex\Tests\Mocks\Post;

it('can handle non existing models', function () {
    $models = Post::factory(3)->make();
    $handler = new DataType\ModelCollectionHandler;

    expect($handler->canHandleValue($models->first()))->toBeFalse();
    expect($handler->canHandleValue($models))->toBeTrue();

    $serialized = $handler->serializeValue($models);
    $unserialized = $handler->unserializeValue($serialized);

    expect($unserialized)->toBeInstanceOf(Collection::class);
    expect($unserialized)->toHaveCount(3);

    $unserialized->every(fn ($item) => expect($item)->toBeInstanceOf(Post::class));
});

it('can handle existing models', function () {
    Post::factory()->create(['title' => 'a']);
    Post::factory()->create(['title' => 'b']);
    Post::factory()->create(['title' => 'c']);

    $handler = new DataType\ModelCollectionHandler;

    expect($handler->canHandleValue(Post::first()))->toBeFalse();
    expect($handler->canHandleValue(Post::get()))->toBeTrue();

    $serialized = $handler->serializeValue(Post::get());
    $unserialized = $handler->unserializeValue($serialized);

    expect($unserialized)->toBeInstanceOf(Collection::class);
    expect($unserialized)->toHaveCount(3);

    expect($unserialized->pluck('title')->sort()->toArray())->toEqual(['a', 'b', 'c']);
});

it('will serialize empty value if no collection is passed', function () {
    $model = Post::factory()->create();

    $handler = new DataType\ModelCollectionHandler;

    $serialized = $handler->serializeValue($model);

    expect($serialized)->toBe('');
    expect($handler->unserializeValue($serialized))->toBeNull();
});

it('will unserialize to null for invalid values', function () {
    $model = Post::factory()->create();

    $handler = new DataType\ModelCollectionHandler;

    expect($handler->unserializeValue('123'))->toBeNull();
    expect($handler->unserializeValue($model))->toBeNull();
    expect($handler->unserializeValue(123))->toBeNull();
});
