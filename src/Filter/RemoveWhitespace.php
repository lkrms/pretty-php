<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Filter;

use Lkrms\PrettyPHP\Concern\FilterTrait;
use Lkrms\PrettyPHP\Contract\Filter;

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