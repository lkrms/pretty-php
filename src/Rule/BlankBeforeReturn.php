<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\TokenType;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Rule\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Support\TokenTypeIndex;

/**
 * Add a blank line before return and yield statements unless they appear
 * consecutively or at the beginning of a compound statement
 *
 * @api
 */
final class BlankBeforeReturn implements TokenRule
{
    use TokenRuleTrait;

    public static function getPriority(string $method): ?int
    {
        switch ($method) {
            case self::PROCESS_TOKENS:
                return 97;

            default:
                return null;
        }
    }

    public static function getTokenTypes(TokenTypeIndex $typeIndex): array
    {
        return TokenType::RETURN;
    }

    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            if (
                $token->PrevSibling
                && $token->PrevSibling->Statement
                && $this->Idx->Return[$token->PrevSibling->Statement->id]
            ) {
                continue;
            }

            $token->applyBlankLineBefore();
        }
    }
}
