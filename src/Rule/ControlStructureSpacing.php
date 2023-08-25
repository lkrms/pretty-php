<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\TokenType;
use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Rule\Concern\MultiTokenRuleTrait;
use Lkrms\PrettyPHP\Rule\Contract\MultiTokenRule;

/**
 * Apply whitespace to control structures where the body has no enclosing braces
 *
 * Control structures that meet this criteria are also reported to the user via
 * {@see Formatter::reportCodeProblem()}.
 *
 * @api
 */
final class ControlStructureSpacing implements MultiTokenRule
{
    use MultiTokenRuleTrait;

    public function getPriority(string $method): ?int
    {
        switch ($method) {
            case self::PROCESS_TOKENS:
                return 83;

            default:
                return null;
        }
    }

    public function getTokenTypes(): array
    {
        return [
            ...TokenType::HAS_STATEMENT_WITH_OPTIONAL_BRACES,
            ...TokenType::HAS_EXPRESSION_AND_STATEMENT_WITH_OPTIONAL_BRACES,
        ];
    }

    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            // Ignore the second half of `elseif` expressed as `else if`
            if ($token->id === T_IF &&
                    $token->_prevCode &&
                    $token->_prevCode->id === T_ELSE) {
                continue;
            }

            if ($token->id === T_ELSE &&
                    $token->_nextCode->id === T_IF) {
                $body = $token->_nextSibling->_nextSibling->_nextSibling;
            } elseif ($this->TypeIndex->HasStatementWithOptionalBraces[$token->id]) {
                $body = $token->_nextSibling;
            } else {
                $body = $token->_nextSibling->_nextSibling;
            }

            // Ignore enclosed and empty bodies
            if ($body->id === T_OPEN_BRACE ||
                    $body->id === T_COLON ||
                    $body->id === T_SEMICOLON ||
                    $body->IsStatementTerminator) {
                continue;
            }

            $token->BodyIsUnenclosed = true;

            // Add a newline before the token unless it continues a control
            // structure where the previous body had enclosing braces
            if (!$token->_prevCode ||
                    $token->_prevCode->id !== T_CLOSE_BRACE ||
                    !$token->continuesControlStructure()) {
                $token->WhitespaceBefore |= WhitespaceType::LINE;
                $token->WhitespaceMaskPrev |= WhitespaceType::LINE;
                $token->_prev->WhitespaceMaskNext |= WhitespaceType::LINE;
            }

            // Add newlines and suppress blank lines before unenclosed bodies
            $body->WhitespaceBefore |= WhitespaceType::LINE | WhitespaceType::SPACE;
            $body->WhitespaceMaskPrev |= WhitespaceType::LINE;
            $body->WhitespaceMaskPrev &= ~WhitespaceType::BLANK;
            $body->_prev->WhitespaceMaskNext |= WhitespaceType::LINE;

            // Find the last token in the body
            $end = null;
            $continues = false;
            if ($token->id === T_DO) {
                $continues = true;
            } elseif ($token->is([T_IF, T_ELSEIF])) {
                $end = $body->prevSibling()->nextSiblingOf(T_IF, T_ELSEIF, T_ELSE);
                if ($end->id === T_IF) {
                    $end = $body->EndStatement;
                } elseif (!$end->IsNull) {
                    $end = $end->_prevCode;
                    $continues = true;
                }
            }
            if (!$end ||
                    $end->IsNull ||
                    $end->Index > $body->EndStatement->Index) {
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

            if (!$this->Formatter->CollectCodeProblems) {
                continue;
            }

            $this->Formatter->reportCodeProblem(
                $this,
                'Braces not used in %s control structure',
                $token,
                $end,
                $token->getTokenName()
            );
        }
    }
}
