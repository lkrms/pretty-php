<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Contract;

use Lkrms\Pretty\Php\TokenCollection;

interface BlockRule extends Rule
{
    public const PROCESS_BLOCK    = 'processBlock';
    public const AFTER_BLOCK_LOOP = 'afterBlockLoop';

    /**
     * Apply the rule to a code block
     *
     * An array of one or more {@see TokenCollection}s, each representing a line
     * with one or more {@see \Lkrms\Pretty\Php\Token}s, is passed to
     * {@see BlockRule::processBlock()} for every block of consecutive non-empty
     * lines in the output file.
     *
     * @param TokenCollection[] $block
     */
    public function processBlock(array $block): void;

    public function afterBlockLoop(): void;
}
