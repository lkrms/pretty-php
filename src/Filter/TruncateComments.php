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
            if ($token->id === \T_DOC_COMMENT) {
                $token->text = '/** */';
            } elseif ($token->id === \T_COMMENT) {
                $token->text = $token->text[0] === '/'
                    ? ($token->text[1] === '/' ? '//' : '/* */')
                    : (
                        \PHP_VERSION_ID < 80000
                        && substr($token->text, 0, 2) === '#['
                            ? '#'
                            : '//'
                    );
            }
        }

        return $tokens;
    }
}
