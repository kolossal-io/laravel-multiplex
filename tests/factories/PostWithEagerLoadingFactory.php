<?php

namespace Kolossal\Multiplex\Tests\Factories;

use Kolossal\Multiplex\Tests\Mocks\PostWithEagerLoading;

class PostWithEagerLoadingFactory extends PostFactory
{
    protected $model = PostWithEagerLoading::class;
}
