# Upgrade Multiplex

## 2.x to 3.x

### Laravel 11+

Version 3.x requires Laravel 11 or higher. If you are using an older version of Laravel, you should continue using version 2.x of `laravel-multiplex`.

### Removed `withoutCurrent`, `withoutHistory` and `joinLatest` scopes

The `withoutCurrent`, `withoutHistory` and `joinLatest` scopes have been removed in favor of `current` (or `onlyCurrent`) and `history` (or `onlyHistory`) scopes. Also the `onlyCurrent` scope can no longer be used on any of the `meta` relations, but you can still use it on the `Meta` model directly, like `Meta::onlyCurrent()` which is the same as using `Meta::current()`.

### `LatestMetaRelation`

The `HasMeta` trait no longer uses a standard `MorphMany` relation for the meta relations. Instead a custom `LatestMetaRelation` is used which includes a sorting order by leaveraging SQL Window Functions. If you changed the default behavior you might have to adjust your code.

## 1.x to 2.x

### SQL Window Functions

Version 2.x replaces aggregate functions with SQL Window Functions. Your database must support Window Functions for this to work, such as:

- MySQL **8.0+**
- MariaDB **10.2+** (with limitations), best **10.4+**
- PostgreSQL **9.0+**
- SQLite **3.25+**
- SQL Server **2012+**

### Update your Index

It is recommended to remove any existing indexes on the `meta` table and create a new index optimized for the updated queries in version 2.x. For example, you can drop old indexes and add a new composite index:

```sql
CREATE INDEX meta_lookup_index ON meta (
    metable_type,
    metable_id,
    `key`,
    published_at,
    id
);
```

If you are installing to a new project, just use the migration.
