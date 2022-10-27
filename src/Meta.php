<?php

namespace Kolossal\Multiplex;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Kolossal\Multiplex\DataType\Registry;

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
 * @property-read bool $is_current
 * @property-read bool $is_planned
 * @property-read ?string $raw_value
 * @property-read MorphTo $metable
 *
 * @method static Builder|Meta joinLatest($now = null)
 * @method static Builder|Meta newModelQuery()
 * @method static Builder|Meta newQuery()
 * @method static Builder|Meta onlyCurrent($now = null)
 * @method static Builder|Meta published()
 * @method static Builder|Meta planned()
 * @method static Builder|Meta publishedBefore($time = null)
 * @method static Builder|Meta publishedAfter($time = null)
 * @method static Builder|Meta query()
 * @method static Builder|Meta whereCreatedAt($value)
 * @method static Builder|Meta whereId($value)
 * @method static Builder|Meta whereKey($value)
 * @method static Builder|Meta whereMetableId($value)
 * @method static Builder|Meta whereMetableType($value)
 * @method static Builder|Meta wherePublishedAt($value)
 * @method static Builder|Meta whereType($value)
 * @method static Builder|Meta whereUpdatedAt($value)
 * @method static Builder|Meta whereValue($value)
 * @method static Builder|Meta whereValueEmpty()
 * @method static Builder|Meta whereValueIn(array $values, ?string $type = null)
 * @method static Builder|Meta whereValueNotEmpty()
 * @method static Builder|Meta withoutCurrent($now = null)
 * @method static Builder|Meta withoutHistory($now = null)
 * @mixin \Eloquent
 */
class Meta extends Model
{
    use HasFactory;
    use HasTimestamps;

    protected $guarded = [
        'id',
        'metable_type',
        'metable_id',
        'type',
    ];

    /**
     * Hide the aggregate columns from our custom join scope `scopeJoinLatest()`.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'id_aggregate',
        'published_at_aggregate',
        'key_aggregate',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'published_at' => 'datetime',
    ];

    protected $table = 'meta';

    protected $cachedValue;

    protected ?string $forceType = null;

    public static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            /** @var Meta $model */
            $model->attributes['published_at'] ??= Carbon::now();
        });
    }

    /**
     * Metable Relation.
     *
     * @return MorphTo
     */
    public function metable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Set forced type to be used.
     *
     * @param  ?string  $value
     * @return self
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
     * @return mixed
     *
     * @throws Exceptions\DataTypeException
     */
    public function getValueAttribute()
    {
        return $this->cachedValue ??= $this->getDataTypeRegistry()
            ->getHandlerForType($this->attributes['type'])
            ->unserializeValue($this->attributes['value']);
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
     *
     * @return bool
     */
    public function getIsCurrentAttribute(): bool
    {
        /** @phpstan-ignore-next-line  */
        return $this->metable->meta
            ?->first(fn (Meta $meta) => $meta->key === $this->key)
            ?->is($this) ?? false;
    }

    /**
     * Determine if this is a planned record not yet published.
     *
     * @return bool
     */
    public function getIsPlannedAttribute(): bool
    {
        return $this->published_at?->isFuture() ?? false;
    }

    /**
     * Retrieve the underlying serialized value.
     *
     * @return ?string
     */
    public function getRawValueAttribute(): ?string
    {
        return $this->attributes['value'] ?? null;
    }

    /**
     * Load the datatype Registry from the container.
     *
     * @return Registry
     */
    public static function getDataTypeRegistry(): Registry
    {
        return app('multiplex.datatype.registry');
    }

    /**
     * Query records where value is considered empty.
     *
     * @param  Builder  $query
     * @return void
     */
    public function scopeWhereValueEmpty(Builder $query): void
    {
        $query->where(fn ($q) => $q->whereNull('value')->orWhere('value', '=', ''));
    }

    /**
     * Query records where value is considered not empty.
     *
     * @param  Builder  $query
     * @return void
     */
    public function scopeWhereValueNotEmpty(Builder $query): void
    {
        $query->where(fn ($q) => $q->whereNotNull('value')->where('value', '!=', ''));
    }

    /**
     * Query records where value equals the serialized version of the given value.
     * If `$type` is omited the type will be taken from the data type registry.
     *
     * @param  Builder  $query
     * @param  mixed  $operator
     * @param  mixed  $value
     * @param  ?string  $type
     * @return void
     */
    public function scopeWhereValue(Builder $query, $value, $operator = '=', ?string $type = null): void
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
     * @param  Builder<Meta>  $query
     * @param  array  $values
     * @param  ?string  $type
     * @return void
     */
    public function scopeWhereValueIn(Builder $query, array $values, ?string $type = null): void
    {
        $registry = $this->getDataTypeRegistry();

        $serializedValues = collect($values)->map(function ($value) use ($registry, $type) {
            $type = $type ?? $registry->getTypeForValue($value);

            return [
                'type' => $type,
                'value' => $registry->getHandlerForType($type)->serializeValue($value),
            ];
        });

        $query->where(function ($query) use ($serializedValues) {
            $serializedValues->groupBy('type')->each(function ($values, $type) use ($query) {
                $query->orWhere(fn ($q) => $q->where('type', $type)->whereIn('value', $values->pluck('value')));
            });
        });
    }

    /**
     * Query published meta only.
     *
     * @param  Builder<Meta>  $query
     * @return void
     */
    public function scopePublished(Builder $query): void
    {
        $query->publishedBefore();
    }

    /**
     * Query meta published before given timestamp.
     *
     * @param  Builder<Meta>  $query
     * @param  string|\DateTimeInterface|null  $time
     * @return void
     */
    public function scopePublishedBefore(Builder $query, $time = null): void
    {
        $query->where('meta.published_at', '<=', $time ? Carbon::parse($time) : Carbon::now());
    }

    /**
     * Query planned meta only.
     *
     * @param  Builder<Meta>  $query
     * @return void
     */
    public function scopePlanned(Builder $query): void
    {
        $query->publishedAfter();
    }

    /**
     * Query meta published after given timestamp.
     *
     * @param  Builder<Meta>  $query
     * @param  string|\DateTimeInterface|null  $time
     * @return void
     */
    public function scopePublishedAfter(Builder $query, $time = null): void
    {
        $query->where('meta.published_at', '>', $time ? Carbon::parse($time) : Carbon::now());
    }

    /**
     * Query records not being the latest meta for any key.
     *
     * @param  Builder<Meta>  $query
     * @param  string|\DateTimeInterface|null  $now
     * @return void
     */
    public function scopeWithoutCurrent(Builder $query, $now = null): void
    {
        $query->whereNotIn('id', static::query()->joinLatest($now)->select('meta.id'));
    }

    /**
     * Query records not being the latest meta for any key.
     *
     * @param  Builder<Meta>  $query
     * @param  string|\DateTimeInterface|null  $now
     * @return void
     */
    public function scopeWithoutHistory(Builder $query, $now = null): void
    {
        $query->where(function ($query) use ($now) {
            $query->publishedAfter($now)
                ->orWhereIn('id', static::query()->joinLatest($now)->select('meta.id'));
        });
    }

    /**
     * Query only the latest meta for any key.
     *
     * @param  Builder<Meta>  $query
     * @param  string|\DateTimeInterface|null  $now
     * @return void
     */
    public function scopeOnlyCurrent(Builder $query, $now = null): void
    {
        $query->joinLatest($now);
    }

    /**
     * Add a join to find only records matching or not matching the latest published record per key.
     * Will only query for meta records from the past by default.
     *
     * @param  Builder<Meta>  $query
     * @param  string|\DateTimeInterface|null  $now
     * @return void
     */
    public function scopeJoinLatest(Builder $query, $now = null): void
    {
        /**
         * Create a subquery based on the given query and find the most recent publishing
         * date by getting the most recent `published_at` timestamp in the past.
         */
        $latestPublishAt = $query->clone()
            ->select('key', DB::raw('MAX(published_at) as published_at_aggregate'))
            ->publishedBefore($now)
            ->groupBy('key');

        /**
         * There may be multiple meta data with the exact same `published_at` timestamp
         * so let's find the record that was last saved by querying for the maximum `id` in a join.
         */
        $maxId = $query->clone()
            ->select(DB::raw('`key` AS key_aggregate'), 'published_at', DB::raw('MAX(id) as id_aggregate'))
            ->groupBy('key', 'published_at');

        /**
         * Now that we have subqueries to join let’s build the complete query
         * and look for the record that matches the most recent entry for every `key`.
         */
        $query->joinSub($maxId, 'max_id', function ($join) use ($latestPublishAt) {
            $join->on('meta.id', '=', 'max_id.id_aggregate')
                ->joinSub($latestPublishAt, 'max_published_at', function ($join) {
                    $join->on('max_id.published_at', '=', 'max_published_at.published_at_aggregate')
                        ->on('max_id.key_aggregate', '=', 'max_published_at.key');
                });
        });
    }
}
