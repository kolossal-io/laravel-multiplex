<?php

namespace Kolossal\Multiplex\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Kolossal\Multiplex\Events\MetaHasBeenAdded;
use Kolossal\Multiplex\Events\MetaHasBeenRemoved;
use Kolossal\Multiplex\Tests\Mocks\Post;
use PHPUnit\Framework\Attributes\Test;

final class EventsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Post::travelBack();

        Event::fake([
            MetaHasBeenAdded::class,
            MetaHasBeenRemoved::class,
        ]);
    }

    /** @test */
    public function it_will_fire_meta_added_event(): void
    {
        $model = Post::factory()->create();
        $model->setMeta('foo', 'bar');

        Event::assertNotDispatched(MetaHasBeenAdded::class);

        $model->saveMeta();

        Event::assertDispatched(MetaHasBeenAdded::class);
    }

    /** @test */
    public function it_will_fire_meta_added_event_when_creating(): void
    {
        Post::factory()->create([
            'title' => 'Title',
        ]);

        Event::assertNotDispatched(MetaHasBeenAdded::class);

        Post::factory()->create([
            'title' => 'Title',
            'foo' => 'bar',
        ]);

        Event::assertDispatched(MetaHasBeenAdded::class);
    }

    /** @test */
    public function it_will_fire_meta_added_event_when_assigning_fluently(): void
    {
        $model = Post::factory()->create();
        $model->title = 'Title changed';
        $model->save();

        Event::assertNotDispatched(MetaHasBeenAdded::class);

        $model->foo = 'bar';

        Event::assertNotDispatched(MetaHasBeenAdded::class);

        $model->save();

        Event::assertDispatched(MetaHasBeenAdded::class);
    }

    /** @test */
    public function it_will_fire_meta_added_event_when_saving_directly(): void
    {
        $model = Post::factory()->create();
        $model->saveMeta('foo', 'bar');

        Event::assertDispatched(MetaHasBeenAdded::class);
    }

    /** @test */
    public function it_will_fire_meta_added_event_only_for_dirty_meta(): void
    {
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
    }

    /** @test */
    public function it_will_fire_meta_added_event_for_each_meta_individually(): void
    {
        $model = Post::factory()->create();

        $model->saveMeta('foo', 'bar');
        $model->setMeta('bar', 123);
        $model->thisthat = false;

        Event::assertDispatchedTimes(MetaHasBeenAdded::class, 1);

        $model->save();
        $model->saveMeta('thisthat', false);

        $this->assertDatabaseCount('meta', 3);
        Event::assertDispatchedTimes(MetaHasBeenAdded::class, 3);
    }

    /** @test */
    public function it_will_pass_meta_to_meta_added_event(): void
    {
        $model = Post::factory()->create();

        $model->saveMeta('foo', 'bar');

        Event::assertDispatched(function (MetaHasBeenAdded $event) use ($model) {
            return $event->meta->is($model->meta()->first());
        });
    }

    /** @test */
    public function it_will_pass_metable_class_name_to_meta_added_event(): void
    {
        $model = Post::factory()->create();

        $model->saveMeta('foo', 'bar');

        Event::assertDispatched(function (MetaHasBeenAdded $event) {
            return $event->type === Post::class;
        });
    }

    /** @test */
    public function it_will_pass_metable_model_to_meta_added_event(): void
    {
        $model = Post::factory()->create();

        $model->saveMeta('foo', 'bar');

        Event::assertDispatched(function (MetaHasBeenAdded $event) use ($model) {
            return $event->model->is($model);
        });
    }

    /** @test */
    public function it_will_fire_meta_removed_event(): void
    {
        $model = Post::factory()->create();
        $model->setMeta('foo', 'bar');

        $model->saveMeta();

        Event::assertNotDispatched(MetaHasBeenRemoved::class);

        $model->deleteMeta('foo');

        Event::assertDispatched(MetaHasBeenRemoved::class);
    }

    /** @test */
    public function it_will_fire_meta_removed_event_for_meta_individually(): void
    {
        $model = Post::factory()->create();
        $model->saveMeta('foo', 'bar');
        $model->saveMeta('bar', 123);

        Event::assertNotDispatched(MetaHasBeenRemoved::class);

        $model->deleteMeta(['foo', 'bar']);

        Event::assertDispatchedTimes(MetaHasBeenRemoved::class, 2);
    }

    /** @test */
    public function it_will_not_fire_meta_removed_event_when_purging(): void
    {
        $model = Post::factory()->create();
        $model->saveMeta('foo', 'bar');
        $model->saveMeta('bar', 123);

        $model->purgeMeta();

        Event::assertNotDispatched(MetaHasBeenRemoved::class);
    }

    /** @test */
    public function it_will_fire_meta_removed_only_with_latest_meta(): void
    {
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
            $this->assertSame('bar', $latest->value);

            return $event->meta->is($latest);
        });
    }

    /** @test */
    public function it_will_not_fire_meta_removed_for_nonexistent_meta(): void
    {
        $model = Post::factory()->create();
        $model->saveMeta('foo', 'bar');

        Event::assertNotDispatched(MetaHasBeenRemoved::class);

        $model->deleteMeta('bar');

        Event::assertNotDispatched(MetaHasBeenRemoved::class);
    }
}
