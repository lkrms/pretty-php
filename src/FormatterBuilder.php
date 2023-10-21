<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP;

use Lkrms\Concept\Builder;
use Lkrms\PrettyPHP\Catalog\FormatterFlag;
use Lkrms\PrettyPHP\Filter\Contract\Filter;
use Lkrms\PrettyPHP\Rule\Contract\Rule;
use Lkrms\PrettyPHP\Support\TokenTypeIndex;

/**
 * Creates Formatter objects via a fluent interface
 *
 * @method $this insertSpaces(bool $value = true) Use spaces for indentation? (default: true)
 * @method $this tabSize(int $value) The size of a tab, in spaces
 * @method $this disableRules(array<class-string<Rule>> $value) Non-mandatory rules to disable
 * @method $this enableRules(array<class-string<Rule>> $value) Additional rules to enable
 * @method $this disableFilters(array<class-string<Filter>> $value) Optional filters to disable
 * @method $this flags(int-mask-of<FormatterFlag::*> $value) Debugging flags
 * @method $this tokenTypeIndex(TokenTypeIndex|null $value) Provide a customised token type index
 *
 * @uses Formatter
 *
 * @extends Builder<Formatter>
 */
final class FormatterBuilder extends Builder
{
    /**
     * @internal
     */
    protected static function getService(): string
    {
        return Formatter::class;
    }

    /**
     * @internal
     */
    protected static function getTerminators(): array
    {
        return [];
    }
}
