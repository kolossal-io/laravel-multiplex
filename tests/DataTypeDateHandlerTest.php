<?php

namespace Kolossal\Multiplex\Tests;

use Carbon\Carbon;
use Kolossal\Multiplex\DataType\DateHandler;
use PHPUnit\Framework\Attributes\Test;

final class DataTypeDateHandlerTest extends TestCase
{
    /** @test */
    public function it_will_parse_to_specified_date_format(): void
    {
        $handler = new DateHandler;

        $this->assertSame(
            '2022-04-01',
            $handler->serializeValue('2022-04-01 14:00 Europe/Berlin')
        );
    }

    /** @test */
    public function it_will_unserialize_using_specified_date_format_if_possible(): void
    {
        $handler = new DateHandler;

        $this->assertTrue(
            $handler->unserializeValue('2022-04-01')->eq(Carbon::create(2022, 4, 1))
        );
    }

    /** @test */
    public function it_will_fallback_to_carbon_parse(): void
    {
        $handler = new DateHandler;

        $this->assertTrue(
            $handler->unserializeValue('01.04.2022 14:00 Europe/Berlin')->eq(Carbon::create(2022, 3, 31, 22))
        );
    }

    /** @test */
    public function it_can_handle_handle_date_strings(): void
    {
        $handler = new DateHandler;

        $this->assertTrue($handler->canHandleValue('2024-03-29'));
        $this->assertTrue($handler->canHandleValue('1970-01-01'));
    }

    /** @test */
    public function it_cannot_handle_handle_invalid_values(): void
    {
        $handler = new DateHandler;

        $this->assertFalse($handler->canHandleValue(now()));
        $this->assertFalse($handler->canHandleValue('2024-3-29'));
        $this->assertFalse($handler->canHandleValue('20240329'));
        $this->assertFalse($handler->canHandleValue(true));
    }
}
