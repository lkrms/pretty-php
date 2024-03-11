<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Filter;

use Lkrms\PrettyPHP\Concern\ExtensionTrait;
use Lkrms\PrettyPHP\Contract\Filter;
use Salient\Core\Utility\Pcre;

/**
 * Remove empty DocBlocks
 *
 * @api
 */
final class RemoveEmptyDocBlocks implements Filter
{
    use ExtensionTrait;

    /**
     * @inheritDoc
     */
    public function filterTokens(array $tokens): array
    {
        foreach ($tokens as $token) {
            if ($token->id === \T_DOC_COMMENT
                    && Pcre::match('#^/\*\*[\s\*]*\*/$#', $token->text)) {
                continue;
            }
            $filtered[] = $token;
        }

        return $filtered ?? [];
    }
}
