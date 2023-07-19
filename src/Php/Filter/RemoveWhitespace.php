<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Filter;

use Lkrms\Pretty\Php\Concern\FilterTrait;
use Lkrms\Pretty\Php\Contract\Filter;

/**
 * Remove whitespace tokens
 *
 * @api
 */
final class RemoveWhitespace implements Filter
{
    use FilterTrait;

    public function filterTokens(array $tokens): array
    {
        $filtered = [];
        foreach ($tokens as $token) {
            if ($token->id !== T_WHITESPACE) {
                $filtered[] = $token;
            }
        }

        return $filtered;
    }
}
