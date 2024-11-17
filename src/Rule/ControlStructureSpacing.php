<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\TokenFlag;
use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\TokenTypeIndex;

/**
 * Apply whitespace to control structures where the body has no enclosing braces
 *
 * Control structures that meet this criteria are also reported to the user via
 * {@see Formatter::registerProblem()}.
 *
 * @api
 */
final class ControlStructureSpacing implements TokenRule
{
    use TokenRuleTrait;

    public static function getPriority(string $method): ?int
    {
        switch ($method) {
            case self::PROCESS_TOKENS:
                return 83;

            default:
                return null;
        }
    }

    public static function getTokenTypes(TokenTypeIndex $idx): array
    {
        return TokenTypeIndex::merge(
            $idx->HasStatementWithOptionalBraces,
            $idx->HasExpressionAndStatementWithOptionalBraces,
        );
    }

    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            // Ignore the second half of `elseif` expressed as `else if`
            if ($token->id === \T_IF
                    && $token->PrevCode
                    && $token->PrevCode->id === \T_ELSE) {
                continue;
            }

            assert($token->NextCode !== null);

            if ($token->id === \T_ELSE
                    && $token->NextCode->id === \T_IF) {
                assert($token->NextCode->NextCode !== null);
                $body = $token->NextCode->NextCode->NextSibling;
            } elseif ($this->Idx->HasStatementWithOptionalBraces[$token->id]) {
                $body = $token->NextCode;
            } else {
                $body = $token->NextCode->NextSibling;
            }

            assert($body !== null);

            // Ignore enclosed and empty bodies
            if ($body->id === \T_OPEN_BRACE
                    || $body->id === \T_COLON
                    || $body->id === \T_SEMICOLON
                    || ($body->Flags & TokenFlag::STATEMENT_TERMINATOR)) {
                continue;
            }

            $token->Flags |= TokenFlag::HAS_UNENCLOSED_BODY;

            // Add a newline before the token unless it continues a control
            // structure where the previous body had enclosing braces
            if (!$token->PrevCode
                    || $token->PrevCode->id !== \T_CLOSE_BRACE
                    || !$token->continuesControlStructure()) {
                $token->WhitespaceBefore |= WhitespaceType::LINE;
                $token->WhitespaceMaskPrev |= WhitespaceType::LINE;
                $token->Prev->WhitespaceMaskNext |= WhitespaceType::LINE;
            }

            // Add newlines and suppress blank lines before unenclosed bodies
            $body->WhitespaceBefore |= WhitespaceType::LINE | WhitespaceType::SPACE;
            $body->WhitespaceMaskPrev |= WhitespaceType::LINE;
            $body->WhitespaceMaskPrev &= ~WhitespaceType::BLANK;
            $body->Prev->WhitespaceMaskNext |= WhitespaceType::LINE;

            // Find the last token in the body
            $end = null;
            $continues = false;
            if ($token->id === \T_DO) {
                $continues = true;
            } elseif ($this->Idx->IfOrElseIf[$token->id]) {
                assert($body->PrevSibling !== null);
                $end = $body->PrevSibling->nextSiblingFrom($this->Idx->IfElseIfOrElse);
                if ($end->id === \T_IF) {
                    $end = $body->EndStatement;
                } elseif ($end->id !== \T_NULL) {
                    $end = $end->PrevCode;
                    $continues = true;
                }
            }
            if (!$end
                    || $end->id === \T_NULL
                    || $end->Index > $body->EndStatement->Index) {
                $end = $body->pragmaticEndOfExpression()
                            ->withTerminator();
            }

            // Use PreIndent to apply indentation that isn't clobbered by
            // `StandardIndentation`
            foreach ($body->collect($end) as $t) {
                $t->PreIndent++;
            }

            // Add a newline after the body
            $end->WhitespaceAfter |= WhitespaceType::LINE | WhitespaceType::SPACE;
            $end->WhitespaceMaskNext |= WhitespaceType::LINE;

            // If the control structure continues, suppress blank lines after
            // the body
            if ($continues) {
                $end->WhitespaceMaskNext &= ~WhitespaceType::BLANK;
            }

            if (!$this->Formatter->DetectProblems) {
                continue;
            }

            $this->Formatter->registerProblem(
                'Braces not used in %s control structure',
                $token,
                $end,
                $token->getTokenName(),
            );
        }
    }
}
