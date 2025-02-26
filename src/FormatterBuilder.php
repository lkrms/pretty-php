<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP;

use Lkrms\PrettyPHP\Catalog\FormatterFlag;
use Lkrms\PrettyPHP\Catalog\HeredocIndent;
use Lkrms\PrettyPHP\Catalog\ImportSortOrder;
use Lkrms\PrettyPHP\Contract\Extension;
use Salient\Core\Builder;
use Salient\Utility\Support\Indentation;

/**
 * @method $this insertSpaces(bool $value = true) Use spaces for indentation? (default: true)
 * @method $this tabSize(int<1,max> $value) The size of a tab, in spaces
 * @method $this disable(array<class-string<Extension>> $value) Extensions to disable
 * @method $this enable(array<class-string<Extension>> $value) Extensions to enable
 * @method $this flags(int-mask-of<FormatterFlag::*> $value) Formatter flags
 * @method $this tokenIndex(AbstractTokenIndex|null $value) Custom token index
 * @method $this preferredEol(string $value) End-of-line sequence used if line endings are not preserved or if there are no line breaks in the input
 * @method $this preserveEol(bool $value = true) Preserve line endings? (default: true)
 * @method $this spacesBesideCode(int $value) Spaces applied between code and comments on the same line
 * @method $this heredocIndent(HeredocIndent::* $value) Heredoc indentation type
 * @method $this importSortOrder(ImportSortOrder::* $value) Alias/import statement order
 * @method $this oneTrueBraceStyle(bool $value = true) Format braces using the One True Brace Style? (default: false)
 * @method $this collapseEmptyDeclarationBodies(bool $value = true) Collapse empty declaration bodies to the end of the declaration? (default: true)
 * @method $this collapseDeclareHeaders(bool $value = true) Collapse headers like "<?php declare(strict_types=1);" to one line? (default: true)
 * @method $this expandHeaders(bool $value = true) Apply blank lines between "<?php" and subsequent declarations? (default: false)
 * @method $this tightDeclarationSpacing(bool $value = true) Remove blank lines between declarations of the same type where possible? (default: false)
 * @method $this indentBetweenTags(bool $value = true) Add a level of indentation to code between indented tags? (default: false)
 * @method $this psr12(bool $value = true) Enforce strict PSR-12 / PER Coding Style compliance? (default: false)
 * @method Formatter withoutExtensions(array<class-string<Extension>> $extensions = []) Get an instance with the given extensions disabled
 * @method Formatter withExtensions(array<class-string<Extension>> $enable, array<class-string<Extension>> $disable = [], bool $preserveCurrent = true) Get an instance with the given extensions enabled
 * @method Formatter with(("MaxAssignmentPadding"|"MaxDoubleArrowColumn") $property, int|null $value) Get an instance with a value applied to the given setting
 * @method string format(string $code, string|null $eol = null, Indentation|null $indentation = null, string|null $filename = null, bool $fast = false) Format PHP code (see {@see Formatter::format()})
 *
 * @api
 *
 * @extends Builder<Formatter>
 *
 * @generated
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
        return [
            'withoutExtensions',
            'withExtensions',
            'with',
            'format',
        ];
    }
}
