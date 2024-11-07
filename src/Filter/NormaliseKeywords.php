<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Filter;

use Lkrms\PrettyPHP\Concern\ExtensionTrait;
use Lkrms\PrettyPHP\Contract\Filter;
use Lkrms\PrettyPHP\Token;
use Salient\Utility\Regex;

/**
 * Normalise keywords
 *
 * @api
 */
final class NormaliseKeywords implements Filter
{
    use ExtensionTrait;

    /**
     * @inheritDoc
     */
    public function filterTokens(array $tokens): array
    {
        foreach ($tokens as $token) {
            if ($token->id === \T_YIELD_FROM && strcasecmp($token->text, 'yield from')) {
                $text = Regex::replace('/[^[:alpha:]]++/', ' ', $token->text);
                if ($token instanceof Token) {
                    $token->setText($text);
                } else {
                    $token->text = $text;
                }
            }
        }

        return $tokens;
    }
}
