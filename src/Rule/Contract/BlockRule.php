<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule\Contract;

use Lkrms\PrettyPHP\Support\TokenCollection;
use Lkrms\PrettyPHP\Token\Token;

interface BlockRule extends Rule
{
    public const PROCESS_BLOCK = 'processBlock';

    /**
     * Apply the rule to a code block
     *
     * An array of one or more {@see TokenCollection}s, each representing a line
     * with one or more {@see Token}s, is passed to
     * {@see BlockRule::processBlock()} for every block of consecutive non-empty
     * lines in the output file.
     *
     * @param TokenCollection[] $block
     */
    public function processBlock(array $block): void;
}
