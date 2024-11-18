<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Contract;

use Lkrms\PrettyPHP\Token;

/**
 * @api
 */
interface StatementRule extends Rule
{
    public const PROCESS_STATEMENTS = 'processStatements';

    /**
     * Apply the rule to the given statements
     *
     * An array of statements is passed to this method once per document.
     *
     * @param array<int,Token> $statements
     */
    public function processStatements(array $statements): void;
}
