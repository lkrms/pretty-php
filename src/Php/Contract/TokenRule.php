<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Contract;

use Lkrms\Pretty\Php\Catalog\TokenType;
use Lkrms\Pretty\Php\Token;

interface TokenRule extends Rule
{
    public const PROCESS_TOKEN = 'processToken';

    /**
     * Return token types the rule is interested in
     *
     * Tokens of these types are passed to
     * {@see MultiTokenRule::processTokens()} if implemented,
     * {@see TokenRule::processToken()} otherwise.
     *
     * To receive all tokens, return `['*']`, otherwise return either a list of
     * token types, or an index returned by {@see TokenType::getIndex()}.
     *
     * @return array{'*'}|int[]|array<int,bool>
     */
    public function getTokenTypes(): array;

    /**
     * Return true if tokens must be passed to the rule in document order
     *
     */
    public function getRequiresSortedTokens(): bool;

    /**
     * Apply the rule to a token
     *
     * Non-whitespace tokens of the types returned by
     * {@see TokenRule::getTokenTypes()} are passed to
     * {@see TokenRule::processToken()}.
     *
     */
    public function processToken(Token $token): void;
}
