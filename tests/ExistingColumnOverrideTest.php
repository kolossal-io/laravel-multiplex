<?php

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Kolossal\Multiplex\Exceptions\MetaException;
use Kolossal\Multiplex\Tests\Mocks\Post;
use Kolossal\Multiplex\Tests\Mocks\PostWithExistingColumn;

uses(\Kolossal\Multiplex\Tests\Traits\AccessesProtectedProperties::class);

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('will throw for key equal to existing column name', function () {
    $this->assertDatabaseCount('meta', 0);

    $model = Post::factory()->create();

    $this->expectException(MetaException::class);
    $this->expectExceptionMessage('Meta key `title` seems to be a model attribute. You must explicitly allow this attribute via `$metaKeys`.');

    $model->setMeta('title', 'bar');
});

it('will set existing columns as expected', function () {
    $this->assertDatabaseCount('meta', 0);

    $model = Post::factory()->create();

    $model->title = 'New title';
    $model->foo = 'bar';

    $model->save();

    expect($model->refresh()->title)->toBe('New title');
    $this->assertDatabaseCount('meta', 1);
});

it('will allow existing column to be allowed explicitely', function () {
    $this->assertDatabaseCount('meta', 0);

    $model = Post::factory()->create(['title' => 'Initial title']);

    $model->metaKeys([
        'title',
        'foo',
    ]);

    expect($model->title)->toBe('Initial title');

    $model->setMeta('title', 'Changed title');
    $model->setMeta('foo', 'bar');

    $model->save();

    $this->assertDatabaseCount('meta', 2);
    $this->assertDatabaseHas('sample_posts', ['title' => 'Initial title']);
    expect($model->getMeta('title'))->toBe('Changed title');
});

it('will prefer meta over existing column if defined explicitly', function () {
    $model = Post::factory()->create(['title' => 'Initial title']);

    $model->metaKeys([
        'title',
        'foo',
    ]);

    expect($model->title)->toBe('Initial title');

    $model->setMeta('title', 'Changed title');

    $model->save();

    $this->assertDatabaseHas('sample_posts', ['title' => 'Initial title']);
    expect($model->getMeta('title'))->toBe('Changed title');
    expect($model->title)->toBe('Changed title');

    $model->saveMeta('title', 'Changed again');

    expect($model->title)->toBe('Changed again');
});

it('will fallback to existing column for explicitly defined meta keys', function () {
    $model = with(
        DB::table('sample_posts')->insertGetId(['title' => 'Initial title']),
        fn ($id) => PostWithExistingColumn::findOrFail($id)
    );

    $this->assertDatabaseCount('meta', 0);
    $this->assertDatabaseHas('sample_posts', ['title' => 'Initial title']);

    expect($model->title)->toBe('Initial title');

    $model->saveMeta('title', 'Changed title');
    expect($model->title)->toBe('Changed title');

    $model->saveMeta('title', null);
    expect($model->title)->toBeNull();

    $model->deleteMeta('title');
    expect($model->title)->toBe('Initial title');
});

it('will fallback to existing column for dynamically defined meta keys', function () {
    $model = Post::factory()->create(['title' => 'Initial title']);

    $model->metaKeys([
        'title',
        'foo',
    ]);

    $this->assertDatabaseCount('meta', 0);
    $this->assertDatabaseHas('sample_posts', ['title' => 'Initial title']);

    expect($model->title)->toBe('Initial title');

    $model->saveMeta('title', 'Changed title');
    expect($model->title)->toBe('Changed title');

    $model->saveMeta('title', null);
    expect($model->title)->toBeNull();

    $model->deleteMeta('title');
    expect($model->title)->toBe('Initial title');
});

it('will fallback to existing column for unpublished meta', function () {
    $model = with(
        DB::table('sample_posts')->insertGetId(['title' => 'Initial title']),
        fn ($id) => PostWithExistingColumn::findOrFail($id)
    );

    $this->assertDatabaseCount('meta', 0);
    $this->assertDatabaseHas('sample_posts', ['title' => 'Initial title']);

    expect($model->title)->toBe('Initial title');

    $model->saveMetaAt('title', 'Changed title', Carbon::now()->addHour());
    expect($model->refresh()->title)->toBe('Initial title');

    $this->travelTo(Carbon::now()->addHour());
    expect($model->refresh()->title)->toBe('Changed title');
});

it('will fallback to null for unpublished meta', function () {
    $model = with(
        DB::table('sample_posts')->insertGetId(['title' => null]),
        fn ($id) => PostWithExistingColumn::findOrFail($id)
    );

    expect($model->title)->toBeNull();

    $model->saveMetaAt('title', 'Changed title', Carbon::now()->addHour());
    expect($model->title)->toBeNull();

    $this->travelTo(Carbon::now()->addHour());
    expect($model->refresh()->title)->toBe('Changed title');
});

it('will not touch database for explicitly defined keys', function () {
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

    expect($a->title)->toBe('Initial title');
    expect($b->title)->toBeNull();
    expect($c->title)->toBe('Cee title');

    $a->title = 'New title';
    $a->save();

    $b->saveMeta('title', 'A title');

    $c->deleteMeta('title');

    $this->assertDatabaseHas('sample_posts', ['id' => 1, 'title' => 'Initial title']);
    $this->assertDatabaseHas('sample_posts', ['id' => 2, 'title' => null]);
    $this->assertDatabaseHas('sample_posts', ['id' => 3, 'title' => null]);

    expect($a->title)->toBe('New title');
    expect($b->title)->toBe('A title');
    expect($c->title)->toBe(null);
});

it('will remove database attributes equals to explicit keys when retrieving', function () {
    $modelA = with(
        DB::table('sample_posts')->insertGetId(['title' => 'Title A']),
        fn ($id) => PostWithExistingColumn::findOrFail($id)
    );

    $modelB = with(
        DB::table('sample_posts')->insertGetId(['title' => 'Title B']),
        fn ($id) => Post::findOrFail($id)
    );

    expect($modelA->getOriginal('title'))->toBe('Title A');
    $this->assertArrayNotHasKey('title', $this->getProtectedProperty($modelA, 'attributes'));
    expect($modelA->isDirty('title'))->toBeFalse();
    expect($modelA->isDirty())->toBeFalse();

    expect($modelB->getOriginal('title'))->toBe('Title B');
    expect($this->getProtectedProperty($modelB, 'attributes'))->toHaveKey('title');
    expect($this->getProtectedProperty($modelB, 'attributes')['title'])->toBe('Title B');
    expect($modelB->isDirty('title'))->toBeFalse();
    expect($modelB->isDirty())->toBeFalse();

    $modelA->title = 'Another Title A';
    $modelB->title = 'Another Title B';

    expect($modelA->isDirty('title'))->toBeFalse();
    expect($modelA->isMetaDirty('title'))->toBeTrue();

    expect($modelB->isDirty('title'))->toBeTrue();
    expect($modelB->isMetaDirty('title'))->toBeFalse();

    expect($modelA->title)->toBe('Title A');
    expect($modelB->title)->toBe('Another Title B');
});

it('will not change meta when using update method', function () {
    DB::table('sample_posts')->insertGetId(['title' => 'Title']);

    $this->assertDatabaseCount('sample_posts', 1);
    $this->assertDatabaseCount('meta', 0);

    PostWithExistingColumn::query()->update([
        'title' => 'Updated Title',
    ]);

    $this->assertDatabaseCount('sample_posts', 1);
    $this->assertDatabaseCount('meta', 0);

    expect(PostWithExistingColumn::first()->title)->toBe('Updated Title');
});

it('will include meta value in collection if overriding column', function () {
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
        expect($model->title)->toBe("Meta Title {$num}");
    });

    DB::table('meta')->delete();

    $this->assertDatabaseCount('sample_posts', 10);
    $this->assertDatabaseCount('meta', 0);

    PostWithExistingColumn::orderBy('id')->get()->each(function ($model, $i) {
        $num = $i + 1;
        $this->assertArrayNotHasKey('title', $this->getProtectedProperty($model, 'attributes'));
        expect($model->title)->toBe("Title {$num}");
    });
});

it('will include column values in collection if not overriding column', function () {
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
        expect($this->getProtectedProperty($model, 'attributes'))->toHaveKey('title');
        expect($model->title)->toBe("Meta Title {$num}");
    });
});

it('can append overwriting meta values in array', function () {
    DB::table('sample_posts')->insertGetId(['title' => 'Title']);

    $this->assertDatabaseCount('meta', 0);

    expect(PostWithExistingColumn::first()->toArray()['title'])->toBe('Title');

    $model = PostWithExistingColumn::first();
    $model->title = 'Meta Title';
    $model->save();

    $this->assertDatabaseCount('meta', 1);

    expect(PostWithExistingColumn::first()->toArray()['title'])->toBe('Meta Title');

    DB::table('meta')->delete();

    expect(PostWithExistingColumn::first()->toArray()['title'])->toBe('Title');
});

it('can append overwriting meta values in collection array', function () {
    Collection::times(10, function ($num) {
        DB::table('sample_posts')->insertGetId(['title' => "Title {$num}"]);
    });

    $this->assertDatabaseCount('meta', 0);

    PostWithExistingColumn::orderBy('id')->get()->each(function ($model, $i) {
        $num = $i + 1;
        expect($model->toArray()['title'])->toBe("Title {$num}");

        $model->title = "Meta Title {$num}";
        $model->save();
    });

    $this->assertDatabaseCount('meta', 10);

    PostWithExistingColumn::orderBy('id')->get()->each(function ($model, $i) {
        $num = $i + 1;
        expect($model->toArray()['title'])->toBe("Meta Title {$num}");
    });
});

it('can assign meta when creating by array', function () {
    $this->assertDatabaseCount('meta', 0);

    PostWithExistingColumn::unguard();

    PostWithExistingColumn::create([
        'foo' => 'bar',
        'title' => 'Title',
    ]);

    $this->assertDatabaseCount('meta', 2);
    $this->assertDatabaseHas('sample_posts', ['title' => null]);

    expect(PostWithExistingColumn::first()->title)->toBe('Title');
    expect(PostWithExistingColumn::first()->foo)->toBe('bar');
});

it('will cast fallback fields as expected', function () {
    $this->assertDatabaseCount('meta', 0);

    DB::table('sample_posts')->insert([
        'title' => 'Title',
        'boolean_field' => 1,
        'float_field' => 120,
        'integer_field' => '120',
    ]);

    $this->assertDatabaseCount('meta', 0);

    $post = PostWithExistingColumn::first();

    expect($post->boolean_field)->toBe(true);
    expect($post->float_field)->toBe(120.0);
    expect($post->integer_field)->toBe(120);

    $post->boolean_field = 0;
    $post->float_field = '123';
    $post->integer_field = 125.3;

    $post->save();

    expect($post->boolean_field)->toBe(false);
    expect($post->float_field)->toBe(123.0);
    expect($post->integer_field)->toBe(125);
});
