<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Contract;

use Lkrms\PrettyPHP\Token\Token;

interface MultiTokenRule extends TokenRule
{
    public const PROCESS_TOKENS = 'processTokens';

    /**
     * Apply the rule to an array of tokens
     *
     * An array of non-whitespace tokens of the types returned by
     * {@see TokenRule::getTokenTypes()} is passed to
     * {@see MultiTokenRule::processTokens()} once per run.
     *
     * If a rule implements {@see MultiTokenRule}, the formatter calls
     * this method in favour of {@see TokenRule::processToken()}.
     *
     * @param array<int,Token> $tokens
     */
    public function processTokens(array $tokens): void;
}
