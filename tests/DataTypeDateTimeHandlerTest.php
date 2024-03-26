<?php

namespace Kolossal\Multiplex\Tests;

use Carbon\Carbon;
use Kolossal\Multiplex\DataType\DateTimeHandler;
use PHPUnit\Framework\Attributes\Test;

final class DataTypeDateTimeHandlerTest extends TestCase
{
    /** @test */
    public function it_will_parse_to_specified_datetime_format(): void
    {
        $handler = new DateTimeHandler;

        $this->assertSame(
            '2022-04-01 14:00:00.000000+0200',
            $handler->serializeValue('2022-04-01 14:00 Europe/Berlin')
        );
    }

    /** @test */
    public function it_will_unserialize_using_specified_datetime_format_if_possible(): void
    {
        $handler = new DateTimeHandler;

        $this->assertTrue(
            $handler->unserializeValue('2022-04-01 14:00:00.000000+0200')->eq(Carbon::create(2022, 4, 1, 12))
        );
    }

    /** @test */
    public function it_will_fallback_to_carbon_parse(): void
    {
        $handler = new DateTimeHandler;

        $this->assertTrue(
            $handler->unserializeValue('01.04.2022 14:00 Europe/Berlin')->eq(Carbon::create(2022, 4, 1, 12))
        );
    }
}
