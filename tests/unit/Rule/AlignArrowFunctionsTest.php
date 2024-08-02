<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Rule;

use Lkrms\PrettyPHP\Rule\AlignArrowFunctions;
use Lkrms\PrettyPHP\Tests\TestCase;
use Lkrms\PrettyPHP\Formatter;
use Lkrms\PrettyPHP\FormatterBuilder as FormatterB;

final class AlignArrowFunctionsTest extends TestCase
{
    /**
     * @dataProvider outputProvider
     *
     * @param Formatter|FormatterB $formatter
     */
    public function testOutput(string $expected, string $code, $formatter): void
    {
        $this->assertFormatterOutputIs($expected, $code, $formatter);
    }

    /**
     * @return array<array{string,string,Formatter|FormatterB}>
     */
    public static function outputProvider(): array
    {
        $formatterB = Formatter::build();

        return [
            [
                <<<'PHP'
<?php
$alpha = bravo($charlie, fn() =>
                             delta($echo));

PHP,
                <<<'PHP'
<?php
$alpha = bravo($charlie, fn() =>
    delta($echo));
PHP,
                $formatterB
                    ->enable([AlignArrowFunctions::class])
            ],
        ];
    }
}
