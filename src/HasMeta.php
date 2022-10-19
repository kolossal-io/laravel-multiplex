<?php

namespace Kolossal\Meta;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Kolossal\Meta\Exceptions\MetaException;

trait HasMeta
{
    /**
     * The allowed meta keys.
     *
     * @var array
     */
    protected array $metaKeys = ['*'];

    /**
     * Collection of the changed meta data for this model.
     *
     * @var Collection|null
     */
    protected ?Collection $metaChanges = null;

    /**
     * Cache storage for table column names.
     *
     * @var array
     */
    protected array $metaSchemaColumnsCache = [];

    /**
     * Auto-save meta data when model is saved.
     *
     * @var bool
     */
    protected bool $autosaveMeta = true;

    /**
     * The timestamp used to determine which meta is published yet.
     *
     * @var Carbon|null
     */
    protected ?Carbon $metaTimestamp = null;

    /**
     * Boot the model trait.
     *
     * @return void
     */
    public static function bootHasMeta()
    {
        static::saved(function ($model) {
            if ($model->autosaveMeta === true) {
                $model->saveMeta();
            }
        });

        static::deleted(function ($model) {
            if (
                $model->autosaveMeta === true
                && ! in_array(SoftDeletes::class, class_uses($model))
            ) {
                $model->purgeMeta();
            }
        });

        if (method_exists(__CLASS__, 'forceDeleted')) {
            static::forceDeleted(function ($model) {
                if ($model->autosaveMeta === true) {
                    $model->purgeMeta();
                }
            });
        }
    }

    /**
     * Enable or disable auto-saving of meta data.
     *
     * @return self
     */
    public function autosaveMeta(bool $enable = true): self
    {
        $this->autosaveMeta = $enable;

        return $this;
    }

    /**
     * Get the allowed meta keys for the model.
     *
     * @return array<string>
     */
    public function getMetaKeys(): array
    {
        return $this->metaKeys;
    }

    /**
     * Set the allowed meta keys for the model.
     *
     * @param  array<string>  $fillable
     * @return $this
     */
    public function metaKeys(array $metaKeys): static
    {
        $this->metaKeys = $metaKeys;

        return $this;
    }

    /**
     * Determine if the meta keys are guarded.
     *
     * @return bool
     */
    public function isMetaGuarded(): bool
    {
        return ! in_array('*', $this->getMetaKeys());
    }

    /**
     * Determine if the given key is an allowed meta key.
     *
     * @param  string  $key
     * @return bool
     */
    public function isModelAttribute(string $key): bool
    {
        return
            $this->hasSetMutator($key) ||
            $this->hasAttributeSetMutator($key) ||
            $this->isEnumCastable($key) ||
            $this->isClassCastable($key) ||
            str_contains($key, '->') ||
            $this->hasColumn($key) ||
            array_key_exists($key, parent::getAttributes());
    }

    /**
     * Determine if the given key was explicitly allowed.
     *
     * @param  string  $key
     * @return bool
     */
    public function isExplicitlyAllowedMetaKey(string $key): bool
    {
        return in_array($key, $this->getMetaKeys())
            || with($this->getCasts(), function ($casts) use ($key) {
                return isset($casts[$key]) && $casts[$key] === MetaAttribute::class;
            });
    }

    /**
     * Determine if the given key is an allowed meta key.
     *
     * @param  string  $key
     * @return bool
     */
    public function isValidMetaKey(string $key): bool
    {
        if ($this->isExplicitlyAllowedMetaKey($key)) {
            return true;
        }

        if ($this->isModelAttribute($key)) {
            return false;
        }

        return ! $this->isMetaGuarded();
    }

    /**
     * Determine if model table has a given column.
     *
     * @param  [string]  $column
     * @return bool
     */
    public function hasColumn($column): bool
    {
        $class = get_class($this);

        if (! isset($this->metaSchemaColumnsCache[$class])) {
            $this->metaSchemaColumnsCache[$class] = collect(
                $this->getConnection()
                    ->getSchemaBuilder()
                    ->getColumnListing($this->getTable()) ?? []
            )->map(fn ($item) => strtolower($item))->toArray();
        }

        return in_array(strtolower($column), $this->metaSchemaColumnsCache[$class]);
    }

    /**
     * Get the timestamp to take as `now` when looking up meta data.
     *
     * @return Carbon
     */
    public function getMetaTimestamp(): Carbon
    {
        return $this->metaTimestamp
            ? Carbon::parse($this->metaTimestamp)
            : Carbon::now();
    }

    /**
     * Relationship to all `Meta` models associated with this model.
     *
     * @return MorphMany
     */
    public function allMeta(): MorphMany
    {
        return $this->morphMany($this->getMetaClassName(), 'metable');
    }

    /**
     * Relationship to only published `Meta` models associated with this model.
     *
     * @return MorphMany
     */
    public function publishedMeta(): MorphMany
    {
        return $this->allMeta()->published();
    }

    /**
     * Relationship to the `Meta` model.
     * Groups by `key` and only shows the latest item that is published yet.
     *
     * @return MorphMany
     */
    public function meta(): MorphMany
    {
        return $this->allMeta()->groupByKeyTakeLatest($this->getMetaTimestamp());
    }

    /**
     * Get Meta model class name.
     *
     * @return string
     */
    protected function getMetaClassName(): string
    {
        return config('meta.model', Meta::class);
    }

    /**
     * Get meta value for key.
     *
     * @param  string  $key
     * @param  mixed  $default
     * @return  mixed
     */
    public function getMeta(string $key, $default = null)
    {
        return $this->findMeta($key)?->value ?? $default;
    }

    /**
     * {@inheritDoc}
     */
    public function getAttribute($key)
    {
        if (! $key) {
            return;
        }

        /**
         * If the given key is not explicitly allowed but exists as a real attribute
         * let’s not try to find a meta value for the given key.
         */
        if (! $this->isExplicitlyAllowedMetaKey($key) && ($attr = parent::getAttribute($key)) !== null) {
            return $attr;
        }

        /**
         * If there is a relation with the same name as the given key load the relation.
         */
        if ($this->isRelation($key)) {
            return $this->getRelation($key);
        }

        /**
         * There seems to be no attribute given and no relation so we either have a key
         * explicitly listed as a meta key or the wildcard (*) was used. Let’s get the meta
         * value for the given key and pipe the result through an accessor if possible.
         * Finally delegate back to `parent::getAttribute()` if no meta exists.
         */
        $value = $this->getMeta($key);

        return with(Str::camel('get_'.$key.'_meta'), function ($accessor) use ($value) {
            if (! method_exists($this, $accessor)) {
                return $value;
            }

            return $this->{$accessor}($value);
        }) ?? value(fn () => ! $this->hasMeta($key) ? parent::getAttribute($key) : null);
    }

    /**
     * Determine wether the given meta exists.
     *
     * @param  string  $key
     * @return bool
     */
    public function hasMeta(string $key): bool
    {
        return (bool) $this->findMeta($key);
    }

    /**
     * Find current Meta model for the given key.
     *
     * @param  string  $key
     * @return  ?Meta
     */
    public function findMeta($key)
    {
        if (! $this->exists) {
            return null;
        }

        return $this->meta?->first(fn ($meta) => $meta->key === $key);
    }

    /**
     * Get the dirty meta collection.
     *
     * @return Collection
     */
    public function getDirtyMeta(): Collection
    {
        return $this->getMetaChanges();
    }

    /**
     * Determine if meta is dirty.
     *
     * @param  string|null  $key
     * @return bool
     */
    public function isMetaDirty(?string $key = null): bool
    {
        return (bool) with(
            $this->getMetaChanges(),
            fn ($meta) => $key ? $meta->has($key) : $meta->isNotEmpty()
        );
    }

    /**
     * Add or update the value of the `Meta` at a given key.
     *
     * @param  string|array  $key
     * @param  mixed  $value
     *
     * @throws MetaException if invalid key is used.
     */
    public function setMeta($key, $value = null)
    {
        if (is_array($key)) {
            return $this->setMetaFromArray($key);
        }

        return $this->setMetaFromString($key, $value);
    }

    /**
     * Publish the given meta data at the specified time.
     *
     * @param  string|array  $key
     * @param  mixed  $value
     * @param  string|DateTimeInterface|null  $publishAt
     *
     * @throws MetaException if invalid key is used.
     */
    public function setMetaAt($key, $value = null, $publishAt = null)
    {
        if (count(func_get_args()) === 2 && is_array($key)) {
            return $this->setMetaFromArray($key, Carbon::parse($value));
        }

        return $this->setMetaFromString($key, $value, Carbon::parse($publishAt));
    }

    /**
     * Set meta values from array of $key => $value pairs.
     *
     * @param  array  $metas
     * @param  ?Carbon  $publishAt
     * @return Collection
     *
     * @throws MetaException if invalid keys are used.
     */
    protected function setMetaFromArray(array $metas, ?Carbon $publishAt = null): Collection
    {
        return collect($metas)->map(function ($value, $key) use ($publishAt) {
            return $this->setMetaFromString($key, $value, $publishAt);
        });
    }

    /**
     * Add or update the value of the `Meta` at a given string key.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @param  ?Carbon  $publishAt
     *
     * @throws MetaException if invalid key is used.
     */
    protected function setMetaFromString($key, $value, ?Carbon $publishAt = null): Meta
    {
        $key = strtolower($key);

        /**
         * If one is trying to set a model attribute as meta without explicitly
         * whitelisting the attribute throw an exception.
         */
        if ($this->isModelAttribute($key) && ! $this->isExplicitlyAllowedMetaKey($key)) {
            throw MetaException::modelAttribute($key);
        }

        /**
         * Check if the given key was whitelisted.
         */
        if (! $this->isValidMetaKey($key)) {
            throw MetaException::invalidKey($key);
        }

        /**
         * Get all changed meta from our cache collection.
         */
        $meta = $this->getMetaChanges();

        /**
         * Let’s check if there is a mutator for the given meta key and pipe
         * the given value through it if so.
         */
        $value = with(Str::camel('set_'.$key.'_meta'), function ($mutator) use ($value) {
            if (! method_exists($this, $mutator)) {
                return $value;
            }

            return $this->{$mutator}($value);
        });

        $attributes = ['value' => $value];

        /**
         * If `$publishAt` is set the meta should probably be published in the future
         * or one is trying to create a historic record. Set `published_at` accordingly.
         * If `published_at` is `null` it will be set to the current date in the `Meta` model.
         */
        if ($publishAt) {
            $attributes['published_at'] = $publishAt;
        }

        if (($model = $this->findMeta($key))) {
            $model->forceFill($attributes);

            /**
             * If there already is a persisted meta for the given key, let’s check if the
             * given value would result in a dirty model – if not skip here.
             */
            if ($model->isClean()) {
                return $model;
            }

            $model->forceFill($model->getOriginal());
        }

        /**
         * Fill the meta with the given attributes and save the changes in our collection.
         * This will not persist the given meta to the database.
         */
        return $meta[$key] = (new Meta(['key' => $key]))->forceFill($attributes);
    }

    /**
     * Determine wether the given meta was changed.
     *
     * @param  string  $key
     * @return bool
     */
    public function hasMetaChanged(string $key): bool
    {
        return $this->getMetaChanges()->has($key);
    }

    /**
     * Reset the meta changes collection for the given key.
     * Resets the entire collection if nothing is passed.
     *
     * @param  ?string  $key
     */
    public function resetMetaChanges(?string $key = null): Collection
    {
        if ($key && $this->metaChanges) {
            $this->metaChanges->forget($key);

            return $this->metaChanges;
        }

        return $this->metaChanges = new Collection;
    }

    /**
     * Reset the meta changes for the given key.
     *
     * @param  string  $key
     */
    public function resetMeta(string $key): Collection
    {
        return $this->resetMetaChanges($key);
    }

    /**
     * Delete the given meta key or keys.
     *
     * @param  string|array<string>  $key
     *
     * @throws MetaException if invalid key is used.
     */
    public function deleteMeta($key): bool
    {
        DB::beginTransaction();

        $keys = collect(is_array($key) ? $key : [$key]);

        /**
         * If one of the given keys is invalid throw an exception. Otherwise delete all
         * meta records for the given keys from the database.
         */
        $deleted = $keys
            ->each(function ($key) {
                if (! $this->isValidMetaKey($key)) {
                    throw MetaException::invalidKey($key);
                }
            })
            ->filter(fn ($key) => $this->allMeta()->where('key', $key)->delete());

        DB::commit();

        /**
         * Remove the deleted meta models from the collection of changes
         * and refresh the meta relations to prevent having stale data.
         */
        if ($deleted) {
            $deleted->each(fn ($key) => $this->resetMetaChanges($key));
            $this->refreshMetaRelations();
        }

        /** Check if all given keys could be deleted. */
        return $deleted->count() === $keys->count();
    }

    /**
     * Delete all meta for the given model.
     *
     * @return self
     */
    public function purgeMeta(): self
    {
        $this->allMeta()->delete();

        return $this;
    }

    /**
     * Get the locally collected meta data.
     *
     * @return Collection
     */
    public function getMetaChanges(): Collection
    {
        if (! is_null($this->metaChanges)) {
            return $this->metaChanges;
        }

        return $this->resetMetaChanges();
    }

    /**
     * {@inheritDoc}
     */
    public function setAttribute($key, $value)
    {
        if (! $this->isValidMetaKey($key)) {
            return parent::setAttribute($key, $value);
        }

        return $this->setMetaFromString($key, $value);
    }

    /**
     * Refresh the meta relations.
     *
     * @return self
     */
    public function refreshMetaRelations(): self
    {
        if ($this->relationLoaded('allMeta')) {
            $this->unsetRelation('allMeta');
        }

        if ($this->relationLoaded('meta')) {
            $this->unsetRelation('meta');
        }

        return $this;
    }

    /**
     * Store a single Meta model.
     *
     * @param  Meta  $meta
     * @return Meta|false
     */
    protected function storeMeta(?Meta $meta)
    {
        if (! $meta) {
            return false;
        }

        /**
         * If `$metaTimestamp` is set we probably are storing meta for the future or past.
         */
        if ($this->metaTimestamp) {
            $meta->published_at ??= $this->metaTimestamp;
        }

        return $this->allMeta()->save($meta);
    }

    /**
     * Store the meta data from the Meta Collection.
     * Returns `true` if all meta was saved successfully.
     *
     * @param  string|array|null  $key
     * @param  mixed|null  $value
     * @return bool
     */
    public function saveMeta($key = null, $value = null): bool
    {
        /**
         * If we have exactly two arguments set and save the value for the given key.
         */
        if (count(func_get_args()) === 2) {
            $this->setMeta($key, $value);

            return $this->saveMeta($key);
        }

        /**
         * Get all pending meta changes.
         */
        $changes = $this->getMetaChanges();

        /**
         * If no arguments were passed, all changes should be persisted.
         */
        if (empty(func_get_args())) {
            return tap($changes->every(function (Meta $meta, $key) use ($changes) {
                return tap($this->storeMeta($meta), fn ($saved) => $saved && $changes->forget($key));
            }), fn () => $this->refreshMetaRelations());
        }

        /**
         * If only one argument was passed and it’s an array, let’s assume it
         * is a key => value pair that should be stored.
         */
        if (is_array($key)) {
            return collect($key)->every(fn ($value, $name) => $this->saveMeta($name, $value));
        }

        /**
         * Otherwise pull and delete the given key from the array of changes and
         * persist the change. Refresh the relations afterwards to prevent stale data.
         */

        /** @var Meta $meta */
        $meta = $changes->pull($key);

        return tap((bool) $this->storeMeta($meta), function ($saved) {
            if ($saved) {
                $this->refreshMetaRelations();
            }
        });
    }

    /**
     * Immediately save the given meta for a specific publishing time.
     *
     * @param  string|array  $key
     * @param  mixed  $value
     * @param  string|DateTimeInterface|null  $publishAt
     *
     * @throws MetaException if invalid key is used.
     */
    public function saveMetaAt($key = null, $value = null, $publishAt = null)
    {
        $args = func_get_args();

        $previousTimestamp = $this->metaTimestamp;
        $this->metaTimestamp = Carbon::parse(array_pop($args));

        return tap($this->saveMeta(...$args), fn () => $this->metaTimestamp = $previousTimestamp);
    }

    /**
     * Store the model without saving attached meta data.
     *
     * @return bool
     */
    public function saveWithoutMeta(): bool
    {
        $previousSetting = $this->autosaveMeta;

        $this->autosaveMeta = false;

        return tap($this->save(), fn () => $this->autosaveMeta = $previousSetting);
    }

    /**
     * Travel to the specified point in time for storing or retrieving meta.
     *
     * @param  string|DateTimeInterface|null  $time
     * @return self
     */
    public function withMetaAt($time): self
    {
        $time = $time ? Carbon::parse($time) : null;

        if (gettype($this->metaTimestamp) !== gettype($time) || ! $this->metaTimestamp?->equalTo($time)) {
            $this->refreshMetaRelations();
        }

        $this->metaTimestamp = $time;

        return $this;
    }

    /**
     * Travel to the current time for storing or retrieving meta.
     *
     * @return self
     */
    public function withCurrentMeta(): self
    {
        return $this->withMetaAt(null);
    }

    /**
     * Query records having meta data for the given key.
     * Pass an array to find records having meta for at least one of the given keys.
     *
     * @param  Builder  $query
     * @param  string|array  $key
     * @return void
     */
    public function scopeWhereHasMeta(Builder $query, $key): void
    {
        $keys = is_array($key) ? $key : [$key];

        $query->whereHas('publishedMeta', function (Builder $query) use ($keys) {
            $query->whereIn('key', $keys);
        });
    }

    /**
     * Query records not having meta data for the given key.
     * Pass an array to find records not having meta for any of the given keys.
     *
     * @param  Builder  $query
     * @param  string|array  $key
     * @return void
     */
    public function scopeWhereDoesntHaveMeta(Builder $query, $key): void
    {
        $keys = is_array($key) ? $key : [$key];

        $query->whereDoesntHave('publishedMeta', function (Builder $query) use ($keys) {
            $query->whereIn('key', $keys);
        });
    }

    /**
     * Query records having meta with a specific key and value.
     * If the `$value` parameter is omitted, the $operator parameter will be considered the value.
     *
     * @param  Builder  $query
     * @param  string  $key
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return void
     */
    public function scopeWhereMeta(Builder $query, string $key, $operator, $value = null): void
    {
        if (! isset($value)) {
            $value = $operator;
            $operator = '=';
        }

        $query->whereHas('publishedMeta', function (Builder $query) use ($key, $operator, $value) {
            $query->groupByKeyTakeLatest()->where('meta.key', $key)->whereValue($value, $operator);
        });
    }

    /**
     * Query records having raw meta with a specific key and value without checking type.
     * Make sure that the supplied $value is a string or string castable.
     * If the `$value` parameter is omitted, the $operator parameter will be considered the value.
     *
     * @param  Builder  $query
     * @param  string  $key
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return void
     */
    public function scopeWhereRawMeta(Builder $query, string $key, $operator, $value = null): void
    {
        if (! isset($value)) {
            $value = $operator;
            $operator = '=';
        }

        $query->whereHas('publishedMeta', function (Builder $query) use ($key, $operator, $value) {
            $query->groupByKeyTakeLatest()->where('meta.key', $key)->where('value', $operator, $value);
        });
    }

    /**
     * Query records having meta with a specific value and the given type.
     * If the `$value` parameter is omitted, the $operator parameter will be considered the value.
     *
     * Available types can be found in `config('meta.datatypes')`.
     *
     * @param  Builder  $query
     * @param  string  $type
     * @param  string  $key
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return void
     */
    public function scopeWhereMetaOfType(Builder $query, string $type, string $key, $operator, $value = null): void
    {
        if (! isset($value)) {
            $value = $operator;
            $operator = '=';
        }

        $query->whereHas('publishedMeta', function (Builder $query) use ($type, $key, $operator, $value) {
            $query->groupByKeyTakeLatest()->where('meta.key', $key)->whereValue($value, $operator, $type);
        });
    }

    /**
     * Query records having one of the given values for the given key.
     *
     * @param  Builder  $query
     * @param  string  $key
     * @param  array  $values
     * @return void
     */
    public function scopeWhereMetaIn(Builder $query, string $key, array $values): void
    {
        $query->whereHas('publishedMeta', function (Builder $query) use ($key, $values) {
            $query->groupByKeyTakeLatest()->where('meta.key', $key)->whereValueIn($values);
        });
    }
}
