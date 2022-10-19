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
    <a href="https://packagist.org/packages/kolossal-io/laravel-multiplex"><img src="https://img.shields.io/packagist/v/kolossal-io/laravel-multiplex.svg?style=flat-square" alt="Latest Version on Packagist"></a>
    <a href="https://github.com/kolossal-io/laravel-multiplex/actions?query=workflow%3Arun-tests+branch%3Amain"><img src="https://img.shields.io/github/workflow/status/kolossal-io/laravel-multiplex/run-tests?label=tests" alt="GitHub Tests Action Status"></a>
    <a href="https://github.com/kolossal-io/laravel-multiplex/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain"><img src="https://img.shields.io/github/workflow/status/kolossal-io/laravel-multiplex/Fix%20PHP%20code%20style%20issues?label=code%20style" alt="GitHub Code Style Action Status"></a>
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

// You may also schedule changes.
$post->setMetaAt('is_outdated', true, '+2 years');
```

## Features

-   Metadata is saved in versions: Schedule changes to metadata, change history or retrieve metadata from a specific point in time.
-   Supports fluent syntax: Use your model's metadata as if they were properties.
-   Polymorphic relationship allows adding metadata to any Eloquent model without worrying about the database schema.
-   Type conversion system heavily based on [Laravel-Metable](https://github.com/plank/laravel-metable) allows data of numerous different scalar and object types to be stored and retrieved.

## Installation

You can install the package via composer:

```bash
composer require kolossal-io/laravel-multiplex
```

Publish the migrations to create the `meta` table where meta data will be stored.

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

By default you may use any `key` for metadata.

```php
$model->setMeta('foo', 'bar');
// or
$model->foo = 'bar';
```

You may also set multiple meta values by passing an array.

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

You may save your model without saving metadata.

```php
$model->saveWithoutMeta();

$model->isMetaDirty(); // true

$model->saveMeta();
```

You may also store meta data right away without waiting for the parent model to be saved.

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

| metable_type      | metable_id | key     |  value | type      |
| ----------------- | ---------: | ------- | -----: | --------- |
| `App\Models\Post` |        `1` | `color` | `#000` | `string`  |
| `App\Models\Post` |        `1` | `likes` |   `24` | `integer` |
| `App\Models\Post` |        `2` | `color` | `#fff` | `string`  |
| `App\Models\Post` |        `2` | `hide`  | `true` | `boolean` |

## Retrieving Metadata

You can access metadata as if they were properties on your model.

```php
$post->likes; // (int) 24
$post->color; // (string) '#000'
```

Or use the `getMeta` method to specify a callback value for non-existend meta.

```php
$post->getMeta('likes', 0); // Use `0` as a fallback.
```

## Configuration

There is no need to configure anything but if you like, you can publish the config file with:

```bash
php artisan vendor:publish --tag="laravel-multiplex-config"
```

## Credits

This package is heavily based on and inspired by [Laravel-Metable](https://github.com/plank/laravel-metable) by [Sean Fraser](https://github.com/frasmage) as well as [laravel-meta](https://github.com/kodeine/laravel-meta) by [Kodeine](https://github.com/kodeine).

## License

Copyright (c) [kolossal](https://kolossal.io)

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
