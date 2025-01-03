<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\DeclarationType as Type;
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

    /**
     * @inheritDoc
     */
    public static function getPriority(string $method): ?int
    {
        return [
            self::PROCESS_LIST => 98,
            self::PROCESS_DECLARATIONS => 98,
        ][$method] ?? null;
    }

    /**
     * @inheritDoc
     */
    public static function getDeclarationTypes(array $all): array
    {
        return [
            Type::_CONST => true,
            Type::_USE => true,
            Type::PROPERTY => true,
            Type::PARAM => true,
            Type::USE_CONST => true,
            Type::USE_FUNCTION => true,
            Type::USE_TRAIT => true,
        ];
    }

    /**
     * @inheritDoc
     */
    public static function needsSortedDeclarations(): bool
    {
        return false;
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
     * If interface lists break over multiple lines and neither `StrictLists`
     * nor `AlignLists` are enabled, a newline is added before the first
     * interface.
     *
     * Arrays and argument lists with trailing ("magic") commas are split into
     * one item per line.
     *
     * If parameter lists have one or more attributes with a trailing newline,
     * every attribute is placed on its own line, and blank lines are added
     * before and after annotated parameters to improve readability.
     */
    public function processList(Token $parent, TokenCollection $items, Token $lastChild): void
    {
        if (!$parent->CloseBracket) {
            if (
                !$this->ListRuleEnabled
                && ($parent->id === \T_EXTENDS || $parent->id === \T_IMPLEMENTS)
                && $items->tokenHasNewlineBefore()
            ) {
                /** @var Token */
                $token = $items->first();
                $token->applyWhitespace(Space::LINE_BEFORE);
            }
            return;
        }

        if ($lastChild->id === \T_COMMA) {
            $items->add($parent->CloseBracket)
                  ->applyWhitespace(Space::CRITICAL_LINE_BEFORE);
        }

        if ($parent->id === \T_OPEN_PARENTHESIS && $parent->isParameterList()) {
            $this->normaliseDeclarationList($items);
        }
    }

    /**
     * Apply the rule to the given declarations
     *
     * Newlines are added between comma-delimited constant declarations and
     * property declarations. When neither `StrictLists` nor `AlignLists` are
     * enabled, they are also added to `use` statements between comma-delimited
     * imports and traits that break over multiple lines.
     *
     * If a list of property hooks has one or more attributes with a trailing
     * newline, every attribute is placed on its own line, and blank lines are
     * added before and after annotated hooks to improve readability.
     */
    public function processDeclarations(array $declarations): void
    {
        foreach ($declarations as $token) {
            $type = $token->Data[TokenData::NAMED_DECLARATION_TYPE];

            if (
                $type === Type::_CONST
                || $type === Type::PROPERTY
                || $type & Type::_USE
            ) {
                $commas = $token->withNextSiblings($token->EndStatement)
                                ->getAnyOf(\T_COMMA);
                if (!($type & Type::_USE) || (
                    !$this->ListRuleEnabled
                    && $commas->tokenHasNewlineBeforeNextCode()
                )) {
                    $commas->applyWhitespace(Space::LINE_AFTER);
                }
            }

            if ($type & Type::PROPERTY) {
                /** @var TokenCollection */
                $hooks = $token->Data[TokenData::PROPERTY_HOOKS];
                if ($hooks->count()) {
                    $this->normaliseDeclarationList($hooks);
                }
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
            $attributes = $item->withNextSiblingsFrom($this->Idx->Attribute, true);
            $itemTokens[$item->index] = $attributes;
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
                $item->applyBlankBefore(true);
                $addBlankBefore = false;
                $hasBlankBefore = true;
            } else {
                $hasBlankBefore = false;
            }
            $tokens = $itemTokens[$item->index]
                ?? $item->withNextSiblingsFrom($this->Idx->Attribute, true);
            $tokens[] = $item->skipNextSiblingFrom($this->Idx->Attribute);
            foreach ($tokens as $token) {
                $token->applyWhitespace(Space::LINE_BEFORE);
                if ($this->Idx->Attribute[$token->id]) {
                    $token = $token->CloseBracket ?? $token;
                    $token->Whitespace |= Space::LINE_AFTER;
                    // Add a blank line before each item with an attribute, and
                    // another before the next item
                    $addBlankBefore = true;
                }
            }
            if ($i++ && $addBlankBefore && !$hasBlankBefore) {
                $item->applyBlankBefore(true);
            }
        }
    }
}
