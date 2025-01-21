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
switch ($foo) {
    //
    case 0:
        // Indented
    case 1:
        bar();
        // Indented
    case 2:
        break;
    //
    case 3:
        // Indented

        // Indented

    //
    case 4:
        baz();
        // Indented

        // Indented

    //
    case 5:
        break;
        // Indented

    //

    //
    case 6:
        qux();

        // Indented

    //
    case 7:
        break;

    //

    //
    default:
}

PHP,
                <<<'PHP'
<?php
switch ($foo) {
//
case 0:
// Indented
case 1:
bar();
// Indented
case 2:
break;
//
case 3:
// Indented

// Indented

//
case 4:
baz();
// Indented

// Indented

//
case 5:
break;
// Indented

//

//
case 6:
qux();

// Indented

//
case 7:
break;

//

//
default:
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
