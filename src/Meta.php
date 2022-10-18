<?php

namespace Kolossal\Meta;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
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

    protected $hidden = [
        'latest_id',
    ];

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
    protected function getDataTypeRegistry(): Registry
    {
        return app('meta.datatype.registry');
    }

    /**
     * Scope only the latest meta for any key.
     * Will only query for meta records from the past by default.
     *
     * @param  Builder  $query
     * @param  Carbon|null  $now
     * @return Builder
     */
    public function scopeGroupByKeyLatest(Builder $query, ?Carbon $now = null): Builder
    {
        return $query->addSelect([
            'latest_id' => Meta::select('m.id')
                ->from('meta as m')
                ->where('m.published_at', '<=', $now ?? Carbon::now())
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
}
