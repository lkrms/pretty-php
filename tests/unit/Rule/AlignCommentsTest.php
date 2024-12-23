<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Rule;

use Lkrms\PrettyPHP\Rule\AlignComments;
use Lkrms\PrettyPHP\Tests\TestCase;

final class AlignCommentsTest extends TestCase
{
    /**
     * @dataProvider outputProvider
     */
    public function testOutput(string $expected, string $code): void
    {
        $this->assertCodeFormatIs($expected, $code, [AlignComments::class]);
    }

    /**
     * @return array<array{string,string}>
     */
    public static function outputProvider(): array
    {
        return [
            [
                <<<'PHP'
<?php
$a = 1;      //
$b = 10;     //
$c = 100;
$d = 1000;   //
$e = 10000;  //

PHP,
                <<<'PHP'
<?php
$a = 1;  //
$b = 10;  #
$c = 100;
$d = 1000;  //
$e = 10000;  #
PHP,
            ],
            [
                <<<'PHP'
<?php
$a = 1;     //
            //
$b = 10;    //
            //
$c = 100;   //
//
$d = 1000;  //
//

//

PHP,
                <<<'PHP'
<?php
$a = 1;  //
 //
$b = 10;  #
 #
$c = 100;  //
 #
$d = 1000;  #
 //

//
PHP,
            ],
            [
                <<<'PHP'
<?php
$a = 1;   /* line 1
             line 2 */
$b = 10;  /* line 1
             line 2 */
/* comment */
/* comment */

PHP,
                <<<'PHP'
<?php
$a = 1;  /* line 1
            line 2 */
$b = 10;  /* line 1
             line 2 */
 /* comment */
/* comment */
PHP,
            ],
            [
                <<<'PHP'
<?php
$a = 1; /* inline */     /* line 1
                            line 2 */
$b = 10; /* inline */    // one-line 1
                         // one-line 2
$c = 100; /* inline */   // shell-style 1
                         // shell-style 2
$d = 1000;               /* inline */
$e = 10000; /* inline */ // one-line 1
/* inline */
// one-line 2

PHP,
                <<<'PHP'
<?php
$a = 1; /* inline */ /* line 1
                        line 2 */
$b = 10; /* inline */ // one-line 1
 // one-line 2
$c = 100; /* inline */ # shell-style 1
 # shell-style 2
$d = 1000; /* inline */
$e = 10000; /* inline */ // one-line 1
/* inline */ // one-line 2
PHP,
            ],
        ];
    }
}
