<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

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
            self::PROCESS_STATEMENTS => 95,
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
                !$this->preserveOneLine($token, $end)
                && $this->Idx->Attribute[$token->id]
            ) {
                $start = $token->skipNextSiblingFrom($this->Idx->Attribute);
                $this->preserveOneLine($start, $end);
            }
        }
    }
}
