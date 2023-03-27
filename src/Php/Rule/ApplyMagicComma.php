<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Rule;

use Lkrms\Pretty\Php\Concern\ListRuleTrait;
use Lkrms\Pretty\Php\Contract\ListRule;
use Lkrms\Pretty\Php\Token;
use Lkrms\Pretty\Php\TokenCollection;
use Lkrms\Pretty\WhitespaceType;

use const Lkrms\Pretty\Php\T_ID_MAP as T;

/**
 * Explode arrays and arguments with a trailing comma into one item per line
 *
 */
final class ApplyMagicComma implements ListRule
{
    use ListRuleTrait;

    public function getPriority(string $method): ?int
    {
        return 360;
    }

    public function processList(Token $owner, TokenCollection $items): void
    {
        if ($owner->ClosedBy->prevCode()->is(T[',']) &&
            !($owner->prevCode()->is(T_LIST) ||
                (($adjacent = $owner->adjacent(T[','], T[']'])) && $adjacent->is(T['='])) ||
                (($root = $owner->withParentsWhile(T['['])->last()) &&
                    $root->prevCode()->is(T_AS) &&
                    $root->parent()->prevCode()->is(T_FOREACH)))) {
            $items[] = $owner->ClosedBy ?: $owner;
            $items->addWhitespaceBefore(WhitespaceType::LINE, true);
        }
    }
}
