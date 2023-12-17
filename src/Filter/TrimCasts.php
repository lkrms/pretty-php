<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Filter;

use Lkrms\PrettyPHP\Concern\ExtensionTrait;
use Lkrms\PrettyPHP\Filter\Contract\Filter;

/**
 * Remove whitespace inside cast operators
 *
 * @api
 */
final class TrimCasts implements Filter
{
    use ExtensionTrait;

    public function filterTokens(array $tokens): array
    {
        foreach ($tokens as $token) {
            if ($token->id === \T_INT_CAST ||
                    $token->id === \T_BOOL_CAST ||
                    $token->id === \T_DOUBLE_CAST ||
                    $token->id === \T_STRING_CAST ||
                    $token->id === \T_ARRAY_CAST ||
                    $token->id === \T_OBJECT_CAST ||
                    $token->id === \T_UNSET_CAST) {
                $token->setText('(' . trim($token->text, " \n\r\t\v\0()") . ')');
            }
        }

        return $tokens;
    }
}
