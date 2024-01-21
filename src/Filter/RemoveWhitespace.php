<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Filter;

use Lkrms\PrettyPHP\Concern\ExtensionTrait;
use Lkrms\PrettyPHP\Contract\Filter;

/**
 * Remove whitespace tokens and control characters other than horizontal tabs,
 * line feeds and carriage returns
 *
 * @api
 */
final class RemoveWhitespace implements Filter
{
    use ExtensionTrait;

    public function filterTokens(array $tokens): array
    {
        foreach ($tokens as $token) {
            if ($token->id === \T_WHITESPACE ||
                    $token->id === \T_BAD_CHARACTER) {
                continue;
            }
            $filtered[] = $token;
        }

        return $filtered ?? [];
    }
}
