<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Filter;

use Lkrms\PrettyPHP\Filter\Concern\FilterTrait;
use Lkrms\PrettyPHP\Filter\Contract\Filter;

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
