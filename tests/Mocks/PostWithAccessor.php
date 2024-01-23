<?php

namespace Kolossal\Multiplex\Tests\Mocks;

class PostWithAccessor extends Post
{
    protected $metaKeys = [
        'title',
    ];

    public function getTitleMeta($value)
    {
        return $value ? "Testing {$value} passed." : $value;
    }
}
