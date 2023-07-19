<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Filter;

use Lkrms\Pretty\Php\Concern\FilterTrait;
use Lkrms\Pretty\Php\Contract\Filter;

/**
 * Remove tokens with no content
 *
 * @api
 */
final class RemoveEmptyTokens implements Filter
{
    use FilterTrait;

    public function filterTokens(array $tokens): array
    {
        $filtered = [];
        foreach ($tokens as $token) {
            if ($token->text !== '') {
                $filtered[] = $token;
            }
        }

        return $filtered;
    }
}
