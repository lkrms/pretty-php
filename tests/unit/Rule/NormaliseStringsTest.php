<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Rule;

use Lkrms\PrettyPHP\Formatter;

final class NormaliseStringsTest extends \Lkrms\PrettyPHP\Tests\TestCase
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
            'leading tabs + tab indentation' => [
                <<<PHP
<?php
if (true) {
\t\$foo = 'bar
\t\tbaz
\t\tqux';
}

PHP,
                <<<'PHP'
<?php
if (true) {
$foo = "bar
\t\tbaz
\t\tqux";
}
PHP,
                [
                    'insertSpaces' => false,
                ],
            ],
            'leading + inline tabs + tab indentation' => [
                <<<PHP
<?php
if (true) {
\t\$foo = "bar
\t\tbaz
\t\tqux\\tquux";
}

PHP,
                <<<'PHP'
<?php
if (true) {
$foo = "bar
\t\tbaz
\t\tqux\tquux";
}
PHP,
                [
                    'insertSpaces' => false,
                ],
            ],
            'leading tabs' => [
                <<<'PHP'
<?php
if (true) {
    $foo = "bar
\t\tbaz
\t\tqux";
}

PHP,
                <<<'PHP'
<?php
if (true) {
$foo = "bar
\t\tbaz
\t\tqux";
}
PHP,
            ],
            'leading + inline tabs' => [
                <<<'PHP'
<?php
if (true) {
    $foo = "bar
\t\tbaz
\t\tqux\tquux";
}

PHP,
                <<<'PHP'
<?php
if (true) {
$foo = "bar
\t\tbaz
\t\tqux\tquux";
}
PHP,
            ],
        ];
    }
}
