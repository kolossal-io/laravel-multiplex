<?php

namespace Kolossal\Meta;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
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
     * Collection of the meta data for this model.
     *
     * @var Collection|null
     */
    protected ?Collection $metaCollection = null;

    /**
     * Cache storage for table column names.
     *
     * @var array
     */
    protected array $schemaColumnsCache = [];

    /**
     * Boot the model trait.
     *
     * @return void
     */
    public static function bootHasMeta()
    {
        static::saved(function ($model) {
            $model->saveMeta();
        });
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
    public function isMetaKey(string $key): bool
    {
        if (
            $this->hasSetMutator($key) ||
            $this->hasAttributeSetMutator($key) ||
            $this->isEnumCastable($key) ||
            $this->isClassCastable($key) ||
            str_contains($key, '->') ||
            $this->hasColumn($key) ||
            array_key_exists($key, parent::getAttributes())
        ) {
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

        if (! isset($this->schemaColumnsCache[$class])) {
            $this->schemaColumnsCache[$class] = collect(
                $this->getConnection()
                    ->getSchemaBuilder()
                    ->getColumnListing($this->getTable()) ?? []
            )->map(fn ($item) => strtolower($item))->toArray();
        }

        return in_array(strtolower($column), $this->schemaColumnsCache[$class]);
    }

    /**
     * Relationship to the `Meta` model.
     *
     * @return MorphMany
     */
    public function meta(): MorphMany
    {
        return $this->morphMany($this->getMetaClassName(), 'metable');
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
     * @throws MetaException if unknown key is used.
     */
    public function setMeta($key, $value)
    {
        if (is_array($key)) {
            return $this->setMetaArray($key);
        }

        $this->setMetaString($key, $value);
    }

    protected function setMetaString($key, $value)
    {
        $key = strtolower($key);

        if (! $this->isMetaKey($key)) {
            throw MetaException::unknownKey($key);
        }

        $meta = $this->getMetaCollection();

        if ($meta->has($key)) {
            return tap($meta[$key], fn ($item) => $item->value = $value);
        }

        return $meta[$key] = new Meta([
            'key' => $key,
            'value' => $value,
        ]);
    }

    /**
     * Set meta values from array of $key => $value pairs.
     *
     * @param  array  $metas
     * @return Collection
     */
    protected function setMetaArray(array $metas): Collection
    {
        return collect($metas)->map(function ($value, $key) {
            return $this->setMetaString($key, $value);
        });
    }

    /**
     * Get the locally collected meta data.
     *
     * @return Collection
     */
    public function getMetaCollection(): Collection
    {
        if (! is_null($this->metaCollection)) {
            return $this->metaCollection;
        }

        if ($this->exists && ! is_null($this->meta)) {
            return $this->metaCollection = $this->meta->keyBy('key');
        }

        return $this->metaCollection = new Collection;
    }

    /**
     * Store the meta data from the Meta Collection.
     *
     * @return void
     */
    public function saveMeta()
    {
        foreach ($this->getMetaCollection() as $meta) {
            /** @var Meta $meta */
            if ($meta->isDirty()) {
                $this->meta()->save($meta);
            }
        }
    }
}
