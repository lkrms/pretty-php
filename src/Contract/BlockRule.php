<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Contract;

use Lkrms\PrettyPHP\Internal\TokenCollection;

/**
 * @api
 */
interface BlockRule extends Rule
{
    public const PROCESS_BLOCK = 'processBlock';

    /**
     * Apply the rule to the given code block
     *
     * An array of {@see TokenCollection} objects, each representing a line with
     * code and/or comment tokens, is passed to this method for each block of
     * consecutive non-empty output lines.
     *
     * @param non-empty-list<TokenCollection> $lines
     */
    public function processBlock(array $lines): void;
}
