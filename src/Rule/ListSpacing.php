<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\DeclarationType;
use Lkrms\PrettyPHP\Catalog\TokenData;
use Lkrms\PrettyPHP\Catalog\WhitespaceFlag as Space;
use Lkrms\PrettyPHP\Concern\DeclarationRuleTrait;
use Lkrms\PrettyPHP\Concern\ListRuleTrait;
use Lkrms\PrettyPHP\Contract\DeclarationRule;
use Lkrms\PrettyPHP\Contract\ListRule;
use Lkrms\PrettyPHP\Internal\TokenCollection;
use Lkrms\PrettyPHP\Token;

/**
 * Apply whitespace to lists
 *
 * @api
 */
final class ListSpacing implements ListRule, DeclarationRule
{
    use ListRuleTrait;
    use DeclarationRuleTrait;

    private bool $ListRuleEnabled;

    public static function getPriority(string $method): ?int
    {
        return [
            self::PROCESS_LIST => 98,
            self::PROCESS_DECLARATIONS => 98,
        ][$method] ?? null;
    }

    public static function getDeclarationTypes(array $all): array
    {
        return [
            DeclarationType::PROPERTY => true,
            DeclarationType::PARAM => true,
        ];
    }

    /**
     * @inheritDoc
     */
    public function boot(): void
    {
        $this->ListRuleEnabled = $this->Formatter->Enabled[StrictLists::class]
            ?? $this->Formatter->Enabled[AlignLists::class]
            ?? false;
    }

    /**
     * Apply the rule to a token and the list of items associated with it
     *
     * Arrays and argument lists with trailing ("magic") commas are split into
     * one item per line.
     *
     * If parameter lists have one or more attributes with a trailing newline,
     * every attribute is placed on its own line, and blank lines are added
     * before and after annotated parameters to improve readability.
     *
     * If interface lists break over multiple lines and neither `StrictLists`
     * nor `AlignLists` are enabled, a newline is added before the first
     * interface.
     */
    public function processList(Token $parent, TokenCollection $items): void
    {
        if (!$parent->ClosedBy) {
            if (!$this->ListRuleEnabled && $items->tokenHasNewlineBefore()) {
                /** @var Token */
                $token = $items->first();
                $token->applyWhitespace(Space::LINE_BEFORE);
            }
            return;
        }

        // If the list has a "magic comma", add a newline before each item and
        // another before the close bracket
        /** @var Token */
        $last = $parent->ClosedBy->PrevCode;
        if ($last->id === \T_COMMA) {
            $items->add($parent->ClosedBy)
                  ->applyWhitespace(Space::CRITICAL_LINE_BEFORE);
        }

        if ($parent->id === \T_OPEN_PARENTHESIS && $parent->isParameterList()) {
            $this->normaliseDeclarationList($items);
        }
    }

    /**
     * Apply the rule to the given declarations
     *
     * If a list of property hooks has one or more attributes with a trailing
     * newline, every attribute is placed on its own line, and blank lines are
     * added before and after annotated hooks to improve readability.
     */
    public function processDeclarations(array $declarations): void
    {
        foreach ($declarations as $token) {
            /** @var TokenCollection */
            $hooks = $token->Data[TokenData::PROPERTY_HOOKS];
            if ($hooks->count()) {
                $this->normaliseDeclarationList($hooks);
            }
        }
    }

    /**
     * @param iterable<Token> $items
     */
    private function normaliseDeclarationList(iterable $items): void
    {
        $hasAttributeWithNewline = false;
        foreach ($items as $item) {
            $attributes = $item->withNextSiblingsWhile($this->Idx->Attribute, true);
            $itemTokens[$item->Index] = $attributes;
            if (
                $attributes->tokenHasNewlineAfter(true)
                || $attributes->shift()->tokenHasNewlineBefore()
            ) {
                $hasAttributeWithNewline = true;
                break;
            }
        }
        if (!$hasAttributeWithNewline) {
            return;
        }

        $addBlankBefore = false;
        $i = 0;
        foreach ($items as $item) {
            if ($addBlankBefore) {
                $item->applyBlankLineBefore(true);
                $addBlankBefore = false;
                $hasBlankBefore = true;
            } else {
                $hasBlankBefore = false;
            }
            $tokens = $itemTokens[$item->Index]
                ?? $item->withNextSiblingsWhile($this->Idx->Attribute, true);
            $tokens[] = $item->skipNextSiblingsFrom($this->Idx->Attribute);
            foreach ($tokens as $token) {
                $token->applyWhitespace(Space::LINE_BEFORE);
                if ($this->Idx->Attribute[$token->id]) {
                    $token = $token->ClosedBy ?? $token;
                    $token->Whitespace |= Space::LINE_AFTER;
                    // Add a blank line before each item with an attribute, and
                    // another before the next item
                    $addBlankBefore = true;
                }
            }
            if ($i++ && $addBlankBefore && !$hasBlankBefore) {
                $item->applyBlankLineBefore(true);
            }
        }
    }
}
