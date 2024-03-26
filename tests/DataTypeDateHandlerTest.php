<?php

namespace Kolossal\Multiplex\Tests;

use PHPUnit\Framework\Attributes\Test;
use Carbon\Carbon;
use Kolossal\Multiplex\DataType\DateHandler;

class DataTypeDateHandlerTest extends TestCase
{
    #[Test]
    public function it_will_parse_to_specified_date_format()
    {
        $handler = new DateHandler;

        $this->assertSame(
            '2022-04-01',
            $handler->serializeValue('2022-04-01 14:00 Europe/Berlin')
        );
    }

    #[Test]
    public function it_will_unserialize_using_specified_date_format_if_possible()
    {
        $handler = new DateHandler;

        $this->assertTrue(
            $handler->unserializeValue('2022-04-01')->eq(Carbon::create(2022, 4, 1))
        );
    }

    #[Test]
    public function it_will_fallback_to_carbon_parse()
    {
        $handler = new DateHandler;

        $this->assertTrue(
            $handler->unserializeValue('01.04.2022 14:00 Europe/Berlin')->eq(Carbon::create(2022, 3, 31, 22))
        );
    }
}
