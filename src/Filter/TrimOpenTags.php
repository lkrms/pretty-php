<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Filter;

use Lkrms\PrettyPHP\Concern\FilterTrait;
use Lkrms\PrettyPHP\Contract\Filter;
use Lkrms\PrettyPHP\NavigableToken;

/**
 * Remove whitespace after T_OPEN_TAG and T_OPEN_TAG_WITH_ECHO for comparison
 *
 * @api
 *
 * @implements Filter<NavigableToken>
 */
final class TrimOpenTags implements Filter
{
    use FilterTrait;

    public function filterTokens(array $tokens): array
    {
        foreach ($tokens as $token) {
            if ($token->id === T_OPEN_TAG ||
                    $token->id === T_OPEN_TAG_WITH_ECHO) {
                $token->setText(rtrim($token->text));
            }
        }

        return $tokens;
    }
}
