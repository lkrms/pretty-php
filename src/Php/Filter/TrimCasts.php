<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Filter;

use Lkrms\Pretty\Php\Concern\FilterTrait;
use Lkrms\Pretty\Php\Contract\Filter;
use Lkrms\Pretty\Php\NavigableToken;

/**
 * Remove whitespace inside cast operators
 *
 * @api
 *
 * @implements Filter<NavigableToken>
 */
final class TrimCasts implements Filter
{
    use FilterTrait;

    public function filterTokens(array $tokens): array
    {
        foreach ($tokens as $token) {
            if ($token->id === T_INT_CAST ||
                    $token->id === T_BOOL_CAST ||
                    $token->id === T_DOUBLE_CAST ||
                    $token->id === T_STRING_CAST ||
                    $token->id === T_ARRAY_CAST ||
                    $token->id === T_OBJECT_CAST ||
                    $token->id === T_UNSET_CAST) {
                $token->setText('(' . trim($token->text, " \n\r\t\v\0()") . ')');
            }
        }

        return $tokens;
    }
}
