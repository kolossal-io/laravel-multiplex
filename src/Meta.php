<?php

namespace Kolossal\Multiplex;

use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Kolossal\Multiplex\Database\Eloquent\MetaBuilder;
use Kolossal\Multiplex\DataType\Registry;
use Kolossal\Multiplex\Tests\Factories\MetaFactory;

/**
 * Kolossal\Multiplex\Meta
 *
 * @property int $id
 * @property string $metable_type
 * @property int $metable_id
 * @property string $key
 * @property mixed $value
 * @property string|null $type
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int $meta_row_num
 *
 * @mixin \Eloquent
 */
class Meta extends Model
{
    use HasConfigurableMorphType;

    /** @use HasFactory<MetaFactory> */
    use HasFactory;

    use HasTimestamps;

    protected static string $builder = MetaBuilder::class;

    protected $guarded = [
        'id',
        'metable_type',
        'metable_id',
        'type',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'published_at' => 'datetime',
    ];

    protected $table = 'meta';

    protected mixed $cachedValue = null;

    protected ?string $forceType = null;

    public static function boot(): void
    {
        parent::boot();

        static::saving(function ($model): void {
            /** @var Meta $model */
            $model->attributes['published_at'] ??= Carbon::now();
        });
    }

    /**
     * Metable Relation.
     *
     * @return MorphTo<Model, $this>
     */
    public function metable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Set forced type to be used.
     */
    public function forceType(?string $value): self
    {
        $this->forceType = $value;

        return $this;
    }

    /**
     * Accessor for value.
     *
     * Will unserialize the value before returning it.
     *
     * Successive access will be loaded from cache.
     *
     *
     * @throws Exceptions\DataTypeException
     */
    public function getValueAttribute(): mixed
    {
        if ($this->cachedValue) {
            return $this->cachedValue;
        }

        if (!isset($this->attributes['type']) || !isset($this->attributes['value'])) {
            return null;
        }

        /** @var string $type */
        $type = $this->attributes['type'];

        /** @var string $value */
        $value = $this->attributes['value'];

        return $this->cachedValue = $this->getDataTypeRegistry()
            ->getHandlerForType($type)
            ->unserializeValue($value);
    }

    /**
     * Mutator for value.
     *
     * The `type` attribute will be automatically updated to match the datatype of the input.
     *
     * @param  mixed  $value
     *
     * @throws Exceptions\DataTypeException
     */
    public function setValueAttribute($value): void
    {
        $registry = $this->getDataTypeRegistry();

        $this->attributes['type'] = $this->forceType ?? $registry->getTypeForValue($value);

        $this->attributes['value'] = is_null($value)
            ? $value
            : $registry->getHandlerForType($this->attributes['type'])->serializeValue($value);

        $this->cachedValue = null;
    }

    /**
     * Determine if this is the most recent meta for this key.
     */
    public function getIsCurrentAttribute(): bool
    {
        // @phpstan-ignore-next-line
        return $this->metable?->meta
            ?->first(fn(Meta $meta) => $meta->key === $this->key)
            ?->is($this) ?? false;
    }

    /**
     * Determine if this is a planned record not yet published.
     */
    public function getIsPlannedAttribute(): bool
    {
        return $this->published_at?->isFuture() ?? false;
    }

    /**
     * Retrieve the underlying serialized value.
     */
    public function getRawValueAttribute(): mixed
    {
        return $this->attributes['value'] ?? null;
    }

    /**
     * Load the datatype Registry from the container.
     */
    public static function getDataTypeRegistry(): Registry
    {
        return app('multiplex.datatype.registry');
    }

    /**
     * Query records where value is considered empty.
     *
     * @param  MetaBuilder<Meta>  $query
     */
    public function scopeWhereValueEmpty(MetaBuilder $query): void
    {
        $query->where(fn($q) => $q->whereNull('value')->orWhere('value', '=', ''));
    }

    /**
     * Query records where value is considered not empty.
     *
     * @param  MetaBuilder<Meta>  $query
     */
    public function scopeWhereValueNotEmpty(MetaBuilder $query): void
    {
        $query->where(fn($q) => $q->whereNotNull('value')->where('value', '!=', ''));
    }

    /**
     * Query records where value equals the serialized version of the given value.
     * If `$type` is omited the type will be taken from the data type registry.
     *
     * @param  MetaBuilder<Meta>  $query
     * @param  mixed  $value
     * @param  mixed  $operator
     */
    public function scopeWhereValue(MetaBuilder $query, $value, $operator = '=', ?string $type = null): void
    {
        $registry = $this->getDataTypeRegistry();

        $type ??= $registry->getTypeForValue($value);

        $serializedValue = is_null($value)
            ? $value
            : $registry->getHandlerForType($type)->serializeValue($value);

        $query->where('type', $type)->where('value', $operator, $serializedValue);
    }

    /**
     * Query records where value equals the serialized version of one of the given values.
     * If `$type` is omited the type will be taken from the data type registry.
     *
     * @param  MetaBuilder<Meta>  $query
     * @param  array<mixed>  $values
     */
    public function scopeWhereValueIn(MetaBuilder $query, array $values, ?string $type = null): void
    {
        $registry = $this->getDataTypeRegistry();

        $serializedValues = collect($values)->map(function ($value) use ($registry, $type) {
            $type = $type ?? $registry->getTypeForValue($value);

            return [
                'type' => $type,
                'value' => $registry->getHandlerForType($type)->serializeValue($value),
            ];
        });

        $query->where(function ($query) use ($serializedValues): void {
            $serializedValues->groupBy('type')->each(function ($values, $type) use ($query) {
                $query->orWhere(fn($q) => $q->where('type', $type)->whereIn('value', $values->pluck('value')));
            });
        });
    }

    /**
     * Query published meta only.
     *
     * @param  MetaBuilder<Meta>  $query
     */
    public function scopePublished(MetaBuilder $query): void
    {
        $query->publishedBefore();
    }

    /**
     * Query meta published before given timestamp.
     *
     * @param  MetaBuilder<Meta>  $query
     * @param  string|\DateTimeInterface|null  $time
     */
    public function scopePublishedBefore(MetaBuilder $query, $time = null): void
    {
        $query->where(
            'meta.published_at',
            '<=',
            $time ? Carbon::parse($time) : Carbon::now()
        );
    }

    /**
     * Query planned meta only.
     *
     * @param  MetaBuilder<Meta>  $query
     */
    public function scopePlanned(MetaBuilder $query): void
    {
        $query->publishedAfter();
    }

    /**
     * Query meta published after given timestamp.
     *
     * @param  MetaBuilder<Meta>  $query
     * @param  string|\DateTimeInterface|null  $time
     */
    public function scopePublishedAfter(MetaBuilder $query, $time = null): void
    {
        $query->where(
            'meta.published_at',
            '>',
            $time ? Carbon::parse($time) : Carbon::now()
        );
    }

    /**
     * Query only historical meta for any key.
     *
     * @param  MetaBuilder<Meta>  $query
     * @param  string|\DateTimeInterface|null  $now
     */
    public function scopeHistory(MetaBuilder $query, $now = null): void
    {
        if ($query->isRelationQuery()) {
            trigger_error(
                'Warning: Using the history() scope on of the meta relations is not supported. Please use the historicMeta() relation instead.',
                E_USER_WARNING
            );

            return;
        }

        /** @var MetaBuilder<Meta> $window */
        $window = static::query();

        $window->withRowNumber(true);

        $query->fromSub($window, 'meta')
            ->publishedBefore($now)
            ->where('meta_row_num', '>', 1);
    }

    /**
     * Query only historical meta for any key.
     *
     * @param  MetaBuilder<Meta>  $query
     * @param  string|\DateTimeInterface|null  $now
     */
    public function scopeOnlyHistory(MetaBuilder $query, $now = null): void
    {
        $query->history($now);
    }

    /**
     * Query only the latest meta for any key.
     *
     * @param  MetaBuilder<Meta>  $query
     * @param  string|\DateTimeInterface|null  $now
     */
    public function scopeCurrent(MetaBuilder $query, $now = null): void
    {
        if ($query->isRelationQuery()) {
            trigger_error(
                'Warning: Using the current() scope on of the meta relations is not supported. Please use the meta() relation instead.',
                E_USER_WARNING
            );

            return;
        }

        /** @var MetaBuilder<Meta> $window */
        $window = static::query();

        $window->withRowNumber(true)
            ->publishedBefore($now);

        $query->fromSub($window, 'meta')
            ->where('meta_row_num', 1);
    }

    /**
     * Query only the latest meta for any key.
     *
     * @param  MetaBuilder<Meta>  $query
     * @param  string|\DateTimeInterface|null  $now
     */
    public function scopeOnlyCurrent(MetaBuilder $query, $now = null): void
    {
        $query->current($now);
    }
}
