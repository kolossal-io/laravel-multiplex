<?php

namespace Kolossal\Multiplex\Tests\Mocks;

class PostWithAccessor extends Post
{
    protected $metaKeys = [
        'title',
        'datetime_field' => 'date',
    ];

    public function getTitleMeta($value)
    {
        return $value ? "Testing {$value} passed." : $value;
    }
}
