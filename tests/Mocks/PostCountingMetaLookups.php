<?php

namespace Kolossal\Multiplex\Tests\Mocks;

use Kolossal\Multiplex\Meta;

class PostCountingMetaLookups extends Post
{
    protected $table = 'sample_posts';

    protected $metaKeys = ['*', 'title'];

    public int $metaLookups = 0;

    public function findMeta($key): ?Meta
    {
        $this->metaLookups++;

        return parent::findMeta($key);
    }

    public function getDefaultedMeta($value)
    {
        return $value ?? 'default';
    }
}
