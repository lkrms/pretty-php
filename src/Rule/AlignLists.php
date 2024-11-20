<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\TokenFlag;
use Lkrms\PrettyPHP\Concern\ListRuleTrait;
use Lkrms\PrettyPHP\Contract\ListRule;
use Lkrms\PrettyPHP\Internal\TokenCollection;
use Lkrms\PrettyPHP\Token;

/**
 * Align arguments, array elements and other list items with their
 * owners
 */
final class AlignLists implements ListRule
{
    use ListRuleTrait;

    /** @var array<int,true> */
    private array $ListOwnersByIndex = [];

    public static function getPriority(string $method): ?int
    {
        return [
            self::PROCESS_LIST => 400,
            self::CALLBACK => 710,
        ][$method] ?? null;
    }

    public function processList(Token $parent, TokenCollection $items): void
    {
        $first = $items->first();
        $lastToken = $parent->ClosedBy
            ?: $items->last()->pragmaticEndOfExpression();

        // Do nothing if:
        // - an interface list has a leading line break, or
        // - the list does not break over multiple lines
        if ((!$parent->ClosedBy
                    && $parent->hasNewlineBeforeNextCode())
                || !$first->collect($lastToken)->hasNewline()) {
            return;
        }

        $items->forEach(
            fn(Token $item) =>
                $item->AlignedWith = $parent
        );

        $this->Formatter->registerCallback(
            static::class,
            $first,
            fn() => $this->alignList($parent, $items, $first, $lastToken)
        );

        $this->ListOwnersByIndex[$parent->Index] = true;
    }

    private function alignList(Token $parent, TokenCollection $items, Token $first, Token $lastToken): void
    {
        $callback =
            function (Token $item, Token $to, int $delta) use ($parent) {
                if (!$delta) {
                    return;
                }
                while (($adjacent = $to->lastSiblingBeforeNewline()) !== $to
                    && ($adjacent->id !== \T_OPEN_BRACE
                        || !($adjacent->Flags & TokenFlag::STRUCTURAL_BRACE
                            || $adjacent->isMatchOpenBrace())
                        || $adjacent->Depth > $parent->Depth)) {
                    $to = $adjacent;
                }
                while (($adjacent = $to->adjacentBeforeNewline(false))
                    && ($adjacent->id !== \T_OPEN_BRACE
                        || !($adjacent->Flags & TokenFlag::STRUCTURAL_BRACE
                            || $adjacent->isMatchOpenBrace())
                        || $adjacent->Depth > $parent->Depth)) {
                    $to = $adjacent->pragmaticEndOfExpression();
                }
                $item->collect($to)->forEach(
                    fn(Token $t) => $t->LinePadding += $delta
                );
            };

        if (!$parent->hasNewlineBeforeNextCode()) {
            $delta = $parent->alignmentOffset() + ($parent->ClosedBy ? 0 : 1);
            $callback($first, $lastToken, $delta);
        }

        $items->forEach(
            fn(Token $item, ?Token $next) =>
                $callback(
                    $item,
                    $next && $next->PrevCode && $next->PrevCode->PrevCode
                        ? $next->PrevCode->PrevCode
                        : ($parent->ClosedBy && $parent->ClosedBy->PrevCode
                            ? $parent->ClosedBy->PrevCode
                            : $item->pragmaticEndOfExpression()),
                    $item->alignmentOffset(false, $this->ListOwnersByIndex[$item->Index] ?? false)
                )
        );
    }

    /**
     * @inheritDoc
     */
    public function reset(): void
    {
        $this->ListOwnersByIndex = [];
    }
}
