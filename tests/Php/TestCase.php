<?php declare(strict_types=1);

namespace Lkrms\Pretty\Tests\Php;

use Lkrms\Pretty\Php\Formatter;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    public function assertFormatterOutputIs(string $code, string $expected, string $tab = '    ', string $message = ''): void
    {
        $formatter = new Formatter($tab);
        $this->assertSame($expected, $formatter->format($code), $message);
    }
}
