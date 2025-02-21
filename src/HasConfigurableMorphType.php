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
        // @phpstan-ignore function.alreadyNarrowedType
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
        return in_array($this->morphType(), ['uuid', 'ulid']);
    }

    /**
     * Determine if the given value is a valid unique id.
     */
    protected function isValidUniqueMorphId(string $value): bool
    {
        if ($this->morphType() === 'ulid') {
            return Str::isUlid($value);
        }

        return Str::isUuid($value);
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
     * @param  \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Eloquent\Relations\Relation<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model, \Kolossal\Multiplex\Meta>  $query
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
            /** @var class-string<\Illuminate\Database\Eloquent\Model> $class */
            $class = get_class($this);
            throw (new ModelNotFoundException)->setModel($class, $value);
        }

        if (!$field && is_string($value) && in_array($this->getRouteKeyName(), $this->uniqueIds()) && !$this->isValidUniqueMorphId($value)) {
            /** @var class-string<\Illuminate\Database\Eloquent\Model> $class */
            $class = get_class($this);
            throw (new ModelNotFoundException)->setModel($class, $value);
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
