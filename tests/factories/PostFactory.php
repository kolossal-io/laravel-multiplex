<?php

namespace Kolossal\Multiplex\Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Kolossal\Multiplex\Tests\Mocks\Post;

class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition()
    {
        return [
            'title' => $this->faker->sentence(),
        ];
    }
}
