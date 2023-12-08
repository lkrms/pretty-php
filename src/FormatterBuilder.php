<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP;

use Lkrms\Concept\Builder;
use Lkrms\PrettyPHP\Catalog\FormatterFlag;
use Lkrms\PrettyPHP\Catalog\HeredocIndent;
use Lkrms\PrettyPHP\Catalog\ImportSortOrder;
use Lkrms\PrettyPHP\Filter\Contract\Filter;
use Lkrms\PrettyPHP\Rule\Contract\Rule;
use Lkrms\PrettyPHP\Support\TokenTypeIndex;

/**
 * Creates Formatter objects via a fluent interface
 *
 * @method $this insertSpaces(bool $value = true) Use spaces for indentation? (default: true)
 * @method $this tabSize((2|4|8) $value) The size of a tab, in spaces
 * @method $this disableRules(array<class-string<Rule>> $value) Non-mandatory rules to disable
 * @method $this enableRules(array<class-string<Rule>> $value) Additional rules to enable
 * @method $this disableFilters(array<class-string<Filter>> $value) Optional filters to disable
 * @method $this flags(int-mask-of<FormatterFlag::*> $value) Debugging flags
 * @method $this tokenTypeIndex(TokenTypeIndex|null $value) Provide a customised token type index
 * @method $this preferredEol(string $value) End-of-line sequence used when line endings are not preserved or when there are no line breaks in the input
 * @method $this preserveEol(bool $value = true) True if line endings are preserved (default: true)
 * @method $this spacesBesideCode(int $value) Spaces between code and comments on the same line
 * @method $this heredocIndent(HeredocIndent::* $value) Indentation applied to heredocs and nowdocs
 * @method $this importSortOrder(ImportSortOrder::* $value) Set Formatter::$ImportSortOrder
 * @method $this oneTrueBraceStyle(bool $value = true) True if braces are formatted using the One True Brace Style (default: false)
 *
 * @uses Formatter
 *
 * @extends Builder<Formatter>
 */
final class FormatterBuilder extends Builder
{
    /**
     * @inheritDoc
     */
    protected static function getService(): string
    {
        return Formatter::class;
    }
}
