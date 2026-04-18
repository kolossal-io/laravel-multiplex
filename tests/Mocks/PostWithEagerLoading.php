<?php

namespace Kolossal\Multiplex\Tests\Mocks;

class PostWithEagerLoading extends Post
{
    protected $with = ['meta'];
}
