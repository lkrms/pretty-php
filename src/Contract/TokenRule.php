<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Contract;

use Lkrms\PrettyPHP\Catalog\TokenType;
use Lkrms\PrettyPHP\Support\TokenTypeIndex;
use Lkrms\PrettyPHP\Token\Token;

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
     * @return int[]|array<int,bool>|array{'*'}
     */
    public static function getTokenTypes(TokenTypeIndex $typeIndex): array;

    /**
     * Return true if tokens must be passed to the rule in document order
     */
    public static function getRequiresSortedTokens(): bool;

    /**
     * Apply the rule to a token
     *
     * Non-whitespace tokens of the types returned by
     * {@see TokenRule::getTokenTypes()} are passed to
     * {@see TokenRule::processToken()}.
     */
    public function processToken(Token $token): void;
}
