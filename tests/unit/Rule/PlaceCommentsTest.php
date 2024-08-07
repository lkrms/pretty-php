<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Rule;

use Lkrms\PrettyPHP\Tests\TestCase;

final class PlaceCommentsTest extends TestCase
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
            'switch comments' => [
                <<<'PHP'
<?php
switch ($a) {
    //
    case 0:
    case 1:
        //
        func();
        // Indented
    case 2:
        // Indented
    case 3:
        func2();
        break;

        // Indented

    case 4:
        func2();
        break;

        // Indented

    //
    case 5:
        func();
        break;

    //
    default:
        break;
}

PHP,
                <<<'PHP'
<?php
switch ($a) {
//
case 0:
case 1:
//
func();
// Indented
case 2:
// Indented
case 3:
func2();
break;

// Indented

case 4:
func2();
break;

// Indented

//
case 5:
func();
break;

//
default:
break;
}
PHP,
            ],
            'multi-line comment #1' => [
                <<<'PHP'
<?php
if (true) {
    /*
     * A multi-line "docblock" with an empty line and no leading asterisks
     */
}

PHP,
                <<<'PHP'
<?php
if (true) {
/**********
  A multi-line "docblock" with an empty line and no leading asterisks

 **********/
}
PHP,
            ],
            'multi-line comment #2' => [
                <<<'PHP'
<?php
if (true) {
    /*
     * A multi-line comment with an empty line and no leading asterisks
     */
}

PHP,
                <<<'PHP'
<?php
if (true) {
/*
  A multi-line comment with an empty line and no leading asterisks

 */
}
PHP,
            ],
        ];
    }
}
