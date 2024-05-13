<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Filter;

use Lkrms\PrettyPHP\Catalog\TokenType;
use Lkrms\PrettyPHP\Concern\ExtensionTrait;
use Lkrms\PrettyPHP\Contract\Filter;
use Lkrms\PrettyPHP\Token\Token;

/**
 * Remove whitespace inside cast operators
 *
 * @api
 */
final class TrimCasts implements Filter
{
    use ExtensionTrait;

    private const CAST_TEXT = [
        \T_INT_CAST => '(int)',
        \T_BOOL_CAST => '(bool)',
        \T_DOUBLE_CAST => '(float)',
        \T_STRING_CAST => '(string)',
        \T_ARRAY_CAST => '(array)',
        \T_OBJECT_CAST => '(object)',
        \T_UNSET_CAST => '(unset)',
    ];

    /** @var array<int,bool> */
    private array $CastIndex;

    /**
     * @inheritDoc
     */
    public function boot(): void
    {
        $this->CastIndex = TokenType::getIndex(
            ...TokenType::CAST,
        );
    }

    /**
     * @inheritDoc
     */
    public function filterTokens(array $tokens): array
    {
        foreach ($tokens as $token) {
            if ($this->CastIndex[$token->id]) {
                $text = self::CAST_TEXT[$token->id];
                if ($text !== $token->text) {
                    if ($token instanceof Token) {
                        $token->setText($text);
                    } else {
                        $token->text = $text;
                    }
                }
            }
        }

        return $tokens;
    }
}
