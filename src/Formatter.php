<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP;

use Lkrms\PrettyPHP\Catalog\FormatterFlag;
use Lkrms\PrettyPHP\Catalog\HeredocIndent;
use Lkrms\PrettyPHP\Catalog\ImportSortOrder;
use Lkrms\PrettyPHP\Catalog\TokenData;
use Lkrms\PrettyPHP\Catalog\TokenFlag;
use Lkrms\PrettyPHP\Catalog\TokenType;
use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Contract\BlockRule;
use Lkrms\PrettyPHP\Contract\Extension;
use Lkrms\PrettyPHP\Contract\Filter;
use Lkrms\PrettyPHP\Contract\ListRule;
use Lkrms\PrettyPHP\Contract\Rule;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Exception\FormatterException;
use Lkrms\PrettyPHP\Exception\InvalidFormatterException;
use Lkrms\PrettyPHP\Exception\InvalidSyntaxException;
use Lkrms\PrettyPHP\Filter\CollectColumn;
use Lkrms\PrettyPHP\Filter\EvaluateNumbers;
use Lkrms\PrettyPHP\Filter\EvaluateStrings;
use Lkrms\PrettyPHP\Filter\MoveComments;
use Lkrms\PrettyPHP\Filter\RemoveEmptyDocBlocks;
use Lkrms\PrettyPHP\Filter\RemoveEmptyTokens;
use Lkrms\PrettyPHP\Filter\RemoveHeredocIndentation;
use Lkrms\PrettyPHP\Filter\RemoveWhitespace;
use Lkrms\PrettyPHP\Filter\SortImports;
use Lkrms\PrettyPHP\Filter\TrimCasts;
use Lkrms\PrettyPHP\Filter\TrimOpenTags;
use Lkrms\PrettyPHP\Filter\TruncateComments;
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
use Lkrms\PrettyPHP\Rule\EssentialWhitespace;
use Lkrms\PrettyPHP\Rule\HangingIndentation;
use Lkrms\PrettyPHP\Rule\HeredocIndentation;
use Lkrms\PrettyPHP\Rule\ListSpacing;
use Lkrms\PrettyPHP\Rule\NormaliseComments;
use Lkrms\PrettyPHP\Rule\OperatorSpacing;
use Lkrms\PrettyPHP\Rule\PlaceBraces;
use Lkrms\PrettyPHP\Rule\PlaceComments;
use Lkrms\PrettyPHP\Rule\PreserveNewlines;
use Lkrms\PrettyPHP\Rule\PreserveOneLineStatements;
use Lkrms\PrettyPHP\Rule\ProtectStrings;
use Lkrms\PrettyPHP\Rule\SimplifyNumbers;
use Lkrms\PrettyPHP\Rule\SimplifyStrings;
use Lkrms\PrettyPHP\Rule\StandardIndentation;
use Lkrms\PrettyPHP\Rule\StandardWhitespace;
use Lkrms\PrettyPHP\Rule\StatementSpacing;
use Lkrms\PrettyPHP\Rule\StrictExpressions;
use Lkrms\PrettyPHP\Rule\StrictLists;
use Lkrms\PrettyPHP\Rule\SwitchIndentation;
use Lkrms\PrettyPHP\Rule\VerticalWhitespace;
use Lkrms\PrettyPHP\Support\Problem;
use Lkrms\PrettyPHP\Support\TokenCollection;
use Lkrms\PrettyPHP\Support\TokenTypeIndex;
use Lkrms\PrettyPHP\Token\GenericToken;
use Lkrms\PrettyPHP\Token\Token;
use Salient\Contract\Core\Buildable;
use Salient\Core\Concern\HasBuilder;
use Salient\Core\Concern\HasImmutableProperties;
use Salient\Core\Facade\Profile;
use Salient\Core\Indentation;
use Salient\Utility\Arr;
use Salient\Utility\Env;
use Salient\Utility\Get;
use Closure;
use CompileError;
use InvalidArgumentException;
use LogicException;
use Throwable;

/**
 * Formats PHP code
 *
 * @implements Buildable<FormatterBuilder>
 */
final class Formatter implements Buildable
{
    /** @use HasBuilder<FormatterBuilder> */
    use HasBuilder;
    use HasImmutableProperties;

    /**
     * Use spaces for indentation?
     *
     * @readonly
     */
    public bool $InsertSpaces;

    /**
     * The size of a tab, in spaces
     *
     * @readonly
     * @phpstan-var 2|4|8
     */
    public int $TabSize;

    /**
     * A series of spaces equivalent to a tab
     *
     * @readonly
     * @phpstan-var ("  "|"    "|"        ")
     */
    public string $SoftTab;

    /**
     * The string used for indentation
     *
     * @readonly
     * @phpstan-var ("  "|"    "|"        "|"	")
     */
    public string $Tab;

    /**
     * Token type index
     *
     * @readonly
     */
    public TokenTypeIndex $TokenTypeIndex;

    /**
     * End-of-line sequence used when line endings are not preserved or when
     * there are no line breaks in the input
     *
     * @readonly
     */
    public string $PreferredEol;

    /**
     * True if line endings are preserved
     *
     * @readonly
     */
    public bool $PreserveEol;

    /**
     * Spaces between code and comments on the same line
     *
     * @readonly
     */
    public int $SpacesBesideCode;

    /**
     * Indentation applied to heredocs and nowdocs
     *
     * @readonly
     * @var HeredocIndent::*
     */
    public int $HeredocIndent;

    /**
     * @readonly
     * @var ImportSortOrder::*
     */
    public int $ImportSortOrder;

    /**
     * True if braces are formatted using the One True Brace Style
     *
     * @readonly
     */
    public bool $OneTrueBraceStyle;

    /**
     * True if blank lines between declarations of the same type are removed
     * where possible
     *
     * @readonly
     */
    public bool $TightDeclarationSpacing;

    /**
     * Enforce strict PSR-12 / PER Coding Style compliance?
     *
     * @readonly
     */
    public bool $Psr12;

    /**
     * False if calls to registerProblem() are ignored
     *
     * @readonly
     */
    public bool $DetectProblems;

    /**
     * False if line breaks are only preserved between statements
     *
     * When the {@see PreserveNewlines} rule is disabled, `false` is assigned to
     * this property and the rule is reinstated to preserve blank lines between
     * statements.
     *
     * @readonly
     */
    public bool $PreserveNewlines;

    /**
     * An index of enabled extensions
     *
     * @readonly
     * @var array<class-string<Extension>,true>
     */
    public array $Enabled;

    // --

    /** @readonly */
    public bool $IncreaseIndentBetweenUnenclosedTags = true;
    /** @readonly */
    public bool $RelaxAlignmentCriteria = false;
    /** @readonly */
    public bool $NewlineBeforeFnDoubleArrows = false;

    /**
     * If the first object operator in a chain of method calls has a leading
     * newline, align with the start of the chain?
     *
     * Only applies if {@see AlignChains} is enabled.
     *
     * ```php
     * // If `false`:
     * $result = $object
     *     ->method1();
     *
     * // If `true`:
     * $result = $object
     *               ->method1();
     * ```
     *
     * @readonly
     */
    public bool $AlignFirstCallInChain = true;

    // --

    /**
     * @var array<class-string<Rule>>
     */
    public const DEFAULT_RULES = [
        ProtectStrings::class,
        SimplifyNumbers::class,
        SimplifyStrings::class,
        NormaliseComments::class,
        StandardWhitespace::class,
        StatementSpacing::class,
        OperatorSpacing::class,
        ControlStructureSpacing::class,
        PlaceComments::class,
        PlaceBraces::class,
        PreserveNewlines::class,
        VerticalWhitespace::class,
        ListSpacing::class,
        StandardIndentation::class,
        SwitchIndentation::class,
        DeclarationSpacing::class,
        HangingIndentation::class,
        HeredocIndentation::class,
        EssentialWhitespace::class,
    ];

    /**
     * @var array<class-string<Rule>>
     */
    public const OPTIONAL_RULES = [
        SimplifyNumbers::class,
        SimplifyStrings::class,
        PreserveNewlines::class,
        PreserveOneLineStatements::class,
        BlankBeforeReturn::class,
        StrictExpressions::class,
        Drupal::class,
        Laravel::class,
        Symfony::class,
        WordPress::class,
        AlignChains::class,
        StrictLists::class,
        AlignArrowFunctions::class,
        AlignTernaryOperators::class,
        AlignLists::class,
        AlignData::class,
        AlignComments::class,
        DeclarationSpacing::class,
    ];

    /**
     * @var array<class-string<Rule>>
     */
    public const NO_TAB_RULES = [
        AlignChains::class,
        AlignArrowFunctions::class,
        AlignTernaryOperators::class,
        AlignLists::class,
    ];

    /**
     * @var array<array<class-string<Rule>>>
     */
    public const INCOMPATIBLE_RULES = [
        [
            StrictLists::class,
            AlignLists::class,
        ],
    ];

    /**
     * @var array<class-string<Filter>>
     */
    public const DEFAULT_FILTERS = [
        CollectColumn::class,
        RemoveWhitespace::class,
        RemoveHeredocIndentation::class,
        RemoveEmptyDocBlocks::class,
        MoveComments::class,
        SortImports::class,
        TrimCasts::class,
    ];

    /**
     * @var array<class-string<Filter>>
     */
    public const OPTIONAL_FILTERS = [
        RemoveEmptyDocBlocks::class,
        MoveComments::class,
        SortImports::class,
        TrimCasts::class,
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
        AlignLists::class,
    ];

    // --

    /** @var array<class-string<Rule>> */
    private array $PreferredRules;
    /** @var array<class-string<Filter>> */
    private array $PreferredFilters;

    // --

    /** @var array<class-string<Rule>> */
    private array $Rules;
    /** @var array<class-string<TokenRule>,array<int,true>|array{'*'}> */
    private array $RuleTokenTypes;

    /**
     * [ key => [ rule, method ] ]
     *
     * @var array<string,array{class-string<TokenRule|ListRule>,string}>
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
    /** @var array<class-string<Filter>> */
    private array $FormatFilters;
    /** @var array<class-string<Filter>> */
    private array $ComparisonFilters;
    /** @var array<class-string<Rule>,Rule> */
    private array $RuleMap;
    /** @var Filter[] */
    private array $FormatFilterList;
    /** @var Filter[] */
    private array $ComparisonFilterList;
    /** @var array<class-string<Extension>,Extension> */
    private array $Extensions;
    private bool $ExtensionsLoaded = false;

    // --

    public ?string $Filename = null;

    /**
     * Indentation used in the input, if known
     */
    public ?Indentation $Indentation = null;

    /** @var array<int,Token> */
    public array $Tokens;
    /** @var array<int,array<int,Token>> */
    private array $TokenIndex;

    /**
     * [ priority => [ token index => [ [ rule, callback ], ... ] ] ]
     *
     * @var array<int,array<int,array<array{class-string<Rule>,Closure}>>>|null
     */
    private ?array $Callbacks = null;

    /** @var Problem[]|null */
    public ?array $Problems = null;
    /** @var array<string,string>|null */
    public ?array $Log = null;

    // --

    private bool $Debug;
    private bool $LogProgress;
    private Parser $Parser;
    /** @readonly */
    public Renderer $Renderer;

    /**
     * Creates a new Formatter object
     *
     * @phpstan-param 2|4|8 $tabSize
     * @param array<class-string<Extension>> $disable Non-mandatory extensions to disable
     * @param array<class-string<Extension>> $enable Optional extensions to enable
     * @param int-mask-of<FormatterFlag::*> $flags Debugging flags
     * @param TokenTypeIndex|null $tokenTypeIndex Provide a customised token type index
     * @param HeredocIndent::* $heredocIndent
     * @param ImportSortOrder::* $importSortOrder
     */
    public function __construct(
        bool $insertSpaces = true,
        int $tabSize = 4,
        array $disable = [],
        array $enable = [],
        int $flags = 0,
        ?TokenTypeIndex $tokenTypeIndex = null,
        string $preferredEol = \PHP_EOL,
        bool $preserveEol = true,
        int $spacesBesideCode = 2,
        int $heredocIndent = HeredocIndent::MIXED,
        int $importSortOrder = ImportSortOrder::DEPTH,
        bool $oneTrueBraceStyle = false,
        bool $tightDeclarationSpacing = false,
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
        $this->TokenTypeIndex = $tokenTypeIndex ?? new TokenTypeIndex();
        $this->PreferredEol = $preferredEol;
        $this->PreserveEol = $preserveEol;
        $this->SpacesBesideCode = $spacesBesideCode;
        $this->HeredocIndent = $heredocIndent;
        $this->ImportSortOrder = $importSortOrder;
        $this->OneTrueBraceStyle = $oneTrueBraceStyle;
        $this->TightDeclarationSpacing = $tightDeclarationSpacing;
        $this->Psr12 = $psr12;

        $this->Debug = ($flags & FormatterFlag::DEBUG) || Env::getDebug();
        $this->LogProgress = $this->Debug && ($flags & FormatterFlag::LOG_PROGRESS);
        $this->DetectProblems = (bool) ($flags & FormatterFlag::DETECT_PROBLEMS);

        $this->resolveExtensions($rules, $filters, $enable, $disable);
        $this->PreferredRules = $rules;
        $this->PreferredFilters = $filters;

        $this->apply();
    }

    /**
     * @return static
     */
    private function apply(): self
    {
        if ($this->Psr12) {
            $this->InsertSpaces = true;
            $this->TabSize = 4;
            $this->PreferredEol = "\n";
            $this->PreserveEol = false;
            $this->HeredocIndent = HeredocIndent::HANGING;
            $this->OneTrueBraceStyle = false;
            $this->NewlineBeforeFnDoubleArrows = true;

            $enable = array_merge(
                self::PSR12_ENABLE,
                $this->PreferredRules,
                $this->PreferredFilters,
            );

            $disable = array_merge(
                self::PSR12_DISABLE,
                array_diff(
                    Arr::extend(self::DEFAULT_RULES, ...self::DEFAULT_FILTERS),
                    $enable,
                ),
            );

            $this->resolveExtensions($rules, $filters, $enable, $disable);
        } else {
            $rules = $this->PreferredRules;
            $filters = $this->PreferredFilters;
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
            $this->TokenTypeIndex = $this->TokenTypeIndex->withoutPreserveNewline();
            $rules[] = PreserveNewlines::class;
        } else {
            $this->PreserveNewlines = true;
            $this->TokenTypeIndex = $this->TokenTypeIndex->withPreserveNewline();
        }

        foreach (self::INCOMPATIBLE_RULES as $incompatible) {
            $incompatible = array_intersect($incompatible, $rules);
            if (count($incompatible) > 1) {
                $names = [];
                foreach ($incompatible as $rule) {
                    $names[] = Get::basename($rule);
                }
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

        Profile::startTimer(__METHOD__ . '#sort-rules');

        $tokenTypes = [];
        $mainLoop = [];
        $blockLoop = [];
        $beforeRender = [];
        $callbackPriorities = [];
        $i = 0;
        foreach ($rules as $rule) {
            if (is_a($rule, TokenRule::class, true)) {
                /** @var int[]|array<int,bool>|array{'*'} */
                $types = $rule::getTokenTypes($this->TokenTypeIndex);
                $first = Arr::first($types);
                if (is_bool($first)) {
                    // Reduce an index to entries with a value of `true`
                    $index = $types;
                    $types = [];
                    /** @var int $type */
                    foreach ($index as $type => $value) {
                        if ($value) {
                            $types[$type] = true;
                        }
                    }
                } elseif (is_int($first)) {
                    // Convert a list of types to an index
                    /** @var array<int,true> */
                    $types = array_fill_keys($types, true);
                } elseif ($types !== ['*']) {
                    throw new LogicException(sprintf(
                        'Invalid return value: %s::getTokenTypes()',
                        $rule,
                    ));
                }
                $tokenTypes[$rule] = $types;
                $mainLoop[$rule . '#token'] = [$rule, TokenRule::PROCESS_TOKENS, $i];
            }
            if (is_a($rule, ListRule::class, true)) {
                $mainLoop[$rule . '#list'] = [$rule, ListRule::PROCESS_LIST, $i];
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
        $this->RuleTokenTypes = $tokenTypes;
        $this->MainLoop = $this->sortRules($mainLoop);
        $this->BlockLoop = $this->sortRules($blockLoop);
        $this->BeforeRender = $this->sortRules($beforeRender);
        $this->CallbackPriorities = $callbackPriorities;

        Profile::stopTimer(__METHOD__ . '#sort-rules');

        $withComparison = array_merge($filters, self::COMPARISON_FILTERS);
        // Column numbers are unnecessary when comparing tokens
        $withoutColumn = array_diff($withComparison, [CollectColumn::class]);

        $this->FormatFilters = $filters;
        $this->ComparisonFilters = $withoutColumn;

        /** @var array<class-string<Extension>,true> */
        $enabled = Arr::toIndex(Arr::extend($rules, ...$withComparison));
        $this->Enabled = $enabled;

        $this->Parser = new Parser($this);
        $this->Renderer = new Renderer($this);

        return $this;
    }

    /**
     * Get the formatter's indentation settings
     */
    public function getIndentation(): Indentation
    {
        return new Indentation($this->InsertSpaces, $this->TabSize);
    }

    /**
     * Get the formatter's token array
     *
     * @return Token[]|null
     */
    public function getTokens(): ?array
    {
        return $this->Tokens ?? null;
    }

    /**
     * @internal
     *
     * @param mixed $value
     * @return static
     */
    public function with(string $property, $value): self
    {
        if (!isset([
            'IncreaseIndentBetweenUnenclosedTags' => true,
            'RelaxAlignmentCriteria' => true,
            'NewlineBeforeFnDoubleArrows' => true,
            'AlignFirstCallInChain' => true,
        ][$property])) {
            // @codeCoverageIgnoreStart
            throw new InvalidArgumentException(
                sprintf('Invalid property: %s', $property)
            );
            // @codeCoverageIgnoreEnd
        }

        return $this->withPropertyValue($property, $value)
                    ->apply();
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
        return $this->doWithExtensions($enable, $disable, $preserveCurrent)
                    ->apply();
    }

    /**
     * @param array<class-string<Extension>> $enable
     * @param array<class-string<Extension>> $disable
     * @return static
     */
    private function doWithExtensions(array $enable, array $disable = [], bool $preserveCurrent = true): self
    {
        if ($preserveCurrent) {
            $enable = array_merge(
                $enable,
                $this->PreferredRules,
                $this->PreferredFilters,
            );

            $disable = array_merge(
                $disable,
                array_diff(
                    Arr::extend(self::DEFAULT_RULES, ...self::DEFAULT_FILTERS),
                    $enable,
                ),
            );
        }

        $this->resolveExtensions($rules, $filters, $enable, $disable);

        return $this->withPropertyValue('PreferredRules', $rules)
                    ->withPropertyValue('PreferredFilters', $filters);
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
     * Get formatted code
     *
     * 1. Load enabled extensions (if not already loaded)
     * 2. Reset the formatter and enabled extensions
     * 3. Detect the end-of-line sequence used in `$code` (if not given)
     * 4. Convert line breaks in `$code` to `"\n"` if needed
     * 5. Tokenize, filter and parse `$code` (see {@see Parser::parse()})
     * 6. Find lists comprised of
     *    - one or more comma-delimited items between `[]` or `()`, or
     *    - two or more interfaces after `extends` or `implements`
     * 7. Process enabled {@see TokenRule} and {@see ListRule} extensions in one
     *    loop, ordered by priority
     * 8. Find blocks comprised of two or more consecutive non-blank lines
     * 9. Process enabled {@see BlockRule} extensions in priority order
     * 10. Process callbacks registered in (7) or (9) in priority and token
     *     order
     * 11. Call enabled {@see Rule::beforeRender()} methods in priority order
     * 12. Render output
     * 13. Validate output (if `$fast` is `false`)
     *
     * @param string|null $eol The end-of-line sequence used in `$code`, if
     * known.
     * @param Indentation|null $indentation The indentation used in `$code`, if
     * known.
     * @param string|null $filename For reporting purposes only. No filesystem
     * operations are performed on `$filename`.
     */
    public function format(
        string $code,
        ?string $eol = null,
        ?Indentation $indentation = null,
        ?string $filename = null,
        bool $fast = false
    ): string {
        $errorLevel = error_reporting();
        if ($errorLevel & \E_COMPILE_WARNING) {
            error_reporting($errorLevel & ~\E_COMPILE_WARNING);
        }

        if (!$this->ExtensionsLoaded) {
            Profile::startTimer(__METHOD__ . '#load-extensions');
            try {
                $this->RuleMap = array_combine($this->Rules, $this->getExtensions($this->Rules));
                $this->FormatFilterList = $this->getExtensions($this->FormatFilters);
                $this->ComparisonFilterList = $this->getExtensions($this->ComparisonFilters);
                $this->ExtensionsLoaded = true;
            } finally {
                Profile::stopTimer(__METHOD__ . '#load-extensions');
            }
        }

        Profile::startTimer(__METHOD__ . '#reset');
        $this->reset();
        $this->resetExtensions();
        Profile::stopTimer(__METHOD__ . '#reset');

        Profile::startTimer(__METHOD__ . '#detect-eol');
        if ($eol === null || $eol === '') {
            $eol = Get::eol($code);
        }
        if ($eol !== null && $eol !== "\n") {
            $code = str_replace($eol, "\n", $code);
        }
        if ($eol === null || !$this->PreserveEol) {
            $eol = $this->PreferredEol;
        }
        Profile::stopTimer(__METHOD__ . '#detect-eol');

        Profile::startTimer(__METHOD__ . '#parse-input');
        try {
            $this->Filename = $filename;
            $this->Indentation = $indentation;
            $this->Tokens = $this->Parser->parse(
                $code,
                ...$this->FormatFilterList
            );

            if (!$this->Tokens) {
                if (!$this->Debug) {
                    unset($this->Tokens);
                }
                return '';
            }

            $last = end($this->Tokens);
            if (
                $last->Flags & TokenFlag::CODE
                && $last->Statement
                && $last->Statement->id !== \T_HALT_COMPILER
            ) {
                $last->WhitespaceAfter |= WhitespaceType::LINE;
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

        Profile::startTimer(__METHOD__ . '#index-tokens');
        foreach ($this->Tokens as $index => $token) {
            $this->TokenIndex[$token->id][$index] = $token;
        }
        Profile::stopTimer(__METHOD__ . '#index-tokens');

        Profile::startTimer(__METHOD__ . '#find-lists');
        // Get non-empty open brackets
        $listParents = $this->sortTokensByType([
            \T_OPEN_BRACKET => true,
            \T_OPEN_PARENTHESIS => true,
            \T_ATTRIBUTE => true,
            \T_EXTENDS => true,
            \T_IMPLEMENTS => true,
        ]);
        $lists = [];
        foreach ($listParents as $i => $parent) {
            if ($parent->ClosedBy === $parent->NextCode) {
                continue;
            }
            switch ($parent->id) {
                case \T_EXTENDS:
                case \T_IMPLEMENTS:
                    $items = $parent->nextSiblingsWhile($this->TokenTypeIndex->DeclarationList)
                                    ->filter(
                                        fn(Token $t, ?Token $next, ?Token $prev) =>
                                            !$prev || ($t->PrevCode && $t->PrevCode->id === \T_COMMA)
                                    );
                    $count = $items->count();
                    if ($count > 1) {
                        // @phpstan-ignore-next-line
                        $parent->Flags |= TokenFlag::LIST_PARENT;
                        $parent->Data[TokenData::LIST_ITEM_COUNT] = $count;
                        foreach ($items as $token) {
                            $token->Data[TokenData::LIST_PARENT] = $parent;
                        }
                        $lists[$i] = $items;
                    }
                    continue 2;

                case \T_OPEN_PARENTHESIS:
                    $prev = $parent->PrevCode;
                    if (!$prev) {
                        continue 2;
                    }
                    if ($prev->id === \T_CLOSE_BRACE
                            && !$prev->isStructuralBrace()) {
                        break;
                    }
                    if ($prev->PrevCode
                            && $prev->is(TokenType::AMPERSAND)
                            && $prev->PrevCode->is([\T_FN, \T_FUNCTION])) {
                        break;
                    }
                    if ($prev->is([
                        \T_ARRAY,
                        \T_DECLARE,
                        \T_FOR,
                        \T_ISSET,
                        \T_LIST,
                        \T_STATIC,
                        \T_UNSET,
                        \T_USE,
                        \T_VARIABLE,
                        ...TokenType::MAYBE_ANONYMOUS,
                        ...TokenType::DEREFERENCEABLE_SCALAR_END,
                        ...TokenType::NAME_WITH_READONLY,
                    ])) {
                        break;
                    }

                    continue 2;

                case \T_OPEN_BRACKET:
                    if ($parent->isArrayOpenBracket()) {
                        break;
                    }

                    if ($parent->children()->hasOneOf(\T_COMMA)) {
                        // This line should never be reached
                        break;
                    }

                    continue 2;
            }
            $delimiter = $parent->PrevCode && $parent->PrevCode->id === \T_FOR
                ? \T_SEMICOLON
                : \T_COMMA;
            $items = $parent->children()
                            ->filter(fn(Token $t, ?Token $next, ?Token $prev) =>
                                         $t->id !== $delimiter && (
                                             !$prev || (
                                                 $t->PrevCode
                                                 && $t->PrevCode->id === $delimiter
                                             )
                                         ));
            $count = $items->count();
            if (!$count) {
                continue;
            }
            // @phpstan-ignore-next-line
            $parent->Flags |= TokenFlag::LIST_PARENT;
            $parent->Data[TokenData::LIST_ITEM_COUNT] = $count;
            foreach ($items as $token) {
                $token->Data[TokenData::LIST_PARENT] = $parent;
            }
            $lists[$i] = $items;
        }
        Profile::stopTimer(__METHOD__ . '#find-lists');

        $logProgress = $this->LogProgress
            ? fn(string $rule, string $after) => $this->logProgress($rule, $after)
            : fn() => null;

        foreach ($this->MainLoop as [$_class, $method]) {
            /** @var TokenRule|ListRule */
            $rule = $this->RuleMap[$_class];
            $_rule = Get::basename($_class);
            Profile::startTimer($_rule, 'rule');

            if ($method === ListRule::PROCESS_LIST) {
                foreach ($lists as $i => $list) {
                    /** @var ListRule $rule */
                    $rule->processList($listParents[$i], clone $list);
                }
                Profile::stopTimer($_rule, 'rule');
                $logProgress($_rule, ListRule::PROCESS_LIST);
                continue;
            }

            /** @var TokenRule $rule */
            $types = $this->RuleTokenTypes[$_class];
            if ($types === []) {
                Profile::stopTimer($_rule, 'rule');
                continue;
            }
            if ($types === ['*']) {
                $tokens = $this->Tokens;
            } elseif ($rule->getRequiresSortedTokens()) {
                /** @var array<int,true> $types */
                $tokens = $this->sortTokensByType($types);
            } else {
                /** @var array<int,true> $types */
                $tokens = $this->getTokensByType($types);
            }
            if (!$tokens) {
                Profile::stopTimer($_rule, 'rule');
                continue;
            }
            $rule->processTokens($tokens);
            Profile::stopTimer($_rule, 'rule');
            $logProgress($_rule, TokenRule::PROCESS_TOKENS);
        }

        Profile::startTimer(__METHOD__ . '#find-blocks');

        /** @var array<TokenCollection[]> */
        $blocks = [];

        /** @var TokenCollection[] */
        $block = [];

        $line = new TokenCollection();

        /** @var Token */
        $token = reset($this->Tokens);

        while ($keep = true) {
            if ($token && $token->id !== \T_INLINE_HTML) {
                $before = $token->effectiveWhitespaceBefore();
                if ($before & WhitespaceType::BLANK) {
                    $endOfBlock = true;
                } elseif ($before & WhitespaceType::LINE) {
                    $endOfLine = true;
                }
            } else {
                $endOfBlock = true;
                $keep = false;
            }
            if ($endOfLine ?? $endOfBlock ?? false) {
                if ($line->count()) {
                    $block[] = $line;
                    $line = new TokenCollection();
                }
                unset($endOfLine);
            }
            if ($endOfBlock ?? false) {
                if ($block) {
                    $blocks[] = $block;
                }
                $block = [];
                unset($endOfBlock);
            }
            if (!$token) {
                break;
            }
            if ($keep) {
                $line[] = $token;
            }
            $token = $token->Next;
        }

        Profile::stopTimer(__METHOD__ . '#find-blocks');

        foreach ($this->BlockLoop as [$_class]) {
            /** @var BlockRule */
            $rule = $this->RuleMap[$_class];
            $_rule = Get::basename($_class);
            Profile::startTimer($_rule, 'rule');
            foreach ($blocks as $block) {
                $rule->processBlock($block);
            }
            Profile::stopTimer($_rule, 'rule');
            $logProgress($_rule, BlockRule::PROCESS_BLOCK);
        }

        if ($this->Callbacks) {
            ksort($this->Callbacks);
            foreach ($this->Callbacks as $priority => &$tokenCallbacks) {
                ksort($tokenCallbacks);
                foreach ($tokenCallbacks as $index => $callbacks) {
                    foreach ($callbacks as $i => [$rule, $callback]) {
                        $_rule = Get::basename($rule);
                        Profile::startTimer($_rule, 'rule');
                        $callback();
                        Profile::stopTimer($_rule, 'rule');
                        $logProgress($_rule, "{closure:$index:$i}");
                    }
                    unset($tokenCallbacks[$index]);
                }
                unset($this->Callbacks[$priority]);
            }
        }

        foreach ($this->BeforeRender as [$_class]) {
            $rule = $this->RuleMap[$_class];
            $_rule = Get::basename($_class);
            Profile::startTimer($_rule, 'rule');
            $rule->beforeRender($this->Tokens);
            Profile::stopTimer($_rule, 'rule');
            $logProgress($_rule, Rule::BEFORE_RENDER);
        }

        Profile::startTimer(__METHOD__ . '#render');
        try {
            /** @var Token */
            $first = reset($this->Tokens);
            $out = $this->Renderer->render($first, $last, false, true, true);
        } catch (Throwable $ex) {
            // @codeCoverageIgnoreStart
            throw new FormatterException(
                'Unable to render output',
                null,
                $this->Debug ? $this->Tokens : null,
                $this->Log,
                null,
                $ex
            );
            // @codeCoverageIgnoreEnd
        } finally {
            Profile::stopTimer(__METHOD__ . '#render');
            if (!$this->Debug) {
                unset($this->Tokens);
            }
        }

        if ($fast) {
            return $eol === "\n"
                ? $out
                : str_replace("\n", $eol, $out);
        }

        Profile::startTimer(__METHOD__ . '#parse-output');
        try {
            $after = Token::tokenizeForComparison(
                $out,
                \TOKEN_PARSE,
                ...$this->ComparisonFilterList
            );
        } catch (CompileError $ex) {
            // @codeCoverageIgnoreStart
            throw new FormatterException(
                'Unable to parse output',
                $out,
                $this->Tokens ?? null,
                $this->Log,
                null,
                $ex
            );
            // @codeCoverageIgnoreEnd
        } finally {
            Profile::stopTimer(__METHOD__ . '#parse-output');
        }

        $before = Token::tokenizeForComparison(
            $code,
            \TOKEN_PARSE,
            ...$this->ComparisonFilterList
        );

        $before = $this->simplifyTokens($before);
        $after = $this->simplifyTokens($after);
        if ($before !== $after) {
            // @codeCoverageIgnoreStart
            throw new FormatterException(
                "Parsed output doesn't match input",
                $out,
                $this->Tokens ?? null,
                $this->Log,
                ['before' => $before, 'after' => $after]
            );
            // @codeCoverageIgnoreEnd
        }

        return $eol === "\n"
            ? $out
            : str_replace("\n", $eol, $out);
    }

    /**
     * @param array<int,true> $types
     * @return array<int,Token>
     */
    private function sortTokensByType(array $types): array
    {
        $tokens = $this->getTokensByType($types);
        ksort($tokens, \SORT_NUMERIC);
        return $tokens;
    }

    /**
     * @param array<int,true> $types
     * @return array<int,Token>
     */
    private function getTokensByType(array $types): array
    {
        $tokens = array_intersect_key($this->TokenIndex, $types);
        if ($base = array_shift($tokens)) {
            return array_replace($base, ...$tokens);
        }
        return [];
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
            /** @var int|null */
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
     * @param GenericToken[] $tokens
     * @return array<array{int,string}>
     */
    private function simplifyTokens(array $tokens): array
    {
        $simple = [];
        foreach ($tokens as $token) {
            $simple[] = [$token->id, $token->text];
        }

        return $simple;
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
        $index = $first->Index;
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
        if (!$this->DetectProblems) {
            return;
        }

        $this->Problems[] = new Problem(
            $message,
            $this->Filename,
            $start,
            $end,
            ...$values,
        );
    }

    /**
     * @template T of Extension
     *
     * @param array<class-string<T>> $extensions
     * @return array<T>
     */
    private function getExtensions(array $extensions): array
    {
        foreach ($extensions as $ext) {
            $result[] = $this->Extensions[$ext] ??= $this->getExtension($ext);
        }
        /** @var array<T> */
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
        /** @var T&Extension */
        $ext = new $extension($this);
        $ext->boot();
        return $ext;
    }

    private function resetExtensions(): void
    {
        foreach ($this->Extensions as $ext) {
            $ext->reset();
        }
    }

    /**
     * @param array<class-string<Rule>>|null $rules
     * @param-out array<class-string<Rule>> $rules
     * @param array<class-string<Filter>>|null $filters
     * @param-out array<class-string<Filter>> $filters
     * @param array<class-string<Extension>> $enable
     * @param array<class-string<Extension>> $disable
     */
    private function resolveExtensions(?array &$rules, ?array &$filters, array $enable, array $disable): void
    {
        $rules = $this->getEnabled($enable, $disable, self::DEFAULT_RULES, self::OPTIONAL_RULES);
        $filters = $this->getEnabled($enable, $disable, self::DEFAULT_FILTERS, self::OPTIONAL_FILTERS);
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

    private function __clone()
    {
        $this->flush();
        $this->Parser = $this->Parser->withFormatter($this);
    }

    /**
     * Clear state that should not persist beyond a change to the formatter's
     * configuration
     */
    private function flush(): void
    {
        $this->reset();

        $this->Enabled = [];
        $this->Rules = [];
        $this->RuleTokenTypes = [];
        $this->MainLoop = [];
        $this->BlockLoop = [];
        $this->BeforeRender = [];
        $this->CallbackPriorities = [];
        $this->FormatFilters = [];
        $this->ComparisonFilters = [];
        $this->RuleMap = [];
        $this->FormatFilterList = [];
        $this->ComparisonFilterList = [];
        $this->Extensions = [];
        $this->ExtensionsLoaded = false;
    }

    /**
     * Clear state that should not persist beyond a formatting payload
     */
    private function reset(): void
    {
        $this->Filename = null;
        unset($this->Tokens);
        unset($this->TokenIndex);
        $this->Callbacks = null;
        $this->Problems = null;
        $this->Log = null;
    }

    private function logProgress(string $rule, string $after): void
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
        } catch (Throwable $ex) {
            throw new FormatterException(
                'Unable to render partially formatted output',
                null,
                $this->Tokens,
                $this->Log,
                $ex
            );
        } finally {
            Profile::stopTimer(__METHOD__ . '#render');
        }
        $this->Log[$rule . '-' . $after] = $out;
    }
}
