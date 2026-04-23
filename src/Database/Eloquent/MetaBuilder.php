<?php

namespace Kolossal\Multiplex\Database\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * @template TModel of \Illuminate\Database\Eloquent\Model
 *
 * @extends Builder<TModel>
 */
class MetaBuilder extends Builder
{
    protected bool $isRelationQuery = false;

    /**
     * @return MetaBuilder<TModel>
     */
    public function withRowNumber(bool $fullSelect = false): self
    {
        $grammar = $this->query->getGrammar();

        $key = $grammar->wrap('meta.key');
        $metableType = $grammar->wrap('meta.metable_type');
        $metableId = $grammar->wrap('meta.metable_id');
        $publishedAt = $grammar->wrap('meta.published_at');
        $id = $grammar->wrap('meta.id');

        $this->addSelect(
            $fullSelect ? '*' : 'id',
            // @phpstan-ignore-next-line argument.type
            DB::raw("ROW_NUMBER() OVER (
                PARTITION BY {$metableType}, {$metableId}, {$key}
                ORDER BY {$publishedAt} DESC, {$id} DESC
            ) AS meta_row_num"),
        );

        return $this;
    }

    /**
     * @return MetaBuilder<TModel>
     */
    public function asRelationQuery(bool $value = true): self
    {
        $this->isRelationQuery = $value;

        return $this;
    }

    public function isRelationQuery(): bool
    {
        return $this->isRelationQuery;
    }
}
