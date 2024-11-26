<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Filter;

use Lkrms\PrettyPHP\Concern\ExtensionTrait;
use Lkrms\PrettyPHP\Contract\Filter;

/**
 * Remove whitespace tokens and invalid control characters
 *
 * @api
 */
final class RemoveWhitespace implements Filter
{
    use ExtensionTrait;

    /**
     * @inheritDoc
     */
    public function filterTokens(array $tokens): array
    {
        foreach ($tokens as $token) {
            if ($this->Idx->Whitespace[$token->id]) {
                continue;
            }
            $filtered[] = $token;
        }

        return $filtered ?? [];
    }
}
