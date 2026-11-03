<?php

namespace Kolossal\Multiplex\Tests\Mocks;

class PostWithManyMetaKeys extends Post
{
    protected $table = 'sample_posts';

    protected $metaKeys = [];

    public function __construct(array $attributes = [])
    {
        $this->metaKeys = array_map(fn($i) => "meta_{$i}", range(1, 60));

        parent::__construct($attributes);
    }
}
