<?php

namespace Kolossal\Multiplex\Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Kolossal\Multiplex\Tests\Mocks\PostWithExistingColumn;

class PostWithExistingColumnFactory extends Factory
{
    protected $model = PostWithExistingColumn::class;

    public function definition()
    {
        return [];
    }
}
