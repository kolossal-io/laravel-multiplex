<?php

namespace kolossal\MetaRevision\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \kolossal\MetaRevision\MetaRevision
 */
class MetaRevision extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \kolossal\MetaRevision\MetaRevision::class;
    }
}
