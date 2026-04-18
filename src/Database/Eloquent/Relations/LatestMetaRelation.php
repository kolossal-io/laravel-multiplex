<?php

namespace Kolossal\Multiplex\Database\Eloquent\Relations;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Kolossal\Multiplex\Database\Eloquent\MetaBuilder;

/**
 * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
 * @template TDeclaringModel of \Illuminate\Database\Eloquent\Model
 *
 * @extends MorphMany<TRelatedModel, TDeclaringModel>
 */
class LatestMetaRelation extends MorphMany
{
    protected ?Closure $metaWindowConditionCallback = null;

    /**
     * Create a new morph one or many relationship instance.
     *
     * @param  MetaBuilder<TRelatedModel>  $query
     * @param  TDeclaringModel  $parent
     * @param  string  $type
     * @param  string  $id
     * @param  string  $localKey
     */
    public function __construct(
        MetaBuilder $query,
        Model $parent,
        $type,
        $id,
        $localKey,
        ?Closure $where = null
    ) {
        $this->morphType = $type;

        $this->morphClass = $parent->getMorphClass();

        $this->metaWindowConditionCallback = $where;

        parent::__construct($query, $parent, $type, $id, $localKey);
    }

    /**
     * Set the base constraints on the relation query.
     *
     * @return void
     */
    public function addConstraints()
    {
        if (!static::$constraints) {
            return;
        }

        $this->addMetaConstraints($this->parent);
    }

    /** {@inheritDoc} */
    public function addEagerConstraints(array $models)
    {
        $this->addMetaConstraints($models);
    }

    /**
     * @param  TDeclaringModel|array<int, TDeclaringModel>  $model
     */
    protected function addMetaConstraints(array|Model $model): void
    {
        /** @var MetaBuilder<TRelatedModel> $windowQuery */
        $windowQuery = $this->related->newQuery();

        $windowQuery
            ->withRowNumber()
            ->where($this->morphType, $this->morphClass)
            ->when(
                is_array($model),
                function ($query) use ($model) {
                    /** @var array<int, TDeclaringModel> $model */
                    $query->whereIn(
                        $this->foreignKey,
                        $this->getKeys($model, $this->localKey)
                    );
                },
                function ($query) use ($model) {
                    $query->where($this->foreignKey, $model->{$this->localKey});
                }
            )
            ->when(
                $this->metaWindowConditionCallback,
                function ($query) {
                    $callback = $this->metaWindowConditionCallback;

                    if (is_callable($callback)) {
                        $callback($query);
                    }
                }
            );

        /** @var MetaBuilder<TRelatedModel> $relationQuery */
        $relationQuery = $this->getRelationQuery();

        $relationQuery
            ->asRelationQuery()
            ->joinSub(
                $windowQuery,
                'latest_meta',
                function ($join) {
                    $join->on('meta.id', '=', 'latest_meta.id');
                }
            );
    }
}
