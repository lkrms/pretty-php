<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Rule\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Support\TokenTypeIndex;
use Lkrms\PrettyPHP\Token\Token;

/**
 * Apply whitespace to statement terminators
 */
final class StatementSpacing implements TokenRule
{
    use TokenRuleTrait;

    public static function getPriority(string $method): ?int
    {
        switch ($method) {
            case self::PROCESS_TOKEN:
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

    public function processToken(Token $token): void
    {
        switch ($token->id) {
            case \T_COLON:
                // Ignore colons that don't start an alternative syntax block
                if (!$token->ClosedBy) {
                    return;
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
                        return;
                    }
                    $token->WhitespaceAfter |= WhitespaceType::SPACE;
                    $token->WhitespaceMaskNext |= WhitespaceType::SPACE;
                    $token->Next->WhitespaceMaskPrev |= WhitespaceType::SPACE;
                    return;
                }

                // Don't make any changes after __halt_compiler()
                if ($token->Statement->id === \T_HALT_COMPILER) {
                    return;
                }

                // Don't collapse whitespace before empty statements unless they
                // follow a close bracket or semicolon
                if ($token->Statement === $token) {
                    if ($this->Formatter->CollectCodeProblems) {
                        $this->Formatter->reportCodeProblem(
                            'Empty statement',
                            $token,
                        );
                    }
                    if (!$this->TypeIndex->CloseBracket[$token->Prev->id]
                            && $token->Prev->id !== \T_SEMICOLON) {
                        return;
                    }
                }

                break;
        }

        $token->WhitespaceBefore = WhitespaceType::NONE;
        $token->WhitespaceMaskPrev = WhitespaceType::NONE;
        $token->WhitespaceAfter |= WhitespaceType::LINE | WhitespaceType::SPACE;
    }
}
