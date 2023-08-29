<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Filter;

use Lkrms\PrettyPHP\Concern\ExtensionTrait;
use Lkrms\PrettyPHP\Filter\Contract\Filter;

/**
 * Remove whitespace tokens
 *
 * @api
 */
final class RemoveWhitespace implements Filter
{
    use ExtensionTrait;

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
