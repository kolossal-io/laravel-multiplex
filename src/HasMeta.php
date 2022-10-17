<?php

namespace Kolossal\Meta;

use Illuminate\Database\Eloquent\Relations\MorphMany;
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
     * Determine if the given key is an allowed meta key.
     *
     * @param  string  $key
     * @return bool
     */
    public function isValidMetaKey(string $key): bool
    {
        if ($this->isModelAttribute($key)) {
            return false;
        }

        if (! $this->isMetaGuarded()) {
            return true;
        }

        return in_array($key, $this->getMetaKeys());
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
     * Relationship to the `Meta` model.
     * Groups by `key` and only shows the latest item that is published yet.
     *
     * @return MorphMany
     */
    public function meta(): MorphMany
    {
        return $this->allMeta()
            ->addSelect([
                'latest_id' => Meta::select('m.id')
                    ->from('meta as m')
                    ->where('m.published_at', '<=', $this->getMetaTimestamp())
                    ->whereColumn('m.metable_id', 'meta.metable_id')
                    ->whereColumn('m.metable_type', 'meta.metable_type')
                    ->whereColumn('m.key', 'meta.key')
                    ->orderByDesc('m.published_at')
                    ->orderByDesc('m.id')
                    ->take(1),
            ])
            ->whereColumn('id', 'latest_id')
            ->groupBy('key');
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

        if ($this->isModelAttribute($key)) {
            throw MetaException::modelAttribute($key);
        }

        if (! $this->isValidMetaKey($key)) {
            throw MetaException::invalidKey($key);
        }

        $meta = $this->getMetaChanges();

        $value = with(Str::camel('set_'.$key.'_meta'), function ($mutator) use ($value) {
            if (! method_exists($this, $mutator)) {
                return $value;
            }

            return $this->{$mutator}($value);
        });

        $attributes = ['value' => $value];

        if ($publishAt) {
            $attributes['published_at'] = $publishAt;
        }

        return $meta[$key] = ($meta->has($key)
            ? $meta[$key]->replicate(['published_at'])
            : new Meta(['key' => $key])
        )->forceFill($attributes);
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
     * Reset the meta collection for the given key.
     * Resets the entire collection if nothing is passed.
     *
     * @param  ?string  $key
     */
    public function resetMeta(?string $key = null): Collection
    {
        if ($key && $this->metaChanges) {
            $this->metaChanges->pull($key)?->delete();

            return $this->metaChanges;
        }

        return $this->metaChanges = new Collection;
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

        $deleted = $keys
            ->each(function ($key) {
                if (! $this->isValidMetaKey($key)) {
                    throw MetaException::invalidKey($key);
                }
            })
            ->every(fn ($key) => $this->allMeta()->where('key', $key)->delete());

        DB::commit();

        if ($deleted) {
            $keys->each(fn ($key) => $this->resetMeta($key));
        }

        return $deleted;
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

        return $this->resetMeta();
    }

    /**
     * {@inheritDoc}
     *
     * @throws MetaException if invalid key is used.
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
     * @return void
     */
    public function refreshMeta(): void
    {
        if ($this->relationLoaded('allMeta')) {
            $this->unsetRelation('allMeta');
        }

        if ($this->relationLoaded('meta')) {
            $this->unsetRelation('meta');
        }
    }

    /**
     * Store a single Meta model.
     *
     * @param  Meta  $meta
     * @return Meta|false
     */
    protected function storeMeta(Meta $meta)
    {
        if ($this->metaTimestamp) {
            $meta->published_at ??= $this->metaTimestamp;
        }

        return $this->allMeta()->save($meta);
    }

    /**
     * Store the meta data from the Meta Collection.
     * Returns `true` if all meta was saved successfully.
     *
     * @param  ?string  $key
     * @param  mixed  $value
     * @return bool
     */
    public function saveMeta(?string $key = null, $value = null): bool
    {
        if (empty(func_get_args())) {
            return tap($this->getMetaChanges()->every(
                fn (Meta $meta) => $this->storeMeta($meta)
            ), fn () => $this->refreshMeta());
        }

        if (! isset(func_get_args()[1])) {
            /** @var Meta $meta */
            $meta = $this->getMetaChanges()->pull($key);

            return tap((bool) $this->storeMeta($meta), function ($saved) {
                if ($saved) {
                    $this->refreshMeta();
                }
            });
        }

        $this->setMeta($key, $value);

        return $this->saveMeta($key);
    }

    /**
     * Immediately save the given meta for a specific publishing time.
     *
     * @param  ?string  $key
     * @param  mixed  $value
     * @param  string|DateTimeInterface|null  $publishAt
     *
     * @throws MetaException if invalid key is used.
     */
    public function saveMetaAt(?string $key = null, $value = null, $publishAt = null)
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
}
