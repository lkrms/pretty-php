<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Filter;

use Lkrms\PrettyPHP\Catalog\ImportSortOrder;
use Lkrms\PrettyPHP\Rule\AlignComments;
use Lkrms\PrettyPHP\Tests\TestCase;
use Lkrms\PrettyPHP\Formatter;
use Lkrms\PrettyPHP\FormatterBuilder as FormatterB;

final class SortImportsTest extends TestCase
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

        return [
            'with comments #1' => [
                <<<'PHP'
<?php
use Alpha;    /*
               * One
               * multi-line
               * comment
               */
use Bravo;    // A one-line comment
use Charlie;  // Multiple
              // one-line comments
              // with indent from code
use Delta;    // Multiple
// one-line
// comments
use Foxtrot;

// A standalone comment

/**
 * A docblock
 */
class foo extends bar {}

PHP,
                <<<'PHP'
<?php
use Foxtrot;
use Delta; // Multiple
// one-line
// comments
use Charlie; // Multiple
 // one-line comments
 // with indent from code
use Bravo; // A one-line comment
use Alpha; /* One
* multi-line
* comment */
// A standalone comment
/**
 * A docblock
 */
class foo extends bar {}
PHP,
                $formatterB
                    ->enable([AlignComments::class]),
            ],
            'with comments #2' => [
                <<<'PHP'
<?php
use B;  // Multiple
// one-line
// comments
use C;
// Different comment type = new block
use A;

PHP,
                <<<'PHP'
<?php
use C;
use B; // Multiple
// one-line
// comments
# Different comment type = new block
use A;
PHP,
                $formatter,
            ],
            'with comments #3' => [
                <<<'PHP'
<?php
// Comment 1
use B;
use C;
// Comment 2
use A;

PHP,
                <<<'PHP'
<?php
// Comment 1
use C;
use B;
// Comment 2
use A;
PHP,
                $formatter,
            ],
            'sorted by depth' => [
                <<<'PHP'
<?php
use B\C\F\H\A as AA;
use B\C\F\H\H as HHHH;
use B\C\F\H\M;
use B\C\F\{H, J};
use B\C\F\{H as HH, K};
use B\C\F\G;
use B\C\F\H as HHH;
use B\C\F\I;
use B\C\E;
use B\C\F as FF;
use B\C;
use B\D;
use S\T\U\F\F;
use A;
use B;
use L;

PHP,
                <<<'PHP'
<?php
use B\C\F\I;
use B\D;
use A;
use B\C\E;
use B\C\F\{H,J};
use B\C\F\G;
use B\C\F\H as HHH;
use B\C\F\H\M;
use B\C\F\H\H as HHHH;
use B\C\F\H\A as AA;
use B\C\F\{H as HH,K};
use L;
use S\T\U\F\F;
use B;
use B\C;
use B\C\F as FF;
PHP,
                $formatter,
            ],
            'sorted by name' => [
                <<<'PHP'
<?php
use A;
use B;
use B\C;
use B\C\E;
use B\C\F as FF;
use B\C\F\G;
use B\C\F\{H, J};
use B\C\F\{H as HH, K};
use B\C\F\H as HHH;
use B\C\F\H\A as AA;
use B\C\F\H\H as HHHH;
use B\C\F\H\M;
use B\C\F\I;
use B\D;
use L;
use S\T\U\F\F;

PHP,
                <<<'PHP'
<?php
use B\C\F\I;
use B\D;
use B\C\F\{H,J};
use A;
use B\C\F\G;
use B\C\E;
use B\C\F\H\M;
use B\C\F\H\H as HHHH;
use B\C\F\H\A as AA;
use B\C\F\H as HHH;
use B\C\F\{H as HH,K};
use S\T\U\F\F;
use L;
use B\C\F as FF;
use B\C;
use B;
PHP,
                $formatterB
                    ->importSortOrder(ImportSortOrder::NAME),
            ],
            'with traits' => [
                <<<'PHP'
<?php
use B\C\F\{H, I};
use B\C\F\G;
use B\C\F\J;
use B\C\E;
use B\D;
use A;

class foo
{
    use H;
    use C;
    use A;
    use B {
        C::value insteadof D;
    }
}

PHP,
                <<<'PHP'
<?php
use B\C\F\J;
use B\D;
use A;
use B\C\E;
use B\C\F\{H, I};
use B\C\F\G;

class foo
{
use H;
use C;
use A;
use B { C::value insteadof D; }
}
PHP,
                $formatter,
            ],
            'with close tag terminator' => [
                <<<'PHP'
<?php
use A;
use B;
use C
?>
PHP,
                <<<'PHP'
<?php
use C;
use B;
use A?>
PHP,
                $formatter,
            ],
            'with close tag terminator and comments' => [
                <<<'PHP'
<?php
use A;  // Comment 3
use B;  // Comment 2
use C   // Comment 1
?>
PHP,
                <<<'PHP'
<?php
use C;  // Comment 1
use B;  // Comment 2
use A   // Comment 3 ?>
PHP,
                $formatterB
                    ->enable([AlignComments::class]),
            ],
        ];
    }
}
