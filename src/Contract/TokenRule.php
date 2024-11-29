<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Contract;

use Lkrms\PrettyPHP\Token;
use Lkrms\PrettyPHP\TokenIndex;

/**
 * @api
 */
interface TokenRule extends Rule
{
    public const PROCESS_TOKENS = 'processTokens';

    /**
     * Get tokens the rule is interested in
     *
     * Matching tokens are passed to {@see TokenRule::processTokens()} during
     * formatting.
     *
     * Returns a partial or complete token index, or `['*']` for all tokens.
     *
     * @return array<int,bool>|array{'*'}
     */
    public static function getTokens(TokenIndex $idx): array;

    /**
     * Check if the rule requires tokens to be given in document order
     */
    public static function needsSortedTokens(): bool;

    /**
     * Apply the rule to the given tokens
     *
     * Tokens matching the return value of {@see TokenRule::getTokens()} are
     * passed to this method once per document.
     *
     * @param array<int,Token> $tokens
     */
    public function processTokens(array $tokens): void;
}
