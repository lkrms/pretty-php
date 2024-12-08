<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\TokenFlag;
use Lkrms\PrettyPHP\Catalog\WhitespaceFlag as Space;
use Lkrms\PrettyPHP\Concern\TokenRuleTrait;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\TokenIndex;

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
        return [
            self::PROCESS_TOKENS => 83,
        ][$method] ?? null;
    }

    public static function getTokens(TokenIndex $idx): array
    {
        return TokenIndex::merge(
            $idx->HasStatementWithOptionalBraces,
            $idx->HasExpressionAndStatementWithOptionalBraces,
        );
    }

    public function processTokens(array $tokens): void
    {
        foreach ($tokens as $token) {
            // Ignore the second half of `elseif` expressed as `else if`
            if (
                $token->id === \T_IF
                && $token->PrevCode
                && $token->PrevCode->id === \T_ELSE
            ) {
                continue;
            }

            assert($token->NextCode !== null);

            if (
                $token->id === \T_ELSE
                && $token->NextCode->id === \T_IF
            ) {
                assert($token->NextCode->NextCode !== null);
                $body = $token->NextCode->NextCode->NextSibling;
            } elseif ($this->Idx->HasStatementWithOptionalBraces[$token->id]) {
                $body = $token->NextCode;
            } else {
                $body = $token->NextCode->NextSibling;
            }

            assert($body !== null);

            // Ignore enclosed and empty bodies
            if (
                $body->id === \T_OPEN_BRACE
                || $body->id === \T_COLON
                || $body->id === \T_SEMICOLON
                || ($body->Flags & TokenFlag::STATEMENT_TERMINATOR)
            ) {
                continue;
            }

            $token->Flags |= TokenFlag::HAS_UNENCLOSED_BODY;

            // Add a newline before the token unless it continues a control
            // structure where the previous body had enclosing braces
            if (
                !$token->PrevCode
                || $token->PrevCode->id !== \T_CLOSE_BRACE
                || !$token->continuesControlStructure()
            ) {
                $token->applyWhitespace(Space::LINE_BEFORE);
            }

            // Add newlines and suppress blank lines before unenclosed bodies
            $body->Whitespace |= Space::NO_BLANK_BEFORE | Space::LINE_BEFORE | Space::SPACE_BEFORE;
            $body->removeWhitespace(Space::NO_LINE_BEFORE);

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
            if (
                !$end
                || $end->id === \T_NULL
                || $end->index > $body->EndStatement->index
            ) {
                $end = $body->pragmaticEndOfExpression()
                            ->withTerminator();
            }

            // Use PreIndent to apply indentation that isn't clobbered by
            // `StandardIndentation`
            foreach ($body->collect($end) as $t) {
                $t->PreIndent++;
            }

            // Add a newline after the body
            $end->Whitespace |= Space::LINE_AFTER | Space::SPACE_AFTER;
            $end->removeWhitespace(Space::NO_LINE_AFTER);

            // If the control structure continues, suppress blank lines after
            // the body
            if ($continues) {
                $end->Whitespace |= Space::NO_BLANK_AFTER;
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
