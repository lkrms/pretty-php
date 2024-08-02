<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Rule;

use Lkrms\PrettyPHP\Tests\TestCase;

final class AlignCommentsTest extends TestCase
{
    /**
     * @dataProvider outputProvider
     */
    public function testOutput(string $expected, string $code): void
    {
        $this->assertCodeFormatIs($expected, $code);
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function outputProvider(): array
    {
        return [
            'standalone comments' => [
                <<<'PHP'
<?php
$a = 1;
$b = 2;  //

//
$c = 3;

PHP,
                <<<'PHP'
<?php
$a = 1;
$b = 2;  //

//
$c = 3;
PHP,
            ],
            'mixed comment types' => [
                <<<'PHP'
<?php
echo 'This is a test';  // This is a one-line c++ style comment
/* This is a multi line comment
   yet another line of comment */
echo 'This is yet another test';
echo 'One Final Test';  // This is (or was) a one-line shell-style comment
?>
PHP,
                <<<'PHP'
<?php
echo 'This is a test'; // This is a one-line c++ style comment
/* This is a multi line comment
   yet another line of comment */
echo 'This is yet another test';
echo 'One Final Test'; # This is (or was) a one-line shell-style comment
?>
PHP,
            ],
        ];
    }
}
