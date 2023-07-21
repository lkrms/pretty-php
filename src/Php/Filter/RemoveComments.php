<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Filter;

use Lkrms\Pretty\Php\Concern\FilterTrait;
use Lkrms\Pretty\Php\Contract\Filter;

/**
 * Remove comments for comparison
 *
 * @api
 */
final class RemoveComments implements Filter
{
    use FilterTrait;

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
