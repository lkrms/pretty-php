<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\TokenSubId;
use Lkrms\PrettyPHP\Catalog\WhitespaceFlag as Space;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\TokenTypeIndex;

/**
 * Apply whitespace to statement terminators
 */
final class StatementSpacing implements TokenRule
{
    use TokenRuleTrait;

    public static function getPriority(string $method): ?int
    {
        return [
            self::PROCESS_TOKENS => 80,
        ][$method] ?? null;
    }

    public static function getTokenTypes(TokenTypeIndex $idx): array
    {
        return [
            \T_COLON => true,
            \T_SEMICOLON => true,
        ];
    }

    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            switch ($token->id) {
                case \T_COLON:
                    // Ignore colons that don't start an alternative syntax block
                    if (!$token->ClosedBy && $token->getSubId() !== TokenSubId::COLON_LABEL_DELIMITER) {
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
                        $token->applyWhitespace(Space::SPACE_AFTER);
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
                        if (!$this->Idx->CloseBracket[$token->Prev->id]
                                && $token->Prev->id !== \T_SEMICOLON) {
                            continue 2;
                        }
                    }

                    break;
            }

            $token->Whitespace |= Space::NONE_BEFORE | Space::LINE_AFTER | Space::SPACE_AFTER;
        }
    }
}
