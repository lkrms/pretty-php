<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Filter;

use Lkrms\PrettyPHP\Catalog\ImportSortOrder;
use Lkrms\PrettyPHP\Rule\AlignComments;
use Lkrms\PrettyPHP\Formatter;

final class SortImportsTest extends \Lkrms\PrettyPHP\Tests\TestCase
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
            'with comments #1' => [
                <<<'PHP'
<?php
use Alpha;    /* One
               * multi-line
               * comment */
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
                [
                    'addRules' => [AlignComments::class],
                ],
            ],
            'with comments #2' => [
                <<<'PHP'
<?php
use B;  // Multiple
// one-line
// comments
use C;
# Different comment type = new block
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
                [
                    'callback' =>
                        fn(Formatter $f) =>
                            $f->with('ImportSortOrder', ImportSortOrder::NAME),
                ],
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
            ],
        ];
    }
}
