<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Contract;

use Lkrms\PrettyPHP\Catalog\TokenType;
use Lkrms\PrettyPHP\Support\TokenTypeIndex;
use Lkrms\PrettyPHP\Token\Token;

interface TokenRule extends Rule
{
    public const PROCESS_TOKENS = 'processTokens';

    /**
     * Return token types the rule is interested in
     *
     * Tokens of these types are passed to {@see TokenRule::processTokens()}.
     *
     * To receive all tokens, return `['*']`, otherwise return either a list of
     * token types, or an index returned by {@see TokenType::getIndex()}.
     *
     * @return int[]|array<int,bool>|array{'*'}
     */
    public static function getTokenTypes(TokenTypeIndex $typeIndex): array;

    /**
     * Return true if tokens must be passed to the rule in document order
     */
    public static function getRequiresSortedTokens(): bool;

    /**
     * Apply the rule to an array of tokens
     *
     * An array of non-whitespace tokens of the types returned by
     * {@see TokenRule::getTokenTypes()} is passed to
     * {@see TokenRule::processTokens()} once per run.
     *
     * @param array<int,Token> $tokens
     */
    public function processTokens(array $tokens): void;
}
