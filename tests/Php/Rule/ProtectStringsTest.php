<?php declare(strict_types=1);

namespace Lkrms\Pretty\Tests\Php\Rule;

final class ProtectStringsTest extends \Lkrms\Pretty\Tests\Php\TestCase
{
    /**
     * @dataProvider processTokenProvider
     */
    public function testProcessToken(string $code, string $expected)
    {
        $this->assertFormatterOutputIs($code, $expected);
    }

    public static function processTokenProvider()
    {
        return [
            'nested heredocs' => [
                <<<'PHP'
<?php
$docBlock = <<<EOF
/**
 {$this->getLines(
$this->desc
? <<<EOF
 * $desc
 *
EOF
: ''
)}
 */
EOF;
PHP,
                <<<'PHP'
<?php
$docBlock = <<<EOF
    /**
     {$this->getLines(
        $this->desc
            ? <<<EOF
                 * $desc
                 *
                EOF
            : ''
    )}
     */
    EOF;

PHP,
            ],
            'nested strings' => [
                <<<'PHP'
<?php
$docBlock = "/**
 {$this->getLines(
$this->desc
? " * $desc
 *"
: ''
)}
 */";
PHP,
                <<<'PHP'
<?php
$docBlock = "/**
 {$this->getLines(
    $this->desc
        ? " * $desc
 *"
        : ''
)}
 */";

PHP,
            ],
        ];
    }
}
