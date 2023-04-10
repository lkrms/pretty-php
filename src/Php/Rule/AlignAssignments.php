<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\BlockRuleTrait;
use Lkrms\Pretty\Php\Contract\BlockRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenType;

use const Lkrms\Pretty\Php\T_ID_MAP as T;

/**
 * Align consecutive assignment operators and double arrows when they have the
 * same context
 *
 */
final class AlignAssignments implements BlockRule
{
    use BlockRuleTrait;

    public function getPriority(string $method): ?int
    {
        return 340;
    }

    public function processBlock(array $block): void
    {
        if (count($block) < 2) {
            return;
        }
        $group = [];
        $stack = null;
        $parent = null;
        $indent = null;
        $alignedWith = null;
        $isAssignment = null;
        $lastToken2 = null;
        $startGroup =
            function (Token $t1, Token $t2) use (&$group, &$stack, &$parent, &$indent, &$alignedWith, &$isAssignment, &$lastToken2) {
                $stack = $t2->BracketStack;
                $parent = null;
                $indent = $t2->indent();
                $alignedWith = $this->getAlignedWith($t1, $t2);
                $isAssignment = $t2->is(TokenType::OPERATOR_ASSIGNMENT);
                $lastToken2 = $t2;
                $group[] = [$t1, $t2];
            };
        $skipped = 0;
        while ($block) {
            $line = array_shift($block);
            /** @var Token $token1 */
            $token1 = $line[0];
            // Don't allow comments to disrupt alignment
            if (count($line) === 1 &&
                    $token1->isOneLineComment(true) &&
                    !$skipped++) {
                $lastToken2 = $token1;
                continue;
            }
            // Ditto for 1-line `else`/`elseif`/`catch`/`finally` constructs
            if (count($group) > 1 &&
                    $token1->is(T['}']) && ($last = $line->last())->is(T['{']) &&
                    $token1->Statement === $last->Statement &&
                    !$skipped++) {
                $parent = ['depth' => array_key_last($token1->BracketStack), 'statement' => $token1->Statement];
                $lastToken2 = $last;
                continue;
            }
            $skipped = 0;
            if (($token2 = $line->getFirstOf(
                ...TokenType::OPERATOR_ASSIGNMENT,
                ...TokenType::OPERATOR_DOUBLE_ARROW
            )) &&
                    !$token2->hasNewlineAfterCode() &&
                    !$this->codeSinceLastAssignmentHasNewline($lastToken2, $token1)) {
                if (is_null($stack)) {
                    $startGroup($token1, $token2);
                    continue;
                }
                if (($stack === $token2->BracketStack ||
                        ($parent &&
                            count($stack) === count($token2->BracketStack) &&
                            ($token2->BracketStack[$parent['depth']]->Statement ?? null) === $parent['statement'])) &&
                        ($indent === $token2->indent() ||
                            ($alignedWith && $alignedWith === $this->getAlignedWith($token1, $token2))) &&
                        !($isAssignment xor $token2->is(TokenType::OPERATOR_ASSIGNMENT))) {
                    $group[] = [$token1, $token2];
                    $lastToken2 = $token2;
                    continue;
                }
            }
            $this->maybeRegisterGroup($group);
            $group = [];
            $stack = null;
            $parent = null;
            $indent = null;
            $alignedWith = null;
            $isAssignment = null;
            $lastToken2 = null;
            if ($token2) {
                $startGroup($token1, $token2);
            }
        }
        $this->maybeRegisterGroup($group);
    }

    private function getAlignedWith(Token $token1, Token $token2): ?Token
    {
        if ($token1->AlignedWith) {
            return $token1->AlignedWith;
        }
        $parent = $token2->parent();
        if ($parent->Index < $token1->Index) {
            return null;
        }

        return $parent->nextCode()->AlignedWith;
    }

    private function codeSinceLastAssignmentHasNewline(?Token $lastToken2, Token $token1): bool
    {
        if (!$lastToken2) {
            return false;
        }

        return $lastToken2->collect($token1->prevCode())
                          ->filter(fn(Token $t) => $t->IsCode)
                          ->hasNewline();
    }

    private function assignmentHasInnerNewline(Token $token2): bool
    {
        return $token2->collect($token2->pragmaticEndOfExpression())
                      ->filter(fn(Token $t) => $t->IsCode)
                      ->hasNewline();
    }

    /**
     * @param array<array{0:Token,1:Token}> $group
     */
    private function maybeRegisterGroup(array $group): void
    {
        if (count($group) < 2) {
            return;
        }
        $this->Formatter->registerCallback(
            $this,
            $group[0][1],
            fn() => $this->alignGroup($group),
            710
        );
    }

    /**
     * @param array<array{0:Token,1:Token}> $group
     */
    private function alignGroup(array $group): void
    {
        $lengths = [];
        $max = 0;
        $count = count($group);

        /** @var Token $token1 */
        foreach ($group as $i => [$token1, $token2]) {
            $length = mb_strlen(ltrim($token1->collect($token2)->render(true, false), "\n"));

            // If the last assignment in the group breaks over multiple lines
            // and can't be accommodated without increasing $max, ignore it to
            // avoid output like:
            //
            //     $a               = $b;
            //     $cc              = $dd;
            //     [$e, $f, $g, $h] = [
            //         $i,
            //         $j,
            //         $k,
            //         $l,
            //     ];
            if ($i + 1 === $count && $length - $max > 4 &&
                    $this->assignmentHasInnerNewline($token2)) {
                if ($count < 3) {
                    return;
                }
                array_pop($group);
                break;
            }
            $lengths[] = $length;
            $max = max($max, $length);
        }

        /** @var Token $token2 */
        foreach ($group as $i => [$token1, $token2]) {
            $token2->Padding += $max - $lengths[$i];
        }
    }
}
