<p align="center">
  <br />
  <a href="https://github.com/kolossal-io/laravel-multiplex">
    <picture>
      <source media="(prefers-color-scheme: dark)" srcset="./.github/logo-dark.svg">
      <source media="(prefers-color-scheme: light)" srcset="./.github/logo-light.svg">
      <img alt="Multiplex" src="./.github/logo-light.svg" width="316" height="72" style="max-width: 100%;">
    </picture>
  </a>
</p>

<p align="center">
  A Laravel package to attach time-sliced meta data to Eloquent models.
</p>

<p align="center">
    <a href="https://packagist.org/packages/kolossal-io/laravel-multiplex"><img src="https://img.shields.io/badge/Laravel-^9.0-green.svg?style=flat-square" alt="Laravel"></a>
    <a href="https://packagist.org/packages/kolossal-io/laravel-multiplex"><img src="https://img.shields.io/packagist/v/kolossal-io/laravel-multiplex.svg?style=flat-square" alt="Latest Version on Packagist"></a>
    <a href="https://codecov.io/gh/kolossal-io/laravel-multiplex" > 
    <img src="https://codecov.io/gh/kolossal-io/laravel-multiplex/branch/main/graph/badge.svg?token=330354GI30"/> 
    </a>
    <a href="https://github.com/kolossal-io/laravel-multiplex/actions/workflows/tests.yml"><img src="https://github.com/kolossal-io/laravel-multiplex/actions/workflows/tests.yml/badge.svg" alt="GitHub Tests Action Status"></a>
</p>

---

## What it Does

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

-   Metadata is saved in versions: Schedule changes to metadata,  
    change history or retrieve metadata for a specific point in time.
-   Supports fluent syntax: Use your model’s metadata as if they were properties.
-   Polymorphic relationship allows adding metadata to any Eloquent model  
    without worrying about the database schema.
-   Type conversion system heavily based on [Laravel-Metable](https://github.com/plank/laravel-metable) allows data  
    of numerous different scalar and object types to be stored and retrieved.

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

Multiplex will take care of serializing and unserializing datatypes for you. The polymorphic `meta` table may look something like this:

| metable_type      | metable_id | key     |  value | type      | …   |
| ----------------- | ---------: | ------- | -----: | --------- | --- |
| `App\Models\Post` |        `1` | `color` | `#000` | `string`  | …   |
| `App\Models\Post` |        `1` | `likes` |   `24` | `integer` | …   |
| `App\Models\Post` |        `2` | `color` | `#fff` | `string`  | …   |
| `App\Models\Post` |        `2` | `hide`  | `true` | `boolean` | …   |

### Schedule Metadata

You can save metadata for a specific publishing date.

```php
$post->saveMeta('favorite_band', 'The Mars Volta');
$post->saveMetaAt('favorite_band', 'Portishead', '+1 week');

// Changing taste in music: This will return `The Mars Volta` now but `Portishead` in a week.
$post->favorite_band;
```

This way you can change historic data as well.

```php
$post->saveMetaAt('favorite_band', 'Arctic Monkeys', '-5 years');
$post->saveMetaAt('favorite_band', 'Tool', '-1 year');

// This will return `Tool` – which is true since this is indeed a good band.
$post->favorite_band;
```

## Retrieving Metadata

You can access metadata as if they were properties on your model.

```php
$post->likes; // (int) 24
$post->color; // (string) '#000'
```

Or use the `getMeta` method to specify a callback value for non-existent meta.

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

If you instead want to retrieve all meta that was published yet, use the `publishedMeta` relation.

```php
// This array will also include `Jimi Hendrix´.
$post->publishedMeta->toArray();
```

If you want to inspect _all_ metadata including unpublished records, use the `allMeta` relation.

```php
$post->allMeta->toArray();
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

## Time Traveling

You can get the metadata for a model at a specific point in time.

```php
$post = Post::first()->withMetaAt('-1 week');
$post->favorite_band; // Tool
$post->withMetaAt(Carbon::now())->favorite_band; // The Mars Volta
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

## Delete Metadata

You can delete any metadata associated with the model from the database.

```php
// Delete all meta records for the `color` key.
$post->deleteMeta('color');

// Or delete all meta records associated with the model.
$post->purgeMeta();
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

## Configuration

There is no need to configure anything but if you like, you can publish the config file with:

```bash
php artisan vendor:publish --tag="laravel-multiplex-config"
```

## Credits

This package is heavily based on and inspired by [Laravel-Metable](https://github.com/plank/laravel-metable) by [Sean Fraser](https://github.com/frasmage) as well as [laravel-meta](https://github.com/kodeine/laravel-meta) by [Kodeine](https://github.com/kodeine). The [Package Skeleton](https://github.com/spatie/package-skeleton-laravel) by the great [Spatie](https://spatie.be/) was used as a starting point.

## License

<p>
    <br />
    <a href="https://kolossal.io" target="_blank">
    <picture>
        <source media="(prefers-color-scheme: dark)" srcset="./.github/kolossal-logo-dark.svg">
        <source media="(prefers-color-scheme: light)" srcset="./.github/kolossal-logo-light.svg">
        <img alt="Multiplex" src="./.github/kolossal-log-light.svg" width="138" height="32" style="max-width: 100%;">
    </picture>
    </a>
    <br />
    <br />
</p>

Copyright © [kolossal](https://kolossal.io). Released under [MIT License](LICENSE.md).
