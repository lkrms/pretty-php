<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests;

use Lkrms\Facade\Sys;
use Lkrms\PrettyPHP\Formatter;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    final public static function assertFormatterOutputIs(string $expected, string $code, Formatter $formatter): void
    {
        $first = $formatter->format($code);
        $second = $formatter->format($first, null, true);
        self::assertSame($expected, $first, 'Output is not formatted correctly.');
        self::assertSame($expected, $second, 'Output is not idempotent.');
        if ($code) {
            $last = end($formatter->Tokens);
            self::assertSame($last->pos, $last->OutputPos, 'pos and OutputPos do not match.');
        }
    }

    /**
     * @param array{insertSpaces?:bool|null,tabSize?:int|null,skipRules?:string[],addRules?:string[],skipFilters?:string[],callback?:(callable(Formatter): Formatter)|null} $options
     */
    final public static function getFormatter(array $options): Formatter
    {
        $formatter = new Formatter(
            $options['insertSpaces'] ?? true,
            $options['tabSize'] ?? 4,
            $options['skipRules'] ?? [],
            $options['addRules'] ?? [],
            $options['skipFilters'] ?? []
        );
        if ($callback = ($options['callback'] ?? null)) {
            return $callback($formatter);
        }
        return $formatter;
    }

    /**
     * @param string[] $addRules
     * @param string[] $skipRules
     * @param string[] $skipFilters
     */
    final public function assertCodeFormatIs(
        string $expected,
        string $code,
        array $addRules = [],
        array $skipRules = [],
        array $skipFilters = [],
        bool $insertSpaces = true,
        int $tabSize = 4
    ): void {
        self::assertFormatterOutputIs($expected, $code, $this->prepareFormatter(
            new Formatter(
                $insertSpaces,
                $tabSize,
                $skipRules,
                $addRules,
                $skipFilters
            )
        ));
    }

    protected function prepareFormatter(Formatter $formatter): Formatter
    {
        return $formatter;
    }

    protected function setUp(): void
    {
        Sys::pushTimers();
    }

    protected function tearDown(): void
    {
        Sys::popTimers();
    }
}
