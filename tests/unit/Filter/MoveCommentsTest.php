<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Filter;

use Lkrms\PrettyPHP\Support\TokenTypeIndex;
use Lkrms\PrettyPHP\Tests\TestCase;
use Lkrms\PrettyPHP\Formatter;
use Lkrms\PrettyPHP\FormatterBuilder as FormatterB;

final class MoveCommentsTest extends TestCase
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
        $formatter = $formatterB->build();

        $idx = new TokenTypeIndex();
        $mixed = $idx->withMixedOperators();
        $first = $idx->withLeadingOperators();
        $last = $idx->withTrailingOperators();

        $input1 = <<<'PHP'
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
PHP;

        $input2 = <<<'PHP'
<?php
$foo =  // Comment
    !  // Comment
    $qux  // Comment
        ? ($bar  // Comment
            * $baz  // Comment
            / 100)  // Comment
        . '%'  // Comment
        : $quux;  // Comment

$foo =  /* Comment */
    !  /* Comment */
    $qux  /* Comment */
        ? ($bar  /* Comment */
            * $baz  /* Comment */
            / 100)  /* Comment */
        . '%'  /* Comment */
        : $quux;  /* Comment */

$foo =  /** DocBlock */
    !  /** DocBlock */
    $qux  /** DocBlock */
        ? ($bar  /** DocBlock */
            * $baz  /** DocBlock */
            / 100)  /** DocBlock */
        . '%'  /** DocBlock */
        : $quux;  /** DocBlock */

$foo  // Comment
    =  // Comment
        !  // Comment
        $qux ?  // Comment
        ($bar *  // Comment
            $baz /  // Comment
            100) .  // Comment
        '%' :  // Comment
        $quux;  // Comment

$foo  /* Comment */
    =  /* Comment */
        !  /* Comment */
        $qux ?  /* Comment */
        ($bar *  /* Comment */
            $baz /  /* Comment */
            100) .  /* Comment */
        '%' :  /* Comment */
        $quux;  /* Comment */

$foo  /** DocBlock */
    =  /** DocBlock */
        !  /** DocBlock */
        $qux ?  /** DocBlock */
        ($bar *  /** DocBlock */
            $baz /  /** DocBlock */
            100) .  /** DocBlock */
        '%' :  /** DocBlock */
        $quux;  /** DocBlock */
PHP;

        return [
            'Comments before commas' => [
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
                $input1,
                $formatter,
            ],
            'Comments before and after operators + mixed' => [
                <<<'PHP'
<?php
$foo =  // Comment
    // Comment
    !$qux  // Comment
        ? ($bar  // Comment
            * $baz  // Comment
            / 100)  // Comment
        . '%'  // Comment
        : $quux;  // Comment

$foo =  /* Comment */
    /* Comment */
    !$qux  /* Comment */
        ? ($bar  /* Comment */
            * $baz  /* Comment */
            / 100)  /* Comment */
        . '%'  /* Comment */
        : $quux;  /* Comment */

$foo =  /* DocBlock */
    !
    /** DocBlock */
    $qux  /* DocBlock */
        ? ($bar  /* DocBlock */
            * $baz  /* DocBlock */
            / 100)  /* DocBlock */
        . '%'  /* DocBlock */
        : $quux;
/** DocBlock */
$foo =  // Comment
    // Comment
    // Comment
    !$qux ?  // Comment
    ($bar  // Comment
        * $baz  // Comment
        / 100)  // Comment
    . '%' :  // Comment
    $quux;  // Comment

$foo =  /* Comment */
    /* Comment */
    /* Comment */
    !$qux ?  /* Comment */
    ($bar  /* Comment */
        * $baz  /* Comment */
        / 100)  /* Comment */
    . '%' :  /* Comment */
    $quux;  /* Comment */

$foo =  /* DocBlock */
    /* DocBlock */
    !
    /** DocBlock */
    $qux ?
    /** DocBlock */
    ($bar *
        /** DocBlock */
        $baz /
        /** DocBlock */
        100) .
    /** DocBlock */
    '%' :
    /** DocBlock */
    $quux;
/** DocBlock */

PHP,
                $input2,
                $formatterB->tokenTypeIndex($mixed),
            ],
            'Comments before and after operators + first' => [
                <<<'PHP'
<?php
$foo =  // Comment
    // Comment
    !$qux  // Comment
        ? ($bar  // Comment
            * $baz  // Comment
            / 100)  // Comment
        . '%'  // Comment
        : $quux;  // Comment

$foo =  /* Comment */
    /* Comment */
    !$qux  /* Comment */
        ? ($bar  /* Comment */
            * $baz  /* Comment */
            / 100)  /* Comment */
        . '%'  /* Comment */
        : $quux;  /* Comment */

$foo =  /* DocBlock */
    !
    /** DocBlock */
    $qux  /* DocBlock */
        ? ($bar  /* DocBlock */
            * $baz  /* DocBlock */
            / 100)  /* DocBlock */
        . '%'  /* DocBlock */
        : $quux;
/** DocBlock */
$foo =  // Comment
    // Comment
    // Comment
    !$qux ?  // Comment
    ($bar  // Comment
        * $baz  // Comment
        / 100)  // Comment
    . '%' :  // Comment
    $quux;  // Comment

$foo =  /* Comment */
    /* Comment */
    /* Comment */
    !$qux ?  /* Comment */
    ($bar  /* Comment */
        * $baz  /* Comment */
        / 100)  /* Comment */
    . '%' :  /* Comment */
    $quux;  /* Comment */

$foo =  /* DocBlock */
    /* DocBlock */
    !
    /** DocBlock */
    $qux ?
    /** DocBlock */
    ($bar *
        /** DocBlock */
        $baz /
        /** DocBlock */
        100) .
    /** DocBlock */
    '%' :
    /** DocBlock */
    $quux;
/** DocBlock */

PHP,
                $input2,
                $formatterB->tokenTypeIndex($first),
            ],
            'Comments before and after operators + last' => [
                <<<'PHP'
<?php
$foo =  // Comment
    // Comment
    !$qux  // Comment
        ? ($bar *  // Comment
            $baz /  // Comment
            100) .  // Comment
        '%'  // Comment
        : $quux;  // Comment

$foo =  /* Comment */
    /* Comment */
    !$qux  /* Comment */
        ? ($bar *  /* Comment */
            $baz /  /* Comment */
            100) .  /* Comment */
        '%'  /* Comment */
        : $quux;  /* Comment */

$foo =  /* DocBlock */
    !
    /** DocBlock */
    $qux  /* DocBlock */
        ? ($bar *  /* DocBlock */
            $baz /  /* DocBlock */
            100) .  /* DocBlock */
        '%'  /* DocBlock */
        : $quux;
/** DocBlock */
$foo =  // Comment
    // Comment
    // Comment
    !$qux ?  // Comment
    ($bar *  // Comment
        $baz /  // Comment
        100) .  // Comment
    '%' :  // Comment
    $quux;  // Comment

$foo =  /* Comment */
    /* Comment */
    /* Comment */
    !$qux ?  /* Comment */
    ($bar *  /* Comment */
        $baz /  /* Comment */
        100) .  /* Comment */
    '%' :  /* Comment */
    $quux;  /* Comment */

$foo =  /* DocBlock */
    /* DocBlock */
    !
    /** DocBlock */
    $qux ?
    /** DocBlock */
    ($bar *
        /** DocBlock */
        $baz /
        /** DocBlock */
        100) .
    /** DocBlock */
    '%' :
    /** DocBlock */
    $quux;
/** DocBlock */

PHP,
                $input2,
                $formatterB->tokenTypeIndex($last),
            ],
        ];
    }
}
