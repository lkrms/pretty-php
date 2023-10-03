<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule\Preset;

use Lkrms\PrettyPHP\Catalog\TokenType;
use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Rule\Concern\MultiTokenRuleTrait;
use Lkrms\PrettyPHP\Rule\Contract\ListRule;
use Lkrms\PrettyPHP\Rule\Contract\MultiTokenRule;
use Lkrms\PrettyPHP\Support\TokenCollection;
use Lkrms\PrettyPHP\Token\Token;

/**
 * Apply formatting specific to Symfony's coding standards
 *
 * - Suppress horizontal space before and after '.'
 * - Add a space after 'fn' in arrow functions
 * - Add a newline before parameters in constructor declarations where one or
 *   more are promoted
 * - Suppress newlines between parameters in other function declarations
 */
final class Symfony implements MultiTokenRule, ListRule
{
    use MultiTokenRuleTrait;

    public function getPriority(string $method): ?int
    {
        switch ($method) {
            case self::PROCESS_TOKENS:
                return 100;

            case self::PROCESS_LIST:
                return 100;

            default:
                return null;
        }
    }

    public function getTokenTypes(): array
    {
        return [
            T_CONCAT,
            T_FN,
        ];
    }

    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            switch ($token->id) {
                case T_CONCAT;
                    $token->WhitespaceMaskPrev &= ~WhitespaceType::SPACE;
                    $token->WhitespaceMaskNext &= ~WhitespaceType::SPACE;
                    continue 2;

                case T_FN:
                    $token->WhitespaceAfter |= WhitespaceType::SPACE;
                    $token->WhitespaceMaskNext |= WhitespaceType::SPACE;
                    continue 2;
            }
        }
    }

    public function processList(Token $owner, TokenCollection $items): void
    {
        if (!$owner->isParameterList()) {
            return;
        }

        foreach ($items as $item) {
            if ($this->TypeIndex->Visibility[$item->id]) {
                foreach ($items as $item) {
                    $item->WhitespaceBefore |= WhitespaceType::LINE;
                }
                return;
            }
        }

        $owner->outer()->maskInnerWhitespace(~WhitespaceType::BLANK & ~WhitespaceType::LINE);
    }
}
