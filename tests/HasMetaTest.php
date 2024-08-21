<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Kolossal\Multiplex\Exceptions\MetaException;
use Kolossal\Multiplex\Meta;
use Kolossal\Multiplex\MetaAttribute;
use Kolossal\Multiplex\Tests\Mocks\Dummy;
use Kolossal\Multiplex\Tests\Mocks\Post;
use Kolossal\Multiplex\Tests\Mocks\PostWithAccessor;
use Kolossal\Multiplex\Tests\Mocks\PostWithExistingColumn;
use Kolossal\Multiplex\Tests\Mocks\PostWithoutSoftDelete;
use Kolossal\Multiplex\Tests\Mocks\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Post::travelBack();
});

it('can set any keys as meta by default', function () {
    $this->assertDatabaseCount('meta', 0);

    $model = Post::factory()->create();

    $model->setMeta('foo', 'bar');
    $model->setMeta('another', 125.12);
    $model->save();

    $this->assertDatabaseCount('meta', 2);
});

it('can set meta fluently', function () {
    $this->assertDatabaseCount('meta', 0);

    $model = Post::factory()->create();

    $model->foo = 'bar';
    $model->another = 125.12;
    $model->save();

    $this->assertDatabaseCount('meta', 2);
});

it('will save meta when model is saved', function () {
    $model = Post::factory()->create();

    $model->title = 'Post title 2';
    $model->setMeta('foo', 'bar');
    $model->bar = 125;

    $this->assertDatabaseCount('meta', 0);

    $model->save();

    $this->assertDatabaseHas('sample_posts', ['title' => 'Post title 2']);
    $this->assertDatabaseCount('meta', 2);
});

it('can save model without meta', function () {
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
});

it('can disable meta autosave', function () {
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
});

it('will handle allowed keys', function () {
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
});

it('will handle allowed keys in arrays', function () {
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
});

it('will use meta keys from property', function () {
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

    expect($model->metaKeys())->toEqual(['foo', 'bar']);
    expect($model->getExplicitlyAllowedMetaKeys())->toEqual(['title', 'foo', 'bar']);
});

it('will use meta keys from method', function () {
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

    expect($model->metaKeys())->toEqual(['bar']);
    expect($model->getExplicitlyAllowedMetaKeys())->toEqual(['title', 'bar']);
});

it('will use default meta keys as fallback', function () {
    $model = Post::factory()->create();
    expect($model->metaKeys())->toEqual(['*']);
    expect($model->getExplicitlyAllowedMetaKeys())->toEqual(['appendable_foo']);
});

it('will throw for unallowed keys', function () {
    $model = Post::factory()->create();

    $model->metaKeys([
        'foo',
    ]);

    $model->setMeta('foo', 'bar');

    $this->expectException(MetaException::class);
    $this->expectExceptionMessage('Meta key `bar` is not a valid key.');

    $model->setMeta('bar', 125);
});

it('will throw for unallowed keys in arrays', function () {
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
});

it('lets laravel handle unallowed keys assigned fluently', function () {
    $this->expectException(PDOException::class);

    $model = Post::factory()->create();

    $model->metaKeys([
        'foo',
    ]);

    $model->setMeta('foo', 'bar');
    $model->bar = 125;

    $model->save();
});

it('can unguard meta keys', function () {
    $model = Post::factory()->create();

    $model->metaKeys([
        'foo',
    ]);

    expect(Post::isMetaUnguarded())->toBeFalse();

    Post::unguardMeta();

    $model->setMeta('foo', 'bar');
    $model->bar = 125;

    $model->save();

    expect(Post::isMetaUnguarded())->toBeTrue();
    $this->assertDatabaseCount('meta', 2);

    Post::unguardMeta(false);
});

it('can reguard meta keys', function () {
    $this->expectException(MetaException::class);
    $this->expectExceptionMessage('Meta key `bar` is not a valid key.');

    $model = Post::factory()->create();

    $model->metaKeys([
        'foo',
    ]);

    Post::unguardMeta();
    expect(Post::isMetaUnguarded())->toBeTrue();

    Post::reguardMeta();

    $model->setMeta('foo', 'bar');
    $model->setMeta('bar', 125);
});

it('can contain wildcard mixed with allowed keys', function () {
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
});

it('will return meta by get accessor', function () {
    $model = Post::factory()->create();

    $model->saveMeta('foo', 'bar');

    expect(Post::first()->getMeta('foo'))->toBe('bar');
});

it('will return null if meta is not found', function () {
    $model = Post::factory()->create();

    $model->saveMeta('bar', 123);

    expect(Post::first()->getMeta('foo'))->toBeNull();
    expect(Post::first()->getMeta('bar'))->toBe(123);
});

it('can return fallback if meta is not found', function () {
    $model = Post::factory()->create();

    $model->saveMeta('bar', 123);

    expect(Post::first()->getMeta('foo', 'fallback'))->toBe('fallback');
    expect(Post::first()->getMeta('bar', 'fallback'))->toBe(123);
});

it('will show if meta exists', function () {
    $model = Post::factory()->create();

    expect($model->hasMeta('foo'))->toBeFalse();

    $model->saveMeta('foo', 'bar');

    expect($model->hasMeta('foo'))->toBeTrue();
});

it('can set meta from array', function () {
    $model = Post::factory()->create();

    $model->saveMeta([
        'foo' => 'bar',
        'bar' => 123,
    ]);

    expect($model->refresh()->foo)->toBe('bar');
    expect($model->refresh()->bar)->toBe(123);
});

it('can set meta for the future', function () {
    $model = Post::factory()->create();

    $model->setMeta('foo', 'bar');
    $model->setMetaAt('foo', 'change', '+1 hour');

    $model->save();

    expect(Post::first()->foo)->toBeNull();

    $this->travelTo(Carbon::now()->addHour());

    expect(Post::first()->foo)->toBe('change');
});

it('can set meta for the future by array', function () {
    $model = Post::factory()->create();

    $model->setMetaAt([
        'foo' => 'bar',
        'bar' => true,
    ], '+1 hour');

    $model->save();

    expect(Post::first()->foo)->toBeNull();
    expect(Post::first()->bar)->toBeNull();

    $this->travelTo(Carbon::now()->addHour());

    expect(Post::first()->foo)->toBe('bar');
    expect(Post::first()->bar)->toBe(true);
});

it('can save meta for the future', function () {
    $model = Post::factory()->create();

    $model->saveMeta('foo', 'bar');
    $model->saveMetaAt('foo', 'change', '+1 hour');

    expect($model->refresh()->foo)->toBe('bar');

    $this->travelTo(Carbon::now()->addHour());

    expect($model->refresh()->foo)->toBe('change');
});

it('can save meta with same value for different timestamps', function () {
    $this->travelTo('2020-02-01 00:00:00');

    $model = Post::factory()->create();

    expect($model->allMeta()->count())->toEqual(0);

    $model->saveMetaAt('foo', 123.29, '2020-01-01 00:00:00');
    $model->saveMetaAt('foo', 123.29, '2019-01-01 00:00:00');
    $model->saveMetaAt('foo', 123.29, '2021-01-01 00:00:00');

    expect($model->allMeta()->count())->toEqual(3);
});

it('can save multiple meta for the future', function () {
    $model = Post::factory()->create();

    $model->setMeta('foo', 'bar');
    $model->setMeta('bar', 123);

    $model->saveMetaAt('+1 hour');
    $model->refresh();

    expect($model->foo)->toBeNull();
    expect($model->bar)->toBeNull();

    $this->travelTo(Carbon::now()->addHour());
    $model->refresh();

    expect($model->foo)->toBe('bar');
    expect($model->bar)->toBe(123);
});

it('can set meta for future from array', function () {
    $model = Post::factory()->create();

    $model->saveMeta([
        'foo' => 'bar',
        'bar' => 123,
    ]);

    $model->saveMetaAt([
        'foo' => 'change',
        'bar' => false,
    ], '+1 hour');

    expect($model->refresh()->foo)->toBe('bar');
    expect($model->refresh()->bar)->toBe(123);

    $this->travelTo(Carbon::now()->addHour());

    expect($model->refresh()->foo)->toBe('change');
    expect($model->refresh()->bar)->toBe(false);
});

it('will handle future meta versions as non existent', function () {
    $model = Post::factory()->create();

    expect($model->hasMeta('foo'))->toBeFalse();

    $model->saveMetaAt('foo', 'bar', '+1 hour');

    expect($model->refresh()->hasMeta('foo'))->toBeFalse();

    $this->travelTo(Carbon::now()->addHour());

    expect($model->refresh()->hasMeta('foo'))->toBeTrue();
});

it('will return meta fluently', function () {
    $model = Post::factory()->create(['title' => 'Title']);

    $model->foo = 'bar';
    $model->save();
    $model->saveMeta('bar', 123);

    expect(Post::first()->title)->toBe('Title');
    expect(Post::first()->foo)->toBe('bar');
    expect(Post::first()->bar)->toBe(123);
});

it('will respect get meta accessors', function () {
    $model = PostWithAccessor::factory()->create(['title' => null]);

    expect($model->title)->toBeNull();

    $model->allMeta()->delete();

    expect($model->title)->toBeNull();

    $model->saveMeta('title', 'Accessor');

    expect($model->title)->toBe('Testing Accessor passed.');
});

it('will use meta accessors for fallback values', function () {
    $model = PostWithAccessor::factory()->create(['title' => null]);

    DB::table('meta')->truncate();

    expect($model->title)->toBeNull();

    DB::table('sample_posts')->where('id', $model->id)
        ->update(['title' => 'Fallback Accessor']);

    $model = PostWithAccessor::first();

    expect($model->refresh()->title)->toBe('Testing Fallback Accessor passed.');
});

it('will respect set meta mutators', function () {
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
});

it('can delete meta', function () {
    $model = Post::factory()->create();

    $model->setMeta('foo', 'bar');

    $model->save();

    $this->assertDatabaseHas('meta', ['key' => 'foo']);

    $model->deleteMeta('foo');

    $this->assertDatabaseMissing('meta', ['key' => 'foo']);
});

it('will delete all meta versions', function () {
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
});

it('can delete meta from array', function () {
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
});

it('will throw when deleting invalid keys', function () {
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
});

it('can reset meta key before save', function () {
    $model = Post::factory()->create();

    $model->setMeta('foo', 'bar');
    $model->save();

    $model->setMeta('foo', 'changed');
    $model->resetMeta('foo');
    $model->save();

    $this->assertDatabaseHas('meta', ['key' => 'foo', 'value' => 'bar']);
    $this->assertDatabaseMissing('meta', ['key' => 'foo', 'value' => 'changed']);
});

it('can reset all meta', function () {
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
});

it('can save meta directly', function () {
    Post::factory()->create()->saveMeta('foo', 'bar');

    $this->assertDatabaseHas('meta', ['key' => 'foo', 'value' => 'bar']);
});

it('can save selected meta key only', function () {
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
});

it('will save meta updates even if parent is clean', function () {
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

    expect(Post::whereDate('updated_at', '2022-10-02')->exists())->toBeTrue();
    expect(Meta::whereDate('created_at', '2022-10-02')->exists())->toBeTrue();
    expect(Meta::whereDate('created_at', '2022-10-03')->exists())->toBeTrue();
    expect(Meta::whereDate('created_at', '2022-10-04')->exists())->toBeTrue();
});

it('contains only most recent meta per key', function () {
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

    expect($model->meta)->toHaveCount(2);

    expect($model->meta->pluck('value', 'key')->sort()->toArray())->toEqual([
        'bar' => 340.987,
        'foo' => 'changed',
    ]);
});

it('contains only published meta data', function () {
    $this->travelTo('2021-10-01 12:00:00');

    $model = Post::factory()->create();
    $model->saveMeta('foo', 'bar');

    $model->saveMetaAt('foo', 'changed', '2022-10-31 11:00:00');
    $model->saveMetaAt('bar', 340.987, '2022-10-31 15:00:00');
    $model->saveMetaAt('bar', true, '2022-10-31 12:00:00');

    expect($model->allMeta)->toHaveCount(4);
    expect($model->meta)->toHaveCount(1);
    expect($model->meta->first()->value)->toBe('bar');

    $this->travelTo('2022-10-31 11:30:00');

    $model->refresh();

    expect($model->meta)->toHaveCount(1);
    expect($model->meta->first()->value)->toBe('changed');

    $this->travelTo('2022-10-31 12:00:00');

    $model->refresh();

    expect($model->meta)->toHaveCount(2);
    expect($model->meta->pluck('value', 'key')['foo'])->toBe('changed');
    expect($model->meta->pluck('value', 'key')['bar'])->toBe(true);

    $this->travelTo('2022-12-01 12:00:00');

    $model->refresh();

    expect($model->meta)->toHaveCount(2);
    expect($model->meta->pluck('value', 'key')['foo'])->toBe('changed');
    expect($model->meta->pluck('value', 'key')['bar'])->toBe(340.987);
});

it('may change datatype of meta data', function () {
    $model = Post::factory()->create();
    $model->saveMeta('foo', 'bar');

    expect($model->meta->first()->type)->toBe('string');
    expect($model->meta->first()->value)->toBe('bar');

    $model->saveMeta('foo', 123);

    expect($model->meta->first()->type)->toBe('integer');
    expect($model->meta->first()->value)->toBe(123);

    $model->saveMeta('foo', false);

    expect($model->meta->first()->type)->toBe('boolean');
    expect($model->meta->first()->value)->toBe(false);
});

it('will store dirty meta only', function () {
    $this->travelTo('2022-10-01 12:00:00');

    $model = Post::factory()->create();

    $model->saveMeta('foo', 'bar');
    $model->saveMeta('bar', 123);

    $this->assertDatabaseCount('meta', 2);

    expect($model->getMeta('foo'))->toBe('bar');
    expect($model->getMeta('bar'))->toBe(123);
    expect($model->meta->pluck('updated_at', 'key')->get('foo')->isSameDay('2022-10-01'))->toBeTrue();
    expect($model->meta->pluck('updated_at', 'key')->get('bar')->isSameDay('2022-10-01'))->toBeTrue();

    $this->travelTo('2022-10-02 12:00:00');

    $model->saveMeta('foo', 'bar');
    $model->saveMeta('bar', 123);

    $this->assertDatabaseCount('meta', 2);

    $model->foo = 'bar';
    $model->bar = 123.0;
    $model->save();

    $this->assertDatabaseCount('meta', 3);

    expect($model->getMeta('foo'))->toBe('bar');
    expect($model->getMeta('bar'))->toBe(123.0);
    expect($model->meta->pluck('updated_at', 'key')->get('foo')->isSameDay('2022-10-01'))->toBeTrue();
    expect($model->meta->pluck('updated_at', 'key')->get('bar')->isSameDay('2022-10-02'))->toBeTrue();
});

it('will show if meta is dirty', function () {
    $model = Post::factory()->create();

    expect($model->isMetaDirty())->toBeFalse();
    expect($model->getDirtyMeta())->toHaveCount(0);

    $model->foo = 'bar';

    expect($model->isMetaDirty())->toBeTrue();
    expect($model->getDirtyMeta())->toHaveCount(1);

    $model->setMeta('foo', 'changed');
    $model->bar = 12;

    expect($model->isMetaDirty())->toBeTrue();
    expect($model->getDirtyMeta())->toHaveCount(2);

    $model->save();

    expect($model->isMetaDirty())->toBeFalse();
    expect($model->getDirtyMeta())->toHaveCount(0);
});

it('can add casted meta fields to models visible fields', function () {
    $model = Post::factory()->create(['title' => 'Title']);

    $array = Post::first()->append('appendable_foo')->toArray();

    expect($array)->toHaveKey('title');
    expect($array)->toHaveKey('appendable_foo');
    expect($array['appendable_foo'])->toBeNull();

    $model->saveMeta('appendable_foo', 'this works.');

    expect(Post::first()->append('appendable_foo')->toArray()['appendable_foo'])->toBe('this works.');
});

it('will return null for casted meta field without trait', function () {
    $model = new Dummy;

    expect($model->append('appendable_foo')->toArray()['appendable_foo'])->toBeNull();
});

it('can set casted fields not in whitelist', function () {
    $model = Post::factory()->create(['title' => 'Title']);

    $model->metaKeys(['foo']);

    $model->saveMeta('foo', 'bar');
    $model->saveMeta('appendable_foo', 'this works.');

    expect(Post::first()->appendable_foo)->toBe('this works.');

    $model = Post::first();

    $model->appendable_foo = 'this also works.';
    $model->save();

    expect(Post::first()->foo)->toBe('bar');
    expect(Post::first()->appendable_foo)->toBe('this also works.');
    expect(Post::first()->append('appendable_foo')->toArray()['appendable_foo'])->toBe('this also works.');
});

it('will return correct datatype for casted meta attributes', function () {
    $model = Post::factory()->create(['title' => 'Title']);

    $model->metaKeys(['foo']);

    $model->saveMeta('appendable_foo', 123);
    expect(Post::first()->appendable_foo)->toBe(123);

    $model->saveMeta('appendable_foo', false);
    expect(Post::first()->appendable_foo)->toBe(false);

    $model->saveMeta('appendable_foo', 150.024);
    expect(Post::first()->appendable_foo)->toBe(150.024);

    $model->saveMeta('appendable_foo', null);
    expect(Post::first()->appendable_foo)->toBeNull();
});

it('will return null for undefined casted meta field', function () {
    $model = Post::factory()->create(['title' => 'Title']);

    $model->metaKeys(['foo']);

    expect(Post::first()->appendable_foo)->toBeNull();
});

it('will return column value for casted meta fields having equally named column', function () {
    $model = Post::factory()->create(['title' => 'Title']);

    $model->metaKeys(['foo']);

    Schema::table('sample_posts', fn ($table) => $table->string('appendable_foo')->nullable());
    DB::table('sample_posts')->update(['appendable_foo' => 'Fallback']);

    expect(Post::first()->appendable_foo)->toBe('Fallback');

    $model->saveMetaAt('appendable_foo', 8000.99, Carbon::now()->addDay());

    $this->travelTo(Carbon::now()->addHours(23)->addMinutes(50));

    expect(Post::first()->appendable_foo)->toBe('Fallback');

    $this->travelTo(Carbon::now()->addMinutes(10));

    expect(Post::first()->appendable_foo)->toBe(8000.99);
});

it('can save multiple meta for a given date', function () {
    $model = Post::factory()->create();

    $model->setMeta('foo', 'bar');
    $model->bar = 125;

    expect($model->saveMetaAt('+1 day'))->toBeTrue();

    expect(Post::first()->foo)->toBeNull();
    expect(Post::first()->bar)->toBeNull();

    $model->setMeta([
        'foo' => 'old value',
        'bar' => false,
    ]);

    expect($model->saveMetaAt('-1 day'))->toBeTrue();

    expect(Post::first()->foo)->toBe('old value');
    expect(Post::first()->bar)->toBe(false);

    $this->travelTo(Carbon::now()->addDay());

    expect(Post::first()->foo)->toBe('bar');
    expect(Post::first()->bar)->toBe(125);
});

it('can inspect model meta at a given point in time', function () {
    $this->travelTo('2022-10-01 12:00:00');

    $model = Post::factory()->create();
    $keys = ['foo', 'another', 'bar'];

    $model->saveMeta([
        'foo' => 'bar',
        'bar' => 125,
    ]);

    $this->travelTo(Carbon::now()->addDay());
    $model->saveMeta('foo', 'updated');

    $this->travelTo(Carbon::now()->addDays(2));
    $model->saveMeta('another', true);

    $this->travelTo(Carbon::now()->addDay());
    $model->saveMeta('bar', 999.125);

    expect(Post::first()->only($keys))->toEqual([
        'foo' => 'updated',
        'another' => true,
        'bar' => 999.125,
    ]);

    expect(Post::first()->withMetaAt('2022-10-01 15:00:00')->only($keys))->toEqual([
        'foo' => 'bar',
        'another' => null,
        'bar' => 125,
    ]);

    expect(Post::with('meta')->first()->withMetaAt('2022-10-01 15:00:00')->only($keys))->toEqual([
        'foo' => 'bar',
        'another' => null,
        'bar' => 125,
    ]);

    expect(Post::with('meta')->first()->withMetaAt('2022-10-04 12:15:00')->only($keys))->toEqual([
        'foo' => 'updated',
        'another' => true,
        'bar' => 125,
    ]);

    $post = Post::first()->withMetaAt('2022-10-02 15:00:00');

    expect($post->only($keys))->toEqual([
        'foo' => 'updated',
        'another' => null,
        'bar' => 125,
    ]);

    expect($post->withMetaAt('2022-10-04 12:15:00')->only($keys))->toEqual([
        'foo' => 'updated',
        'another' => true,
        'bar' => 125,
    ]);

    expect($post->withMetaAt('2022-08-05 12:15:00')->only($keys))->toEqual([
        'foo' => null,
        'another' => null,
        'bar' => null,
    ]);

    expect($post->withCurrentMeta()->only($keys))->toEqual([
        'foo' => 'updated',
        'another' => true,
        'bar' => 999.125,
    ]);
});

it('can travel to the future', function () {
    $model = Post::factory()->create();
    $keys = ['foo', 'another', 'bar'];

    $model->saveMetaAt([
        'foo' => 'updated',
        'bar' => 999.125,
        'another' => true,
    ], '+1 year');

    expect(Post::first()->only($keys))->toEqual([
        'foo' => null,
        'another' => null,
        'bar' => null,
    ]);

    expect(Post::first()->withMetaAt('+1 year')->only($keys))->toEqual([
        'foo' => 'updated',
        'another' => true,
        'bar' => 999.125,
    ]);
});

it('can create meta along with the model', function () {
    Post::factory()->create([
        'title' => 'Post title',
        'foo' => 123,
        'bar' => 'works',
    ]);

    $this->assertDatabaseCount('meta', 2);

    expect(Post::first()->title)->toBe('Post title');
    expect(Post::first()->foo)->toBe(123);
    expect(Post::first()->bar)->toBe('works');
});

it('can fill meta with attributes', function () {
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

    expect($post->title)->toBe('New title');
    expect($post->foo)->toBe(true);
    expect($post->getMeta('bar'))->toBe('also true');
});

it('will delete meta with the model', function () {
    Post::factory()->create([
        'title' => 'Post title',
        'foo' => 123,
        'bar' => 'works',
    ]);

    $this->assertDatabaseCount('meta', 2);
    expect(Post::first()->forceDelete())->toBeTrue();
    $this->assertDatabaseCount('meta', 0);
});

it('will not delete meta for soft deleted model', function () {
    Post::factory()->create([
        'title' => 'Post title',
        'foo' => 123,
        'bar' => 'works',
    ]);

    $this->assertDatabaseCount('meta', 2);
    expect(Post::first()->delete())->toBeTrue();
    $this->assertDatabaseCount('meta', 2);
});

it('will delete meta model without soft delete', function () {
    PostWithoutSoftDelete::factory()->create([
        'title' => 'Post title',
        'foo' => 123,
        'bar' => 'works',
    ]);

    $this->assertDatabaseCount('meta', 2);
    expect(PostWithoutSoftDelete::first()->delete())->toBeTrue();
    $this->assertDatabaseCount('meta', 0);
});

it('loads meta relation', function () {
    $post = Post::factory()->create();

    $post->saveMetaAt('foo', false, '-1 day');
    $post->saveMeta('foo', true);
    $post->saveMetaAt('bar', false, '-1 day');
    $post->saveMetaAt('future', false, '+1 day');

    $this->assertDatabaseCount('meta', 4);
    expect(Post::first()->meta)->toHaveCount(2);
});

it('loads published meta relation', function () {
    $post = Post::factory()->create();

    $post->saveMetaAt('foo', false, '-1 day');
    $post->saveMeta('foo', true);
    $post->saveMetaAt('bar', false, '-1 day');
    $post->saveMetaAt('future', false, '+1 day');

    $this->assertDatabaseCount('meta', 4);
    expect(Post::first()->publishedMeta)->toHaveCount(3);
});

it('loads all meta relation', function () {
    $post = Post::factory()->create();

    $post->saveMetaAt('foo', false, '-1 day');
    $post->saveMeta('foo', true);
    $post->saveMetaAt('bar', false, '-1 day');
    $post->saveMetaAt('future', false, '+1 day');

    $this->assertDatabaseCount('meta', 4);
    expect(Post::first()->allMeta)->toHaveCount(4);
});

it('will throw an error for relation attributes', function () {
    $post = Post::factory()->create();

    $post->saveMeta('other', 'Other Value');

    $this->assertDatabaseCount('meta', 1);

    expect($post->getMeta('other'))->toBe('Other Value');
    expect($post->other)->toBe('Other Value');
    expect($post->other)->toBeString();
    expect($post->getAttribute('other'))->toBeString();

    $this->expectException(MetaException::class);

    $post->saveMeta('meta', 'Meta Value');
});

it('refreshes relations after save', function () {
    $post = Post::factory()->create();

    $post->saveMeta([
        'foo' => 'bar',
        'bar' => true,
    ]);

    $post->saveMetaAt('foo', 'old', '-1 day');
    $post->saveMetaAt('bar', false, '+1 day');

    $post = Post::first();

    expect($post->meta)->toHaveCount(2);
    expect($post->publishedMeta)->toHaveCount(3);
    expect($post->allMeta)->toHaveCount(4);

    $post->foo = 'changed';
    $post->saveMeta();

    expect($post->meta)->toHaveCount(2);
    expect($post->publishedMeta)->toHaveCount(4);
    expect($post->allMeta)->toHaveCount(5);
});

it('will not store clean meta', function () {
    $model = Post::factory()->create();

    expect($model->saveMeta('foo', 'bar'))->toBeInstanceOf(Meta::class);
    expect($model->saveMeta('foo', 'bar'))->toBeFalse();
    expect($model->saveMeta('foo', 'bar'))->toBeFalse();
    expect($model->saveMeta('foo', 'changed'))->toBeInstanceOf(Meta::class);

    $this->assertDatabaseCount('meta', 2);
});

it('can assign meta when creating by array', function () {
    $this->assertDatabaseCount('meta', 0);

    Post::unguard();

    Post::create([
        'foo' => 'bar',
        'title' => 'Title',
    ]);

    $this->assertDatabaseCount('meta', 1);
    $this->assertDatabaseHas('sample_posts', ['title' => 'Title']);

    expect(Post::first()->title)->toBe('Title');
    expect(Post::first()->foo)->toBe('bar');
});

it('can get meta when selecting with id', function () {
    Post::factory(2)->has(Meta::factory()->state(['key' => 'foo']))->create();

    $this->assertDatabaseCount('sample_posts', 2);

    expect(Post::select('id', 'title')->get()->filter(function ($model) {
        return $model->foo;
    })->count())->toEqual(2);
});

it('cannot get meta when selecting without id', function () {
    Post::factory(2)->has(Meta::factory()->state(['key' => 'foo']))->create();

    $this->assertDatabaseCount('sample_posts', 2);

    expect(Post::select('title')->get()->filter(function ($model) {
        return $model->foo;
    })->count())->toEqual(0);
});

it('can pluck meta values', function () {
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

    expect($a->pluckMeta()->toArray())->toEqual([
        'appendable_foo' => null,
        'foo' => 'bar',
        'bar' => null,
    ]);

    expect($b->pluckMeta()->toArray())->toEqual([
        'title' => 'Title',
        'body' => null,
        'another' => 123,
        'boolean_field' => null,
        'float_field' => null,
        'integer_field' => null,
    ]);
});

it('does not load meta if relation is null', function () {
    $post = Post::factory()->create();

    expect($post->user)->toBeNull();
    expect($post->relationLoaded('meta'))->toBeFalse();
});

it('does not load meta if relation is not null', function () {
    $post = Post::factory()->for(User::factory())->create();

    expect($post->user)->toBeInstanceOf(User::class);
    expect($post->relationLoaded('meta'))->toBeFalse();
});
