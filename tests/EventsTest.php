<?php

use Illuminate\Support\Facades\Event;
use Kolossal\Multiplex\Events\MetaHasBeenAdded;
use Kolossal\Multiplex\Events\MetaHasBeenRemoved;
use Kolossal\Multiplex\Tests\Mocks\Post;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Post::travelBack();

    Event::fake([
        MetaHasBeenAdded::class,
        MetaHasBeenRemoved::class,
    ]);
});

it('will fire meta added event', function () {
    $model = Post::factory()->create();
    $model->setMeta('foo', 'bar');

    Event::assertNotDispatched(MetaHasBeenAdded::class);

    $model->saveMeta();

    Event::assertDispatched(MetaHasBeenAdded::class);
});

it('will fire meta added event when creating', function () {
    Post::factory()->create([
        'title' => 'Title',
    ]);

    Event::assertNotDispatched(MetaHasBeenAdded::class);

    Post::factory()->create([
        'title' => 'Title',
        'foo' => 'bar',
    ]);

    Event::assertDispatched(MetaHasBeenAdded::class);
});

it('will fire meta added event when assigning fluently', function () {
    $model = Post::factory()->create();
    $model->title = 'Title changed';
    $model->save();

    Event::assertNotDispatched(MetaHasBeenAdded::class);

    $model->foo = 'bar';

    Event::assertNotDispatched(MetaHasBeenAdded::class);

    $model->save();

    Event::assertDispatched(MetaHasBeenAdded::class);
});

it('will fire meta added event when saving directly', function () {
    $model = Post::factory()->create();
    $model->saveMeta('foo', 'bar');

    Event::assertDispatched(MetaHasBeenAdded::class);
});

it('will fire meta added event only for dirty meta', function () {
    $model = Post::factory()->create();
    $model->saveMeta('foo', 'bar');

    $this->assertDatabaseCount('meta', 1);

    $model->fresh();
    $model->saveMeta('foo', 'bar');

    $this->assertDatabaseCount('meta', 1);
    Event::assertDispatchedTimes(MetaHasBeenAdded::class, 1);

    $model->fresh();
    $model->saveMeta('foo', 'changed');

    $this->assertDatabaseCount('meta', 2);
    Event::assertDispatchedTimes(MetaHasBeenAdded::class, 2);
});

it('will fire meta added event for each meta individually', function () {
    $model = Post::factory()->create();

    $model->saveMeta('foo', 'bar');
    $model->setMeta('bar', 123);
    $model->thisthat = false;

    Event::assertDispatchedTimes(MetaHasBeenAdded::class, 1);

    $model->save();
    $model->saveMeta('thisthat', false);

    $this->assertDatabaseCount('meta', 3);
    Event::assertDispatchedTimes(MetaHasBeenAdded::class, 3);
});

it('will pass meta to meta added event', function () {
    $model = Post::factory()->create();

    $model->saveMeta('foo', 'bar');

    Event::assertDispatched(function (MetaHasBeenAdded $event) use ($model) {
        return $event->meta->is($model->meta()->first());
    });
});

it('will pass metable class name to meta added event', function () {
    $model = Post::factory()->create();

    $model->saveMeta('foo', 'bar');

    Event::assertDispatched(function (MetaHasBeenAdded $event) {
        return $event->type === Post::class;
    });
});

it('will pass metable model to meta added event', function () {
    $model = Post::factory()->create();

    $model->saveMeta('foo', 'bar');

    Event::assertDispatched(function (MetaHasBeenAdded $event) use ($model) {
        return $event->model->is($model);
    });
});

it('will fire meta removed event', function () {
    $model = Post::factory()->create();
    $model->setMeta('foo', 'bar');

    $model->saveMeta();

    Event::assertNotDispatched(MetaHasBeenRemoved::class);

    $model->deleteMeta('foo');

    Event::assertDispatched(MetaHasBeenRemoved::class);
});

it('will fire meta removed event for meta individually', function () {
    $model = Post::factory()->create();
    $model->saveMeta('foo', 'bar');
    $model->saveMeta('bar', 123);

    Event::assertNotDispatched(MetaHasBeenRemoved::class);

    $model->deleteMeta(['foo', 'bar']);

    Event::assertDispatchedTimes(MetaHasBeenRemoved::class, 2);
});

it('will not fire meta removed event when purging', function () {
    $model = Post::factory()->create();
    $model->saveMeta('foo', 'bar');
    $model->saveMeta('bar', 123);

    $model->purgeMeta();

    Event::assertNotDispatched(MetaHasBeenRemoved::class);
});

it('will fire meta removed only with latest meta', function () {
    $model = Post::factory()->create();

    $model->saveMeta('foo', 'bar');
    $model->saveMeta('foo', 123);
    $model->saveMeta('foo', 'bar');

    $this->assertDatabaseCount('meta', 3);

    Event::assertNotDispatched(MetaHasBeenRemoved::class);

    $latest = $model->meta()->orderByDesc('id')->first();

    $model->deleteMeta('foo');

    Event::assertDispatchedTimes(MetaHasBeenRemoved::class, 1);

    Event::assertDispatched(MetaHasBeenRemoved::class, function ($event) use ($latest) {
        expect($latest->value)->toBe('bar');

        return $event->meta->is($latest);
    });
});

it('will not fire meta removed for nonexistent meta', function () {
    $model = Post::factory()->create();
    $model->saveMeta('foo', 'bar');

    Event::assertNotDispatched(MetaHasBeenRemoved::class);

    $model->deleteMeta('bar');

    Event::assertNotDispatched(MetaHasBeenRemoved::class);
});
