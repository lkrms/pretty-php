<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Contract;

use Lkrms\PrettyPHP\Token;
use Lkrms\PrettyPHP\TokenTypeIndex;

/**
 * @api
 */
interface TokenRule extends Rule
{
    public const PROCESS_TOKENS = 'processTokens';

    /**
     * Get token types the rule is interested in
     *
     * Tokens of these types are passed to {@see TokenRule::processTokens()}
     * during formatting.
     *
     * Returns a partial or complete index of token types, or `['*']` for all
     * tokens.
     *
     * @return array<int,bool>|array{'*'}
     */
    public static function getTokenTypes(TokenTypeIndex $idx): array;

    /**
     * Check if the rule requires tokens to be given in document order
     */
    public static function needsSortedTokens(): bool;

    /**
     * Apply the rule to the given tokens
     *
     * Tokens of the types returned by {@see TokenRule::getTokenTypes()} are
     * passed to this method once per document.
     *
     * @param array<int,Token> $tokens
     */
    public function processTokens(array $tokens): void;
}
