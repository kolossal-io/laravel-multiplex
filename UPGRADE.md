# Upgrade Multiplex

## 3.x to 4.x

### Laravel 12+

Version 4.x requires Laravel 12 or higher. Laravel 11 no longer receives security fixes. If you are still using Laravel 11, you should continue using version 3.x of `laravel-multiplex`.

### Removed fallback to database columns

Explicitly allowed meta keys having an equally named table column no longer fall back to the column value if there is no meta. The column is neither read nor written anymore, so the attribute will be `null` until meta is stored for it.

To keep the existing column values, move them to meta records before upgrading, e.g. using the `MetaBackfill` helper (available since 3.1) in a migration:

```php
use App\Models\Post;
use Illuminate\Database\Migrations\Migration;
use Kolossal\Multiplex\Support\MetaBackfill;

return new class extends Migration
{
    public function up(): void
    {
        MetaBackfill::fromColumns(Post::class, ['body', 'color'])->run();
    }
};
```

The meta records are published at the row’s `created_at` by default, so newer meta stays current and the column value becomes part of the history. Use `->dryRun()` to only count the records that would be created. See [Moving Columns to Meta](README.md#moving-columns-to-meta) for all options.

Some behavior differs from the former fallback even after moving the values:

- `deleteMeta()` deletes the moved value as well, the column value will not come back.
- Traveling to a time before the row was created (e.g. via `withMetaAt()`) returns `null`, since the moved value is published at `created_at`.

Also note:

- `setFallbackValue()` and `getFallbackValue()` were removed and `pluckMeta()` no longer accepts the `$withFallbackValues` argument.
- Calling `metaKeys()` on a model now removes the attributes of newly allowed meta keys as well, just like retrieving a model does.
- `getOriginal()` returns the meta value for keys cast to `MetaAttribute`, use `getRawOriginal()` to get the original column value.

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
