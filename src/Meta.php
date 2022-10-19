<?php

namespace Kolossal\Meta;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Kolossal\Meta\DataType\Registry;

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
     * Hide the aggregate columns from our custom join scope `scopeGroupByKeyTakeLatest()`.
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

        $this->attributes['type'] = $registry->getTypeForValue($value);

        $this->attributes['value'] = $registry->getHandlerForType($this->attributes['type'])
            ->serializeValue($value);

        $this->cachedValue = null;
    }

    /**
     * Retrieve the underlying serialized value.
     *
     * @return string
     */
    public function getRawValueAttribute(): string
    {
        return $this->attributes['value'];
    }

    /**
     * Load the datatype Registry from the container.
     *
     * @return Registry
     */
    public static function getDataTypeRegistry(): Registry
    {
        return app('meta.datatype.registry');
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
        $serializedValue = $registry->getHandlerForType($type)->serializeValue($value);

        $query->where('type', $type)->where('value', $operator, $serializedValue);
    }

    /**
     * Query records where value equals the serialized version of one of the given values.
     * If `$type` is omited the type will be taken from the data type registry.
     *
     * @param  Builder  $query
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
     * @param  Builder  $query
     * @param  Carbon|null  $now
     * @return void
     */
    public function scopePublished(Builder $query, ?Carbon $now = null): void
    {
        $query->where('meta.published_at', '<=', $now ?? Carbon::now());
    }

    /**
     * Query only the latest meta for any key.
     * Will only query for meta records from the past by default.
     *
     * @param  Builder  $query
     * @param  Carbon|null  $now
     * @return void
     */
    public function scopeGroupByKeyTakeLatest(Builder $query, ?Carbon $now = null): void
    {
        /**
         * Create a subquery based on the given query and find the most recent publishing
         * date by getting the most recent `published_at` timestamp in the past.
         */
        $latestPublishAt = $query->clone()
            ->select('key', DB::raw('MAX(published_at) as published_at_aggregate'))
            ->where('published_at', '<=', $now ?? Carbon::now())
            ->groupBy('key');

        /**
         * There may be multiple meta data with the exact same `published_at` timestamp
         * so let's find the record that was last saved by querying for the maximum `id` in a join.
         */
        $maxId = $query->clone()
            ->select(DB::raw('key AS key_aggregate'), 'published_at', DB::raw('MAX(id) as id_aggregate'))
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
