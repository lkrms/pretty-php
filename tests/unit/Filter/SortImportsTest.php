<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Filter;

use Lkrms\PrettyPHP\Catalog\ImportSortOrder;
use Lkrms\PrettyPHP\Rule\AlignComments;
use Lkrms\PrettyPHP\Formatter;
use Lkrms\PrettyPHP\FormatterBuilder as FormatterB;

final class SortImportsTest extends \Lkrms\PrettyPHP\Tests\TestCase
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
            'sorted by depth' => [
                <<<'PHP'
<?php
use B\C\F\{H, I};
use B\C\F\G;
use B\C\F\J;
use B\C\E;
use B\D;
use A;

PHP,
                <<<'PHP'
<?php
use B\C\F\J;
use B\D;
use A;
use B\C\E;
use B\C\F\{H, I};
use B\C\F\G;
PHP,
                $formatter,
            ],
            'sorted by name' => [
                <<<'PHP'
<?php
use A;
use B\C\E;
use B\C\F\G;
use B\C\F\{H, I};
use B\C\F\J;
use B\D;

PHP,
                <<<'PHP'
<?php
use B\C\F\J;
use B\D;
use B\C\F\{H, I};
use A;
use B\C\F\G;
use B\C\E;
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
        ];
    }
}
