<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\BlockRuleTrait;
use Lkrms\Pretty\Php\Contract\BlockRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenType;

class AlignAssignments implements BlockRule
{
    use BlockRuleTrait;

    public function processBlock(array $block): void
    {
        if (count($block) < 2) {
            return;
        }
        $group          = [];
        $stack          = null;
        $indent         = null;
        $hasAlignedArgs = null;
        $isAssignment   = null;
        while ($block) {
            $line   = array_shift($block);
            /** @var Token $token1 */
            $token1 = $line[0];
            if (count($line) === 1 && $token1->isOneOf(...TokenType::COMMENT)) {
                continue;
            }
            if (($token2 = $line->getFirstOf(
                ...TokenType::OPERATOR_ASSIGNMENT,
                ...TokenType::OPERATOR_DOUBLE_ARROW
            )) && !$this->lastLineHasInnerNewline(end($group), $token1)) {
                if (is_null($stack)) {
                    $stack          = $token2->BracketStack;
                    $indent         = $token2->indent();
                    $hasAlignedArgs = $token1->Tags['HasAlignedArguments'] ?? false;
                    $isAssignment   = $token2->isOneOf(...TokenType::OPERATOR_ASSIGNMENT);
                    $group[]        = [$token1, $token2];
                    continue;
                }
                if ($stack === $token2->BracketStack &&
                    ($indent === $token2->indent() ||
                        ($hasAlignedArgs && ($token1->Tags['HasAlignedArguments'] ?? false))) &&
                    !($isAssignment xor $token2->isOneOf(...TokenType::OPERATOR_ASSIGNMENT))) {
                    $group[] = [$token1, $token2];
                    continue;
                }
            }
            count($group) < 2 ||
                $this->registerGroup($group);
            $group          = [];
            $stack          = null;
            $indent         = null;
            $hasAlignedArgs = null;
            $isAssignment   = null;
            if ($token2) {
                $stack          = $token2->BracketStack;
                $indent         = $token2->indent();
                $hasAlignedArgs = $token1->Tags['HasAlignedArguments'] ?? false;
                $isAssignment   = $token2->isOneOf(...TokenType::OPERATOR_ASSIGNMENT);
                $group[]        = [$token1, $token2];
            }
        }
        count($group) < 2 ||
            $this->registerGroup($group);
    }

    /**
     * @param array{0:Token,1:Token}|false $last
     */
    private function lastLineHasInnerNewline($last, Token $token1): bool
    {
        if (!$last) {
            return false;
        }
        /** @var Token $lastToken2 */
        [, $lastToken2] = $last;

        return $lastToken2->collect($token1->prevCode())
                          ->filter(fn(Token $t) => $t->isCode())
                          ->hasInnerNewline();
    }

    /**
     * @param array<array{0:Token,1:Token}> $group
     */
    private function registerGroup(array $group): void
    {
        $this->Formatter->registerCallback($this, $group[0][1], fn() => $this->alignGroup($group));
    }

    /**
     * @param array<array{0:Token,1:Token}> $group
     */
    private function alignGroup(array $group): void
    {
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
            $token2->Padding += $max - $lengths[$i];
        }
    }
}
