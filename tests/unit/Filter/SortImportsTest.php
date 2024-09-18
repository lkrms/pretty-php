<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Tests\Filter;

use Lkrms\PrettyPHP\Catalog\ImportSortOrder;
use Lkrms\PrettyPHP\Filter\SortImports;
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
        $builder = Formatter::build();
        $formatter = $builder->build();

        $input = <<<'PHP'
<?php
use B\C\F\I;
use function B\C\F\I_F;
use const B\C\F\I_C;
use B\D;
use function B\D_F;
use const B\D_C;
use B\C\F\{H,J};
use function B\C\F\{H_F,J_F};
use const B\C\F\{H_C,J_C};
use A;
use function A_F;
use const A_C;
use B\C\F\G;
use function B\C\F\G_F;
use const B\C\F\G_C;
use B\C\E;
use function B\C\E_F;
use const B\C\E_C;
use B\C\F\H\M;
use function B\C\F\H\M_F;
use const B\C\F\H\M_C;
use B\C\F\H\H as HHHH;
use function B\C\F\H\H_F as HHHH_F;
use const B\C\F\H\H_C as HHHH_C;
use B\C\F\H\A as AA;
use function B\C\F\H\A_F as AA_F;
use const B\C\F\H\A_C as AA_C;
use B\C\F\H as HHH;
use function B\C\F\H_F as HHH_F;
use const B\C\F\H_C as HHH_C;
use B\C\F\{H as HH,K};
use function B\C\F\{H_F as HH_F,K_F};
use const B\C\F\{H_C as HH_C,K_C};
use S\T\U\F\F;
use function S\T\U\F\F_F;
use const S\T\U\F\F_C;
use L;
use function L_F;
use const L_C;
use B\C\F as FF;
use function B\C\F_F as FF_F;
use const B\C\F_C as FF_C;
use B\C;
use function B\C_F;
use const B\C_C;
use B;
use function B_F;
use const B_C;
PHP;

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
                $builder->enable([AlignComments::class]),
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

use function B\C\F\H\A_F as AA_F;
use function B\C\F\H\H_F as HHHH_F;
use function B\C\F\H\M_F;
use function B\C\F\{H_F, J_F};
use function B\C\F\{H_F as HH_F, K_F};
use function B\C\F\G_F;
use function B\C\F\H_F as HHH_F;
use function B\C\F\I_F;
use function B\C\E_F;
use function B\C\F_F as FF_F;
use function B\C_F;
use function B\D_F;
use function S\T\U\F\F_F;
use function A_F;
use function B_F;
use function L_F;

use const B\C\F\H\A_C as AA_C;
use const B\C\F\H\H_C as HHHH_C;
use const B\C\F\H\M_C;
use const B\C\F\{H_C, J_C};
use const B\C\F\{H_C as HH_C, K_C};
use const B\C\F\G_C;
use const B\C\F\H_C as HHH_C;
use const B\C\F\I_C;
use const B\C\E_C;
use const B\C\F_C as FF_C;
use const B\C_C;
use const B\D_C;
use const S\T\U\F\F_C;
use const A_C;
use const B_C;
use const L_C;

PHP,
                $input,
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

use function A_F;
use function B\C\E_F;
use function B\C\F\G_F;
use function B\C\F\H\A_F as AA_F;
use function B\C\F\H\H_F as HHHH_F;
use function B\C\F\H\M_F;
use function B\C\F\{H_F, J_F};
use function B\C\F\{H_F as HH_F, K_F};
use function B\C\F\H_F as HHH_F;
use function B\C\F\I_F;
use function B\C\F_F as FF_F;
use function B\C_F;
use function B\D_F;
use function B_F;
use function L_F;
use function S\T\U\F\F_F;

use const A_C;
use const B\C\E_C;
use const B\C\F\G_C;
use const B\C\F\H\A_C as AA_C;
use const B\C\F\H\H_C as HHHH_C;
use const B\C\F\H\M_C;
use const B\C\F\{H_C, J_C};
use const B\C\F\{H_C as HH_C, K_C};
use const B\C\F\H_C as HHH_C;
use const B\C\F\I_C;
use const B\C\F_C as FF_C;
use const B\C_C;
use const B\D_C;
use const B_C;
use const L_C;
use const S\T\U\F\F_C;

PHP,
                $input,
                $builder->importSortOrder(ImportSortOrder::NAME),
            ],
            'not sorted' => [
                <<<'PHP'
<?php
use B\C\F\I;
use B\D;
use B\C\F\{H, J};
use A;
use B\C\F\G;
use B\C\E;
use B\C\F\H\M;
use B\C\F\H\H as HHHH;
use B\C\F\H\A as AA;
use B\C\F\H as HHH;
use B\C\F\{H as HH, K};
use S\T\U\F\F;
use L;
use B\C\F as FF;
use B\C;
use B;

use function B\C\F\I_F;
use function B\D_F;
use function B\C\F\{H_F, J_F};
use function A_F;
use function B\C\F\G_F;
use function B\C\E_F;
use function B\C\F\H\M_F;
use function B\C\F\H\H_F as HHHH_F;
use function B\C\F\H\A_F as AA_F;
use function B\C\F\H_F as HHH_F;
use function B\C\F\{H_F as HH_F, K_F};
use function S\T\U\F\F_F;
use function L_F;
use function B\C\F_F as FF_F;
use function B\C_F;
use function B_F;

use const B\C\F\I_C;
use const B\D_C;
use const B\C\F\{H_C, J_C};
use const A_C;
use const B\C\F\G_C;
use const B\C\E_C;
use const B\C\F\H\M_C;
use const B\C\F\H\H_C as HHHH_C;
use const B\C\F\H\A_C as AA_C;
use const B\C\F\H_C as HHH_C;
use const B\C\F\{H_C as HH_C, K_C};
use const S\T\U\F\F_C;
use const L_C;
use const B\C\F_C as FF_C;
use const B\C_C;
use const B_C;

PHP,
                $input,
                $builder->importSortOrder(ImportSortOrder::NONE),
            ],
            'not grouped' => [
                <<<'PHP'
<?php
use B\C\F\I;
use function B\C\F\I_F;
use const B\C\F\I_C;
use B\D;
use function B\D_F;
use const B\D_C;
use B\C\F\{H, J};
use function B\C\F\{H_F, J_F};
use const B\C\F\{H_C, J_C};
use A;
use function A_F;
use const A_C;
use B\C\F\G;
use function B\C\F\G_F;
use const B\C\F\G_C;
use B\C\E;
use function B\C\E_F;
use const B\C\E_C;
use B\C\F\H\M;
use function B\C\F\H\M_F;
use const B\C\F\H\M_C;
use B\C\F\H\H as HHHH;
use function B\C\F\H\H_F as HHHH_F;
use const B\C\F\H\H_C as HHHH_C;
use B\C\F\H\A as AA;
use function B\C\F\H\A_F as AA_F;
use const B\C\F\H\A_C as AA_C;
use B\C\F\H as HHH;
use function B\C\F\H_F as HHH_F;
use const B\C\F\H_C as HHH_C;
use B\C\F\{H as HH, K};
use function B\C\F\{H_F as HH_F, K_F};
use const B\C\F\{H_C as HH_C, K_C};
use S\T\U\F\F;
use function S\T\U\F\F_F;
use const S\T\U\F\F_C;
use L;
use function L_F;
use const L_C;
use B\C\F as FF;
use function B\C\F_F as FF_F;
use const B\C\F_C as FF_C;
use B\C;
use function B\C_F;
use const B\C_C;
use B;
use function B_F;
use const B_C;

PHP,
                $input,
                $builder->disable([SortImports::class]),
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
                $builder->enable([AlignComments::class]),
            ],
        ];
    }
}
