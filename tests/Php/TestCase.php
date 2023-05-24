<?php declare(strict_types=1);

namespace Lkrms\Pretty\Tests\Php;

use Lkrms\Pretty\Php\Formatter;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * @param string[] $skipRules
     * @param string[] $addRules
     * @param string[] $skipFilters
     */
    public function assertFormatterOutputIs(
        string $code,
        string $expected,
        array $addRules = [],
        array $skipRules = [],
        array $skipFilters = [],
        bool $insertSpaces = true,
        int $tabSize = 4
    ): void {
        $formatter = $this->prepareFormatter(new Formatter(
            $insertSpaces,
            $tabSize,
            $skipRules,
            $addRules,
            $skipFilters
        ));

        $first = $formatter->format($code, 3, null, true);
        $second = $formatter->format($first, 3, null, true);
        $this->assertSame($expected, $first);
        $this->assertSame($expected, $second, 'Output is not idempotent.');
    }

    protected function prepareFormatter(Formatter $formatter): Formatter
    {
        return $formatter;
    }
}
