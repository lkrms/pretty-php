<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Filter;

use Lkrms\PrettyPHP\Concern\ExtensionTrait;
use Lkrms\PrettyPHP\Contract\Filter;

/**
 * Normalise binary strings on PHP < 8.0
 *
 * @api
 */
final class NormaliseBinaryStrings implements Filter
{
    use ExtensionTrait;

    /**
     * @inheritDoc
     */
    public function filterTokens(array $tokens): array
    {
        if (\PHP_VERSION_ID >= 80000) {
            return $tokens;
        }

        foreach ($tokens as $token) {
            if (
                (
                    $token->id === 98     // "b"
                    || $token->id === 66  // "B"
                )
                && $token->text[-1] === '"'
            ) {
                $token->id = \T_DOUBLE_QUOTE;
            }
        }

        return $tokens;
    }
}
