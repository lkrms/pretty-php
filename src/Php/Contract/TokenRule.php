<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Contract;

use Lkrms\Pretty\Php\Token;

interface TokenRule extends Rule
{
    public const PROCESS_TOKEN = 'processToken';

    /**
     * Apply the rule to a token
     *
     * Every non-whitespace token in the input file is passed to
     * {@see TokenRule::processToken()}.
     *
     */
    public function processToken(Token $token): void;
}
