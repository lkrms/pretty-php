<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests;

use Lkrms\PrettyPHP\Contract\Extension;
use Lkrms\PrettyPHP\Contract\HasTokenNames;
use Lkrms\PrettyPHP\Formatter;
use Lkrms\PrettyPHP\FormatterBuilder as FormatterB;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Salient\Core\Facade\Profile;
use Salient\Utility\Arr;
use Salient\Utility\Regex;

abstract class TestCase extends PHPUnitTestCase implements HasTokenNames
{
    protected const PHP_COMMAND = [\PHP_BINARY, '-ddisplay_errors=stderr', '-ddisplay_startup_errors=0'];

    /**
     * @param Formatter|FormatterB $formatter
     */
    public static function assertFormatterOutputIs(string $expected, string $code, $formatter): void
    {
        if ($formatter instanceof FormatterB) {
            $formatter = $formatter->build();
        }
        // Preserve tokens for inspection
        $formatter = $formatter->withDebug();
        $first = $formatter->format($code);
        $second = $formatter->format($first, null, null, null, true);
        self::assertSame($expected, $first, 'Output is not formatted correctly.');
        self::assertSame($expected, $second, 'Output is not idempotent.');
        if ($last = Arr::last($formatter->getTokens() ?? [])) {
            self::assertSame($last->pos, $last->OutputPos, 'pos and OutputPos do not match.');
        }
    }

    /**
     * @param array<class-string<Extension>> $enable
     * @param array<class-string<Extension>> $disable
     * @param 2|4|8 $tabSize
     */
    public static function assertCodeFormatIs(
        string $expected,
        string $code,
        array $enable = [],
        array $disable = [],
        bool $insertSpaces = true,
        int $tabSize = 4
    ): void {
        self::assertFormatterOutputIs(
            $expected,
            $code,
            Formatter::build()
                ->insertSpaces($insertSpaces)
                ->tabSize($tabSize)
                ->enable($enable)
                ->disable($disable)
        );
    }

    public static function getFixturesPath(?string $class = null): string
    {
        $class ??= static::class;

        return dirname(__DIR__)
            . '/fixtures/'
            . Regex::replace(
                ['/^Lkrms\\\\PrettyPHP\\\\(?|Tests\\\\(.+)Test$|(.+))/', '/\\\\/'],
                ['$1', '/'],
                $class
            );
    }

    public static function getPackagePath(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * @param int[] $types
     * @return string[]
     */
    public static function getTokenNames(array $types): array
    {
        foreach ($types as $type) {
            $name = token_name($type);
            if (substr($name, 0, 2) !== 'T_') {
                $name = self::TOKEN_NAME[$type] ?? $name;
            }
            $names[] = $name;
        }
        return $names ?? [];
    }

    protected function setUp(): void
    {
        Profile::push();
    }

    protected function tearDown(): void
    {
        Profile::pop();
    }
}
