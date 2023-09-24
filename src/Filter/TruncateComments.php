<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Filter;

use Lkrms\PrettyPHP\Concern\ExtensionTrait;
use Lkrms\PrettyPHP\Filter\Contract\Filter;

/**
 * Remove comments for comparison
 *
 * @api
 */
final class TruncateComments implements Filter
{
    use ExtensionTrait;

    public function filterTokens(array $tokens): array
    {
        $filtered = [];
        foreach ($tokens as $token) {
            if ($token->id !== T_DOC_COMMENT &&
                    $token->id !== T_COMMENT) {
                $filtered[] = $token;
            }
        }

        return $filtered;
    }
}
