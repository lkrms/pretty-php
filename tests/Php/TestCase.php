<?php declare(strict_types=1);

namespace Lkrms\Pretty\Tests\Php;

use Lkrms\Pretty\Php\Formatter;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * @param string[] $skipRules
     * @param string[] $addRules
     */
    public function assertFormatterOutputIs(string $code, string $expected, string $tab = '    ', int $tabSize = 4, array $skipRules = [], array $addRules = [], string $message = ''): void
    {
        $formatter             = new Formatter($tab, $tabSize, $skipRules, $addRules);
        $formatter->QuietLevel = 3;
        $this->assertSame($expected, $formatter->format($code), $message);
    }
}
