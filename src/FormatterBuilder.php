<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP;

use Lkrms\PrettyPHP\Catalog\FormatterFlag;
use Lkrms\PrettyPHP\Catalog\HeredocIndent;
use Lkrms\PrettyPHP\Catalog\ImportSortOrder;
use Lkrms\PrettyPHP\Contract\Extension;
use Lkrms\PrettyPHP\Support\TokenTypeIndex;
use Salient\Core\AbstractBuilder;
use Salient\Core\Indentation;

/**
 * A fluent Formatter factory
 *
 * @method $this insertSpaces(bool $value = true) Use spaces for indentation? (default: true)
 * @method $this tabSize(int $value) The size of a tab, in spaces
 * @method $this disable(array<class-string<Extension>> $value) Non-mandatory extensions to disable
 * @method $this enable(array<class-string<Extension>> $value) Optional extensions to enable
 * @method $this flags(int-mask-of<FormatterFlag::*> $value) Debugging flags
 * @method $this tokenTypeIndex(TokenTypeIndex|null $value) Provide a customised token type index
 * @method $this preferredEol(string $value) End-of-line sequence used when line endings are not preserved or when there are no line breaks in the input
 * @method $this preserveEol(bool $value = true) True if line endings are preserved (default: true)
 * @method $this spacesBesideCode(int $value) Spaces between code and comments on the same line
 * @method $this heredocIndent(HeredocIndent::* $value) Indentation applied to heredocs and nowdocs
 * @method $this importSortOrder(ImportSortOrder::* $value) Set Formatter::$ImportSortOrder
 * @method $this oneTrueBraceStyle(bool $value = true) True if braces are formatted using the One True Brace Style (default: false)
 * @method $this tightDeclarationSpacing(bool $value = true) True if blank lines between declarations of the same type are removed where possible (default: false)
 * @method $this psr12(bool $value = true) Enforce strict PSR-12 / PER Coding Style compliance? (default: false)
 * @method Formatter with(string $property, mixed $value) Call Formatter::with() on a new instance
 * @method Formatter withoutExtensions(array<class-string<Extension>> $extensions = []) Get an instance with the given extensions disabled
 * @method Formatter withExtensions(array<class-string<Extension>> $enable, array<class-string<Extension>> $disable = [], bool $preserveCurrent = true) Get an instance with the given extensions enabled
 * @method string format(string $code, string|null $eol = null, Indentation|null $indentation = null, string|null $filename = null, bool $fast = false) Get formatted code (see {@see Formatter::format()})
 *
 * @extends AbstractBuilder<Formatter>
 *
 * @generated
 */
final class FormatterBuilder extends AbstractBuilder
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
            'with',
            'withoutExtensions',
            'withExtensions',
            'format',
        ];
    }
}
