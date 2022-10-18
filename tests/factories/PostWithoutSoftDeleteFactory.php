<?php

namespace Kolossal\Meta\Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Kolossal\Meta\Tests\Mocks\PostWithoutSoftDelete;

class PostWithoutSoftDeleteFactory extends Factory
{
    protected $model = PostWithoutSoftDelete::class;

    public function definition()
    {
        return [
            'title' => $this->faker->sentence(),
        ];
    }
}
