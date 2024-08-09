<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Rule\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Support\TokenTypeIndex;

/**
 * Apply whitespace to statement terminators
 */
final class StatementSpacing implements TokenRule
{
    use TokenRuleTrait;

    public static function getPriority(string $method): ?int
    {
        switch ($method) {
            case self::PROCESS_TOKENS:
                return 80;

            default:
                return null;
        }
    }

    public static function getTokenTypes(TokenTypeIndex $typeIndex): array
    {
        return [
            \T_COLON,
            \T_SEMICOLON,
        ];
    }

    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            switch ($token->id) {
                case \T_COLON:
                    // Ignore colons that don't start an alternative syntax block
                    if (!$token->ClosedBy) {
                        continue 2;
                    }
                    break;

                case \T_SEMICOLON:
                    // Add SPACE after for loop expression delimiters where the next
                    // expression is non-empty
                    if ($token->Parent
                            && $token->Parent->PrevCode
                            && $token->Parent->id === \T_OPEN_PARENTHESIS
                            && $token->Parent->PrevCode->id === \T_FOR) {
                        if (!$token->NextSibling
                                || $token->NextSibling->id === \T_SEMICOLON) {
                            continue 2;
                        }
                        $token->WhitespaceAfter |= WhitespaceType::SPACE;
                        $token->WhitespaceMaskNext |= WhitespaceType::SPACE;
                        $token->Next->WhitespaceMaskPrev |= WhitespaceType::SPACE;
                        continue 2;
                    }

                    // Don't make any changes after __halt_compiler()
                    if ($token->Statement->id === \T_HALT_COMPILER) {
                        continue 2;
                    }

                    // Don't collapse whitespace before empty statements unless they
                    // follow a close bracket or semicolon
                    if ($token->Statement === $token) {
                        if ($this->Formatter->DetectProblems) {
                            $this->Formatter->registerProblem(
                                'Empty statement',
                                $token,
                            );
                        }
                        if (!$this->TypeIndex->CloseBracket[$token->Prev->id]
                                && $token->Prev->id !== \T_SEMICOLON) {
                            continue 2;
                        }
                    }

                    break;
            }

            $token->WhitespaceBefore = WhitespaceType::NONE;
            $token->WhitespaceMaskPrev = WhitespaceType::NONE;
            $token->WhitespaceAfter |= WhitespaceType::LINE | WhitespaceType::SPACE;
        }
    }
}
