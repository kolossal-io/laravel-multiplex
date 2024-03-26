<?php

namespace Kolossal\Multiplex\Tests;

use PHPUnit\Framework\Attributes\Test;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Kolossal\Multiplex\Exceptions\MetaException;
use Kolossal\Multiplex\Tests\Mocks\Post;
use Kolossal\Multiplex\Tests\Mocks\PostWithExistingColumn;
use Kolossal\Multiplex\Tests\Traits\AccessesProtectedProperties;

class ExistingColumnOverrideTest extends TestCase
{
    use AccessesProtectedProperties;
    use RefreshDatabase;

    #[Test]
    public function it_will_throw_for_key_equal_to_existing_column_name(): void
    {
        $this->assertDatabaseCount('meta', 0);

        $model = Post::factory()->create();

        $this->expectException(MetaException::class);
        $this->expectExceptionMessage('Meta key `title` seems to be a model attribute. You must explicitly allow this attribute via `$metaKeys`.');

        $model->setMeta('title', 'bar');
    }

    #[Test]
    public function it_will_set_existing_columns_as_expected(): void
    {
        $this->assertDatabaseCount('meta', 0);

        $model = Post::factory()->create();

        $model->title = 'New title';
        $model->foo = 'bar';

        $model->save();

        $this->assertSame('New title', $model->refresh()->title);
        $this->assertDatabaseCount('meta', 1);
    }

    #[Test]
    public function it_will_allow_existing_column_to_be_allowed_explicitely(): void
    {
        $this->assertDatabaseCount('meta', 0);

        $model = Post::factory()->create(['title' => 'Initial title']);

        $model->metaKeys([
            'title',
            'foo',
        ]);

        $this->assertSame('Initial title', $model->title);

        $model->setMeta('title', 'Changed title');
        $model->setMeta('foo', 'bar');

        $model->save();

        $this->assertDatabaseCount('meta', 2);
        $this->assertDatabaseHas('sample_posts', ['title' => 'Initial title']);
        $this->assertSame('Changed title', $model->getMeta('title'));
    }

    #[Test]
    public function it_will_prefer_meta_over_existing_column_if_defined_explicitly(): void
    {
        $model = Post::factory()->create(['title' => 'Initial title']);

        $model->metaKeys([
            'title',
            'foo',
        ]);

        $this->assertSame('Initial title', $model->title);

        $model->setMeta('title', 'Changed title');

        $model->save();

        $this->assertDatabaseHas('sample_posts', ['title' => 'Initial title']);
        $this->assertSame('Changed title', $model->getMeta('title'));
        $this->assertSame('Changed title', $model->title);

        $model->saveMeta('title', 'Changed again');

        $this->assertSame('Changed again', $model->title);
    }

    #[Test]
    public function it_will_fallback_to_existing_column_for_explicitly_defined_meta_keys(): void
    {
        $model = with(
            DB::table('sample_posts')->insertGetId(['title' => 'Initial title']),
            fn ($id) => PostWithExistingColumn::findOrFail($id)
        );

        $this->assertDatabaseCount('meta', 0);
        $this->assertDatabaseHas('sample_posts', ['title' => 'Initial title']);

        $this->assertSame('Initial title', $model->title);

        $model->saveMeta('title', 'Changed title');
        $this->assertSame('Changed title', $model->title);

        $model->saveMeta('title', null);
        $this->assertNull($model->title);

        $model->deleteMeta('title');
        $this->assertSame('Initial title', $model->title);
    }

    #[Test]
    public function it_will_fallback_to_existing_column_for_dynamically_defined_meta_keys(): void
    {
        $model = Post::factory()->create(['title' => 'Initial title']);

        $model->metaKeys([
            'title',
            'foo',
        ]);

        $this->assertDatabaseCount('meta', 0);
        $this->assertDatabaseHas('sample_posts', ['title' => 'Initial title']);

        $this->assertSame('Initial title', $model->title);

        $model->saveMeta('title', 'Changed title');
        $this->assertSame('Changed title', $model->title);

        $model->saveMeta('title', null);
        $this->assertNull($model->title);

        $model->deleteMeta('title');
        $this->assertSame('Initial title', $model->title);
    }

    #[Test]
    public function it_will_fallback_to_existing_column_for_unpublished_meta(): void
    {
        $model = with(
            DB::table('sample_posts')->insertGetId(['title' => 'Initial title']),
            fn ($id) => PostWithExistingColumn::findOrFail($id)
        );

        $this->assertDatabaseCount('meta', 0);
        $this->assertDatabaseHas('sample_posts', ['title' => 'Initial title']);

        $this->assertSame('Initial title', $model->title);

        $model->saveMetaAt('title', 'Changed title', '+1 hour');
        $this->assertSame('Initial title', $model->refresh()->title);

        $this->travelTo('+1 hour');
        $this->assertSame('Changed title', $model->refresh()->title);
    }

    #[Test]
    public function it_will_fallback_to_null_for_unpublished_meta(): void
    {
        $model = with(
            DB::table('sample_posts')->insertGetId(['title' => null]),
            fn ($id) => PostWithExistingColumn::findOrFail($id)
        );

        $this->assertNull($model->title);

        $model->saveMetaAt('title', 'Changed title', '+1 hour');
        $this->assertNull($model->title);

        $this->travelTo('+1 hour');
        $this->assertSame('Changed title', $model->refresh()->title);
    }

    #[Test]
    public function it_will_not_touch_database_for_explicitly_defined_keys(): void
    {
        $a = with(
            DB::table('sample_posts')->insertGetId(['title' => 'Initial title']),
            fn ($id) => PostWithExistingColumn::findOrFail($id)
        );

        $b = with(
            DB::table('sample_posts')->insertGetId(['title' => null]),
            fn ($id) => PostWithExistingColumn::findOrFail($id)
        );

        $c = PostWithExistingColumn::make()->fillable(['title']);
        $c->fill(['title' => 'Cee title'])->save();

        $this->assertDatabaseCount('sample_posts', 3);
        $this->assertDatabaseCount('meta', 1);

        $this->assertDatabaseHas('sample_posts', ['id' => 1, 'title' => 'Initial title']);
        $this->assertDatabaseHas('sample_posts', ['id' => 2, 'title' => null]);
        $this->assertDatabaseHas('sample_posts', ['id' => 3, 'title' => null]);

        $this->assertSame('Initial title', $a->title);
        $this->assertNull($b->title);
        $this->assertSame('Cee title', $c->title);

        $a->title = 'New title';
        $a->save();

        $b->saveMeta('title', 'A title');

        $c->deleteMeta('title');

        $this->assertDatabaseHas('sample_posts', ['id' => 1, 'title' => 'Initial title']);
        $this->assertDatabaseHas('sample_posts', ['id' => 2, 'title' => null]);
        $this->assertDatabaseHas('sample_posts', ['id' => 3, 'title' => null]);

        $this->assertSame('New title', $a->title);
        $this->assertSame('A title', $b->title);
        $this->assertSame(null, $c->title);
    }

    #[Test]
    public function it_will_remove_database_attributes_equals_to_explicit_keys_when_retrieving(): void
    {
        $modelA = with(
            DB::table('sample_posts')->insertGetId(['title' => 'Title A']),
            fn ($id) => PostWithExistingColumn::findOrFail($id)
        );

        $modelB = with(
            DB::table('sample_posts')->insertGetId(['title' => 'Title B']),
            fn ($id) => Post::findOrFail($id)
        );

        $this->assertSame('Title A', $modelA->getOriginal('title'));
        $this->assertArrayNotHasKey('title', $this->getProtectedProperty($modelA, 'attributes'));
        $this->assertFalse($modelA->isDirty('title'));
        $this->assertFalse($modelA->isDirty());

        $this->assertSame('Title B', $modelB->getOriginal('title'));
        $this->assertArrayHasKey('title', $this->getProtectedProperty($modelB, 'attributes'));
        $this->assertSame('Title B', $this->getProtectedProperty($modelB, 'attributes')['title']);
        $this->assertFalse($modelB->isDirty('title'));
        $this->assertFalse($modelB->isDirty());

        $modelA->title = 'Another Title A';
        $modelB->title = 'Another Title B';

        $this->assertFalse($modelA->isDirty('title'));
        $this->assertTrue($modelA->isMetaDirty('title'));

        $this->assertTrue($modelB->isDirty('title'));
        $this->assertFalse($modelB->isMetaDirty('title'));

        $this->assertSame('Title A', $modelA->title);
        $this->assertSame('Another Title B', $modelB->title);
    }

    #[Test]
    public function it_will_not_change_meta_when_using_update_method(): void
    {
        DB::table('sample_posts')->insertGetId(['title' => 'Title']);

        $this->assertDatabaseCount('sample_posts', 1);
        $this->assertDatabaseCount('meta', 0);

        PostWithExistingColumn::query()->update([
            'title' => 'Updated Title',
        ]);

        $this->assertDatabaseCount('sample_posts', 1);
        $this->assertDatabaseCount('meta', 0);

        $this->assertSame('Updated Title', PostWithExistingColumn::first()->title);
    }

    #[Test]
    public function it_will_include_meta_value_in_collection_if_overriding_column(): void
    {
        Collection::times(10, function ($num) {
            DB::table('sample_posts')->insertGetId(['title' => "Title {$num}"]);
        });

        $this->assertDatabaseCount('sample_posts', 10);
        $this->assertDatabaseCount('meta', 0);

        PostWithExistingColumn::get()->each(function ($model) {
            $model->title = 'Meta ' . $model->title;
            $model->save();
        });

        $this->assertDatabaseCount('sample_posts', 10);
        $this->assertDatabaseCount('meta', 10);

        PostWithExistingColumn::orderBy('id')->get()->each(function ($model, $i) {
            $num = $i + 1;
            $this->assertArrayNotHasKey('title', $this->getProtectedProperty($model, 'attributes'));
            $this->assertSame("Meta Title {$num}", $model->title);
        });

        DB::table('meta')->delete();

        $this->assertDatabaseCount('sample_posts', 10);
        $this->assertDatabaseCount('meta', 0);

        PostWithExistingColumn::orderBy('id')->get()->each(function ($model, $i) {
            $num = $i + 1;
            $this->assertArrayNotHasKey('title', $this->getProtectedProperty($model, 'attributes'));
            $this->assertSame("Title {$num}", $model->title);
        });
    }

    #[Test]
    public function it_will_include_column_values_in_collection_if_not_overriding_column(): void
    {
        Collection::times(10, function ($num) {
            DB::table('sample_posts')->insertGetId(['title' => "Title {$num}"]);
        });

        $this->assertDatabaseCount('sample_posts', 10);
        $this->assertDatabaseCount('meta', 0);

        Post::get()->each(function ($model) {
            $model->title = 'Meta ' . $model->title;
            $model->save();
        });

        $this->assertDatabaseCount('sample_posts', 10);
        $this->assertDatabaseCount('meta', 0);

        Post::orderBy('id')->get()->each(function ($model, $i) {
            $num = $i + 1;
            $this->assertArrayHasKey('title', $this->getProtectedProperty($model, 'attributes'));
            $this->assertSame("Meta Title {$num}", $model->title);
        });
    }

    #[Test]
    public function it_can_append_overwriting_meta_values_in_array(): void
    {
        DB::table('sample_posts')->insertGetId(['title' => 'Title']);

        $this->assertDatabaseCount('meta', 0);

        $this->assertSame('Title', PostWithExistingColumn::first()->toArray()['title']);

        $model = PostWithExistingColumn::first();
        $model->title = 'Meta Title';
        $model->save();

        $this->assertDatabaseCount('meta', 1);

        $this->assertSame('Meta Title', PostWithExistingColumn::first()->toArray()['title']);

        DB::table('meta')->delete();

        $this->assertSame('Title', PostWithExistingColumn::first()->toArray()['title']);
    }

    #[Test]
    public function it_can_append_overwriting_meta_values_in_collection_array(): void
    {
        Collection::times(10, function ($num) {
            DB::table('sample_posts')->insertGetId(['title' => "Title {$num}"]);
        });

        $this->assertDatabaseCount('meta', 0);

        PostWithExistingColumn::orderBy('id')->get()->each(function ($model, $i) {
            $num = $i + 1;
            $this->assertSame("Title {$num}", $model->toArray()['title']);

            $model->title = "Meta Title {$num}";
            $model->save();
        });

        $this->assertDatabaseCount('meta', 10);

        PostWithExistingColumn::orderBy('id')->get()->each(function ($model, $i) {
            $num = $i + 1;
            $this->assertSame("Meta Title {$num}", $model->toArray()['title']);
        });
    }

    #[Test]
    public function it_can_assign_meta_when_creating_by_array(): void
    {
        $this->assertDatabaseCount('meta', 0);

        PostWithExistingColumn::unguard();

        PostWithExistingColumn::create([
            'foo' => 'bar',
            'title' => 'Title',
        ]);

        $this->assertDatabaseCount('meta', 2);
        $this->assertDatabaseHas('sample_posts', ['title' => null]);

        $this->assertSame('Title', PostWithExistingColumn::first()->title);
        $this->assertSame('bar', PostWithExistingColumn::first()->foo);
    }

    #[Test]
    public function it_will_cast_fallback_fields_as_expected(): void
    {
        $this->assertDatabaseCount('meta', 0);

        DB::table('sample_posts')->insert([
            'title' => 'Title',
            'boolean_field' => 1,
            'float_field' => 120,
            'integer_field' => '120',
        ]);

        $this->assertDatabaseCount('meta', 0);

        $post = PostWithExistingColumn::first();

        $this->assertSame(true, $post->boolean_field);
        $this->assertSame(120.0, $post->float_field);
        $this->assertSame(120, $post->integer_field);

        $post->boolean_field = 0;
        $post->float_field = '123';
        $post->integer_field = 125.3;

        $post->save();

        $this->assertSame(false, $post->boolean_field);
        $this->assertSame(123.0, $post->float_field);
        $this->assertSame(125, $post->integer_field);
    }
}
