<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Contract\BlockRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenType;

class AlignAssignments implements BlockRule
{
    public function __invoke(array $block): void
    {
        if (count($block) < 2) {
            return;
        }
        $group = [];
        $stack = null;
        while ($block) {
            $line   = array_shift($block);
            /** @var Token $token1 */
            $token1 = $line[0];
            if (count($line) === 1 && $token1->isOneOf(...TokenType::COMMENT)) {
                continue;
            }
            if ($token2 = $line->getFirstOf(
                ...TokenType::OPERATOR_ASSIGNMENT,
                ...TokenType::OPERATOR_DOUBLE_ARROW
            )) {
                if (is_null($stack)) {
                    $stack   = $token2->BracketStack;
                    $group[] = [$token1, $token2];
                    continue;
                }
                if ($stack === $token2->BracketStack) {
                    $group[] = [$token1, $token2];
                    continue;
                }
            }
            $this->processGroup($group);
            $group = [];
            $stack = null;
            if ($token2) {
                $stack   = $token2->BracketStack;
                $group[] = [$token1, $token2];
            }
        }
        $this->processGroup($group);
    }

    /**
     * @param array<array{Token,Token}> $group
     */
    private function processGroup(array $group): void
    {
        if (count($group) < 2) {
            return;
        }
        $lengths = [];
        $max     = 0;

        /** @var Token $token1 */
        foreach ($group as [$token1, $token2]) {
            $length    = strlen($token1->collect($token2)->render());
            $lengths[] = $length;
            $max       = max($max, $length);
        }

        /** @var Token $token2 */
        foreach ($group as $i => [$token1, $token2]) {
            $token2->Padding = $max - $lengths[$i];
        }
    }
}
