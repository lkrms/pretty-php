<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP;

use Lkrms\PrettyPHP\Catalog\DeclarationType as Type;
use Lkrms\PrettyPHP\Catalog\FormatterFlag;
use Lkrms\PrettyPHP\Catalog\HeredocIndent;
use Lkrms\PrettyPHP\Catalog\ImportSortOrder;
use Lkrms\PrettyPHP\Catalog\TokenData as Data;
use Lkrms\PrettyPHP\Catalog\TokenFlag as Flag;
use Lkrms\PrettyPHP\Catalog\WhitespaceFlag as Space;
use Lkrms\PrettyPHP\Contract\BlockRule;
use Lkrms\PrettyPHP\Contract\DeclarationRule;
use Lkrms\PrettyPHP\Contract\Extension;
use Lkrms\PrettyPHP\Contract\Filter;
use Lkrms\PrettyPHP\Contract\ListRule;
use Lkrms\PrettyPHP\Contract\Rule;
use Lkrms\PrettyPHP\Contract\StatementRule;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Exception\FormatterException;
use Lkrms\PrettyPHP\Exception\InvalidFormatterException;
use Lkrms\PrettyPHP\Exception\InvalidSyntaxException;
use Lkrms\PrettyPHP\Filter\CollectColumn;
use Lkrms\PrettyPHP\Filter\EvaluateNumbers;
use Lkrms\PrettyPHP\Filter\EvaluateStrings;
use Lkrms\PrettyPHP\Filter\MoveComments;
use Lkrms\PrettyPHP\Filter\NormaliseBinaryStrings;
use Lkrms\PrettyPHP\Filter\NormaliseCasts;
use Lkrms\PrettyPHP\Filter\NormaliseKeywords;
use Lkrms\PrettyPHP\Filter\RemoveEmptyDocBlocks;
use Lkrms\PrettyPHP\Filter\RemoveEmptyTokens;
use Lkrms\PrettyPHP\Filter\RemoveHeredocIndentation;
use Lkrms\PrettyPHP\Filter\RemoveWhitespace;
use Lkrms\PrettyPHP\Filter\SortImports;
use Lkrms\PrettyPHP\Filter\TrimOpenTags;
use Lkrms\PrettyPHP\Filter\TruncateComments;
use Lkrms\PrettyPHP\Internal\Document;
use Lkrms\PrettyPHP\Internal\Problem;
use Lkrms\PrettyPHP\Internal\TokenCollection;
use Lkrms\PrettyPHP\Rule\Preset\Drupal;
use Lkrms\PrettyPHP\Rule\Preset\Laravel;
use Lkrms\PrettyPHP\Rule\Preset\Symfony;
use Lkrms\PrettyPHP\Rule\Preset\WordPress;
use Lkrms\PrettyPHP\Rule\AlignArrowFunctions;
use Lkrms\PrettyPHP\Rule\AlignChains;
use Lkrms\PrettyPHP\Rule\AlignComments;
use Lkrms\PrettyPHP\Rule\AlignData;
use Lkrms\PrettyPHP\Rule\AlignLists;
use Lkrms\PrettyPHP\Rule\AlignTernaryOperators;
use Lkrms\PrettyPHP\Rule\BlankBeforeReturn;
use Lkrms\PrettyPHP\Rule\ControlStructureSpacing;
use Lkrms\PrettyPHP\Rule\DeclarationSpacing;
use Lkrms\PrettyPHP\Rule\EssentialSpacing;
use Lkrms\PrettyPHP\Rule\FormatHeredocs;
use Lkrms\PrettyPHP\Rule\HangingIndentation;
use Lkrms\PrettyPHP\Rule\IndexSpacing;
use Lkrms\PrettyPHP\Rule\NormaliseComments;
use Lkrms\PrettyPHP\Rule\NormaliseNumbers;
use Lkrms\PrettyPHP\Rule\NormaliseStrings;
use Lkrms\PrettyPHP\Rule\OperatorSpacing;
use Lkrms\PrettyPHP\Rule\PlaceBraces;
use Lkrms\PrettyPHP\Rule\PlaceBrackets;
use Lkrms\PrettyPHP\Rule\PlaceComments;
use Lkrms\PrettyPHP\Rule\PreserveNewlines;
use Lkrms\PrettyPHP\Rule\PreserveOneLineStatements;
use Lkrms\PrettyPHP\Rule\ProtectStrings;
use Lkrms\PrettyPHP\Rule\SemiStrictExpressions;
use Lkrms\PrettyPHP\Rule\StandardIndentation;
use Lkrms\PrettyPHP\Rule\StandardSpacing;
use Lkrms\PrettyPHP\Rule\StatementSpacing;
use Lkrms\PrettyPHP\Rule\StrictExpressions;
use Lkrms\PrettyPHP\Rule\StrictLists;
use Lkrms\PrettyPHP\Rule\SwitchIndentation;
use Lkrms\PrettyPHP\Rule\VerticalSpacing;
use Salient\Contract\Core\Buildable;
use Salient\Contract\Core\Immutable;
use Salient\Core\Concern\HasBuilder;
use Salient\Core\Concern\HasMutator;
use Salient\Core\Facade\Profile;
use Salient\Core\Indentation;
use Salient\Utility\Arr;
use Salient\Utility\Get;
use Salient\Utility\Reflect;
use Salient\Utility\Str;
use Closure;
use CompileError;
use InvalidArgumentException;
use LogicException;
use Throwable;

/**
 * @api
 *
 * @implements Buildable<FormatterBuilder>
 */
final class Formatter implements Buildable, Immutable
{
    /** @use HasBuilder<FormatterBuilder> */
    use HasBuilder;
    use HasMutator {
        with as withPropertyValue;
    }

    /**
     * @var array<class-string<Filter>>
     */
    public const DEFAULT_FILTERS = [
        NormaliseBinaryStrings::class,
        CollectColumn::class,
        RemoveWhitespace::class,
        RemoveHeredocIndentation::class,
        RemoveEmptyDocBlocks::class,
        SortImports::class,
        MoveComments::class,
        NormaliseCasts::class,
        NormaliseKeywords::class,
    ];

    /**
     * @var array<class-string<Filter>>
     */
    public const OPTIONAL_FILTERS = [
        RemoveEmptyDocBlocks::class,
        SortImports::class,
        MoveComments::class,
        NormaliseCasts::class,
        NormaliseKeywords::class,
    ];

    /**
     * @var array<class-string<Filter>>
     */
    public const COMPARISON_FILTERS = [
        RemoveEmptyTokens::class,
        EvaluateNumbers::class,
        EvaluateStrings::class,
        TrimOpenTags::class,
        TruncateComments::class,
    ];

    /**
     * @var array<class-string<Rule>>
     */
    public const DEFAULT_RULES = [
        NormaliseComments::class,
        NormaliseStrings::class,
        NormaliseNumbers::class,
        ProtectStrings::class,
        FormatHeredocs::class,
        IndexSpacing::class,
        OperatorSpacing::class,
        StandardSpacing::class,
        StatementSpacing::class,
        ControlStructureSpacing::class,
        PlaceBraces::class,
        PlaceComments::class,
        PreserveNewlines::class,
        VerticalSpacing::class,
        PlaceBrackets::class,
        DeclarationSpacing::class,
        StandardIndentation::class,
        SwitchIndentation::class,
        HangingIndentation::class,
        EssentialSpacing::class,
    ];

    /**
     * @var array<class-string<Rule>>
     */
    public const OPTIONAL_RULES = [
        NormaliseStrings::class,
        NormaliseNumbers::class,
        PreserveNewlines::class,
        PreserveOneLineStatements::class,
        BlankBeforeReturn::class,
        StrictExpressions::class,
        SemiStrictExpressions::class,
        StrictLists::class,
        AlignChains::class,
        DeclarationSpacing::class,
        AlignArrowFunctions::class,
        AlignLists::class,
        AlignTernaryOperators::class,
        Symfony::class,
        Drupal::class,
        Laravel::class,
        WordPress::class,
        AlignComments::class,
        AlignData::class,
    ];

    /**
     * @var array<class-string<Rule>>
     */
    public const NO_TAB_RULES = [
        AlignChains::class,
        AlignArrowFunctions::class,
        AlignLists::class,
        AlignTernaryOperators::class,
    ];

    /**
     * @var array<array<class-string<Rule>>>
     */
    public const INCOMPATIBLE_RULES = [
        [
            StrictLists::class,
            AlignLists::class,
        ],
        [
            StrictExpressions::class,
            SemiStrictExpressions::class,
        ],
    ];

    /**
     * @var array<class-string<Extension>>
     */
    public const PSR12_ENABLE = [
        SortImports::class,
        StrictExpressions::class,
        StrictLists::class,
        DeclarationSpacing::class,
    ];

    /**
     * @var array<class-string<Extension>>
     */
    public const PSR12_DISABLE = [
        PreserveOneLineStatements::class,
        SemiStrictExpressions::class,
        AlignLists::class,
    ];

    /**
     * Use spaces for indentation?
     */
    public bool $InsertSpaces;

    /**
     * The size of a tab, in spaces
     *
     * @phpstan-var 2|4|8
     */
    public int $TabSize;

    /**
     * A series of spaces equivalent to a tab
     *
     * @phpstan-var ("  "|"    "|"        ")
     */
    public string $SoftTab;

    /**
     * The string used for indentation
     *
     * @phpstan-var ("  "|"    "|"        "|"	")
     */
    public string $Tab;

    public AbstractTokenIndex $TokenIndex;

    /**
     * End-of-line sequence used if line endings are not preserved or if there
     * are no line breaks in the input
     */
    public string $PreferredEol;

    /**
     * Preserve line endings?
     */
    public bool $PreserveEol;

    /**
     * Spaces applied between code and comments on the same line
     */
    public int $SpacesBesideCode;

    /** @var HeredocIndent::* */
    public int $HeredocIndent;
    /** @var ImportSortOrder::* */
    public int $ImportSortOrder;

    /**
     * Format braces using the One True Brace Style?
     */
    public bool $OneTrueBraceStyle;

    /**
     * Collapse empty declaration bodies to the end of the declaration?
     */
    public bool $CollapseEmptyDeclarationBodies;

    /**
     * Collapse headers like "<?php declare(strict_types=1);" to one line?
     */
    public bool $CollapseDeclareHeaders;

    /**
     * Apply blank lines between "<?php" and subsequent declarations?
     */
    public bool $ExpandHeaders;

    /**
     * Remove blank lines between declarations of the same type where possible?
     */
    public bool $TightDeclarationSpacing;

    /**
     * Add a level of indentation to code between indented tags?
     */
    public bool $IndentBetweenTags;

    /**
     * Enforce strict PSR-12 / PER Coding Style compliance?
     */
    public bool $Psr12;

    /**
     * If false, calls to registerProblem() are ignored
     */
    public bool $DetectProblems;

    /**
     * If false, line breaks are only preserved between statements
     *
     * When the {@see PreserveNewlines} rule is disabled, `false` is assigned to
     * this property and the rule is reinstated to preserve blank lines between
     * statements.
     */
    public bool $PreserveNewlines;

    /**
     * Enabled extensions
     *
     * @var array<class-string<Extension>,true>
     */
    public array $Enabled;

    public bool $NewlineBeforeFnDoubleArrow = false;
    public ?int $MaxAssignmentPadding = null;
    public ?int $MaxDoubleArrowColumn = null;
    public Renderer $Renderer;

    // --

    public ?string $Filename;

    /**
     * Indentation used in the input, if known
     */
    public ?Indentation $Indentation;

    public Document $Document;
    /** @var Problem[]|null */
    public ?array $Problems = null;
    /** @var array<string,string>|null */
    public ?array $Log = null;

    // --

    private bool $Debug;
    private bool $LogProgress;
    /** @var array<class-string<Filter>> */
    private array $PreferredFilters;
    /** @var array<class-string<Rule>> */
    private array $PreferredRules;
    private Parser $Parser;

    // --

    private ?bool $Applied = false;
    /** @var array<class-string<Filter>> */
    private array $FormatFilters;
    /** @var array<class-string<Filter>> */
    private array $ComparisonFilters;
    /** @var array<class-string<Rule>> */
    private array $Rules;
    /** @var array<class-string<TokenRule>,array{array<int,true>|array{'*'},bool}> */
    private array $TokenRuleTypes;
    /** @var array<class-string<DeclarationRule>,array{array<int,true>|array{'*'},bool}> */
    private array $DeclarationRuleTypes;

    /**
     * [ key => [ rule, method ] ]
     *
     * @var array<string,array{class-string<TokenRule|StatementRule|DeclarationRule|ListRule>,string}>
     */
    private array $MainLoop;

    /**
     * [ key => [ rule, method ] ]
     *
     * @var array<string,array{class-string<BlockRule>,string}>
     */
    private array $BlockLoop;

    /**
     * [ key => [ rule, method ] ]
     *
     * @var array<string,array{class-string<Rule>,string}>
     */
    private array $BeforeRender;

    /** @var array<class-string<Rule>,int> */
    private array $CallbackPriorities;

    // --

    private bool $ExtensionsLoaded = false;
    /** @var array<class-string<Extension>,Extension> */
    private array $Extensions;
    /** @var Filter[] */
    private array $FormatFilterList;
    /** @var Filter[] */
    private array $ComparisonFilterList;
    /** @var array<class-string<Rule>,Rule> */
    private array $RuleMap;

    // --

    /** @var list<Token> */
    private array $Tokens;
    /** @var array<int,array<int,Token>> */
    private array $TokensById;
    /** @var array<int,Token> */
    private array $Statements;
    /** @var array<int,Token> */
    private array $Declarations;
    /** @var array<int,array<int,Token>> */
    private array $DeclarationsByType;

    /**
     * [ priority => [ token index => [ [ rule, callback ], ... ] ] ]
     *
     * @var array<int,array<int,array<array{class-string<Rule>,Closure}>>>|null
     */
    private ?array $Callbacks = null;

    /** @var array<int,true> */
    private static array $AllDeclarationTypes;

    /**
     * @phpstan-param 2|4|8 $tabSize
     * @param array<class-string<Extension>> $disable Extensions to disable
     * @param array<class-string<Extension>> $enable Extensions to enable
     * @param int-mask-of<FormatterFlag::*> $flags Formatter flags
     * @param AbstractTokenIndex|null $tokenIndex Custom token index
     * @param HeredocIndent::* $heredocIndent Heredoc indentation type
     * @param ImportSortOrder::* $importSortOrder Alias/import statement order
     */
    public function __construct(
        bool $insertSpaces = true,
        int $tabSize = 4,
        array $disable = [],
        array $enable = [],
        int $flags = 0,
        ?AbstractTokenIndex $tokenIndex = null,
        string $preferredEol = \PHP_EOL,
        bool $preserveEol = true,
        int $spacesBesideCode = 2,
        int $heredocIndent = HeredocIndent::MIXED,
        int $importSortOrder = ImportSortOrder::DEPTH,
        bool $oneTrueBraceStyle = false,
        bool $collapseEmptyDeclarationBodies = true,
        bool $collapseDeclareHeaders = true,
        bool $expandHeaders = false,
        bool $tightDeclarationSpacing = false,
        bool $indentBetweenTags = false,
        bool $psr12 = false
    ) {
        if (!in_array($tabSize, [2, 4, 8], true)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid tabSize (2, 4 or 8 expected): %d',
                $tabSize
            ));
        }

        $this->InsertSpaces = $insertSpaces;
        $this->TabSize = $tabSize;
        $this->TokenIndex = $tokenIndex ?? new TokenIndex();
        $this->PreferredEol = $preferredEol;
        $this->PreserveEol = $preserveEol;
        $this->SpacesBesideCode = $spacesBesideCode;
        $this->HeredocIndent = $heredocIndent;
        $this->ImportSortOrder = $importSortOrder;
        $this->OneTrueBraceStyle = $oneTrueBraceStyle;
        $this->CollapseEmptyDeclarationBodies = $collapseEmptyDeclarationBodies;
        $this->CollapseDeclareHeaders = $collapseDeclareHeaders;
        $this->ExpandHeaders = $expandHeaders;
        $this->TightDeclarationSpacing = $tightDeclarationSpacing;
        $this->IndentBetweenTags = $indentBetweenTags;
        $this->Psr12 = $psr12;

        $this->Debug = (bool) ($flags & FormatterFlag::DEBUG);
        $this->LogProgress = $this->Debug && ($flags & FormatterFlag::LOG_PROGRESS);
        $this->DetectProblems = (bool) ($flags & FormatterFlag::DETECT_PROBLEMS);

        [$filters, $rules] = $this->resolveExtensions($enable, $disable);
        $this->PreferredFilters = $filters;
        $this->PreferredRules = $rules;

        $this->apply();
    }

    private function __clone()
    {
        $this->flush();
        $this->Applied = null;
    }

    /**
     * Get the formatter's indentation settings
     */
    public function getIndentation(): Indentation
    {
        return new Indentation($this->InsertSpaces, $this->TabSize);
    }

    /**
     * Get extension data as an array of associative arrays
     *
     * @return array<class-string<Extension>,non-empty-array<string,mixed>>
     */
    public function getExtensionData(): array
    {
        foreach ($this->Extensions ?? [] as $_ext => $ext) {
            if ($_data = $ext->getData()) {
                $data[$_ext] = $_data;
            }
        }
        return $data ?? [];
    }

    /**
     * Get an instance with the given extensions disabled
     *
     * @param array<class-string<Extension>> $extensions
     * @return static
     */
    public function withoutExtensions(array $extensions = []): self
    {
        return $this->withExtensions([], $extensions);
    }

    /**
     * Get an instance with the given extensions enabled
     *
     * @param array<class-string<Extension>> $enable
     * @param array<class-string<Extension>> $disable
     * @return static
     */
    public function withExtensions(array $enable, array $disable = [], bool $preserveCurrent = true): self
    {
        if ($preserveCurrent) {
            $enable = array_merge(
                $enable,
                $this->PreferredFilters,
                $this->PreferredRules,
            );

            $disable = array_merge(
                $disable,
                array_diff(
                    Arr::extend(self::DEFAULT_RULES, ...self::DEFAULT_FILTERS),
                    $enable,
                ),
            );
        }

        [$filters, $rules] = $this->resolveExtensions($enable, $disable);

        return $this->withPropertyValue('PreferredFilters', $filters)
                    ->withPropertyValue('PreferredRules', $rules)
                    ->apply();
    }

    /**
     * Get an instance that removes blank lines between declarations of the same
     * type where possible
     *
     * @return static
     */
    public function withTightDeclarationSpacing(): self
    {
        return $this->withPropertyValue('TightDeclarationSpacing', true)
                    ->apply();
    }

    /**
     * Get an instance with strict PSR-12 / PER Coding Style compliance enabled
     *
     * @return static
     */
    public function withPsr12(): self
    {
        return $this->withPropertyValue('Psr12', true)
                    ->apply();
    }

    /**
     * Get an instance with a value applied to the given setting
     *
     * @param ("MaxAssignmentPadding"|"MaxDoubleArrowColumn") $property
     * @param int|null $value
     * @return static
     */
    public function with(string $property, $value): self
    {
        return $this->withPropertyValue($property, $value)
                    ->apply();
    }

    /**
     * @internal
     *
     * @return static
     */
    public function withDebug(): self
    {
        return $this->withPropertyValue('Debug', true)
                    ->apply();
    }

    /**
     * Format PHP code
     *
     * @param string|null $eol The end-of-line sequence used in `$code`, or
     * `null` to detect it automatically.
     * @param Indentation|null $indentation The indentation used in `$code`, or
     * `null` if not known.
     * @param string|null $filename The resource from which `$code` was read.
     */
    public function format(
        string $code,
        ?string $eol = null,
        ?Indentation $indentation = null,
        ?string $filename = null,
        bool $fast = false
    ): string {
        $idx = $this->TokenIndex;

        $errorLevel = error_reporting();
        if ($errorLevel & \E_COMPILE_WARNING) {
            error_reporting($errorLevel & ~\E_COMPILE_WARNING);
        }
        if (\PHP_VERSION_ID < 80000 && $errorLevel & \E_DEPRECATED) {
            error_reporting($errorLevel & ~\E_DEPRECATED);
        }

        if (!$this->ExtensionsLoaded) {
            Profile::startTimer(__METHOD__ . '#load-extensions');
            try {
                $this->FormatFilterList = $this->getExtensions($this->FormatFilters);
                $this->ComparisonFilterList = $this->getExtensions($this->ComparisonFilters);
                $this->RuleMap = $this->getExtensions($this->Rules, true);
                $this->ExtensionsLoaded = true;
            } finally {
                Profile::stopTimer(__METHOD__ . '#load-extensions');
            }
        }

        Profile::startTimer(__METHOD__ . '#reset');
        try {
            $this->reset();
            $this->resetExtensions();
        } finally {
            Profile::stopTimer(__METHOD__ . '#reset');
        }

        Profile::startTimer(__METHOD__ . '#detect-eol');
        try {
            if ($eol === null || $eol === '') {
                $eol = Get::eol($code);
            }
            // If a non-standard end-of-line sequence is given, replace it with
            // something `Str::setEol()` recognises
            if ($eol !== null && !([
                "\n" => true,
                "\r" => true,
                "\r\n" => true,
            ][$eol] ?? false)) {
                $code = str_replace($eol, "\n", $code);
            }
            if ($eol === null || !$this->PreserveEol) {
                $eol = $this->PreferredEol;
            }
            // Normalise every line ending
            $code = Str::setEol($code);
        } finally {
            Profile::stopTimer(__METHOD__ . '#detect-eol');
        }

        Profile::startTimer(__METHOD__ . '#parse-input');
        try {
            $this->Filename = $filename;
            $this->Indentation = $indentation;
            $this->Document = $this->Parser->parse($code, ...$this->FormatFilterList);
            $this->Tokens = $this->Document->Tokens;
            $this->TokensById = $this->Document->TokensById;
            $this->Statements = $this->Document->Statements;
            $this->Declarations = $this->Document->Declarations;
            $this->DeclarationsByType = $this->Document->DeclarationsByType;

            if (!$this->Tokens) {
                $this->Debug || $this->clear();
                return '';
            }

            $last = end($this->Tokens);
            if ($last->Flags & Flag::CODE) {
                /** @var Token */
                $statement = $last->Statement;
                if ($statement->id !== \T_HALT_COMPILER) {
                    $last->Whitespace |= Space::LINE_AFTER;
                }
            }
        } catch (CompileError $ex) {
            throw new InvalidSyntaxException(sprintf(
                '%s in %s:%d',
                Get::basename(get_class($ex)),
                $filename ?? '<input>',
                $ex->getLine(),
            ), $ex);
        } finally {
            Profile::stopTimer(__METHOD__ . '#parse-input');
        }

        Profile::startTimer(__METHOD__ . '#find-lists');
        $lists = [];
        $parents = $this->getTokensByType($this->TokensById, [
            \T_OPEN_PARENTHESIS => true,
            \T_OPEN_BRACKET => true,
            \T_OPEN_BRACE => true,
            \T_ATTRIBUTE => true,
            \T_EXTENDS => true,
            \T_IMPLEMENTS => true,
            \T_INSTEADOF => true,
            \T_STATIC => true,
            \T_GLOBAL => true,
        ]);
        foreach ($parents as $i => $parent) {
            if ($parent->CloseBracket === $parent->NextCode) {
                continue;
            }

            $delimiter = $parent->id === \T_OPEN_PARENTHESIS
                && $parent->PrevCode
                && $parent->PrevCode->id === \T_FOR
                    ? \T_SEMICOLON
                    : \T_COMMA;
            $last = null;
            $items = null;
            $minCount = 1;

            /** @var Token */
            $endStatement = $parent->EndStatement;

            switch ($parent->id) {
                case \T_STATIC:
                    if (
                        $parent->Statement !== $parent
                        || $parent->Flags & Flag::DECLARATION
                    ) {
                        continue 2;
                    }
                    // No break
                case \T_INSTEADOF:
                case \T_GLOBAL:
                    /** @var Token */
                    $last = $endStatement->PrevCode;
                    // No break
                case \T_EXTENDS:
                case \T_IMPLEMENTS:
                    /** @var Token */
                    $first = $parent->NextCode;
                    $last ??= $parent->nextSiblingFrom($idx->OpenBraceOrImplements)->PrevCode;
                    /** @var Token $last */
                    $items = $first->withNextSiblings($last)
                                   ->filter(
                                       fn(Token $t, ?Token $next, ?Token $prev) =>
                                           !$prev
                                           || !$t->PrevCode
                                           || $t->PrevCode->id === $delimiter
                                   );
                    $minCount = 2;
                    break;

                case \T_OPEN_PARENTHESIS:
                    $prev = $parent->PrevCode;
                    if (!$prev || !(
                        $idx->BeforeListParenthesis[$prev->id] || (
                            $prev->id === \T_CLOSE_BRACE
                            && !($prev->Flags & Flag::STRUCTURAL_BRACE)
                        ) || (
                            $prev->PrevCode
                            && $idx->Ampersand[$prev->id]
                            && $idx->FunctionOrFn[$prev->PrevCode->id]
                        )
                    )) {
                        continue 2;
                    }
                    break;

                case \T_OPEN_BRACKET:
                    if (!$parent->isArrayOpenBracket()) {
                        continue 2;
                    }
                    break;

                case \T_OPEN_BRACE:
                    /** @var Token */
                    $statement = $parent->Statement;
                    if (!(
                        $statement->Flags & Flag::DECLARATION
                        && ($statement->Data[Data::DECLARATION_TYPE] & (
                            Type::_USE
                            | Type::_TRAIT
                        )) === Type::_USE
                    )) {
                        continue 2;
                    }
                    break;
            }

            $last ??= ($parent->CloseBracket ?? $endStatement)->PrevCode;
            /** @var Token $last */
            $items ??= $parent->children()
                              ->filter(
                                  fn(Token $t, ?Token $next, ?Token $prev) =>
                                      $t->id !== $delimiter
                                      && $t->Statement === $t
                                      && (
                                          !$prev
                                          || !$t->PrevCode
                                          || $t->PrevCode->id === $delimiter
                                      )
                              );

            $count = $items->count();
            if ($count < $minCount) {
                continue;
            }
            $parent->Flags |= Flag::LIST_PARENT;
            $parent->Data[Data::LIST_DELIMITER] = $delimiter;
            $parent->Data[Data::LIST_ITEMS] = $items;
            $parent->Data[Data::LIST_ITEM_COUNT] = $count;
            foreach ($items as $token) {
                $token->Flags |= Flag::LIST_ITEM;
                $token->Data[Data::LIST_PARENT] = $parent;
            }
            $lists[$i] = [$parent, $items, $last];
        }

        $parents = $this->getTokensByType($this->DeclarationsByType, [
            Type::_CONST => true,
            Type::_USE => true,
            Type::PROPERTY => true,
            Type::USE_CONST => true,
            Type::USE_FUNCTION => true,
            Type::USE_TRAIT => true,
        ]);
        foreach ($parents as $i => $parent) {
            $type = $parent->Data[Data::DECLARATION_TYPE];
            $first = null;
            $last = null;

            /** @var Token */
            $endStatement = $parent->EndStatement;

            switch ($type) {
                case Type::_USE:
                case Type::USE_CONST:
                case Type::USE_FUNCTION:
                case Type::USE_TRAIT:
                    /** @var Token */
                    $first = $parent->NextCode;
                    $first = $first->skipNextSiblingFrom($idx->ConstOrFunction);
                    if ($type === Type::USE_TRAIT) {
                        /** @var Token */
                        $last = $parent->nextSiblingFrom($idx->OpenBraceOrSemicolon)
                                       ->PrevCode;
                    }
                    break;

                case Type::PROPERTY:
                    $first = $parent->nextSiblingOf(\T_VARIABLE);
                    /** @var Token */
                    $last = $parent->nextSiblingFrom($idx->OpenBraceOrSemicolon)
                                   ->PrevCode;
                    break;
            }

            $first ??= $parent->nextSiblingOf(\T_EQUAL)->PrevCode;
            /** @var Token $first */
            $parent = $first->PrevCode;
            /** @var Token $parent */
            $last ??= $endStatement->PrevCode;
            /** @var Token $last */
            $items = $first->withNextSiblings($last)
                           ->filter(
                               fn(Token $t, ?Token $next, ?Token $prev) =>
                                   !$prev
                                   || !$t->PrevCode
                                   || $t->PrevCode->id === \T_COMMA
                           );

            $count = $items->count();
            if ($count < 2) {
                continue;
            }
            $parent->Flags |= Flag::LIST_PARENT;
            $parent->Data[Data::LIST_DELIMITER] = \T_COMMA;
            $parent->Data[Data::LIST_ITEMS] = $items;
            $parent->Data[Data::LIST_ITEM_COUNT] = $count;
            foreach ($items as $token) {
                $token->Flags |= Flag::LIST_ITEM;
                $token->Data[Data::LIST_PARENT] = $parent;
            }
            $lists[$i] = [$parent, $items, $last];
        }
        ksort($lists, \SORT_NUMERIC);
        Profile::stopTimer(__METHOD__ . '#find-lists');

        $logProgress = $this->LogProgress
            ? [$this, 'logProgress']
            : fn() => null;

        foreach ($this->MainLoop as [$class, $method]) {
            /** @var TokenRule|StatementRule|DeclarationRule|ListRule */
            $rule = $this->RuleMap[$class];
            $ruleName = Get::basename($class);
            Profile::startTimer($ruleName, 'rule');

            if ($method === StatementRule::PROCESS_STATEMENTS) {
                /** @var StatementRule $rule */
                $rule->processStatements($this->Statements);
            } elseif ($method === ListRule::PROCESS_LIST) {
                foreach ($lists as [$parent, $items, $last]) {
                    /** @var ListRule $rule */
                    $rule->processList($parent, clone $items, $last);
                }
            } else {
                if ($method === DeclarationRule::PROCESS_DECLARATIONS) {
                    [$types, $sort] = $this->DeclarationRuleTypes[$class];
                    $all = $this->Declarations;
                    $byType = $this->DeclarationsByType;
                } else {
                    [$types, $sort] = $this->TokenRuleTypes[$class];
                    $all = $this->Tokens;
                    $byType = $this->TokensById;
                }
                if ($types) {
                    $tokens = $types === ['*']
                        ? $all
                        : ($sort
                            // @phpstan-ignore argument.type
                            ? $this->sortTokensByType($byType, $types)
                            // @phpstan-ignore argument.type
                            : $this->getTokensByType($byType, $types));
                    if ($tokens) {
                        $rule->$method($tokens);
                    }
                }
            }
            Profile::stopTimer($ruleName, 'rule');
            $logProgress($ruleName, $method);
        }

        if ($this->BlockLoop) {
            Profile::startTimer(__METHOD__ . '#find-blocks');
            $blocks = [];
            $lines = [];
            $line = [];
            $token = reset($this->Tokens);
            $endOfBlock = false;
            $endOfLine = false;
            $keep = true;
            for (;;) {
                if ($token && $token->id !== \T_INLINE_HTML) {
                    $before = $token->getWhitespaceBefore();
                    if ($before & Space::BLANK) {
                        $endOfBlock = true;
                        $endOfLine = true;
                    } elseif ($before & Space::LINE) {
                        $endOfLine = true;
                    }
                } else {
                    $endOfBlock = true;
                    $endOfLine = true;
                    $keep = false;
                }
                if ($endOfLine) {
                    if ($line) {
                        $lines[] = TokenCollection::from($line);
                        $line = [];
                    }
                    $endOfLine = false;
                }
                if ($endOfBlock) {
                    if ($lines) {
                        $blocks[] = $lines;
                        $lines = [];
                    }
                    $endOfBlock = false;
                }
                if (!$token) {
                    break;
                }
                if ($keep) {
                    $line[] = $token;
                } else {
                    $keep = true;
                }
                $token = $token->Next;
            }
            Profile::stopTimer(__METHOD__ . '#find-blocks');

            foreach ($this->BlockLoop as [$class]) {
                /** @var BlockRule */
                $rule = $this->RuleMap[$class];
                $ruleName = Get::basename($class);
                Profile::startTimer($ruleName, 'rule');
                foreach ($blocks as $block) {
                    $rule->processBlock($block);
                }
                Profile::stopTimer($ruleName, 'rule');
                $logProgress($ruleName, BlockRule::PROCESS_BLOCK);
            }
        }

        if ($this->Callbacks) {
            ksort($this->Callbacks);
            foreach ($this->Callbacks as $tokenCallbacks) {
                ksort($tokenCallbacks);
                foreach ($tokenCallbacks as $index => $callbacks) {
                    foreach ($callbacks as $i => [$rule, $callback]) {
                        $ruleName = Get::basename($rule);
                        Profile::startTimer($ruleName, 'rule');
                        $callback();
                        Profile::stopTimer($ruleName, 'rule');
                        $logProgress($ruleName, "{closure:$index:$i}");
                    }
                }
            }
        }

        foreach ($this->BeforeRender as [$class]) {
            $rule = $this->RuleMap[$class];
            $ruleName = Get::basename($class);
            Profile::startTimer($ruleName, 'rule');
            $rule->beforeRender($this->Tokens);
            Profile::stopTimer($ruleName, 'rule');
            $logProgress($ruleName, Rule::BEFORE_RENDER);
        }

        Profile::startTimer(__METHOD__ . '#render');
        try {
            $first = reset($this->Tokens);
            $last = end($this->Tokens);
            $out = $this->Renderer->render($first, $last, false, true);
            // @codeCoverageIgnoreStart
        } catch (Throwable $ex) {
            throw new FormatterException(
                'Unable to render output',
                null,
                $this->Debug ? $this->Tokens : null,
                $this->Log,
                $this->Debug ? $this->getExtensionData() : null,
                $ex,
            );
            // @codeCoverageIgnoreEnd
        } finally {
            Profile::stopTimer(__METHOD__ . '#render');
            $this->Debug || $this->clear();
        }

        if (!$fast) {
            Profile::startTimer(__METHOD__ . '#parse-output');
            try {
                $after = $this->tokenizeAndSimplify($out);
                // @codeCoverageIgnoreStart
            } catch (CompileError $ex) {
                throw new FormatterException(
                    'Unable to parse output',
                    $out,
                    $this->Debug ? $this->Tokens : null,
                    $this->Log,
                    $this->Debug ? $this->getExtensionData() : null,
                    $ex,
                );
                // @codeCoverageIgnoreEnd
            } finally {
                Profile::stopTimer(__METHOD__ . '#parse-output');
            }

            Profile::startTimer(__METHOD__ . '#parse-input-for-comparison');
            try {
                $before = $this->tokenizeAndSimplify($code);
            } finally {
                Profile::stopTimer(__METHOD__ . '#parse-input-for-comparison');
            }

            if ($before !== $after) {
                // @codeCoverageIgnoreStart
                throw new FormatterException(
                    "Parsed output doesn't match input",
                    $out,
                    $this->Debug ? $this->Tokens : null,
                    $this->Log,
                    $this->Debug ? $this->getExtensionData() : null,
                );
                // @codeCoverageIgnoreEnd
            }
        }

        return $eol === "\n"
            ? $out
            : str_replace("\n", $eol, $out);
    }

    /**
     * @param class-string<Rule> $rule
     */
    public function registerCallback(
        string $rule,
        Token $first,
        Closure $callback,
        bool $reverse = false
    ): void {
        if (!isset($this->CallbackPriorities[$rule])) {
            // @codeCoverageIgnoreStart
            throw new LogicException(
                sprintf('Rule has no callback priority: %s', $rule)
            );
            // @codeCoverageIgnoreEnd
        }

        $priority = $this->CallbackPriorities[$rule];
        $index = $first->index;
        if ($reverse) {
            $index = -$index;
        }
        $this->Callbacks[$priority][$index][] = [$rule, $callback];
    }

    /**
     * Report a non-critical problem detected in formatted code
     *
     * @param string $message An sprintf() format string describing the problem.
     * @param Token $start The start of the range of tokens with the problem.
     * @param Token|null $end The end of the range of tokens with the problem,
     * or `null` if the problem only affects one token.
     * @param int|float|string|bool|null ...$values Values for the sprintf()
     * format string.
     */
    public function registerProblem(
        string $message,
        Token $start,
        ?Token $end = null,
        ...$values
    ): void {
        if ($this->DetectProblems) {
            $this->Problems[] = new Problem(
                $message,
                $this->Filename,
                $start,
                $end,
                ...$values,
            );
        }
    }

    /**
     * @template T of Extension
     *
     * @param array<class-string<T>> $extensions
     * @return ($map is true ? array<class-string<T>,T> : list<T>)
     */
    private function getExtensions(array $extensions, bool $map = false): array
    {
        foreach ($extensions as $ext) {
            /** @var T */
            $extension = $this->Extensions[$ext] ??= $this->getExtension($ext);
            if ($map) {
                $result[$ext] = $extension;
            } else {
                $result[] = $extension;
            }
        }
        return $result ?? [];
    }

    /**
     * @template T of Extension
     *
     * @param class-string<T> $extension
     * @return T
     */
    private function getExtension(string $extension): Extension
    {
        $ext = new $extension($this);
        $ext->boot();
        return $ext;
    }

    /**
     * @param array<int,array<int,Token>> $index
     * @param array<int,true> $types
     * @return array<int,Token>
     */
    private function sortTokensByType(array $index, array $types): array
    {
        $tokens = $this->getTokensByType($index, $types);
        ksort($tokens, \SORT_NUMERIC);
        return $tokens;
    }

    /**
     * @param array<int,array<int,Token>> $index
     * @param array<int,true> $types
     * @return array<int,Token>
     */
    private function getTokensByType(array $index, array $types): array
    {
        $tokens = array_intersect_key($index, $types);
        if ($base = array_shift($tokens)) {
            return array_replace($base, ...$tokens);
        }
        return [];
    }

    /**
     * @return $this
     */
    private function logProgress(string $rule, string $after): self
    {
        Profile::startTimer(__METHOD__ . '#render');
        try {
            if ($this->Tokens) {
                $first = reset($this->Tokens);
                $last = end($this->Tokens);
                $out = $this->Renderer->render($first, $last, false);
            } else {
                $out = '';
            }
            // @codeCoverageIgnoreStart
        } catch (Throwable $ex) {
            throw new FormatterException(
                'Unable to render partially formatted output',
                null,
                $this->Tokens,
                $this->Log,
                $this->getExtensionData(),
                $ex,
            );
            // @codeCoverageIgnoreEnd
        } finally {
            Profile::stopTimer(__METHOD__ . '#render');
        }
        $this->Log[$rule . '-' . $after] = $out;

        return $this;
    }

    /**
     * @return array<array{int,string}>
     */
    private function tokenizeAndSimplify(string $code): array
    {
        $this->resetExtensions($this->ComparisonFilterList);
        $tokens = Token::tokenizeForComparison(
            $code,
            \TOKEN_PARSE,
            ...$this->ComparisonFilterList,
        );

        foreach ($tokens as $token) {
            $simple[] = [$token->id, $token->text];
        }

        return $simple ?? [];
    }

    /**
     * @param Extension[]|null $extensions
     * @return $this
     */
    private function resetExtensions(?array $extensions = null): self
    {
        $extensions ??= $this->Extensions;
        foreach ($extensions as $ext) {
            $ext->reset();
        }

        return $this;
    }

    /**
     * @return $this
     */
    private function apply(): self
    {
        if ($this->Applied) {
            return $this;
        }

        if ($this->Psr12) {
            $this->InsertSpaces = true;
            $this->TabSize = 4;
            $this->PreferredEol = "\n";
            $this->PreserveEol = false;
            $this->HeredocIndent = HeredocIndent::HANGING;
            $this->OneTrueBraceStyle = false;
            $this->ExpandHeaders = true;
            $this->NewlineBeforeFnDoubleArrow = true;

            $enable = array_merge(
                self::PSR12_ENABLE,
                $this->PreferredFilters,
                $this->PreferredRules,
            );

            $disable = array_merge(
                self::PSR12_DISABLE,
                array_diff(
                    Arr::extend(self::DEFAULT_RULES, ...self::DEFAULT_FILTERS),
                    $enable,
                ),
            );

            [$filters, $rules] = $this->resolveExtensions($enable, $disable);
        } else {
            $this->NewlineBeforeFnDoubleArrow = false;
            $filters = $this->PreferredFilters;
            $rules = $this->PreferredRules;
        }

        /** @var ("  "|"    "|"        ") */
        $spaces = str_repeat(' ', $this->TabSize);
        $this->SoftTab = $spaces;
        $this->Tab = $this->InsertSpaces ? $spaces : "\t";

        if ($this->SpacesBesideCode < 1) {
            $this->SpacesBesideCode = 1;
        }

        // If using tabs for indentation, disable incompatible rules
        if (!$this->InsertSpaces) {
            $rules = array_diff($rules, self::NO_TAB_RULES);
        }

        // Enable `PreserveNewlines` if disabled, but limit its scope to blank
        // lines between statements
        if (!in_array(PreserveNewlines::class, $rules, true)) {
            $this->PreserveNewlines = false;
            $this->TokenIndex = $this->TokenIndex->withoutPreserveNewline();
            $rules[] = PreserveNewlines::class;
        } else {
            $this->PreserveNewlines = true;
            $this->TokenIndex = $this->TokenIndex->withPreserveNewline();
        }

        foreach (self::INCOMPATIBLE_RULES as $incompatible) {
            $incompatible = array_intersect($incompatible, $rules);
            if (count($incompatible) > 1) {
                $names = array_map([Get::class, 'basename'], $incompatible);
                throw new InvalidFormatterException(sprintf(
                    'Enabled rules are not compatible: %s',
                    implode(', ', $names),
                ));
            }
        }

        if (
            $this->TightDeclarationSpacing
            && !in_array(DeclarationSpacing::class, $rules, true)
        ) {
            throw new InvalidFormatterException(sprintf(
                '%s cannot be disabled when tight declaration spacing is enabled',
                Get::basename(DeclarationSpacing::class),
            ));
        }

        $withComparison = array_merge($filters, self::COMPARISON_FILTERS);
        // Column numbers are unnecessary for comparison
        $withoutColumn = array_diff($withComparison, [CollectColumn::class]);

        $this->FormatFilters = $filters;
        $this->ComparisonFilters = $withoutColumn;

        Profile::startTimer(__METHOD__ . '#sort-rules');

        $tokenTypes = [];
        $declarationTypes = [];
        $mainLoop = [];
        $blockLoop = [];
        $beforeRender = [];
        $callbackPriorities = [];
        $i = 0;
        foreach ($rules as $rule) {
            if (is_a($rule, TokenRule::class, true)) {
                /** @var array<int,bool>|array{'*'} */
                $types = $rule::getTokens($this->TokenIndex);
                if ($types !== ['*']) {
                    $types = array_filter($types);
                }
                $tokenTypes[$rule] = [$types, $rule::needsSortedTokens()];
                $mainLoop[$rule . '#token'] = [$rule, TokenRule::PROCESS_TOKENS, $i];
            }
            if (is_a($rule, ListRule::class, true)) {
                $mainLoop[$rule . '#list'] = [$rule, ListRule::PROCESS_LIST, $i];
            }
            if (is_a($rule, StatementRule::class, true)) {
                $mainLoop[$rule . '#statement'] = [$rule, StatementRule::PROCESS_STATEMENTS, $i];
            }
            if (is_a($rule, DeclarationRule::class, true)) {
                /** @var array<int,bool>|array{'*'} */
                $types = $rule::getDeclarationTypes(
                    self::$AllDeclarationTypes ??=
                        $this->getAllDeclarationTypes()
                );
                if ($types !== ['*']) {
                    $types = array_filter($types);
                }
                $declarationTypes[$rule] = [$types, $rule::needsSortedDeclarations()];
                $mainLoop[$rule . '#declaration'] = [$rule, DeclarationRule::PROCESS_DECLARATIONS, $i];
            }
            if (is_a($rule, BlockRule::class, true)) {
                $blockLoop[$rule] = [$rule, BlockRule::PROCESS_BLOCK, $i];
            }
            $beforeRender[$rule] = [$rule, Rule::BEFORE_RENDER, $i];

            $priority = $rule::getPriority(Rule::CALLBACK);
            if ($priority !== null) {
                $callbackPriorities[$rule] = $priority;
            }

            $i++;
        }

        $this->Rules = $rules;
        $this->TokenRuleTypes = $tokenTypes;
        $this->DeclarationRuleTypes = $declarationTypes;
        $this->MainLoop = $this->sortRules($mainLoop);
        $this->BlockLoop = $this->sortRules($blockLoop);
        $this->BeforeRender = $this->sortRules($beforeRender);
        $this->CallbackPriorities = $callbackPriorities;

        Profile::stopTimer(__METHOD__ . '#sort-rules');

        $this->Enabled = array_fill_keys(
            Arr::extend($rules, ...$withComparison),
            true,
        );

        if ($this->Applied === false) {
            $this->Parser = new Parser($this);
            $this->Renderer = new Renderer($this);
        } else {
            $this->Parser = $this->Parser->withFormatter($this);
            $this->Renderer = $this->Renderer->withFormatter($this);
        }

        $this->Applied = true;

        return $this;
    }

    /**
     * @param array<class-string<Extension>> $enable
     * @param array<class-string<Extension>> $disable
     * @return array{array<class-string<Filter>>,array<class-string<Rule>>}
     */
    private function resolveExtensions(array $enable, array $disable): array
    {
        return [
            $this->getEnabled($enable, $disable, self::DEFAULT_FILTERS, self::OPTIONAL_FILTERS),
            $this->getEnabled($enable, $disable, self::DEFAULT_RULES, self::OPTIONAL_RULES),
        ];
    }

    /**
     * @template T of Extension
     *
     * @param array<class-string<Extension>> $enable
     * @param array<class-string<Extension>> $disable
     * @param array<class-string<T>> $default
     * @param array<class-string<T>> $optional
     * @return array<class-string<T>>
     */
    private function getEnabled(array $enable, array $disable, array $default, array $optional): array
    {
        // 5. Remove eligible extensions found in `$disable` from enabled
        //    extensions, disabling any also found in `$enable`
        return array_diff(
            // 3. Merge default extensions with eligible extensions found in
            //    `$enable`
            array_merge(
                $default,
                // 2. Limit `$enable` to optional extensions
                array_intersect(
                    $optional,
                    // 1. Remove extensions enabled by default from `$enable`
                    array_diff($enable, $default),
                ),
            ),
            // 4. Limit `$disable` to optional extensions
            array_intersect(
                $optional,
                $disable,
            ),
        );
    }

    /**
     * @return array<int,true>
     */
    private function getAllDeclarationTypes(): array
    {
        /** @var int[] */
        $types = Reflect::getConstants(Type::class);
        return array_fill_keys($types, true);
    }

    /**
     * @template TRule of Rule
     *
     * @param array<string,array{class-string<TRule>,string,int}> $rules
     * @return array<string,array{class-string<TRule>,string}>
     */
    private function sortRules(array $rules): array
    {
        $sorted = [];
        foreach ($rules as $key => $value) {
            [$rule, $method] = $value;
            $priority = $rule::getPriority($method);
            if ($priority === null) {
                continue;
            }
            $value[3] = $priority;
            $sorted[$key] = $value;
        }

        // Sort by priority, then index
        uasort(
            $sorted,
            fn($a, $b) => $a[3] <=> $b[3] ?: $a[2] <=> $b[2],
        );

        foreach ($sorted as $key => [$rule, $method]) {
            $result[$key] = [$rule, $method];
        }

        return $result ?? [];
    }

    /**
     * Clear state that should not persist beyond a change to the formatter
     *
     * @return $this
     */
    private function flush(): self
    {
        unset($this->SoftTab);
        unset($this->Tab);
        unset($this->PreserveNewlines);
        unset($this->Enabled);
        unset($this->FormatFilters);
        unset($this->ComparisonFilters);
        unset($this->Rules);
        unset($this->TokenRuleTypes);
        unset($this->DeclarationRuleTypes);
        unset($this->MainLoop);
        unset($this->BlockLoop);
        unset($this->BeforeRender);
        unset($this->CallbackPriorities);
        $this->ExtensionsLoaded = false;
        unset($this->Extensions);
        unset($this->FormatFilterList);
        unset($this->ComparisonFilterList);
        unset($this->RuleMap);

        return $this->reset();
    }

    /**
     * Clear state that should not persist beyond a payload
     *
     * @return $this
     */
    private function reset(): self
    {
        unset($this->Filename);
        unset($this->Indentation);
        $this->Problems = null;
        $this->Log = null;
        $this->Callbacks = null;

        return $this->clear();
    }

    /**
     * Clear the current payload
     *
     * @return $this
     */
    private function clear(): self
    {
        unset($this->Document);
        unset($this->Tokens);
        unset($this->TokensById);
        unset($this->Statements);
        unset($this->Declarations);
        unset($this->DeclarationsByType);

        return $this;
    }
}
