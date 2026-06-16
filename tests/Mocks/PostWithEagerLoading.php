<?php

namespace Kolossal\Multiplex\Tests\Mocks;

class PostWithEagerLoading extends Post
{
    protected $table = 'sample_posts_with_eager_loading';

    protected $with = ['meta'];
}
