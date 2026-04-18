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
    public function withRowNumber(): self
    {
        $this->addSelect(
            '*',
            DB::raw('ROW_NUMBER() OVER (
                PARTITION BY meta.metable_type, meta.metable_id, meta.`key`
                ORDER BY meta.published_at DESC, meta.id DESC
            ) as `meta_row_num`')
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
