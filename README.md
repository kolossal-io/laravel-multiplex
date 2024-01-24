<p align="center">
  <br />
  <a href="https://github.com/kolossal-io/laravel-multiplex">
    <picture>
      <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/kolossal-io/laravel-multiplex/HEAD/.github/logo-dark.svg">
      <source media="(prefers-color-scheme: light)" srcset="https://raw.githubusercontent.com/kolossal-io/laravel-multiplex/HEAD/.github/logo-light.svg">
      <img alt="Multiplex" src="https://raw.githubusercontent.com/kolossal-io/laravel-multiplex/HEAD/.github/logo-light.svg" width="316" height="72" style="max-width: 100%;">
    </picture>
  </a>
</p>

<p align="center">
  A Laravel package to attach time-sliced meta data to Eloquent models.
</p>

<p align="center">
    <a href="https://packagist.org/packages/kolossal-io/laravel-multiplex"><img src="https://img.shields.io/badge/Laravel-^9.0|^10.0-green.svg?style=flat-square" alt="Laravel"></a>
    <a href="https://packagist.org/packages/kolossal-io/laravel-multiplex"><img src="https://img.shields.io/packagist/v/kolossal-io/laravel-multiplex.svg?style=flat-square" alt="Latest Version on Packagist"></a>
    <a href="https://codecov.io/gh/kolossal-io/laravel-multiplex" > 
    <img src="https://codecov.io/gh/kolossal-io/laravel-multiplex/branch/main/graph/badge.svg?token=330354GI30"/> 
    </a>
    <a href="https://github.com/kolossal-io/laravel-multiplex/actions/workflows/tests.yml"><img src="https://github.com/kolossal-io/laravel-multiplex/actions/workflows/tests.yml/badge.svg" alt="GitHub Tests Action Status"></a>
</p>

<p align="center">
    <a href="#table-of-contents">View Table of Contents</a>
</p>

---

## What it does

Multiplex allows you to attach time-sliced metadata to Eloquent models in a convenient way.

```php
$post = \App\Models\Post::first();

// Set meta fluently for any key – `likes` is no column of `Post`.
$post->likes = 24;

// Or use the `setMeta` method.
$post->setMeta('likes', 24);

// You may also schedule changes, for example change the meta in 2 years:
$post->setMetaAt('likes', 6000, '+2 years');
```

## Features

-   Metadata is saved in versions: Schedule changes to metadata, change history or retrieve metadata for a specific point in time.
-   Supports fluent syntax: Use your model’s metadata as if they were properties.
-   Polymorphic relationship allows adding metadata to any Eloquent model without worrying about the database schema.
-   Easy to try: Extend existing database columns of your model with versionable metadata without touching or deleting your original columns.
-   Type conversion system heavily based on [Laravel-Metable](https://github.com/plank/laravel-metable) allows data of numerous different scalar and object types to be stored and retrieved.

## Why another Metadata Package?

The main difference is that the metadata in Multiplex has a timestamp that defines validity. This allows changes to be tracked and planned. You can inspect all metadata on your model at a specific point in time and Multiplex will by default only give you the most current.

Since Multiplex is storing the metadata in a [polymorphic](https://laravel.com/docs/9.x/eloquent-relationships#polymorphic-relationships) table, it can easily be plugged into existing projects to expand properties of your models. This even works without removing the relevant table columns of your model: They are used as a fallback.

And it’s low profile: If you don't like it, just [remove the `HasMeta` Trait](#installation) and everything is back to normal.

## Table of Contents

-   [Installation](#installation)
-   [Attaching Metadata](#attaching-metadata)
-   [Retrieving Metadata](#retrieving-metadata)
-   [Query by Metadata](#query-by-metadata)
-   [Events](#events)
-   [Time Traveling](#time-traveling)
-   [Limit Meta Keys](#limit-meta-keys)
-   [Extending Database Columns](#extending-database-columns)
-   [Deleting Metadata](#deleting-metadata)
-   [Performance](#performance)
-   [Configuration](#configuration)
-   [UUID and ULID Support](#uuid-and-ulid-support)

## Installation

You can install the package via composer:

```bash
composer require kolossal-io/laravel-multiplex
```

Publish the migrations to create the `meta` table where metadata will be stored.

```bash
php artisan migrate
```

Attach the `HasMeta` trait to any Eloquent model that needs meta attached.

```php
use Illuminate\Database\Eloquent\Model;
use Kolossal\Multiplex\HasMeta;

class Post extends Model
{
    use HasMeta;
}
```

## Attaching Metadata

By default you can use any `key` for attaching metadata. You can [limit which keys can be used](#limit-meta-keys).

```php
$model->setMeta('foo', 'bar');
// or
$model->foo = 'bar';
```

You may also set multiple meta values by passing an `array`.

```php
$model->setMeta([
    'hide' => true,
    'color' => '#000',
    'likes' => 24,
]);
```

All metadata will be stored automatically when saving your model.

```php
$model->foo = 'bar';

$model->isMetaDirty(); // true

$model->save();

$model->isMetaDirty(); // false
```

You can also save your model without saving metadata.

```php
$model->saveWithoutMeta();

$model->isMetaDirty(); // true

$model->saveMeta();
```

You can reset metadata changes that were not yet saved.

```php
$model->resetMeta();
```

Metadata can be stored right away without waiting for the parent model to be saved.

```php
// Save the given meta value right now.
$model->saveMeta('foo', 123.45);

// Save only specific keys of the changed meta.
$model->setMeta(['color' => '#fff', 'hide' => false]);
$model->saveMeta('color');
$model->isMetaDirty('hide'); // true

// Save multiple meta values at once.
$model->saveMeta([
    'color' => '#fff',
    'hide' => true,
]);
```

### Schedule Metadata

You can save metadata for a specific publishing date.

```php
$user = Auth::user();

$user->saveMeta('favorite_band', 'The Mars Volta');
$user->saveMetaAt('favorite_band', 'Portishead', '+1 week');

// Changing taste in music: This will return `The Mars Volta` now but `Portishead` in a week.
$user->favorite_band;
```

This way you can change historic data as well.

```php
$user->saveMetaAt('favorite_band', 'Arctic Monkeys', '-5 years');
$user->saveMetaAt('favorite_band', 'Tool', '-1 year');

// This will return `Tool` – which is true since this is indeed a good band.
$user->favorite_band;
```

You may also save multiple metadata records at once.

```php
$user->setMeta('favorite_color', 'blue');
$user->setMeta('favorite_band', 'Jane’s Addiction');
$user->saveMetaAt('+1 week');

// or

$user->saveMetaAt([
    'favorite_color' => 'blue',
    'favorite_band' => 'Jane’s Addiction',
], '+1 week');
```

### How Metadata is stored

Multiplex will store metadata in a polymorphic table and take care of serializing and unserializing datatypes for you. The underlying polymorphic `meta` table may look something like this:

| metable_type    | metable_id | key   | value | type    | published_at        |
| --------------- | ---------: | ----- | ----: | ------- | ------------------- |
| App\Models\Post |        `1` | color |  #000 | string  | 2022-11-29 13:13:45 |
| App\Models\Post |        `1` | likes |    24 | integer | 2020-01-01 00:00:00 |
| App\Models\Post |        `1` | hide  |  true | boolean | 2022-11-27 16:32:08 |
| App\Models\Post |        `1` | color |  #fff | string  | 2030-01-01 00:00:00 |

The corresponding meta values would look like this:

```php
$post = Post::find(1);

$post->color; // string(4) "#000"
$post->likes; // int(24)
$post->hide; // bool(true)

// In the year 2030 `$post->color` will be `#fff`.
```

## Retrieving Metadata

You can access metadata as if they were properties on your model.

```php
$post->likes; // (int) 24
$post->color; // (string) '#000'
```

Or use the `getMeta()` method to specify a fallback value for non-existent meta.

```php
$post->getMeta('likes', 0); // Use `0` as a fallback.
```

You can also retrieve the `meta` relation on your model. This will only retrieve the most recent value per `key` that is released yet.

```php
$post->saveMeta([
    'author' => 'Anthony Kiedis',
    'color' => 'black',
]);

$post->saveMetaAt('author', 'Jimi Hendrix', '1970-01-01');
$post->saveMetaAt('author', 'Omar Rodriguez', '+1 year');

$post->meta->pluck('value', 'key');

/**
 * Illuminate\Support\Collection {
 *   all: [
 *     "author" => "Anthony Kiedis",
 *     "color" => "black",
 *   ],
 * }
 */
```

There is a shorthand to pluck all the current meta data attached to the model. This will include all [explicitly defined meta keys](#limit-meta-keys) with a default of `null`.

```php
// Allow any meta key and explicitly allow `foo` and `bar`.
$post->metaKeys(['*', 'foo', 'bar']);

$post->saveMeta('foo', 'a value');
$post->saveMeta('another', true);

$post->pluckMeta();
/**
 * Illuminate\Support\Collection {
 *   all: [
 *     "foo" => "a value",
 *     "bar" => null,
 *     "another" => true,
 *   ],
 * }
 */
```

If you instead want to retrieve all meta that was published yet, use the `publishedMeta` relation.

```php
// This array will also include `Jimi Hendrix´.
$post->publishedMeta->toArray();
```

If you want to inspect _all_ metadata including unpublished records, use the `allMeta` relation.

```php
$post->allMeta->toArray();
```

You can determine if a `Meta` instance is the most recent published record for the related model or if it is not yet released.

```php
$meta = $post->allMeta->first();

$meta->is_current; // (bool)
$meta->is_planned; // (bool)
```

### Querying `Meta` Model

There are also some query scopes on the `Meta` model itself that may be helpful.

```php
Meta::published()->get(); // Only current and historic meta.

Meta::planned()->get(); // Only meta not yet published.

Meta::publishedBefore('+1 week')->get(); // Only meta published by next week.

Meta::publishedAfter('+1 week')->get(); // Only meta still unpublished in a week.

Meta::onlyCurrent()->get(); // Only current meta without planned or historic data.

Meta::withoutHistory()->get(); // Query without stale records.

Meta::withoutCurrent()->get(); // Query without current records.
```

By default these functions will use `Carbon::now()` to determine what metadata is considered the most recent, but you can also pass a datetime to look from.

```php
// Get records that have been current a month ago.
Meta::onlyCurrent('-1 month')->get();

// Get records that will not be history by tommorow.
Meta::withoutHistory(Carbon::now()->addDay())->get();
```

## Query by Metadata

### Querying Metadata Existence

You can query records having meta data for the given key(s).

```php
// Find posts having at least one meta records for `color` key.
Post::whereHasMeta('color')->get();

// Or pass an array to find records having meta for at least one of the given keys.
Post::whereHasMeta(['color', 'background_color'])->get();
```

### Querying Metadata Absence

You can query records not having meta data for the given key(s).

```php
// Find posts not having any meta records for `color` key.
Post::whereDoesntHaveMeta('color')->get();

// Or find records not having meta for any of the given keys.
Post::whereDoesntHaveMeta(['color', 'background_color'])->get();
```

### Querying Metadata by Value

You can retrieve models having meta with the given key and value.

```php
// Find posts where the current attached color is `black`.
Post::whereMeta('color', 'black')->get();

// Find posts where the current attached color is not `black`.
Post::whereMeta('color', '!=', 'black')->get();

// Find posts that are `visible`.
Post::whereMeta('visible', true)->get();

// There are alternatives for building `or` clauses for all scopes.
Post::whereMeta('visible', true)->orWhere('hidden', false)->get();
```

Multiplex will take care of finding the right datatype for the passed query.

```php
// Matches only meta records with type `boolean`.
Post::whereMeta('hidden', false)->get();

// Matches only meta records with type `datetime`.
Post::whereMeta('release_at', '<=', Carbon::now())->get();
```

You may also query by an array if values. Each array value will be typecasted individually.

```php
// Find posts where `color` is `black` (string) or `false` (boolean).
Post::whereMetaIn('color', ['black', false])->get();
```

If you would like to query without typecasting use `whereRawMeta()` instead.

```php
Post::whereRawMeta('hidden', '')->get();

Post::whereRawMeta('likes', '>', '100')->get();
```

You can also define which [datatype](config/multiplex.php) to use.

```php
Post::whereMetaOfType('integer', 'count', '0')->get();

Post::whereMetaOfType('null', 'foo', '')->get();
```

### Querying empty or non-empty Metadata

You can query for empty or non-empty metadata where `null` or empty strings would be considered being empty.

```php
Post::whereMetaEmpty('favorite_band')->get();

// Get all posts having meta names `likes` and `comments` where *both* of them are not empty.
Post::whereMetaNotEmpty(['likes', 'comments'])->get();
```

## Events

You can listen for the following events that will be fired by Multiplex.

### `MetaHasBeenAdded`

This event will be fired once a new version of meta is saved to the model.

```php
use Kolossal\Multiplex\Events\MetaHasBeenAdded;

class SomeListener
{
    public function handle(MetaHasBeenAdded $event)
    {
        $event->meta; // The Meta model that was added.
        $event->model; // The parent model, same as $event->meta->metable.
        $event->type; // The class name of the parent model.
    }
}
```

### `MetaHasBeenRemoved`

This event will be fired once metadata is removed by using [`deleteMeta`](#deleting-metadata). The event will fire only once per key and the `$meta` property on the event will contain the latest meta only.

```php
use Kolossal\Multiplex\Events\MetaHasBeenRemoved;

class SomeListener
{
    public function handle(MetaHasBeenRemoved $event)
    {
        $event->meta; // The Meta model that was removed.
        $event->model; // The parent model, same as $event->meta->metable.
        $event->type; // The class name of the parent model.
    }
}
```

## Time Traveling

You can get the metadata for a model at a specific point in time.

```php
$user = Auth::user()->withMetaAt('-1 week');
$user->favorite_band; // Tool
$user->withMetaAt(Carbon::now())->favorite_band; // The Mars Volta
```

This way you can inspect the whole set of metadata that was valid at the time.

```php
Post::first()->withMetaAt('2022-10-01 15:00:00')->meta->pluck('value', 'key');
```

You can also query by meta for a specific point in time.

```php
Post::travelTo('-14 days')->whereMetaIn('foo', [false, 0])->get();

Post::travelTo('+2 years')->where('category', 'tech')->get();
```

Remember to travel back if you want to perform further actions.

```php
Post::travelTo('-1 year')->where('category', 'tech')->get();
Post::where('category', 'tech')->get(); // Will still look for meta published last year.

Post::travelBack();
Post::where('category', 'tech')->get(); // Find current meta.
```

## Limit Meta Keys

You can limit which keys can be used for metadata by setting `$metaKeys` on the model.

```php
class Post extends Model
{
    use HasMeta;

    protected array $metaKeys = [
        'color',
        'hide',
    ];
}
```

By default all keys are allowed.

```php
protected array $metaKeys = ['*'];
```

You can also change the allowed meta keys dynamically.

```php
$model->metaKeys(['color', 'hide']);
```

You might as well cast your attributes using the `MetaAttribute` cast which will automatically allow the attribute being used as a meta key.

```php
use Kolossal\Multiplex\MetaAttribute;

class Post extends Model
{
    use HasMeta;

    protected $metaKeys = [];

    protected $casts = [
        'body' => MetaAttribute::class,
    ];
}
```

Trying to assign a value to a meta key that is not allowed will throw a `Kolossal\Multiplex\Exceptions\MetaException`.

If you have [Eloquent Strictness](https://laravel.com/docs/10.x/eloquent#configuring-eloquent-strictness) enabled it is recommended to [explicitely cast the meta attributes to `MetaAttribute`](https://github.com/kolossal-io/laravel-multiplex/issues/19#issuecomment-1584150675).

## Typecast Meta Keys

Sometimes you may wish to force typecasting of meta attributes. You can bypass guessing the correct type and define which type should be used for specific meta keys.

```php
protected array $metaKeys = [
    'foo',
    'count' => 'integer',
    'color' => 'string',
    'hide' => 'boolean',
];
```

## Extending Database Columns

By default Multiplex will not touch columns of your model. But sometimes it might be useful to have meta records as an extension for your existing table columns.

Consider having an existing `Post` model with only a `title` and a `body` column. By explicitely adding `body` to our array of meta keys `body` will be handled by Multiplex from now on – not touching the `posts` table, but using the database column as a fallback.

```php
class Post extends Model
{
    use HasMeta;

    protected $metaKeys = [
        '*',
        'body',
    ];
}
```

```php
\DB::table('posts')->create(['title' => 'A title', 'body' => 'A body.']);

$post = Post::first();

$post->body; // A body.

$post->body = 'This. Is. Meta.';
$post->save();

$post->body; // This. Is. Meta.
$post->deleteMeta('body');

$post->body; // A body.
```

In case of using Multiplex for extending table columns, Multiplex will remove the original column when retrieving models from the database so you don’t get stale data.

## Deleting Metadata

You can delete any metadata associated with the model from the database.

```php
// Delete all meta records for the `color` key.
$post->deleteMeta('color');

// Or delete all meta records associated with the model.
$post->purgeMeta();
```

## Performance

Since Multiplex stores metadata in a polymorphic [One To Many](https://laravel.com/docs/9.x/eloquent-relationships#one-to-many-polymorphic-relations) relationship querying your models could easily result in a [`N+1` query problem](https://laravel.com/docs/9.x/eloquent-relationships#eager-loading).

Depending on your use case you should consider eager loading the `meta` relation, for example using `$with` on your model. This might be especially useful if you are [extending database columns](#extending-database-columns).

```php
// Worst case: 26 queries if `color` is a meta value.
$colors = Post::take(25)->get()->map(
    fn ($post) => $post->color;
);

// Same result with only 2 queries.
$colors = Post::with('meta')->take(25)->get()->map(
    fn ($post) => $post->color;
);
```

## Configuration

There is no need to configure anything but if you like, you can publish the config file with:

```bash
php artisan vendor:publish --tag="multiplex-config"
```

## UUID and ULID Support

If your application uses UUIDs or ULIDs for the model(s) using metadata, you may set the `multiplex.morph_type` setting to `uuid` or `ulid` **before** running the migrations. You might as well set the `MULTIPLEX_MORPH_TYPE` environment variable instead, if you don’t want to publish the configuration file.

This will ensure `Meta` models will use UUID/ULID and that proper keys and foreign keys are used when running the migrations.

## Credits

This package is heavily based on and inspired by [Laravel-Metable](https://github.com/plank/laravel-metable) by [Sean Fraser](https://github.com/frasmage) as well as [laravel-meta](https://github.com/kodeine/laravel-meta) by [Kodeine](https://github.com/kodeine). The [Package Skeleton](https://github.com/spatie/package-skeleton-laravel) by the great [Spatie](https://spatie.be/) was used as a starting point.

## License

<p>
    <br />
    <a href="https://kolossal.io" target="_blank">
    <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/kolossal-io/laravel-multiplex/HEAD/.github/kolossal-logo-dark.svg">
        <source media="(prefers-color-scheme: light)" srcset="https://raw.githubusercontent.com/kolossal-io/laravel-multiplex/HEAD/.github/kolossal-logo-light.svg">
        <img alt="Multiplex" src="https://raw.githubusercontent.com/kolossal-io/laravel-multiplex/HEAD/.github/kolossal-log-light.svg" width="138" height="32" style="max-width: 100%;">
    </picture>
    </a>
    <br />
    <br />
</p>

Copyright © [kolossal](https://kolossal.io). Released under [MIT License](LICENSE.md).
