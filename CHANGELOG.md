# Changelog

All notable changes to `laravel-multiplex` will be documented in this file.

## v1.3.1 - 2024-06-06

### [1.3.1](https://github.com/kolossal-io/laravel-multiplex/compare/v1.3.0...v1.3.1) (2024-06-06)

#### Bug Fixes

* add phpstan types and fix formatting ([2abdf93](https://github.com/kolossal-io/laravel-multiplex/commit/2abdf93430d2fdd3d3ef5ba225db48093b0c4396))

## v1.3.0 - 2024-03-26

### [1.3.0](https://github.com/kolossal-io/laravel-multiplex/compare/v1.2.1...v1.3.0) (2024-03-26)

##### Bug Fixes

* fix database migrations in test cases ([0cc05f1](https://github.com/kolossal-io/laravel-multiplex/commit/0cc05f129db434506a3d36f90a2649ac1d48631a))

##### Features

* add support for Laravel 11 ([00c0ebb](https://github.com/kolossal-io/laravel-multiplex/commit/00c0ebbc38c0b0aeeac7c26daf8d9e3892dea403))

## v1.2.1 - 2024-01-30

### [1.2.1](https://github.com/kolossal-io/laravel-multiplex/compare/v1.2.0...v1.2.1) (2024-01-30)

#### Bug Fixes

* make `metable` nullable ([9041532](https://github.com/kolossal-io/laravel-multiplex/commit/9041532522c9cdb8376e1f3f9cafa617635e71c2))

## v1.2.0 - 2024-01-24

### [1.2.0](https://github.com/kolossal-io/laravel-multiplex/compare/v1.1.1...v1.2.0) (2024-01-24)

##### Features

* support UUIDs and ULIDs ([#31](https://github.com/kolossal-io/laravel-multiplex/issues/31)) ([fdaf720](https://github.com/kolossal-io/laravel-multiplex/commit/fdaf720bc38fcb3f2bcff915f57b357951fcfb1a)), closes [#26](https://github.com/kolossal-io/laravel-multiplex/issues/26)

## v1.1.1 - 2024-01-24

### [1.1.1](https://github.com/kolossal-io/laravel-multiplex/compare/v1.1.0...v1.1.1) (2024-01-24)

#### Bug Fixes

* add missing type hints ([84483b3](https://github.com/kolossal-io/laravel-multiplex/commit/84483b3a103b7271d4f333c8e44772fbc9106976))
* support nunomaduro/collision `^8.0` ([100c08c](https://github.com/kolossal-io/laravel-multiplex/commit/100c08c98e5758ce9584b9deeca8989b960781be))

## v1.1.0 - 2024-01-23

### [1.1.0](https://github.com/kolossal-io/laravel-multiplex/compare/v1.0.2...v1.1.0) (2024-01-23)

##### Bug Fixes

* try to parse `date` field as fallback ([cfc4f83](https://github.com/kolossal-io/laravel-multiplex/commit/cfc4f838cec385ec1d41ff707edaabebc886d987))

##### Features

* use meta accessor for fallback values ([52f5ed4](https://github.com/kolossal-io/laravel-multiplex/commit/52f5ed414ea129e48edcc88b8108e84c58ca97a7))

## v1.0.2 - 2024-01-22

### [1.0.2](https://github.com/kolossal-io/laravel-multiplex/compare/v1.0.1...v1.0.2) (2024-01-22)

#### Bug Fixes

* fix nullable type declaration for default null values ([3e8d2d1](https://github.com/kolossal-io/laravel-multiplex/commit/3e8d2d1f8431c75eb2d90907c4d6b57961586899))
* use `larastan/larastan` instead of `nunomaduro/larastan` ([ca43ca0](https://github.com/kolossal-io/laravel-multiplex/commit/ca43ca07605bc14fc73eefa8e175c35dc0151500))

## v1.0.1 - 2023-08-16

### [1.0.1](https://github.com/kolossal-io/laravel-multiplex/compare/v1.0.0...v1.0.1) (2023-08-16)

#### Bug Fixes

- use multiple cache keys ([3cdaa14](https://github.com/kolossal-io/laravel-multiplex/commit/3cdaa14f5f48e796b31998afb270a3845d783ce6))
