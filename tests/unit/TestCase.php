<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests;

use Lkrms\Facade\Profile;
use Lkrms\PrettyPHP\Formatter;
use Lkrms\Utility\Pcre;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    final public static function assertFormatterOutputIs(string $expected, string $code, Formatter $formatter): void
    {
        $first = $formatter->format($code);
        $second = $formatter->format($first, null, null, true);
        self::assertSame($expected, $first, 'Output is not formatted correctly.');
        self::assertSame($expected, $second, 'Output is not idempotent.');
        if ($code !== '') {
            $last = end($formatter->Tokens);
            self::assertSame($last->pos, $last->OutputPos, 'pos and OutputPos do not match.');
        }
    }

    /**
     * @param array{insertSpaces?:bool|null,tabSize?:2|4|8|null,skipRules?:string[],addRules?:string[],skipFilters?:string[],callback?:(callable(Formatter): Formatter)|null} $options
     */
    final public static function getFormatter(array $options): Formatter
    {
        $formatter = new Formatter(
            $options['insertSpaces'] ?? true,
            $options['tabSize'] ?? 4,
            array_merge($options['skipRules'] ?? [], $options['skipFilters'] ?? []),
            $options['addRules'] ?? []
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
     * @param 2|4|8 $tabSize
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
                array_merge($skipRules, $skipFilters),
                $addRules
            )
        ));
    }

    public static function getFixturesPath(string $class): string
    {
        return dirname(__DIR__)
            . '/fixtures/'
            . Pcre::replace(
                ['/^Lkrms\\\\PrettyPHP\\\\(?|Tests\\\\(.+)Test$|(.+))/', '/\\\\/'],
                ['$1', '/'],
                $class
            );
    }

    protected function prepareFormatter(Formatter $formatter): Formatter
    {
        return $formatter;
    }

    protected function setUp(): void
    {
        Profile::pushTimers();
    }

    protected function tearDown(): void
    {
        Profile::popTimers();
    }
}
