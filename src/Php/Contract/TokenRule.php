<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Contract;

use Lkrms\Pretty\Php\Token;

interface TokenRule extends Rule
{
    public const PROCESS_TOKEN = 'processToken';

    /**
     * Return token types the rule is interested in
     *
     * Tokens of these types are passed to {@see TokenRule::processToken()}. To
     * receive all tokens, return `null`, or for no tokens, return an empty
     * array.
     *
     * @return array<int|string>|null
     */
    public function getTokenTypes(): ?array;

    /**
     * Apply the rule to a token
     *
     * Non-whitespace tokens of the types returned by
     * {@see TokenRule::getTokenTypes()} are passed to
     * {@see TokenRule::processToken()} in sequential order.
     *
     */
    public function processToken(Token $token): void;
}
