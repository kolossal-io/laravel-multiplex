<?php

namespace Kolossal\Multiplex\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Kolossal\Multiplex\Exceptions\MetaException;
use Kolossal\Multiplex\Meta;
use Kolossal\Multiplex\MetaAttribute;
use Kolossal\Multiplex\Tests\Mocks\Dummy;
use Kolossal\Multiplex\Tests\Mocks\Post;
use Kolossal\Multiplex\Tests\Mocks\PostWithExistingColumn;
use Kolossal\Multiplex\Tests\Mocks\PostWithoutSoftDelete;
use PDOException;

class HasMetaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Post::travelBack();
    }

    /** @test */
    public function it_can_set_any_keys_as_meta_by_default()
    {
        $this->assertDatabaseCount('meta', 0);

        $model = Post::factory()->create();

        $model->setMeta('foo', 'bar');
        $model->setMeta('another', 125.12);
        $model->save();

        $this->assertDatabaseCount('meta', 2);
    }

    /** @test */
    public function it_can_set_meta_fluently()
    {
        $this->assertDatabaseCount('meta', 0);

        $model = Post::factory()->create();

        $model->foo = 'bar';
        $model->another = 125.12;
        $model->save();

        $this->assertDatabaseCount('meta', 2);
    }

    /** @test */
    public function it_will_save_meta_when_model_is_saved()
    {
        $model = Post::factory()->create();

        $model->title = 'Post title 2';
        $model->setMeta('foo', 'bar');
        $model->bar = 125;

        $this->assertDatabaseCount('meta', 0);

        $model->save();

        $this->assertDatabaseHas('sample_posts', ['title' => 'Post title 2']);
        $this->assertDatabaseCount('meta', 2);
    }

    /** @test */
    public function it_can_save_model_without_meta()
    {
        $model = Post::factory()->create();

        $model->title = 'Post title 2';
        $model->setMeta('foo', 'bar');
        $model->bar = 125;

        $this->assertDatabaseCount('meta', 0);

        $model->saveWithoutMeta();

        $this->assertDatabaseHas('sample_posts', ['title' => 'Post title 2']);
        $this->assertDatabaseCount('meta', 0);

        $model->saveMeta();

        $this->assertDatabaseCount('meta', 2);
    }

    /** @test */
    public function it_can_disable_meta_autosave()
    {
        $model = Post::factory()->create();

        $model->autosaveMeta(false);

        $model->title = 'Post title 2';
        $model->setMeta('foo', 'bar');
        $model->bar = 125;

        $this->assertDatabaseCount('meta', 0);

        $model->save();

        $this->assertDatabaseHas('sample_posts', ['title' => 'Post title 2']);
        $this->assertDatabaseCount('meta', 0);

        $model->autosaveMeta();
        $model->save();

        $this->assertDatabaseCount('meta', 2);
    }

    /** @test */
    public function it_will_handle_allowed_keys()
    {
        $model = Post::factory()->create();

        $model->metaKeys([
            'foo',
            'bar',
        ]);

        $model->setMeta('foo', 'bar');
        $model->bar = 125;

        $this->assertDatabaseCount('meta', 0);

        $model->save();

        $this->assertDatabaseCount('meta', 2);
    }

    /** @test */
    public function it_will_handle_allowed_keys_in_arrays()
    {
        $model = Post::factory()->create();

        $model->metaKeys([
            'foo',
            'bar',
        ]);

        $model->setMeta([
            'foo' => 'bar',
            'bar' => 125,
        ]);

        $this->assertDatabaseCount('meta', 0);

        $model->save();

        $this->assertDatabaseCount('meta', 2);
    }

    /** @test */
    public function it_will_use_meta_keys_from_property()
    {
        /** @var PostWithoutSoftDelete */
        $model = $this->partialMock(PostWithoutSoftDelete::class, function ($mock) {
            $reflectionClass = new \ReflectionClass($mock);

            tap($reflectionClass->getProperty('metaKeys'), function ($property) use ($mock) {
                $property->setAccessible(true);
                $property->setValue($mock, ['foo', 'bar']);
            });

            tap($reflectionClass->getProperty('casts'), function ($property) use ($mock) {
                $property->setAccessible(true);
                $property->setValue($mock, ['title' => MetaAttribute::class]);
            });
        });

        $this->assertEquals(['foo', 'bar'], $model->metaKeys());
        $this->assertEquals(['title', 'foo', 'bar'], $model->getExplicitlyAllowedMetaKeys());
    }

    /** @test */
    public function it_will_use_meta_keys_from_method()
    {
        /** @var PostWithoutSoftDelete */
        $model = $this->partialMock(PostWithoutSoftDelete::class, function ($mock) {
            $reflectionClass = new \ReflectionClass($mock);

            tap($reflectionClass->getProperty('metaKeys'), function ($property) use ($mock) {
                $property->setAccessible(true);
                $property->setValue($mock, ['foo']);
            });

            tap($reflectionClass->getProperty('casts'), function ($property) use ($mock) {
                $property->setAccessible(true);
                $property->setValue($mock, ['title' => MetaAttribute::class]);
            });
        });

        $model->metaKeys(['bar']);

        $this->assertEquals(['bar'], $model->metaKeys());
        $this->assertEquals(['title', 'bar'], $model->getExplicitlyAllowedMetaKeys());
    }

    /** @test */
    public function it_will_use_default_meta_keys_as_fallback()
    {
        $model = Post::factory()->create();
        $this->assertEquals(['*'], $model->metaKeys());
        $this->assertEquals(['appendable_foo'], $model->getExplicitlyAllowedMetaKeys());
    }

    /** @test */
    public function it_will_throw_for_unallowed_keys()
    {
        $model = Post::factory()->create();

        $model->metaKeys([
            'foo',
        ]);

        $model->setMeta('foo', 'bar');

        $this->expectException(MetaException::class);
        $this->expectExceptionMessage('Meta key `bar` is not a valid key.');

        $model->setMeta('bar', 125);
    }

    /** @test */
    public function it_will_throw_for_unallowed_keys_in_arrays()
    {
        $this->expectException(MetaException::class);
        $this->expectExceptionMessage('Meta key `another` is not a valid key.');

        $model = Post::factory()->create();

        $model->metaKeys([
            'foo',
            'bar',
        ]);

        $model->setMeta([
            'foo' => 'bar',
            'bar' => 125,
            'another' => 'one',
        ]);

        $model->save();
    }

    /** @test */
    public function it_lets_laravel_handle_unallowed_keys_assigned_fluently()
    {
        $this->expectException(PDOException::class);

        $model = Post::factory()->create();

        $model->metaKeys([
            'foo',
        ]);

        $model->setMeta('foo', 'bar');
        $model->bar = 125;

        $model->save();
    }

    /** @test */
    public function it_can_unguard_meta_keys()
    {
        $model = Post::factory()->create();

        $model->metaKeys([
            'foo',
        ]);

        $this->assertFalse(Post::isMetaUnguarded());

        Post::unguardMeta();

        $model->setMeta('foo', 'bar');
        $model->bar = 125;

        $model->save();

        $this->assertTrue(Post::isMetaUnguarded());
        $this->assertDatabaseCount('meta', 2);

        Post::unguardMeta(false);
    }

    /** @test */
    public function it_can_reguard_meta_keys()
    {
        $this->expectException(MetaException::class);
        $this->expectExceptionMessage('Meta key `bar` is not a valid key.');

        $model = Post::factory()->create();

        $model->metaKeys([
            'foo',
        ]);

        Post::unguardMeta();
        $this->assertTrue(Post::isMetaUnguarded());

        Post::reguardMeta();

        $model->setMeta('foo', 'bar');
        $model->setMeta('bar', 125);
    }

    /** @test */
    public function it_can_contain_wildcard_mixed_with_allowed_keys()
    {
        $model = Post::factory()->create();

        $model->metaKeys([
            'foo',
            'bar',
            '*',
        ]);

        $model->setMeta('foo', 'bar');
        $model->bar = 125;
        $model->another = 'one';
        $model->setMeta('bar', 124);

        $this->assertDatabaseCount('meta', 0);

        $model->save();

        $this->assertDatabaseCount('meta', 3);
    }

    /** @test */
    public function it_will_return_meta_by_get_accessor()
    {
        $model = Post::factory()->create();

        $model->saveMeta('foo', 'bar');

        $this->assertSame('bar', Post::first()->getMeta('foo'));
    }

    /** @test */
    public function it_will_return_null_if_meta_is_not_found()
    {
        $model = Post::factory()->create();

        $model->saveMeta('bar', 123);

        $this->assertNull(Post::first()->getMeta('foo'));
        $this->assertSame(123, Post::first()->getMeta('bar'));
    }

    /** @test */
    public function it_can_return_fallback_if_meta_is_not_found()
    {
        $model = Post::factory()->create();

        $model->saveMeta('bar', 123);

        $this->assertSame('fallback', Post::first()->getMeta('foo', 'fallback'));
        $this->assertSame(123, Post::first()->getMeta('bar', 'fallback'));
    }

    /** @test */
    public function it_will_show_if_meta_exists()
    {
        $model = Post::factory()->create();

        $this->assertFalse($model->hasMeta('foo'));

        $model->saveMeta('foo', 'bar');

        $this->assertTrue($model->hasMeta('foo'));
    }

    /** @test */
    public function it_can_set_meta_from_array()
    {
        $model = Post::factory()->create();

        $model->saveMeta([
            'foo' => 'bar',
            'bar' => 123,
        ]);

        $this->assertSame('bar', $model->refresh()->foo);
        $this->assertSame(123, $model->refresh()->bar);
    }

    /** @test */
    public function it_can_set_meta_for_the_future()
    {
        $model = Post::factory()->create();

        $model->setMeta('foo', 'bar');
        $model->setMetaAt('foo', 'change', '+1 hour');

        $model->save();

        $this->assertNull(Post::first()->foo);

        $this->travelTo('+1 hour');

        $this->assertSame('change', Post::first()->foo);
    }

    /** @test */
    public function it_can_set_meta_for_the_future_by_array()
    {
        $model = Post::factory()->create();

        $model->setMetaAt([
            'foo' => 'bar',
            'bar' => true,
        ], '+1 hour');

        $model->save();

        $this->assertNull(Post::first()->foo);
        $this->assertNull(Post::first()->bar);

        $this->travelTo('+1 hour');

        $this->assertSame('bar', Post::first()->foo);
        $this->assertSame(true, Post::first()->bar);
    }

    /** @test */
    public function it_can_save_meta_for_the_future()
    {
        $model = Post::factory()->create();

        $model->saveMeta('foo', 'bar');
        $model->saveMetaAt('foo', 'change', '+1 hour');

        $this->assertSame('bar', $model->refresh()->foo);

        $this->travelTo('+1 hour');

        $this->assertSame('change', $model->refresh()->foo);
    }

    /** @test */
    public function it_can_save_meta_with_same_value_for_different_timestamps()
    {
        $this->travelTo('2020-02-01 00:00:00');

        $model = Post::factory()->create();

        $this->assertEquals(0, $model->allMeta()->count());

        $model->saveMetaAt('foo', 123.29, '2020-01-01 00:00:00');
        $model->saveMetaAt('foo', 123.29, '2019-01-01 00:00:00');
        $model->saveMetaAt('foo', 123.29, '2021-01-01 00:00:00');

        $this->assertEquals(3, $model->allMeta()->count());
    }

    /** @test */
    public function it_can_save_multiple_meta_for_the_future()
    {
        $model = Post::factory()->create();

        $model->setMeta('foo', 'bar');
        $model->setMeta('bar', 123);

        $model->saveMetaAt('+1 hour');
        $model->refresh();

        $this->assertNull($model->foo);
        $this->assertNull($model->bar);

        $this->travelTo('+1 hour');
        $model->refresh();

        $this->assertSame('bar', $model->foo);
        $this->assertSame(123, $model->bar);
    }

    /** @test */
    public function it_can_set_meta_for_future_from_array()
    {
        $model = Post::factory()->create();

        $model->saveMeta([
            'foo' => 'bar',
            'bar' => 123,
        ]);

        $model->saveMetaAt([
            'foo' => 'change',
            'bar' => false,
        ], '+1 hour');

        $this->assertSame('bar', $model->refresh()->foo);
        $this->assertSame(123, $model->refresh()->bar);

        $this->travelTo('+1 hour');

        $this->assertSame('change', $model->refresh()->foo);
        $this->assertSame(false, $model->refresh()->bar);
    }

    /** @test */
    public function it_will_handle_future_meta_versions_as_non_existent()
    {
        $model = Post::factory()->create();

        $this->assertFalse($model->hasMeta('foo'));

        $model->saveMetaAt('foo', 'bar', '+1 hour');

        $this->assertFalse($model->refresh()->hasMeta('foo'));

        $this->travelTo('+1 hour');

        $this->assertTrue($model->refresh()->hasMeta('foo'));
    }

    /** @test */
    public function it_will_return_meta_fluently()
    {
        $model = Post::factory()->create(['title' => 'Title']);

        $model->foo = 'bar';
        $model->save();
        $model->saveMeta('bar', 123);

        $this->assertSame('Title', Post::first()->title);
        $this->assertSame('bar', Post::first()->foo);
        $this->assertSame(123, Post::first()->bar);
    }

    /** @test */
    public function it_will_respect_get_meta_accessors()
    {
        $model = Post::factory()->create();

        $this->assertSame('Empty', $model->test_has_accessor);

        $model->saveMeta('test_has_accessor', 'passed');

        $this->assertSame('Test passed.', $model->test_has_accessor);
    }

    /** @test */
    public function it_will_respect_set_meta_mutators()
    {
        $model = Post::factory()->create();

        $model->metaKeys([
            'foo',
            'test_has_mutator',
        ]);

        $model->setMeta('foo', 'bar');
        $model->test_has_mutator = '--passed';

        $model->save();

        $this->assertDatabaseHas('meta', [
            'value' => 'Test --passed.',
        ]);
    }

    /** @test */
    public function it_can_delete_meta()
    {
        $model = Post::factory()->create();

        $model->setMeta('foo', 'bar');

        $model->save();

        $this->assertDatabaseHas('meta', ['key' => 'foo']);

        $model->deleteMeta('foo');

        $this->assertDatabaseMissing('meta', ['key' => 'foo']);
    }

    /** @test */
    public function it_will_delete_all_meta_versions()
    {
        $model = Post::factory()->create();

        $model->title = 'Title';
        $model->setMeta('foo', 'bar');
        $model->save();

        $model->title = 'Title2';
        $model->setMeta('foo', 'bar2');
        $model->save();

        $model->title = 'Title3';
        $model->foo = 'bar3';
        $model->save();

        $this->assertDatabaseCount('meta', 3);
        $this->assertDatabaseHas('meta', ['key' => 'foo']);

        $model->deleteMeta('foo');

        $this->assertDatabaseMissing('meta', ['key' => 'foo']);
    }

    /** @test */
    public function it_can_delete_meta_from_array()
    {
        $model = Post::factory()->create();

        $model->setMeta('foo', 'bar');
        $model->setMeta('bar', true);
        $model->another = 123;

        $model->save();

        $this->assertDatabaseCount('meta', 3);
        $this->assertDatabaseHas('meta', ['key' => 'bar']);
        $this->assertDatabaseHas('meta', ['key' => 'foo']);
        $this->assertDatabaseHas('meta', ['key' => 'another']);

        $model->deleteMeta(['foo', 'another']);

        $this->assertDatabaseCount('meta', 1);
        $this->assertDatabaseHas('meta', ['key' => 'bar']);
        $this->assertDatabaseMissing('meta', ['key' => 'foo']);
        $this->assertDatabaseMissing('meta', ['key' => 'another']);
    }

    /** @test */
    public function it_will_throw_when_deleting_invalid_keys()
    {
        $this->expectException(MetaException::class);

        $model = Post::factory()->create();

        $model->metaKeys(['foo', 'bar', 'another']);

        $model->setMeta('foo', 'bar');
        $model->bar = true;
        $model->setMeta('another', 123);

        $model->save();

        $this->assertDatabaseCount('meta', 3);
        $this->assertDatabaseHas('meta', ['key' => 'bar']);
        $this->assertDatabaseHas('meta', ['key' => 'foo']);
        $this->assertDatabaseHas('meta', ['key' => 'another']);

        try {
            $model->deleteMeta(['foo', 'invalid']);
        } catch (MetaException $exception) {
            $this->assertDatabaseCount('meta', 3);
            $this->assertDatabaseHas('meta', ['key' => 'bar']);
            $this->assertDatabaseHas('meta', ['key' => 'foo']);
            $this->assertDatabaseHas('meta', ['key' => 'another']);

            throw $exception;
        }
    }

    /** @test */
    public function it_can_reset_meta_key_before_save()
    {
        $model = Post::factory()->create();

        $model->setMeta('foo', 'bar');
        $model->save();

        $model->setMeta('foo', 'changed');
        $model->resetMeta('foo');
        $model->save();

        $this->assertDatabaseHas('meta', ['key' => 'foo', 'value' => 'bar']);
        $this->assertDatabaseMissing('meta', ['key' => 'foo', 'value' => 'changed']);
    }

    /** @test */
    public function it_can_reset_all_meta()
    {
        $model = Post::factory()->create();

        $model->setMeta('foo', 'bar');
        $model->setMeta('bar', 123);
        $model->save();

        $model->setMeta('foo', 'changed');
        $model->bar = 234;

        $model->resetMetaChanges();
        $model->save();

        $this->assertDatabaseHas('meta', ['key' => 'foo', 'value' => 'bar']);
        $this->assertDatabaseHas('meta', ['key' => 'bar', 'value' => '123']);

        $this->assertDatabaseMissing('meta', ['key' => 'foo', 'value' => 'changed']);
        $this->assertDatabaseMissing('meta', ['key' => 'bar', 'value' => '234']);
    }

    /** @test */
    public function it_can_save_meta_directly()
    {
        Post::factory()->create()->saveMeta('foo', 'bar');

        $this->assertDatabaseHas('meta', ['key' => 'foo', 'value' => 'bar']);
    }

    /** @test */
    public function it_can_save_selected_meta_key_only()
    {
        $model = Post::factory()->create();

        $model->setMeta('foo', 'bar');
        $model->setMeta('bar', 123);
        $model->save();

        $model->setMeta('foo', 'changed');
        $model->bar = 234;

        $model->saveMeta('bar');

        $this->assertDatabaseHas('meta', ['key' => 'foo', 'value' => 'bar']);
        $this->assertDatabaseHas('meta', ['key' => 'bar', 'value' => '123']);

        $this->assertDatabaseMissing('meta', ['key' => 'foo', 'value' => 'changed']);
        $this->assertDatabaseHas('meta', ['key' => 'bar', 'value' => '234']);
    }

    /** @test */
    public function it_will_save_meta_updates_even_if_parent_is_clean()
    {
        $this->travelTo('2022-10-01 12:00:00');

        $model = Post::factory()->create();

        $this->travelTo('2022-10-02 12:00:00');

        $model->title = 'Title Update';
        $model->setMeta('foo', 'bar');
        $model->save();

        $this->travelTo('2022-10-03 12:00:00');

        $model->foo = 'bar2';
        $model->save();

        $this->travelTo('2022-10-04 12:00:00');

        $model->setMeta('foo', 'bar3');
        $model->save();

        $this->assertDatabaseCount('sample_posts', 1);
        $this->assertDatabaseCount('meta', 3);

        $this->assertTrue(Post::whereDate('updated_at', '2022-10-02')->exists());
        $this->assertTrue(Meta::whereDate('created_at', '2022-10-02')->exists());
        $this->assertTrue(Meta::whereDate('created_at', '2022-10-03')->exists());
        $this->assertTrue(Meta::whereDate('created_at', '2022-10-04')->exists());
    }

    /** @test */
    public function it_contains_only_most_recent_meta_per_key()
    {
        $this->travelTo('2021-10-01 12:00:00');

        $model = Post::factory()->create();
        $model->saveMeta('foo', 'bar');
        $model->bar = 123.45;
        $model->save();

        $this->travelTo('2022-10-01 12:00:00');

        $model->saveMeta('bar', 340.987);

        $this->travelTo('2022-10-02 12:00:00');

        $model->foo = 'changed';
        $model->save();

        $this->assertCount(2, $model->meta);

        $this->assertEquals([
            'bar' => 340.987,
            'foo' => 'changed',
        ], $model->meta->pluck('value', 'key')->sort()->toArray());
    }

    /** @test */
    public function it_contains_only_published_meta_data()
    {
        $this->travelTo('2021-10-01 12:00:00');

        $model = Post::factory()->create();
        $model->saveMeta('foo', 'bar');

        $model->saveMetaAt('foo', 'changed', '2022-10-31 11:00:00');
        $model->saveMetaAt('bar', 340.987, '2022-10-31 15:00:00');
        $model->saveMetaAt('bar', true, '2022-10-31 12:00:00');

        $this->assertCount(4, $model->allMeta);
        $this->assertCount(1, $model->meta);
        $this->assertSame('bar', $model->meta->first()->value);

        $this->travelTo('2022-10-31 11:30:00');

        $model->refresh();

        $this->assertCount(1, $model->meta);
        $this->assertSame('changed', $model->meta->first()->value);

        $this->travelTo('2022-10-31 12:00:00');

        $model->refresh();

        $this->assertCount(2, $model->meta);
        $this->assertSame('changed', $model->meta->pluck('value', 'key')['foo']);
        $this->assertSame(true, $model->meta->pluck('value', 'key')['bar']);

        $this->travelTo('2022-12-01 12:00:00');

        $model->refresh();

        $this->assertCount(2, $model->meta);
        $this->assertSame('changed', $model->meta->pluck('value', 'key')['foo']);
        $this->assertSame(340.987, $model->meta->pluck('value', 'key')['bar']);
    }

    /** @test */
    public function it_may_change_datatype_of_meta_data()
    {
        $model = Post::factory()->create();
        $model->saveMeta('foo', 'bar');

        $this->assertSame('string', $model->meta->first()->type);
        $this->assertSame('bar', $model->meta->first()->value);

        $model->saveMeta('foo', 123);

        $this->assertSame('integer', $model->meta->first()->type);
        $this->assertSame(123, $model->meta->first()->value);

        $model->saveMeta('foo', false);

        $this->assertSame('boolean', $model->meta->first()->type);
        $this->assertSame(false, $model->meta->first()->value);
    }

    /** @test */
    public function it_will_store_dirty_meta_only()
    {
        $this->travelTo('2022-10-01 12:00:00');

        $model = Post::factory()->create();

        $model->saveMeta('foo', 'bar');
        $model->saveMeta('bar', 123);

        $this->assertDatabaseCount('meta', 2);

        $this->assertSame('bar', $model->getMeta('foo'));
        $this->assertSame(123, $model->getMeta('bar'));
        $this->assertTrue($model->meta->pluck('updated_at', 'key')->get('foo')->isSameDay('2022-10-01'));
        $this->assertTrue($model->meta->pluck('updated_at', 'key')->get('bar')->isSameDay('2022-10-01'));

        $this->travelTo('2022-10-02 12:00:00');

        $model->saveMeta('foo', 'bar');
        $model->saveMeta('bar', 123);

        $this->assertDatabaseCount('meta', 2);

        $model->foo = 'bar';
        $model->bar = 123.0;
        $model->save();

        $this->assertDatabaseCount('meta', 3);

        $this->assertSame('bar', $model->getMeta('foo'));
        $this->assertSame(123.0, $model->getMeta('bar'));
        $this->assertTrue($model->meta->pluck('updated_at', 'key')->get('foo')->isSameDay('2022-10-01'));
        $this->assertTrue($model->meta->pluck('updated_at', 'key')->get('bar')->isSameDay('2022-10-02'));
    }

    /** @test */
    public function it_will_show_if_meta_is_dirty()
    {
        $model = Post::factory()->create();

        $this->assertFalse($model->isMetaDirty());
        $this->assertCount(0, $model->getDirtyMeta());

        $model->foo = 'bar';

        $this->assertTrue($model->isMetaDirty());
        $this->assertCount(1, $model->getDirtyMeta());

        $model->setMeta('foo', 'changed');
        $model->bar = 12;

        $this->assertTrue($model->isMetaDirty());
        $this->assertCount(2, $model->getDirtyMeta());

        $model->save();

        $this->assertFalse($model->isMetaDirty());
        $this->assertCount(0, $model->getDirtyMeta());
    }

    /** @test */
    public function it_can_add_casted_meta_fields_to_models_visible_fields()
    {
        $model = Post::factory()->create(['title' => 'Title']);

        $array = Post::first()->append('appendable_foo')->toArray();

        $this->assertArrayHasKey('title', $array);
        $this->assertArrayHasKey('appendable_foo', $array);
        $this->assertNull($array['appendable_foo']);

        $model->saveMeta('appendable_foo', 'this works.');

        $this->assertSame(
            'this works.',
            Post::first()->append('appendable_foo')->toArray()['appendable_foo'],
        );
    }

    /** @test */
    public function it_will_return_null_for_casted_meta_field_without_trait()
    {
        $model = new Dummy;

        $this->assertNull(
            $model->append('appendable_foo')->toArray()['appendable_foo']
        );
    }

    /** @test */
    public function it_can_set_casted_fields_not_in_whitelist()
    {
        $model = Post::factory()->create(['title' => 'Title']);

        $model->metaKeys(['foo']);

        $model->saveMeta('foo', 'bar');
        $model->saveMeta('appendable_foo', 'this works.');

        $this->assertSame('this works.', Post::first()->appendable_foo);

        $model = Post::first();

        $model->appendable_foo = 'this also works.';
        $model->save();

        $this->assertSame('bar', Post::first()->foo);
        $this->assertSame('this also works.', Post::first()->appendable_foo);
        $this->assertSame('this also works.', Post::first()->append('appendable_foo')->toArray()['appendable_foo']);
    }

    /** @test */
    public function it_will_return_correct_datatype_for_casted_meta_attributes()
    {
        $model = Post::factory()->create(['title' => 'Title']);

        $model->metaKeys(['foo']);

        $model->saveMeta('appendable_foo', 123);
        $this->assertSame(123, Post::first()->appendable_foo);

        $model->saveMeta('appendable_foo', false);
        $this->assertSame(false, Post::first()->appendable_foo);

        $model->saveMeta('appendable_foo', 150.024);
        $this->assertSame(150.024, Post::first()->appendable_foo);

        $model->saveMeta('appendable_foo', null);
        $this->assertNull(Post::first()->appendable_foo);
    }

    /** @test */
    public function it_will_return_null_for_undefined_casted_meta_field()
    {
        $model = Post::factory()->create(['title' => 'Title']);

        $model->metaKeys(['foo']);

        $this->assertNull(Post::first()->appendable_foo);
    }

    /** @test */
    public function it_will_return_column_value_for_casted_meta_fields_having_equally_named_column()
    {
        $model = Post::factory()->create(['title' => 'Title']);

        $model->metaKeys(['foo']);

        Schema::table('sample_posts', fn ($table) => $table->string('appendable_foo')->nullable());
        DB::table('sample_posts')->update(['appendable_foo' => 'Fallback']);

        $this->assertSame('Fallback', Post::first()->appendable_foo);

        $model->saveMetaAt('appendable_foo', 8000.99, Carbon::now()->addDay());

        $this->travelTo('+23 hours 50 minutes');

        $this->assertSame('Fallback', Post::first()->appendable_foo);

        $this->travelTo('+10 minutes');

        $this->assertSame(8000.99, Post::first()->appendable_foo);
    }

    /** @test */
    public function it_can_save_multiple_meta_for_a_given_date()
    {
        $model = Post::factory()->create();

        $model->setMeta('foo', 'bar');
        $model->bar = 125;

        $this->assertTrue($model->saveMetaAt('+1 day'));

        $this->assertNull(Post::first()->foo);
        $this->assertNull(Post::first()->bar);

        $model->setMeta([
            'foo' => 'old value',
            'bar' => false,
        ]);

        $this->assertTrue($model->saveMetaAt('-1 day'));

        $this->assertSame('old value', Post::first()->foo);
        $this->assertSame(false, Post::first()->bar);

        $this->travelTo('+1 day');

        $this->assertSame('bar', Post::first()->foo);
        $this->assertSame(125, Post::first()->bar);
    }

    /** @test */
    public function it_can_inspect_model_meta_at_a_given_point_in_time()
    {
        $this->travelTo('2022-10-01 12:00:00');

        $model = Post::factory()->create();
        $keys = ['foo', 'another', 'bar'];

        $model->saveMeta([
            'foo' => 'bar',
            'bar' => 125,
        ]);

        $this->travelTo('+1 day');
        $model->saveMeta('foo', 'updated');

        $this->travelTo('+2 days');
        $model->saveMeta('another', true);

        $this->travelTo('+1 day');
        $model->saveMeta('bar', 999.125);

        $this->assertEquals([
            'foo' => 'updated',
            'another' => true,
            'bar' => 999.125,
        ], Post::first()->only($keys));

        $this->assertEquals([
            'foo' => 'bar',
            'another' => null,
            'bar' => 125,
        ], Post::first()->withMetaAt('2022-10-01 15:00:00')->only($keys));

        $this->assertEquals([
            'foo' => 'bar',
            'another' => null,
            'bar' => 125,
        ], Post::with('meta')->first()->withMetaAt('2022-10-01 15:00:00')->only($keys));

        $this->assertEquals([
            'foo' => 'updated',
            'another' => true,
            'bar' => 125,
        ], Post::with('meta')->first()->withMetaAt('2022-10-04 12:15:00')->only($keys));

        $post = Post::first()->withMetaAt('2022-10-02 15:00:00');

        $this->assertEquals([
            'foo' => 'updated',
            'another' => null,
            'bar' => 125,
        ], $post->only($keys));

        $this->assertEquals([
            'foo' => 'updated',
            'another' => true,
            'bar' => 125,
        ], $post->withMetaAt('2022-10-04 12:15:00')->only($keys));

        $this->assertEquals([
            'foo' => null,
            'another' => null,
            'bar' => null,
        ], $post->withMetaAt('2022-08-05 12:15:00')->only($keys));

        $this->assertEquals([
            'foo' => 'updated',
            'another' => true,
            'bar' => 999.125,
        ], $post->withCurrentMeta()->only($keys));
    }

    /** @test */
    public function it_can_travel_to_the_future()
    {
        $model = Post::factory()->create();
        $keys = ['foo', 'another', 'bar'];

        $model->saveMetaAt([
            'foo' => 'updated',
            'bar' => 999.125,
            'another' => true,
        ], '+1 year');

        $this->assertEquals([
            'foo' => null,
            'another' => null,
            'bar' => null,
        ], Post::first()->only($keys));

        $this->assertEquals([
            'foo' => 'updated',
            'another' => true,
            'bar' => 999.125,
        ], Post::first()->withMetaAt('+1 year')->only($keys));
    }

    /** @test */
    public function it_can_create_meta_along_with_the_model()
    {
        Post::factory()->create([
            'title' => 'Post title',
            'foo' => 123,
            'bar' => 'works',
        ]);

        $this->assertDatabaseCount('meta', 2);

        $this->assertSame('Post title', Post::first()->title);
        $this->assertSame(123, Post::first()->foo);
        $this->assertSame('works', Post::first()->bar);
    }

    /** @test */
    public function it_can_fill_meta_with_attributes()
    {
        $post = Post::factory()->create();

        $post->mergeFillable(['title', 'foo', 'bar']);

        $post->fill([
            'title' => 'New title',
            'foo' => true,
            'bar' => 'also true',
        ]);

        $this->assertDatabaseCount('meta', 0);

        $post->save();

        $this->assertDatabaseCount('meta', 2);

        $this->assertSame('New title', $post->title);
        $this->assertSame(true, $post->foo);
        $this->assertSame('also true', $post->getMeta('bar'));
    }

    /** @test */
    public function it_will_delete_meta_with_the_model()
    {
        Post::factory()->create([
            'title' => 'Post title',
            'foo' => 123,
            'bar' => 'works',
        ]);

        $this->assertDatabaseCount('meta', 2);
        $this->assertTrue(Post::first()->forceDelete());
        $this->assertDatabaseCount('meta', 0);
    }

    /** @test */
    public function it_will_not_delete_meta_for_soft_deleted_model()
    {
        Post::factory()->create([
            'title' => 'Post title',
            'foo' => 123,
            'bar' => 'works',
        ]);

        $this->assertDatabaseCount('meta', 2);
        $this->assertTrue(Post::first()->delete());
        $this->assertDatabaseCount('meta', 2);
    }

    /** @test */
    public function it_will_delete_meta_model_without_soft_delete()
    {
        PostWithoutSoftDelete::factory()->create([
            'title' => 'Post title',
            'foo' => 123,
            'bar' => 'works',
        ]);

        $this->assertDatabaseCount('meta', 2);
        $this->assertTrue(PostWithoutSoftDelete::first()->delete());
        $this->assertDatabaseCount('meta', 0);
    }

    /** @test */
    public function it_loads_meta_relation()
    {
        $post = Post::factory()->create();

        $post->saveMetaAt('foo', false, '-1 day');
        $post->saveMeta('foo', true);
        $post->saveMetaAt('bar', false, '-1 day');
        $post->saveMetaAt('future', false, '+1 day');

        $this->assertDatabaseCount('meta', 4);
        $this->assertCount(2, Post::first()->meta);
    }

    /** @test */
    public function it_loads_published_meta_relation()
    {
        $post = Post::factory()->create();

        $post->saveMetaAt('foo', false, '-1 day');
        $post->saveMeta('foo', true);
        $post->saveMetaAt('bar', false, '-1 day');
        $post->saveMetaAt('future', false, '+1 day');

        $this->assertDatabaseCount('meta', 4);
        $this->assertCount(3, Post::first()->publishedMeta);
    }

    /** @test */
    public function it_loads_all_meta_relation()
    {
        $post = Post::factory()->create();

        $post->saveMetaAt('foo', false, '-1 day');
        $post->saveMeta('foo', true);
        $post->saveMetaAt('bar', false, '-1 day');
        $post->saveMetaAt('future', false, '+1 day');

        $this->assertDatabaseCount('meta', 4);
        $this->assertCount(4, Post::first()->allMeta);
    }

    /** @test */
    public function it_will_prefer_relations_over_meta()
    {
        $post = Post::factory()->create();

        $post->saveMeta('meta', 'Meta Value');
        $post->saveMeta('other', 'Other Value');

        $this->assertDatabaseCount('meta', 2);

        $this->assertSame('Meta Value', $post->getMeta('meta'));
        $this->assertNotEquals('Meta Value', $post->meta);
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $post->meta);
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $post->getAttribute('meta'));

        $this->assertSame('Other Value', $post->getMeta('other'));
        $this->assertSame('Other Value', $post->other);
        $this->assertIsString($post->other);
        $this->assertIsString($post->getAttribute('other'));
    }

    /** @test */
    public function it_refreshes_relations_after_save()
    {
        $post = Post::factory()->create();

        $post->saveMeta([
            'foo' => 'bar',
            'bar' => true,
        ]);

        $post->saveMetaAt('foo', 'old', '-1 day');
        $post->saveMetaAt('bar', false, '+1 day');

        $post = Post::first();

        $this->assertCount(2, $post->meta);
        $this->assertCount(3, $post->publishedMeta);
        $this->assertCount(4, $post->allMeta);

        $post->foo = 'changed';
        $post->saveMeta();

        $this->assertCount(2, $post->meta);
        $this->assertCount(4, $post->publishedMeta);
        $this->assertCount(5, $post->allMeta);
    }

    /** @test */
    public function it_will_not_store_clean_meta()
    {
        $model = Post::factory()->create();

        $this->assertInstanceOf(Meta::class, $model->saveMeta('foo', 'bar'));
        $this->assertFalse($model->saveMeta('foo', 'bar'));
        $this->assertFalse($model->saveMeta('foo', 'bar'));
        $this->assertInstanceOf(Meta::class, $model->saveMeta('foo', 'changed'));

        $this->assertDatabaseCount('meta', 2);
    }

    /** @test */
    public function it_can_assign_meta_when_creating_by_array()
    {
        $this->assertDatabaseCount('meta', 0);

        Post::unguard();

        Post::create([
            'foo' => 'bar',
            'title' => 'Title',
        ]);

        $this->assertDatabaseCount('meta', 1);
        $this->assertDatabaseHas('sample_posts', ['title' => 'Title']);

        $this->assertSame('Title', Post::first()->title);
        $this->assertSame('bar', Post::first()->foo);
    }

    /** @test */
    public function it_can_get_meta_when_selecting_with_id()
    {
        Post::factory(2)->has(Meta::factory()->state(['key' => 'foo']))->create();

        $this->assertDatabaseCount('sample_posts', 2);

        $this->assertEquals(2, Post::select('id', 'title')->get()->filter(function ($model) {
            return $model->foo;
        })->count());
    }

    /** @test */
    public function it_cannot_get_meta_when_selecting_without_id()
    {
        Post::factory(2)->has(Meta::factory()->state(['key' => 'foo']))->create();

        $this->assertDatabaseCount('sample_posts', 2);

        $this->assertEquals(0, Post::select('title')->get()->filter(function ($model) {
            return $model->foo;
        })->count());
    }

    /** @test */
    public function it_can_pluck_meta_values()
    {
        $a = Post::factory()->create();
        $b = PostWithExistingColumn::factory()->create();

        $a->metaKeys([
            'foo',
            'bar',
        ]);

        $a->saveMeta('foo', 'bar');

        $b->title = 'Title';
        $b->another = 123;
        $b->save();

        $this->assertEquals([
            'appendable_foo' => null,
            'foo' => 'bar',
            'bar' => null,
        ], $a->pluckMeta()->toArray());

        $this->assertEquals([
            'title' => 'Title',
            'body' => null,
            'another' => 123,
            'boolean_field' => null,
            'float_field' => null,
            'integer_field' => null,
        ], $b->pluckMeta()->toArray());
    }
}
