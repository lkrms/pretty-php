<?php declare(strict_types=1);

namespace Lkrms\Pretty\Tests\Php;

use Lkrms\Pretty\Php\Formatter;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    public function assertFormatterOutputIs(string $code, string $expected, string $tab = '    ', int $tabSize = 4, string $message = ''): void
    {
        $formatter             = new Formatter($tab, $tabSize);
        $formatter->QuietLevel = 3;
        $this->assertSame($expected, $formatter->format($code), $message);
    }
}
