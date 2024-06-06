<?php

namespace Kolossal\Multiplex;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

trait HasConfigurableMorphType
{
    /**
     * Initialize the trait.
     */
    public function initializeHasConfigurableMorphType(): void
    {
        if (!$this->usesUniqueIdsInMorphType()) {
            return;
        }

        // @codeCoverageIgnoreStart
        if (property_exists($this, 'usesUniqueIds')) {
            $this->usesUniqueIds = true;

            return;
        }

        static::creating(function (self $model) {
            foreach ($model->uniqueIds() as $column) {
                if (empty($model->{$column})) {
                    $model->{$column} = $model->newUniqueId();
                }
            }
        });
        // @codeCoverageIgnoreEnd
    }

    /**
     * Get the morph key type.
     */
    protected function morphType(): string
    {
        if (is_string(config('multiplex.morph_type')) && in_array(config('multiplex.morph_type'), ['uuid', 'ulid'])) {
            return config('multiplex.morph_type');
        }

        return 'integer';
    }

    /**
     * Determine if unique ids are used in morphTo relation.
     */
    protected function usesUniqueIdsInMorphType(): bool
    {
        return $this->morphType() !== 'integer';
    }

    /**
     * Determine if the given value is a valid unique id.
     */
    protected function isValidUniqueMorphId(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        if ($this->morphType() === 'ulid') {
            return Str::isUlid($value);
        }

        if ($this->morphType() === 'uuid') {
            return Str::isUuid($value);
        }

        return false;
    }

    /**
     * Get the columns that should receive a unique identifier.
     *
     * @return array<string>
     */
    public function uniqueIds(): array
    {
        if (!$this->usesUniqueIdsInMorphType()) {
            return [];
        }

        return [$this->getKeyName()];
    }

    /**
     * Generate a new UUID for the model.
     */
    public function newUniqueId(): ?string
    {
        if (!$this->usesUniqueIdsInMorphType()) {
            return null;
        }

        if ($this->morphType() === 'ulid') {
            return strtolower((string) Str::ulid());
        }

        return (string) Str::orderedUuid();
    }

    /**
     * Retrieve the model for a bound value.
     *
     * @param  \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Eloquent\Relations\Relation<\Illuminate\Database\Eloquent\Model>  $query
     * @param  mixed  $value
     * @param  string|null  $field
     * @return \Illuminate\Contracts\Database\Eloquent\Builder
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        if (!$this->usesUniqueIdsInMorphType()) {
            return parent::resolveRouteBindingQuery($query, $value, $field);
        }

        if ($field && is_string($value) && in_array($field, $this->uniqueIds()) && !$this->isValidUniqueMorphId($value)) {
            /** @var \Illuminate\Database\Eloquent\Model $this */
            throw (new ModelNotFoundException)->setModel(get_class($this), $value);
        }

        if (!$field && is_string($value) && in_array($this->getRouteKeyName(), $this->uniqueIds()) && !$this->isValidUniqueMorphId($value)) {
            /** @var \Illuminate\Database\Eloquent\Model $this */
            throw (new ModelNotFoundException)->setModel(get_class($this), $value);
        }

        return parent::resolveRouteBindingQuery($query, $value, $field);
    }

    /**
     * Get the auto-incrementing key type.
     */
    public function getKeyType(): string
    {
        if (in_array($this->getKeyName(), $this->uniqueIds())) {
            return 'string';
        }

        return $this->keyType;
    }

    /**
     * Get the value indicating whether the IDs are incrementing.
     */
    public function getIncrementing(): bool
    {
        if (in_array($this->getKeyName(), $this->uniqueIds())) {
            return false;
        }

        return $this->incrementing;
    }
}
