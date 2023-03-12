<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Filter;

use Lkrms\Pretty\Php\Contract\Filter;
use Lkrms\Pretty\Php\Token;

/**
 * Use var_export() to normalise string constants for comparison
 *
 */
final class NormaliseStrings implements Filter
{
    public function __invoke(array $tokens): array
    {
        return array_map(
            function (Token $t) {
                if ($t->id !== T_CONSTANT_ENCAPSED_STRING) {
                    return $t;
                }
                $string = '';
                eval("\$string = {$t->text};");
                $t->text = var_export($string, true);

                return $t;
            },
            $tokens
        );
    }
}
