<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Filter;

use Lkrms\Pretty\Php\Concern\FilterTrait;
use Lkrms\Pretty\Php\Contract\Filter;
use Lkrms\Pretty\Php\Token;

/**
 * Use var_export() to normalise string constants for comparison
 *
 */
final class NormaliseStrings implements Filter
{
    use FilterTrait;

    public function filterTokens(array $tokens): array
    {
        $strings = array_filter(
            $tokens,
            fn(Token $t) => $t->id === T_CONSTANT_ENCAPSED_STRING
        );

        $string = '';
        array_walk(
            $strings,
            function (Token $t) use ($string) {
                eval("\$string = {$t->text};");
                $t->setText(var_export($string, true));
            }
        );

        return $tokens;
    }
}
