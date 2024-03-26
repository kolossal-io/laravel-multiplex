<?php

namespace Kolossal\Multiplex;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Kolossal\Multiplex\Events\MetaHasBeenAdded;
use Kolossal\Multiplex\Events\MetaHasBeenRemoved;
use Kolossal\Multiplex\Exceptions\MetaException;

trait HasMeta
{
    /**
     * The allowed meta keys.
     *
     * @var array<string>
     */
    protected array $_metaKeys = ['*'];

    /**
     * Cached array of explicitly allowed meta keys.
     *
     * @var array<string>
     */
    protected ?array $explicitlyAllowedMetaKeys = null;

    /**
     * Collection of the changed meta data for this model.
     */
    protected ?Collection $metaChanges = null;

    /**
     * Collection database columns overridden by meta.
     */
    protected ?Collection $fallbackValues = null;

    /**
     * Cache storage for table column names.
     */
    protected static array $metaSchemaColumnsCache = [];

    /**
     * Auto-save meta data when model is saved.
     */
    protected bool $autosaveMeta = true;

    /**
     * Static timestamp used to determine which meta is published yet.
     */
    protected static ?Carbon $staticMetaTimestamp = null;

    /**
     * The timestamp used to determine which meta is published yet for this model.
     */
    protected ?Carbon $metaTimestamp = null;

    /**
     * Indicates if all meta assignment is unguarded.
     *
     * @var bool
     */
    protected static $metaUnguarded = false;

    /**
     * Boot the model trait.
     */
    public static function bootHasMeta(): void
    {
        static::retrieved(function (Model $model) {
            foreach ($model->getExplicitlyAllowedMetaKeys() as $key) {
                if (isset($model->attributes[$key])) {
                    $model->setFallbackValue($key, Arr::pull($model->attributes, $key));
                }
            }
        });

        static::saved(function (Model $model) {
            if ($model->autosaveMeta === true) {
                $model->saveMeta();
            }
        });

        static::deleted(function (Model $model) {
            if (
                $model->autosaveMeta === true
                && !in_array(SoftDeletes::class, class_uses($model))
            ) {
                $model->purgeMeta();
            }
        });

        if (method_exists(__CLASS__, 'forceDeleted')) {
            static::forceDeleted(function (Model $model) {
                if ($model->autosaveMeta === true) {
                    $model->purgeMeta();
                }
            });
        }
    }

    /**
     * Disable all meta key restrictions.
     */
    public static function unguardMeta(bool $state = true): void
    {
        static::$metaUnguarded = $state;
    }

    /**
     * Re-enable the meta key restrictions.
     */
    public static function reguardMeta(): void
    {
        static::$metaUnguarded = false;
    }

    /**
     * Determine if meta keys are unguarded
     */
    public static function isMetaUnguarded(): bool
    {
        return static::$metaUnguarded;
    }

    /**
     * Add value to the list of columns overridden by meta.
     *
     * @param  mixed  $value
     */
    public function setFallbackValue(string $key, $value = null): self
    {
        ($this->fallbackValues ??= new Collection)->put($key, $value);

        return $this;
    }

    /**
     * Get the fallback value for the given key.
     *
     * @return mixed|null
     */
    public function getFallbackValue(string $key)
    {
        return with($this->fallbackValues?->get($key), function ($value) use ($key) {
            if ($value && ($type = $this->getCastForMetaKey($key))) {
                return $this->getMetaClassName()::getDataTypeRegistry()
                    ->getHandlerForType($type)
                    ->unserializeValue($value);
            }

            return $value;
        });
    }

    /**
     * Enable or disable auto-saving of meta data.
     */
    public function autosaveMeta(bool $enable = true): self
    {
        $this->autosaveMeta = $enable;

        return $this;
    }

    /**
     * Get the value from the $metaKeys property if set or a fallback.
     */
    protected function getMetaKeysProperty(): array
    {
        if (property_exists($this, 'metaKeys') && is_array($this->metaKeys)) {
            return $this->metaKeys;
        }

        return $this->_metaKeys;
    }

    /**
     * Get the allowed meta keys for the model.
     *
     * @return array<string>
     */
    public function getMetaKeys(): array
    {
        return collect($this->getMetaKeysProperty())->map(
            fn ($value, $key) => is_string($key) ? $key : $value
        )->toArray();
    }

    /**
     * Get the forced typecast for the given meta key if there is any.
     *
     * @return ?string
     */
    public function getCastForMetaKey(string $key): ?string
    {
        /** @var ?string $cast */
        $cast = with(
            $this->getMetaKeysProperty(),
            fn ($metaKeys) => isset($metaKeys[$key]) ? $metaKeys[$key] : null
        );

        return $cast;
    }

    /**
     * Get or set the allowed meta keys for the model.
     *
     * @param  array<string>|null  $fillable
     * @return $this
     */
    public function metaKeys(?array $metaKeys = null): array
    {
        if (!$metaKeys) {
            return $this->getMetaKeysProperty();
        }

        if (property_exists($this, 'metaKeys')) {
            $this->metaKeys = $metaKeys;
        } else {
            $this->_metaKeys = $metaKeys;
        }

        $this->getExplicitlyAllowedMetaKeys(false);

        return $this->getMetaKeysProperty();
    }

    /**
     * Determine if the meta key wildcard (*) is set.
     */
    public function isMetaWildcardSet(): bool
    {
        return in_array('*', $this->getMetaKeys());
    }

    /**
     * Determine if the given key is an allowed meta key.
     */
    public function isModelAttribute(string $key): bool
    {
        return
            $this->isRelation($key) ||
            $this->hasSetMutator($key) ||
            $this->hasAttributeSetMutator($key) ||
            $this->isEnumCastable($key) ||
            $this->isClassCastable($key) ||
            str_contains($key, '->') ||
            $this->hasColumn($key) ||
            array_key_exists($key, parent::getAttributes());
    }

    /**
     * Get the meta keys explicitly allowed by using `$metaKeys`
     * or by typecasting to `MetaAttribute::class`.
     */
    public function getExplicitlyAllowedMetaKeys(bool $fromCache = true): array
    {
        if ($this->explicitlyAllowedMetaKeys && $fromCache) {
            return $this->explicitlyAllowedMetaKeys;
        }

        return $this->explicitlyAllowedMetaKeys = collect($this->getCasts())
            ->filter(fn ($cast) => $cast === MetaAttribute::class)
            ->keys()
            ->concat($this->getMetaKeys())
            ->filter(fn ($key) => $key !== '*')
            ->unique()
            ->toArray();
    }

    /**
     * Determine if the given key was explicitly allowed.
     */
    public function isExplicitlyAllowedMetaKey(string $key): bool
    {
        return in_array($key, $this->getExplicitlyAllowedMetaKeys());
    }

    /**
     * Determine if the given key is an allowed meta key.
     */
    public function isValidMetaKey(string $key): bool
    {
        if ($this->isMetaUnguarded()) {
            return true;
        }

        if ($this->isExplicitlyAllowedMetaKey($key)) {
            return true;
        }

        if ($this->isModelAttribute($key)) {
            return false;
        }

        return $this->isMetaWildcardSet();
    }

    /**
     * Determine if model table has a given column.
     *
     * @param  [string]  $column
     */
    public function hasColumn($column): bool
    {
        $class = get_class($this);

        if (!isset(static::$metaSchemaColumnsCache[$class])) {
            static::$metaSchemaColumnsCache[$class] = collect(
                $this->getConnection()
                    ->getSchemaBuilder()
                    ->getColumnListing($this->getTable()) ?? []
            )->map(fn ($item) => strtolower($item))->toArray();
        }

        return in_array(strtolower($column), static::$metaSchemaColumnsCache[$class]);
    }

    /**
     * Set the timestamp to take as `now` when looking up and storing meta data.
     */
    public function setMetaTimestamp(?Carbon $timestamp = null): self
    {
        $this->metaTimestamp = $timestamp;
        $this->refreshMetaRelations();

        return $this;
    }

    /**
     * Get the timestamp to take as `now` when looking up and storing meta data.
     */
    public function getMetaTimestamp(): Carbon
    {
        if ($this->metaTimestamp) {
            return $this->metaTimestamp;
        }

        return static::$staticMetaTimestamp ?? Carbon::now();
    }

    /**
     * Relationship to all `Meta` models associated with this model.
     */
    public function allMeta(): MorphMany
    {
        return $this->morphMany($this->getMetaClassName(), 'metable');
    }

    /**
     * Relationship to only published `Meta` models associated with this model.
     */
    public function publishedMeta(): MorphMany
    {
        return $this->allMeta()->publishedBefore($this->getMetaTimestamp());
    }

    /**
     * Relationship to the `Meta` model.
     * Groups by `key` and only shows the latest item that is published yet.
     */
    public function meta(): MorphMany
    {
        return $this->allMeta()->onlyCurrent($this->getMetaTimestamp());
    }

    /**
     * Get Meta model class name.
     */
    protected function getMetaClassName(): string
    {
        return config('multiplex.model', Meta::class);
    }

    /**
     * Get meta value for key.
     *
     * @param  mixed  $default
     * @return mixed
     */
    public function getMeta(string $key, $default = null)
    {
        return $this->findMeta($key)?->value ?? $default;
    }

    /**
     * Get all meta values as a key => value collection.
     */
    public function pluckMeta(bool $withFallbackValues = false): Collection
    {
        return collect($this->getExplicitlyAllowedMetaKeys())
            ->mapWithKeys(fn ($key) => [$key => $withFallbackValues ? $this->getFallbackValue($key) : null])
            ->merge($this->meta->pluck('value', 'key'));
    }

    /**
     * Get the attribute for the given key and run it through the meta accessor.
     */
    protected function getAttributeFromMetaAccessor($key, $value = null)
    {
        $value ??= $this->getMeta($key);

        $accessor = Str::camel('get_' . $key . '_meta');

        if (!method_exists($this, $accessor)) {
            return $value;
        }

        return $this->{$accessor}($value);
    }

    /**
     * {@inheritDoc}
     */
    public function getAttribute($key)
    {
        if (!$this->isValidMetaKey($key)) {
            return parent::getAttribute($key);
        }

        /**
         * If the given key is not explicitly allowed but exists as a real attribute
         * let’s not try to find a meta value for the given key.
         */
        if (
            !$this->isExplicitlyAllowedMetaKey($key)
            && ($attr = parent::getAttribute($key)) !== null
        ) {
            return $attr;
        }

        /**
         * There seems to be no attribute given and no relation so we either have a key
         * explicitly listed as a meta key or the wildcard (*) was used. Let’s get the meta
         * value for the given key and pipe the result through an accessor if possible.
         * If the value is still `null` check if there is a fallback value which typically
         * means there is an equal named database column which we pulled the value from earlier.
         */
        $value = $this->getAttributeFromMetaAccessor($key);

        if ($value === null && !$this->hasMeta($key)) {
            $value = $this->getAttributeFromMetaAccessor($key, $this->getFallbackValue($key));
        }

        /**
         * Finally delegate back to `parent::getAttribute()` if no meta exists.
         */
        return $value ?? value(
            fn () => !$this->hasMeta($key) ? parent::getAttribute($key) : null
        );
    }

    /**
     * Determine wether the given meta exists.
     */
    public function hasMeta(string $key): bool
    {
        return (bool) $this->findMeta($key);
    }

    /**
     * Find current Meta model for the given key.
     *
     * @param  string  $key
     * @return ?Meta
     */
    public function findMeta($key): ?Meta
    {
        if (!$this->exists || !isset($this->id)) {
            return null;
        }

        return $this->meta?->first(fn ($meta) => $meta->key === $key);
    }

    /**
     * Get the dirty meta collection.
     */
    public function getDirtyMeta(): Collection
    {
        return $this->getMetaChanges();
    }

    /**
     * Determine if meta is dirty.
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
        if (func_num_args() === 2 && is_array($key)) {
            return $this->setMetaFromArray($key, Carbon::parse($value));
        }

        return $this->setMetaFromString($key, $value, Carbon::parse($publishAt));
    }

    /**
     * Set meta values from array of $key => $value pairs.
     *
     * @param  ?Carbon  $publishAt
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
        if ($this->isModelAttribute($key) && !$this->isExplicitlyAllowedMetaKey($key)) {
            throw MetaException::modelAttribute($key);
        }

        /**
         * Check if the given key was whitelisted.
         */
        if (!$this->isValidMetaKey($key)) {
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
        $value = with(Str::camel('set_' . $key . '_meta'), function ($mutator) use ($value) {
            if (!method_exists($this, $mutator)) {
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
        } elseif ($this->metaTimestamp) {
            $attributes['published_at'] = $this->metaTimestamp;
        }

        if (($model = $this->findMeta($key))) {
            $model
                ->forceType($this->getCastForMetaKey($key))
                ->forceFill($attributes);

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
        $modelClassName = $this->getMetaClassName();

        return $meta[$key] = (new $modelClassName(['key' => $key]))
            ->forceType($this->getCastForMetaKey($key))
            ->forceFill($attributes);
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
        if (!app()->environment('testing')) {
            DB::beginTransaction();
        }

        $keys = collect(is_array($key) ? $key : [$key]);

        /**
         * If one of the given keys is invalid throw an exception. Otherwise delete all
         * meta records for the given keys from the database.
         */
        $deleted = $keys
            ->each(function ($key) {
                if (!$this->isValidMetaKey($key)) {
                    throw MetaException::invalidKey($key);
                }
            })
            ->filter(function ($key) {
                $latest = $this->findMeta($key);

                return tap(
                    $this->allMeta()->where('key', $key)->delete(),
                    fn ($deleted) => $deleted && $latest && event(new MetaHasBeenRemoved($latest))
                );
            });

        if (!app()->environment('testing')) {
            DB::commit();
        }

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
     */
    public function purgeMeta(): self
    {
        $this->allMeta()->delete();

        return $this;
    }

    /**
     * Get the locally collected meta data.
     */
    public function getMetaChanges(): Collection
    {
        if (!is_null($this->metaChanges)) {
            return $this->metaChanges;
        }

        return $this->resetMetaChanges();
    }

    /**
     * {@inheritDoc}
     */
    public function setAttribute($key, $value)
    {
        if (!$this->isValidMetaKey($key)) {
            return parent::setAttribute($key, $value);
        }

        return $this->setMetaFromString($key, $value);
    }

    /**
     * Refresh the meta relations.
     */
    public function refreshMetaRelations(): self
    {
        if ($this->relationLoaded('allMeta')) {
            $this->unsetRelation('allMeta');
        }

        if ($this->relationLoaded('publishedMeta')) {
            $this->unsetRelation('publishedMeta');
        }

        if ($this->relationLoaded('meta')) {
            $this->unsetRelation('meta');
        }

        return $this;
    }

    /**
     * Store a single Meta model.
     *
     * @return Meta|false
     */
    protected function storeMeta(Meta $meta)
    {
        /**
         * If `$metaTimestamp` is set we probably are storing meta for the future or past.
         */
        if ($currentTime = $this->getMetaTimestamp()) {
            $meta->published_at ??= $currentTime;
        }

        return tap(
            $this->allMeta()->save($meta),
            fn ($model) => $model && event(new MetaHasBeenAdded($model))
        );
    }

    /**
     * Store the meta data from the Meta Collection.
     * Returns `true` if all meta was saved successfully
     * or the `Meta` model if only one key value pair was submitted.
     *
     * @param  string|array|null  $key
     * @param  mixed|null  $value
     * @return bool|Meta
     *
     * @throws MetaException if invalid key is used.
     */
    public function saveMeta($key = null, $value = null)
    {
        /**
         * If we have exactly two arguments set and save the value for the given key.
         */
        if (func_num_args() === 2) {
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
        if (func_num_args() === 0) {
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
        if (!$changes->has($key)) {
            return false;
        }

        /** @var Meta $meta */
        $meta = $changes->pull($key);

        return tap($this->storeMeta($meta), function ($result) {
            if ((bool) $result) {
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
     * @return bool|Meta
     *
     * @throws MetaException if invalid key is used.
     */
    public function saveMetaAt($key = null, $value = null, $publishAt = null)
    {
        $args = func_get_args();

        $previousTimestamp = $this->metaTimestamp;
        $this->setMetaTimestamp(Carbon::parse(array_pop($args)));

        return tap(
            $this->saveMeta(...$args),
            fn () => $this->setMetaTimestamp($previousTimestamp)
        );
    }

    /**
     * Store the model without saving attached meta data.
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
     */
    public function withMetaAt($time = null): self
    {
        $time = $time ? Carbon::parse($time) : null;

        if (
            gettype($this->metaTimestamp) !== gettype($time)
            || !$this->metaTimestamp?->equalTo($time)
        ) {
            $this->refreshMetaRelations();
        }

        $this->setMetaTimestamp($time);

        return $this;
    }

    /**
     * Travel to the current time for storing or retrieving meta.
     */
    public function withCurrentMeta(): self
    {
        return $this->withMetaAt(null);
    }

    /**
     * Travel to the specified point in time for storing or retrieving meta.
     *
     * @param  string|DateTimeInterface|null  $time
     */
    public function scopeTravelTo(Builder $query, $time = null): void
    {
        static::$staticMetaTimestamp = $time ? Carbon::parse($time) : null;
    }

    /**
     * Travel to the current time for storing or retrieving meta.
     */
    public function scopeTravelBack(Builder $query): void
    {
        $query->travelTo();
    }

    /**
     * Query records having meta data for the given key.
     * Pass an array to find records having meta for at least one of the given keys.
     *
     * @param  string|array  $key
     */
    public function scopeWhereHasMeta(Builder $query, $key, string $boolean = 'and'): void
    {
        $keys = is_array($key) ? $key : [$key];
        $method = $boolean === 'or' ? 'orWhereHas' : 'whereHas';

        $query->{$method}('allMeta', function (Builder $query) use ($keys) {
            $query->publishedBefore($this->getMetaTimestamp())->whereIn('key', $keys);
        });
    }

    /**
     * Query records having meta data for the given key with "or" where clause.
     * Pass an array to find records having meta for at least one of the given keys.
     *
     * @param  string|array  $key
     */
    public function scopeOrWhereHasMeta(Builder $query, $key): void
    {
        $query->whereHasMeta($key, 'or');
    }

    /**
     * Query records not having meta data for the given key.
     * Pass an array to find records not having meta for any of the given keys.
     *
     * @param  string|array  $key
     */
    public function scopeWhereDoesntHaveMeta(Builder $query, $key, string $boolean = 'and'): void
    {
        $keys = is_array($key) ? $key : [$key];
        $method = $boolean === 'or' ? 'orWhereDoesntHave' : 'whereDoesntHave';

        $query->{$method}('allMeta', function (Builder $query) use ($keys) {
            $query->publishedBefore($this->getMetaTimestamp())->whereIn('key', $keys);
        }, '=', count($keys));
    }

    /**
     * Query records not having meta data for the given key  with "or" where clause..
     * Pass an array to find records not having meta for any of the given keys.
     *
     * @param  string|array  $key
     */
    public function scopeOrWhereDoesntHaveMeta(Builder $query, $key): void
    {
        $query->whereDoesntHaveMeta($key, 'or');
    }

    /**
     * Query records having meta with a specific key and value.
     * If the `$value` parameter is omitted, the $operator parameter will be considered the value.
     *
     * @param  string|\Closure  $key
     * @param  mixed  $operator
     * @param  mixed  $value
     * @param  string  $boolean
     */
    public function scopeWhereMeta(Builder $query, $key, $operator = null, $value = null, $boolean = 'and'): void
    {
        if (!isset($value)) {
            $value = $operator;
            $operator = '=';
        }

        $method = $boolean === 'or' ? 'orWhereHas' : 'whereHas';

        $query->{$method}('allMeta', function (Builder $query) use ($key, $operator, $value) {
            $query->onlyCurrent($this->getMetaTimestamp())
                ->where(
                    $key instanceof Closure
                        ? $key
                        : fn ($q) => $q->where('meta.key', $key)->whereValue($value, $operator)
                );
        });
    }

    /**
     * Query records having meta with a specific key and value with "or" clause.
     * If the `$value` parameter is omitted, the $operator parameter will be considered the value.
     *
     * @param  string|\Closure  $key
     * @param  mixed  $operator
     * @param  mixed  $value
     */
    public function scopeOrWhereMeta(Builder $query, $key, $operator = null, $value = null): void
    {
        $query->whereMeta($key, $operator, $value, 'or');
    }

    /**
     * Query records having raw meta with a specific key and value without checking type.
     * Make sure that the supplied $value is a string or string castable.
     * If the `$value` parameter is omitted, the $operator parameter will be considered the value.
     *
     * @param  mixed  $operator
     * @param  mixed  $value
     * @param  string  $boolean
     */
    public function scopeWhereRawMeta(Builder $query, string $key, $operator, $value = null, $boolean = 'and'): void
    {
        if (!isset($value)) {
            $value = $operator;
            $operator = '=';
        }

        $method = $boolean === 'or' ? 'orWhereHas' : 'whereHas';

        $query->{$method}('allMeta', function (Builder $query) use ($key, $operator, $value) {
            $query->onlyCurrent($this->getMetaTimestamp())
                ->where('meta.key', $key)->where('value', $operator, $value);
        });
    }

    /**
     * Query records having raw meta with a specific key and value without checking type with "or" clause.
     * Make sure that the supplied $value is a string or string castable.
     * If the `$value` parameter is omitted, the $operator parameter will be considered the value.
     *
     * @param  mixed  $operator
     * @param  mixed  $value
     */
    public function scopeOrWhereRawMeta(Builder $query, string $key, $operator, $value = null): void
    {
        $query->whereRawMeta($key, $operator, $value, 'or');
    }

    /**
     * Query records having meta with a specific value and the given type.
     * If the `$value` parameter is omitted, the $operator parameter will be considered the value.
     *
     * Available types can be found in `config('multiplex.datatypes')`.
     *
     * @param  mixed  $operator
     * @param  mixed  $value
     * @param  string  $boolean
     */
    public function scopeWhereMetaOfType(Builder $query, string $type, string $key, $operator, $value = null, $boolean = 'and'): void
    {
        if (!isset($value)) {
            $value = $operator;
            $operator = '=';
        }

        $method = $boolean === 'or' ? 'orWhereHas' : 'whereHas';

        $query->{$method}('allMeta', function (Builder $query) use ($type, $key, $operator, $value) {
            $query->onlyCurrent($this->getMetaTimestamp())
                ->where('meta.key', $key)->whereValue($value, $operator, $type);
        });
    }

    /**
     * Query records having meta with a specific value and the given type with "or" clause.
     * If the `$value` parameter is omitted, the $operator parameter will be considered the value.
     *
     * Available types can be found in `config('multiplex.datatypes')`.
     *
     * @param  mixed  $operator
     * @param  mixed  $value
     */
    public function scopeOrWhereMetaOfType(Builder $query, string $type, string $key, $operator, $value = null): void
    {
        $query->whereMetaOfType($type, $key, $operator, $value, 'or');
    }

    /**
     * Query records having one of the given values for the given key.
     *
     * @param  string  $boolean
     */
    public function scopeWhereMetaIn(Builder $query, string $key, array $values, $boolean = 'and'): void
    {
        $method = $boolean === 'or' ? 'orWhereHas' : 'whereHas';

        $query->{$method}('allMeta', function (Builder $query) use ($key, $values) {
            $query->onlyCurrent($this->getMetaTimestamp())
                ->where('meta.key', $key)->whereValueIn($values);
        });
    }

    /**
     * Query records having one of the given values for the given key with "or" clause.
     */
    public function scopeOrWhereMetaIn(Builder $query, string $key, array $values): void
    {
        $query->whereMetaIn($key, $values, 'or');
    }

    /**
     * Query records where meta does not exist or is empty.
     *
     * @param  string|array  $key
     */
    public function scopeWhereMetaEmpty(Builder $query, $key, string $boolean = 'and'): void
    {
        $keys = is_array($key) ? $key : [$key];

        $query->where(function (Builder $query) use ($keys) {
            $query->whereDoesntHaveMeta($keys)->orWhereMeta(
                fn (Builder $q) => $q->whereIn('meta.key', $keys)->whereValueEmpty()
            );
        }, null, null, $boolean);
    }

    /**
     * Query records where meta does not exist or is empty with "or" clause.
     *
     * @param  string|array  $key
     */
    public function scopeOrWhereMetaEmpty(Builder $query, $key): void
    {
        $query->whereMetaEmpty($key, 'or');
    }

    /**
     * Query records where meta exists and is not empty.
     *
     * @param  string|array  $key
     */
    public function scopeWhereMetaNotEmpty(Builder $query, $key, string $boolean = 'and'): void
    {
        $keys = is_array($key) ? $key : [$key];
        $method = $boolean === 'or' ? 'orWhereHas' : 'whereHas';

        $query->{$method}('allMeta', function (Builder $query) use ($keys) {
            $query->onlyCurrent($this->getMetaTimestamp())
                ->whereIn('meta.key', $keys)
                ->whereValueNotEmpty();
        }, '=', count($keys));
    }

    /**
     * Query records where meta exists and is not empty with "or" clause.
     *
     * @param  string|array  $key
     */
    public function scopeOrWhereMetaNotEmpty(Builder $query, $key): void
    {
        $query->whereMetaNotEmpty($key, 'or');
    }
}
