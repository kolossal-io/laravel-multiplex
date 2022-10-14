# A Laravel package to attach versioned meta data to Eloquent models.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/kolossal-io/laravel-meta-revision.svg?style=flat-square)](https://packagist.org/packages/kolossal-io/laravel-meta-revision)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/kolossal-io/laravel-meta-revision/run-tests?label=tests)](https://github.com/kolossal-io/laravel-meta-revision/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/kolossal-io/laravel-meta-revision/Fix%20PHP%20code%20style%20issues?label=code%20style)](https://github.com/kolossal-io/laravel-meta-revision/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/kolossal-io/laravel-meta-revision.svg?style=flat-square)](https://packagist.org/packages/kolossal-io/laravel-meta-revision)

A Laravel package to attach versioned meta data to Eloquent models.

## Installation

You can install the package via composer:

```bash
composer require kolossal-io/laravel-meta-revision
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="laravel-meta-revision-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="laravel-meta-revision-config"
```

This is the contents of the published config file:

```php
return [
];
```

## Usage

```php
$metaRevision = new Kolossal\MetaRevision();
echo $metaRevision->echoPhrase('Hello, kolossal!');
```

## License

Copyright (c) [kolossal](https://kolossal.io)

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
