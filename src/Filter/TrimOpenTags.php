<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Filter;

use Lkrms\PrettyPHP\Concern\ExtensionTrait;
use Lkrms\PrettyPHP\Contract\Filter;

/**
 * Remove whitespace after open tags for comparison
 *
 * @api
 */
final class TrimOpenTags implements Filter
{
    use ExtensionTrait;

    /**
     * @inheritDoc
     */
    public function filterTokens(array $tokens): array
    {
        foreach ($tokens as $token) {
            if ($this->Idx->OpenTag[$token->id]) {
                $token->text = rtrim($token->text);
            }
        }

        return $tokens;
    }
}
