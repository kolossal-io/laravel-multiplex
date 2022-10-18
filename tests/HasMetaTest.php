<?php

namespace Kolossal\Meta\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Kolossal\Meta\Exceptions\MetaException;
use Kolossal\Meta\Meta;
use Kolossal\Meta\Tests\Mocks\Post;
use PDOException;

class HasMetaTest extends TestCase
{
    use RefreshDatabase;

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
    public function it_will_throw_for_keys_equal_to_real_column_names()
    {
        $this->assertDatabaseCount('meta', 0);

        $model = Post::factory()->create();

        $this->expectException(MetaException::class);
        $this->expectExceptionMessage('Meta key `title` seems to be a model attribute. Make sure there is no mutator or `getAttribute` method for the key.');

        $model->setMeta('title', 'bar');
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
    public function it_will_set_real_columns_as_expected()
    {
        $this->assertDatabaseCount('meta', 0);

        $model = Post::factory()->create();

        $model->title = 'New title';
        $model->foo = 'bar';

        $model->save();

        $this->assertSame('New title', $model->refresh()->title);
        $this->assertDatabaseCount('meta', 1);
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
    public function it_will_throw_for_unallowed_keys()
    {
        $this->expectException(MetaException::class);
        $this->expectExceptionMessage('Meta key `bar` is not a valid key.');

        $model = Post::factory()->create();

        $model->metaKeys([
            'foo',
        ]);

        $model->setMeta('foo', 'bar');
        $model->setMeta('bar', 125);

        $model->save();
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

        $model->resetMeta();
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

        $model->foo = 'bar';
        $model->bar = 123.0;
        $model->save();

        $this->assertDatabaseCount('meta', 3);

        $this->assertSame('bar', $model->getMeta('foo'));
        $this->assertSame(123.0, $model->getMeta('bar'));
        $this->assertTrue($model->meta->pluck('updated_at', 'key')->get('foo')->isSameDay('2022-10-01'));
        $this->assertTrue($model->meta->pluck('updated_at', 'key')->get('bar')->isSameDay('2022-10-02'));
    }
}
