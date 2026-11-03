<?php

namespace Kolossal\Multiplex\Tests\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Kolossal\Multiplex\Tests\Mocks\MetaWithFactory;

class MetaWithFactoryFactory extends Factory
{
    protected $model = MetaWithFactory::class;

    public function definition()
    {
        return [
            'key' => $this->faker->unique()->domainWord(),
            'value' => $this->faker->word(),
        ];
    }
}
