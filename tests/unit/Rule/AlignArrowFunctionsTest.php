<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Rule;

use Lkrms\PrettyPHP\Rule\AlignArrowFunctions;
use Lkrms\PrettyPHP\Formatter;

final class AlignArrowFunctionsTest extends \Lkrms\PrettyPHP\Tests\TestCase
{
    /**
     * @dataProvider outputProvider
     *
     * @param array{insertSpaces?:bool|null,tabSize?:int|null,skipRules?:string[],addRules?:string[],skipFilters?:string[],callback?:(callable(Formatter): Formatter)|null} $options
     */
    public function testOutput(string $expected, string $code, array $options = []): void
    {
        $this->assertFormatterOutputIs($expected, $code, $this->getFormatter($options));
    }

    /**
     * @return array<array{string,string,array{insertSpaces?:bool|null,tabSize?:int|null,skipRules?:string[],addRules?:string[],skipFilters?:string[],callback?:(callable(Formatter): Formatter)|null}}>
     */
    public static function outputProvider(): array
    {
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
                [
                    'addRules' => [AlignArrowFunctions::class],
                    'callback' => fn(Formatter $f) => $f,
                ],
            ],
        ];
    }
}
