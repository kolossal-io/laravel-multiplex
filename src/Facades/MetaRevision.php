<?php

namespace Kolossal\MetaRevision\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Kolossal\MetaRevision\MetaRevision
 */
class MetaRevision extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Kolossal\MetaRevision\MetaRevision::class;
    }
}
