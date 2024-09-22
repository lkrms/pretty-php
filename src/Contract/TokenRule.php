<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Contract;

use Lkrms\PrettyPHP\Support\TokenTypeIndex;
use Lkrms\PrettyPHP\Token\Token;

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
     * Returns an index of token types, or `['*']` for all tokens.
     *
     * Example:
     *
     * ```php
     * class MyRule implements TokenRule
     * {
     *     public static function getTokenTypes(TokenTypeIndex $idx): array
     *     {
     *         return [\T_FN => true];
     *     }
     * }
     * ```
     *
     * @return array<int,bool>|array{'*'}
     */
    public static function getTokenTypes(TokenTypeIndex $idx): array;

    /**
     * Check if tokens must be passed to the rule in document order
     */
    public static function getRequiresSortedTokens(): bool;

    /**
     * Apply the rule to the given tokens
     *
     * Tokens of the types returned by {@see TokenRule::getTokenTypes()} are
     * passed to this method once per input file.
     *
     * @param array<int,Token> $tokens
     */
    public function processTokens(array $tokens): void;
}
