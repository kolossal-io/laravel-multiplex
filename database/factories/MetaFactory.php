<?php

namespace Kolossal\Multiplex\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Kolossal\Multiplex\Meta;

/**
 * @extends Factory<Meta>
 */
class MetaFactory extends Factory
{
    protected $model = Meta::class;

    protected function getValue(): mixed
    {
        return $this->faker->randomElement([
            $this->faker->word(),
            $this->faker->numberBetween(),
            $this->faker->dateTime(),
        ]);
    }

    public function definition(): array
    {
        return [
            'key' => $this->faker->unique()->domainWord(),
            'value' => $this->getValue(),
        ];
    }
}
