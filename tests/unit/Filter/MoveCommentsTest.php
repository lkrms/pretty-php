<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Filter;

final class MoveCommentsTest extends \Lkrms\PrettyPHP\Tests\TestCase
{
    /**
     * @dataProvider outputProvider
     */
    public function testOutput(string $expected, string $code): void
    {
        $this->assertCodeFormatIs($expected, $code);
    }

    /**
     * @return array<string[]>
     */
    public static function outputProvider(): array
    {
        return [
            [
                <<<'PHP'
<?php

$foo = [
    $bar,  // Comment
    $baz,  // Comment
    $qux,  // Comment
];

$foo = [
    $bar,  /* DocBlock */
    $baz,  /* DocBlock */
    $qux,  /* DocBlock */
];

function foo(
    $bar,  // Comment
    $baz,  // Comment
    $qux  // Comment
): void {}

function bar(
    /** DocBlock */
    $foo,  /* DocBlock */
    $baz,  /* DocBlock */
    $qux  /* DocBlock */
): void {}

PHP,
                <<<'PHP'
<?php

$foo = [
    $bar // Comment
    , $baz // Comment
    , $qux // Comment
    ,
];

$foo = [
    $bar /** DocBlock */
    , $baz /** DocBlock */
    , $qux /** DocBlock */
    ,
];

function foo(
    $bar // Comment
    , $baz // Comment
    , $qux // Comment
): void {}

function bar(
    /** DocBlock */
    $foo /** DocBlock */
    , $baz /** DocBlock */
    , $qux /** DocBlock */
): void {}
PHP,
            ],
        ];
    }
}
