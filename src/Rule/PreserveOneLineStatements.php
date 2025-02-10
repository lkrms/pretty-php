<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\WhitespaceFlag as Space;
use Lkrms\PrettyPHP\Concern\StatementRuleTrait;
use Lkrms\PrettyPHP\Contract\StatementRule;
use Lkrms\PrettyPHP\Token;

/**
 * Suppress newlines between tokens in statements and control structures that
 * start and end on the same line in the input
 *
 * @api
 */
final class PreserveOneLineStatements implements StatementRule
{
    use StatementRuleTrait;

    /**
     * @inheritDoc
     */
    public static function getPriority(string $method): ?int
    {
        return [
            self::PROCESS_STATEMENTS => 202,
        ][$method] ?? null;
    }

    /**
     * Apply the rule to the given statements
     *
     * Newlines are suppressed between tokens in statements and control
     * structures that start and end on the same line in the input.
     *
     * If a `switch` case and its statement list are on the same line in the
     * input, they are treated as one statement.
     *
     * Attributes on their own line are excluded from consideration.
     *
     * For any control structures that remain where every `if`/`elseif`/`else`
     * or `try`/`catch`/`finally` statement starts and ends on the same line in
     * the input:
     *
     * - newlines are added before each `elseif`/`else`/`catch`/`finally` token
     * - newlines are suppressed between tokens in each statement
     * - blank lines are added before and after the control structure
     */
    public function processStatements(array $statements): void
    {
        foreach ($statements as $token) {
            if (
                ($end = $token->endOfSwitchCaseStatementList())
                && $this->preserveOneLine($token, $end)
            ) {
                continue;
            }

            /** @var Token */
            $end = $token->EndStatement;
            if (
                $this->preserveOneLine($token, $end) || (
                    $this->Idx->Attribute[$token->id]
                    && $this->preserveOneLine(
                        $token->skipNextSiblingFrom($this->Idx->Attribute),
                        $end,
                    )
                )
            ) {
                continue;
            }

            if ($this->Idx->IfOrTry[$token->id]) {
                $parts = $token->withNextSiblings($end)
                               ->getAnyFrom($this->Idx->ContinuesControlStructure);
                if (!$parts->isEmpty()) {
                    $parts[] = $end;
                    $first = $token;
                    $lines = [];
                    foreach ($parts as $next) {
                        /** @var Token */
                        $last = $next === $end
                            ? $next
                            : $next->PrevCode;
                        if ($first->line !== $last->line) {
                            $lines = [];
                            break;
                        }
                        $lines[] = [$first, $last];
                        $first = $next;
                    }
                    if ($lines) {
                        foreach ($lines as [$first, $last]) {
                            $this->preserveOneLine($first, $last, true);
                            if ($first !== $token) {
                                $first->applyWhitespace(Space::LINE_BEFORE);
                            }
                        }
                        /** @var Token */
                        $openTag = $token->OpenTag;
                        if ($openTag->NextCode !== $token) {
                            $token->applyBlankBefore();
                        }
                        if ($last->Next) {
                            $last->Whitespace |= Space::BLANK_AFTER;
                        }
                    }
                }
            }
        }
    }
}
