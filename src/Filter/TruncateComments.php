<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Filter;

use Lkrms\PrettyPHP\Concern\ExtensionTrait;
use Lkrms\PrettyPHP\Contract\Filter;

/**
 * Truncate comments for comparison
 *
 * @api
 */
final class TruncateComments implements Filter
{
    use ExtensionTrait;

    /**
     * @inheritDoc
     */
    public function filterTokens(array $tokens): array
    {
        foreach ($tokens as $token) {
            if ($this->TypeIndex->Comment[$token->id]) {
                $token->text = '';
            }
        }

        return $tokens;
    }
}
