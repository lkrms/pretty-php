<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Filter;

use Lkrms\Pretty\Php\Concern\FilterTrait;
use Lkrms\Pretty\Php\Contract\Filter;
use Lkrms\Pretty\Php\NavigableToken;

/**
 * Use var_export() to normalise string constants for comparison
 *
 * @api
 *
 * @implements Filter<NavigableToken>
 */
final class StandardiseStrings implements Filter
{
    use FilterTrait;

    public function filterTokens(array $tokens): array
    {
        $string = '';
        foreach ($tokens as $token) {
            if ($token->id === T_CONSTANT_ENCAPSED_STRING) {
                eval("\$string = {$token->text};");
                $token->setText(var_export($string, true));
            }
        }

        return $tokens;
    }
}
