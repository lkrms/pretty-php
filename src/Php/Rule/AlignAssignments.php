<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Contract\BlockRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenCollection;
use Lkrms\Pretty\Php\TokenType;

class AlignAssignments implements BlockRule
{
    public function __invoke(array $block): void
    {
        if (count($block) < 2) {
            return;
        }
        $groups = [];
        $group  = [];
        $stack  = null;
        while ($block) {
            $line  = array_shift($block);
            /** @var Token $token */
            $token = $line[0];
            if (count($line) === 1 && $token->isOneOf(...TokenType::COMMENT)) {
                continue;
            }
            if ($line->hasOneOf(
                ...TokenType::OPERATOR_ASSIGNMENT,
                ...TokenType::OPERATOR_DOUBLE_ARROW
            )) {
                if (is_null($stack)) {
                    $stack   = $token->BracketStack;
                    $group[] = $line;
                    continue;
                }
                if ($stack === $token->BracketStack) {
                    $group[] = $line;
                    continue;
                }
            } else {
                $line = null;
            }
            if (count($group) > 1) {
                $groups[] = $group;
            }
            $group = [];
            $stack = null;
            if ($line) {
                $group[] = $line;
            }
        }

        foreach ($groups as $group) {
            $tokens  = [];
            $lengths = [];
            $max     = 0;
            /** @var TokenCollection $line */
            foreach ($group as $line) {
                /** @var Token $token1 */
                $token1 = $line[0];
                $token2 = $line->getFirstOf(
                    ...TokenType::OPERATOR_ASSIGNMENT,
                    ...TokenType::OPERATOR_DOUBLE_ARROW
                );
                $tokens[]  = $token2;
                $length    = strlen($token1->collect($token2)->render());
                $lengths[] = $length;
                $max       = max($max, $length);
            }

            /** @var Token $token */
            foreach ($tokens as $i => $token) {
                $token->Padding = $max - $lengths[$i];
            }
        }
    }
}
