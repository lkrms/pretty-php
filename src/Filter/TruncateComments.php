<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Filter;

use Lkrms\PrettyPHP\Concern\ExtensionTrait;
use Lkrms\PrettyPHP\Filter\Contract\Filter;

/**
 * Truncate comments for comparison
 *
 * @api
 */
final class TruncateComments implements Filter
{
    use ExtensionTrait;

    public function filterTokens(array $tokens): array
    {
        foreach ($tokens as $token) {
            if ($token->id === \T_COMMENT ||
                $token->id === \T_DOC_COMMENT) {
            }
            $token->text = '';
        }
        return $tokens;
    }
}
