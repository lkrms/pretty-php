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
        array $addRules    = [],
        array $skipRules   = [],
        array $skipFilters = [],
        bool $insertSpaces = true,
        int $tabSize       = 4,
        ?string $filename  = null
    ): void {
        $formatter = new Formatter(
            $insertSpaces,
            $tabSize,
            $skipRules,
            $addRules,
            $skipFilters
        );

        $this->assertSame(
            $expected,
            $formatter->format(
                $code,
                3,
                $filename
            ),
            $filename ?: ''
        );
    }
}
