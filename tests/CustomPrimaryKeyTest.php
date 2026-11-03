<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Kolossal\Multiplex\Events\MetaHasBeenRemoved;
use Kolossal\Multiplex\Meta;
use Kolossal\Multiplex\Tests\Mocks\PostWithCustomKey;

uses(RefreshDatabase::class);

it('can read meta of models with a custom primary key', function () {
    $post = PostWithCustomKey::query()->forceCreate(['title' => 'Title']);
    $post->saveMeta('foo', 'bar');

    $post = PostWithCustomKey::first();

    expect($post->foo)->toBe('bar')
        ->and($post->getMeta('foo'))->toBe('bar')
        ->and($post->hasMeta('foo'))->toBeTrue()
        ->and($post->findMeta('foo'))->toBeInstanceOf(Meta::class)
        ->and($post->undefined)->toBeNull()
        ->and($post->title)->toBe('Title');
});

it('can read meta of models with a custom primary key and explicit meta keys', function () {
    $post = PostWithCustomKey::query()->forceCreate([]);
    $post->metaKeys(['foo']);
    $post->saveMeta('foo', 'bar');

    $post = PostWithCustomKey::first();
    $post->metaKeys(['foo']);

    expect($post->foo)->toBe('bar')
        ->and($post->hasMeta('foo'))->toBeTrue();
});

it('will not store unchanged meta of models with a custom primary key again', function () {
    $post = PostWithCustomKey::query()->forceCreate([]);
    $post->saveMeta('foo', 'bar');

    $post = PostWithCustomKey::first();
    $post->foo = 'bar';
    $post->save();

    expect(Meta::count())->toBe(1);
});

it('can delete meta of models with a custom primary key', function () {
    Event::fake([MetaHasBeenRemoved::class]);

    $post = PostWithCustomKey::query()->forceCreate([]);
    $post->saveMeta('foo', 'bar');

    expect($post->deleteMeta('foo'))->toBeTrue()
        ->and($post->foo)->toBeNull();

    Event::assertDispatched(MetaHasBeenRemoved::class);
});
