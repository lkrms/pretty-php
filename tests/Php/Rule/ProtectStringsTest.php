<?php declare(strict_types=1);

namespace Lkrms\Pretty\Tests\Php\Rule;

final class ProtectStringsTest extends \Lkrms\Pretty\Tests\Php\TestCase
{
    /**
     * @dataProvider processTokenProvider
     */
    public function testProcessToken(string $code, string $expected)
    {
        $this->assertFormatterOutputIs($code, $expected);
    }

    public static function processTokenProvider()
    {
        return [
            'nested heredocs' => [
                <<<'PHP'
<?php
$docBlock = <<<EOF
/**
 {$this->getLines(
$this->desc
? <<<EOF
 * $desc
 *
EOF
: ''
)}
 */
EOF;
PHP,
                <<<'PHP'
<?php
$docBlock = <<<EOF
    /**
     {$this->getLines(
        $this->desc
            ? <<<EOF
                 * $desc
                 *
                EOF
            : ''
    )}
     */
    EOF;

PHP,
            ],
            'nested strings' => [
                <<<'PHP'
<?php
$docBlock = "/**
 {$this->getLines(
$this->desc
? " * $desc
 *"
: ''
)}
 */";
PHP,
                <<<'PHP'
<?php
$docBlock = "/**
 {$this->getLines(
    $this->desc
        ? " * $desc
 *"
        : ''
)}
 */";

PHP,
            ],
            'variable parsing' => [
                <<<'PHP'
<?php
$s="$A{$B}c{$C}d";
$s="a$A{$B}c$C";
$s="$A[1]$B[2]c$C[3]d";
$s="a$A[1]$B[2]c$C[3]";
$s="{$A[1]}{$B[2]}c{$C[3]}d";
$s="a{$A[1]}{$B[2]}c{$C[3]}";
$s="{$A->a}{$B->b}c{$C->c}d";
$s="a{$A->a}{$B->b}c{$C->c}";
$s="{$A[$A2->a]}{$B[$B2->b]}c{$C[$C2->c]}d";
$s="a{$A[$A2->a]}{$B[$B2->b]}c{$C[$C2->c]}";
$s="{$A->{$A2->a}}{$B->{$B2->b}}c{$C->{$C2->c}}d";
$s="a{$A->{$A2->a}}{$B->{$B2->b}}c{$C->{$C2->c}}";
$s="${A[1]}${B[2]}c${C[3]}d";
$s="a${A[1]}${B[2]}c${C[3]}";
PHP,
                <<<'PHP'
<?php
$s = "$A{$B}c{$C}d";
$s = "a$A{$B}c$C";
$s = "$A[1]$B[2]c$C[3]d";
$s = "a$A[1]$B[2]c$C[3]";
$s = "{$A[1]}{$B[2]}c{$C[3]}d";
$s = "a{$A[1]}{$B[2]}c{$C[3]}";
$s = "{$A->a}{$B->b}c{$C->c}d";
$s = "a{$A->a}{$B->b}c{$C->c}";
$s = "{$A[$A2->a]}{$B[$B2->b]}c{$C[$C2->c]}d";
$s = "a{$A[$A2->a]}{$B[$B2->b]}c{$C[$C2->c]}";
$s = "{$A->{$A2->a}}{$B->{$B2->b}}c{$C->{$C2->c}}d";
$s = "a{$A->{$A2->a}}{$B->{$B2->b}}c{$C->{$C2->c}}";
$s = "${A[1]}${B[2]}c${C[3]}d";
$s = "a${A[1]}${B[2]}c${C[3]}";

PHP,
            ],
            'variable parsing between backticks' => [
                <<<'PHP'
<?php
$s=`$A{$B}c{$C}d`;
$s=`a$A{$B}c$C`;
$s=`$A[1]$B[2]c$C[3]d`;
$s=`a$A[1]$B[2]c$C[3]`;
$s=`{$A[1]}{$B[2]}c{$C[3]}d`;
$s=`a{$A[1]}{$B[2]}c{$C[3]}`;
$s=`{$A->a}{$B->b}c{$C->c}d`;
$s=`a{$A->a}{$B->b}c{$C->c}`;
$s=`{$A[$A2->a]}{$B[$B2->b]}c{$C[$C2->c]}d`;
$s=`a{$A[$A2->a]}{$B[$B2->b]}c{$C[$C2->c]}`;
$s=`{$A->{$A2->a}}{$B->{$B2->b}}c{$C->{$C2->c}}d`;
$s=`a{$A->{$A2->a}}{$B->{$B2->b}}c{$C->{$C2->c}}`;
$s=`${A[1]}${B[2]}c${C[3]}d`;
$s=`a${A[1]}${B[2]}c${C[3]}`;
PHP,
                <<<'PHP'
<?php
$s = `$A{$B}c{$C}d`;
$s = `a$A{$B}c$C`;
$s = `$A[1]$B[2]c$C[3]d`;
$s = `a$A[1]$B[2]c$C[3]`;
$s = `{$A[1]}{$B[2]}c{$C[3]}d`;
$s = `a{$A[1]}{$B[2]}c{$C[3]}`;
$s = `{$A->a}{$B->b}c{$C->c}d`;
$s = `a{$A->a}{$B->b}c{$C->c}`;
$s = `{$A[$A2->a]}{$B[$B2->b]}c{$C[$C2->c]}d`;
$s = `a{$A[$A2->a]}{$B[$B2->b]}c{$C[$C2->c]}`;
$s = `{$A->{$A2->a}}{$B->{$B2->b}}c{$C->{$C2->c}}d`;
$s = `a{$A->{$A2->a}}{$B->{$B2->b}}c{$C->{$C2->c}}`;
$s = `${A[1]}${B[2]}c${C[3]}d`;
$s = `a${A[1]}${B[2]}c${C[3]}`;

PHP,
            ],
        ];
    }
}
