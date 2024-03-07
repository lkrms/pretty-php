<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP;

use Lkrms\PrettyPHP\Catalog\FormatterFlag;
use Lkrms\PrettyPHP\Catalog\HeredocIndent;
use Lkrms\PrettyPHP\Catalog\ImportSortOrder;
use Lkrms\PrettyPHP\Catalog\TokenType;
use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Contract\BlockRule;
use Lkrms\PrettyPHP\Contract\Extension;
use Lkrms\PrettyPHP\Contract\Filter;
use Lkrms\PrettyPHP\Contract\ListRule;
use Lkrms\PrettyPHP\Contract\MultiTokenRule;
use Lkrms\PrettyPHP\Contract\Rule;
use Lkrms\PrettyPHP\Contract\TokenRule;
use Lkrms\PrettyPHP\Exception\FormatterException;
use Lkrms\PrettyPHP\Exception\IncompatibleRulesException;
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
use Lkrms\PrettyPHP\Rule\BlankLineBeforeReturn;
use Lkrms\PrettyPHP\Rule\ControlStructureSpacing;
use Lkrms\PrettyPHP\Rule\DeclarationSpacing;
use Lkrms\PrettyPHP\Rule\EssentialWhitespace;
use Lkrms\PrettyPHP\Rule\HangingIndentation;
use Lkrms\PrettyPHP\Rule\HeredocIndentation;
use Lkrms\PrettyPHP\Rule\ListSpacing;
use Lkrms\PrettyPHP\Rule\NormaliseComments;
use Lkrms\PrettyPHP\Rule\NormaliseNumbers;
use Lkrms\PrettyPHP\Rule\NormaliseStrings;
use Lkrms\PrettyPHP\Rule\OperatorSpacing;
use Lkrms\PrettyPHP\Rule\PlaceBraces;
use Lkrms\PrettyPHP\Rule\PlaceComments;
use Lkrms\PrettyPHP\Rule\PreserveLineBreaks;
use Lkrms\PrettyPHP\Rule\PreserveOneLineStatements;
use Lkrms\PrettyPHP\Rule\ProtectStrings;
use Lkrms\PrettyPHP\Rule\StandardIndentation;
use Lkrms\PrettyPHP\Rule\StandardWhitespace;
use Lkrms\PrettyPHP\Rule\StatementSpacing;
use Lkrms\PrettyPHP\Rule\StrictExpressions;
use Lkrms\PrettyPHP\Rule\StrictLists;
use Lkrms\PrettyPHP\Rule\SwitchIndentation;
use Lkrms\PrettyPHP\Rule\SymmetricalBrackets;
use Lkrms\PrettyPHP\Rule\VerticalWhitespace;
use Lkrms\PrettyPHP\Support\CodeProblem;
use Lkrms\PrettyPHP\Support\TokenCollection;
use Lkrms\PrettyPHP\Support\TokenTypeIndex;
use Lkrms\PrettyPHP\Token\Token;
use Salient\Contract\Core\Buildable;
use Salient\Core\Concern\HasBuilder;
use Salient\Core\Concern\HasImmutableProperties;
use Salient\Core\Exception\InvalidArgumentException;
use Salient\Core\Facade\Console;
use Salient\Core\Facade\Profile;
use Salient\Core\Utility\Arr;
use Salient\Core\Utility\Env;
use Salient\Core\Utility\Get;
use Salient\Core\Utility\Inflect;
use Salient\Core\Utility\Str;
use CompileError;
use LogicException;
use PhpToken;
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
     * A series of spaces equivalent to an indent
     *
     * @readonly
     */
    public string $SoftTab;

    /**
     * The string used for indentation
     *
     * @readonly
     * @var ("  "|"    "|"        "|"	")
     */
    public string $Tab;

    /**
     * Indexed token types
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
     * @var HeredocIndent::*
     */
    public int $HeredocIndent;

    /**
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
     * Enforce strict PSR-12 / PER Coding Style compliance?
     */
    public bool $Psr12;

    /**
     * False if calls to reportCodeProblem() are ignored
     *
     * @readonly
     */
    public bool $CollectCodeProblems;

    /**
     * False if line breaks are only preserved between statements
     *
     * When the {@see PreserveLineBreaks} rule is disabled, `false` is assigned
     * to this property and the rule is reinstated to preserve blank lines
     * between statements.
     *
     * @readonly
     */
    public bool $PreserveLineBreaks;

    /**
     * An index of enabled extensions
     *
     * @readonly
     * @var array<class-string<Extension>,true>
     */
    public array $Enabled;

    // --

    public bool $IncreaseIndentBetweenUnenclosedTags = true;

    public bool $RelaxAlignmentCriteria = false;

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
     */
    public bool $AlignFirstCallInChain = true;

    // --

    /**
     * @var array<class-string<Rule>>
     */
    public const DEFAULT_RULES = [
        ProtectStrings::class,
        NormaliseNumbers::class,
        NormaliseStrings::class,
        NormaliseComments::class,
        StandardWhitespace::class,
        StatementSpacing::class,
        OperatorSpacing::class,
        ControlStructureSpacing::class,
        PlaceComments::class,
        PlaceBraces::class,
        PreserveLineBreaks::class,
        SymmetricalBrackets::class,
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
        NormaliseNumbers::class,
        NormaliseStrings::class,
        PreserveLineBreaks::class,
        PreserveOneLineStatements::class,
        BlankLineBeforeReturn::class,
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

    /**
     * @var array<class-string<Rule>>
     */
    private array $PreferredRules;

    /**
     * @var array<class-string<Filter>>
     */
    private array $PreferredFilters;

    // --

    /**
     * @var array<class-string<Rule>>
     */
    private array $Rules;

    /**
     * @var array<class-string<TokenRule>,array<int,true>|array{'*'}>
     */
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

    /**
     * @var array<class-string<Rule>,int>
     */
    private array $CallbackPriorities;

    /**
     * @var array<class-string<Filter>>
     */
    private array $FormatFilters;

    /**
     * @var array<class-string<Filter>>
     */
    private array $ComparisonFilters;

    /**
     * @var array<class-string<Rule>,Rule>
     */
    private array $RuleMap;

    /**
     * @var Filter[]
     */
    private array $FormatFilterList;

    /**
     * @var Filter[]
     */
    private array $ComparisonFilterList;

    /**
     * @var array<class-string<Extension>,Extension>
     */
    private array $Extensions;

    private bool $ExtensionsLoaded = false;

    // --

    /**
     * @var array<int,Token>|null
     */
    public ?array $Tokens = null;

    /**
     * @var array<int,array<int,Token>>|null
     */
    private ?array $TokenIndex = null;

    /**
     * [ priority => [ token index => [ [ rule, callback ], ... ] ] ]
     *
     * @var array<int,array<int,array<array{class-string<Rule>,callable}>>>|null
     */
    private ?array $Callbacks = null;

    /**
     * @var CodeProblem[]|null
     */
    public ?array $CodeProblems = null;

    /**
     * @var array<string,string>|null
     */
    public ?array $Log = null;

    // --

    private bool $Debug;

    private bool $LogProgress;

    private bool $ReportCodeProblems;

    /**
     * Creates a new Formatter object
     *
     * Rules are processed from lowest to highest priority (smallest to biggest
     * number).
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
        $this->TokenTypeIndex = $tokenTypeIndex ?: new TokenTypeIndex();
        $this->PreferredEol = $preferredEol;
        $this->PreserveEol = $preserveEol;
        $this->SpacesBesideCode = $spacesBesideCode;
        $this->HeredocIndent = $heredocIndent;
        $this->ImportSortOrder = $importSortOrder;
        $this->OneTrueBraceStyle = $oneTrueBraceStyle;
        $this->Psr12 = $psr12;

        $this->Debug = ($flags & FormatterFlag::DEBUG) || Env::debug();
        $this->LogProgress = $this->Debug && ($flags & FormatterFlag::LOG_PROGRESS);
        $this->ReportCodeProblems = (bool) ($flags & FormatterFlag::REPORT_CODE_PROBLEMS);
        $this->CollectCodeProblems = $this->ReportCodeProblems || ($flags & FormatterFlag::COLLECT_CODE_PROBLEMS);

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
            $this->NewlineBeforeFnDoubleArrows = true;
            $this->OneTrueBraceStyle = false;

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

        $this->SoftTab = str_repeat(' ', $this->TabSize);
        $this->Tab = $this->InsertSpaces ? $this->SoftTab : "\t";

        if ($this->SpacesBesideCode < 1) {
            $this->SpacesBesideCode = 1;
        }

        // If using tabs for indentation, disable incompatible rules
        if (!$this->InsertSpaces) {
            $rules = array_diff($rules, self::NO_TAB_RULES);
        }

        // Enable `PreserveLineBreaks` if disabled, but limit its scope to blank
        // lines between statements
        if (!in_array(PreserveLineBreaks::class, $rules, true)) {
            $this->PreserveLineBreaks = false;
            $this->TokenTypeIndex = $this->TokenTypeIndex->withoutPreserveNewline();
            $rules[] = PreserveLineBreaks::class;
        } else {
            $this->PreserveLineBreaks = true;
            $this->TokenTypeIndex = $this->TokenTypeIndex->withPreserveNewline();
        }

        foreach (self::INCOMPATIBLE_RULES as $incompatible) {
            $incompatible = array_intersect($incompatible, $rules);
            if (count($incompatible) > 1) {
                throw new IncompatibleRulesException(...$incompatible);
            }
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
                $first = $types ? reset($types) : null;
                if (is_bool($first)) {
                    // Reduce an index to entries with a value of `true`
                    $index = $types;
                    $types = [];
                    foreach ($index as $type => $value) {
                        if ($value) {
                            $types[$type] = true;
                        }
                    }
                } elseif (is_int($first)) {
                    // Convert a list of types to an index
                    /** @var int[] */
                    $list = $types;
                    $types = Arr::toIndex($list);
                }
                $tokenTypes[$rule] = $types;

                if (is_a($rule, MultiTokenRule::class, true)) {
                    $mainLoop[$rule . '#token'] = [$rule, MultiTokenRule::PROCESS_TOKENS, $i];
                } else {
                    $mainLoop[$rule . '#token'] = [$rule, TokenRule::PROCESS_TOKEN, $i];
                }
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

        return $this;
    }

    /**
     * Get an instance with a value applied to a given property
     *
     * @internal
     *
     * @param mixed $value
     * @return static
     */
    public function with(string $property, $value): self
    {
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
     * Get an instance with strict PSR-12 / PER Coding Style compliance enabled
     *
     * @return static
     */
    public function withPsr12()
    {
        return $this->withPropertyValue('Psr12', true)
                    ->apply();
    }

    /**
     * @internal
     *
     * @return static
     */
    public function withDebug()
    {
        return $this->withPropertyValue('Debug', true)
                    ->apply();
    }

    /**
     * Get formatted code
     *
     *  1. Call `reset()` on the formatter, filters and rules
     *  2. Detect end-of-line sequence if not given and replace line breaks with
     *     `"\n"` if needed
     *  3. `tokenize()` input and apply formatting filters
     *  4. `prepareTokens()` for formatting
     *  5. Find lists, i.e. comma-delimited items between non-empty square or
     *     round brackets, and interface names after `extends` or `implements`
     *  6. Process enabled {@see TokenRule} and {@see ListRule} rules in one
     *     loop, ordered by priority
     *  7. Find blocks, i.e. groups of tokens representing two or more
     *     consecutive non-blank lines
     *  8. Process enabled {@see BlockRule} rules in priority order
     *  9. Process registered callbacks in priority and token order
     * 10. Process rules that implement `beforeRender()` in priority order
     * 11. Render and validate output
     *
     * @param string|null $eol The end-of-line sequence used in `$code`, if
     * known.
     * @param string|null $filename For reporting purposes only. No file
     * operations are performed on `$filename`.
     */
    public function format(
        string $code,
        ?string $eol = null,
        ?string $filename = null,
        bool $fast = false
    ): string {
        $errorLevel = error_reporting();
        if ($errorLevel & \E_COMPILE_WARNING) {
            error_reporting($errorLevel & ~\E_COMPILE_WARNING);
        }

        Profile::startTimer(__METHOD__ . '#reset');

        if (!$this->ExtensionsLoaded) {
            $this->RuleMap = array_combine($this->Rules, $this->getExtensions($this->Rules));
            $this->FormatFilterList = $this->getExtensions($this->FormatFilters);
            $this->ComparisonFilterList = $this->getExtensions($this->ComparisonFilters);
            $this->ExtensionsLoaded = true;
        }

        $this->reset();
        $this->resetExtensions();

        Profile::stopTimer(__METHOD__ . '#reset');

        Profile::startTimer(__METHOD__ . '#detect-eol');
        $eol = Str::coalesce($eol, null);
        $eol ??= Get::eol($code);
        if ((string) $eol !== '' && $eol !== "\n") {
            $code = str_replace($eol, "\n", $code);
        }
        $eol = (string) $eol !== '' && $this->PreserveEol
            ? $eol
            : $this->PreferredEol;
        Profile::stopTimer(__METHOD__ . '#detect-eol');

        Profile::startTimer(__METHOD__ . '#tokenize-input');
        try {
            $this->Tokens = Token::tokenize(
                $code, \TOKEN_PARSE, $this, ...$this->FormatFilterList
            );

            if (!$this->Tokens) {
                return '';
            }
        } catch (CompileError $ex) {
            throw new InvalidSyntaxException(
                sprintf(
                    'Formatting failed: %s cannot be parsed',
                    Str::coalesce($filename, 'input')
                ),
                $ex
            );
        } finally {
            Profile::stopTimer(__METHOD__ . '#tokenize-input');
        }

        $last = end($this->Tokens);
        if ($last->IsCode && $last->Statement->id !== \T_HALT_COMPILER) {
            $last->WhitespaceAfter |= WhitespaceType::LINE;
        }

        Profile::startTimer(__METHOD__ . '#index-tokens');
        foreach ($this->Tokens as $index => $token) {
            $this->TokenIndex[$token->id][$index] = $token;
        }
        Profile::stopTimer(__METHOD__ . '#index-tokens');

        Profile::startTimer(__METHOD__ . '#find-lists');
        // Get non-empty open brackets
        $listParents = $this->sortTokens([
            \T_OPEN_BRACKET => true,
            \T_OPEN_PARENTHESIS => true,
            \T_ATTRIBUTE => true,
            \T_EXTENDS => true,
            \T_IMPLEMENTS => true,
        ]);
        $lists = [];
        foreach ($listParents as $i => $parent) {
            if ($parent->ClosedBy === $parent->_nextCode) {
                continue;
            }
            switch ($parent->id) {
                case \T_EXTENDS:
                case \T_IMPLEMENTS:
                    $items =
                        $parent->nextSiblingsWhile(...TokenType::DECLARATION_LIST)
                               ->filter(fn(Token $t, ?Token $next, ?Token $prev) =>
                                            !$prev || $t->_prevCode->id === \T_COMMA);
                    $count = $items->count();
                    if ($count > 1) {
                        $parent->IsListParent = true;
                        $parent->ListItemCount = $count;
                        foreach ($items as $token) {
                            $token->ListParent = $parent;
                        }
                        $lists[$i] = $items;
                    }
                    continue 2;

                case \T_OPEN_PARENTHESIS:
                    $prev = $parent->_prevCode;
                    if (!$prev) {
                        continue 2;
                    }
                    if ($prev->id === \T_CLOSE_BRACE &&
                            !$prev->isStructuralBrace(false)) {
                        break;
                    }
                    if ($prev->_prevCode &&
                            $prev->is(TokenType::AMPERSAND) &&
                            $prev->_prevCode->is([\T_FN, \T_FUNCTION])) {
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
                    if ($parent->Expression === $parent) {
                        break;
                    }
                    $prev = $parent->_prevCode;
                    if ($prev && (
                        $prev->is([
                            \T_CLOSE_BRACE,
                            \T_STRING_VARNAME,
                            \T_VARIABLE,
                            ...TokenType::DEREFERENCEABLE_SCALAR_END,
                            ...TokenType::NAME,
                            ...TokenType::MAGIC_CONSTANT,
                        ]) || (
                            $prev->_prevCode &&
                            $prev->_prevCode->id === \T_DOUBLE_COLON &&
                            $prev->is(TokenType::SEMI_RESERVED)
                        )
                        // This check should never be necessary
                    ) && !$parent->children()->hasOneOf(\T_COMMA)) {
                        continue 2;
                    }

                    break;
            }
            $delimiter = $parent->_prevCode && $parent->_prevCode->id === \T_FOR
                ? \T_SEMICOLON
                : \T_COMMA;
            $items =
                $parent->children()
                       ->filter(fn(Token $t, ?Token $next, ?Token $prev) =>
                                    $t->id !== $delimiter &&
                                        (!$prev || $t->_prevCode->id === $delimiter));
            $count = $items->count();
            if (!$count) {
                continue;
            }
            $parent->IsListParent = true;
            $parent->ListItemCount = $count;
            foreach ($items as $token) {
                $token->ListParent = $parent;
            }
            $lists[$i] = $items;
        }
        Profile::stopTimer(__METHOD__ . '#find-lists');

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
                !$this->LogProgress || $this->logProgress($_rule, ListRule::PROCESS_LIST);
                continue;
            }

            $types = $this->RuleTokenTypes[$_class];
            if ($types === []) {
                Profile::stopTimer($_rule, 'rule');
                continue;
            }
            if ($types === ['*']) {
                $tokens = $this->Tokens;
            } elseif ($rule->getRequiresSortedTokens()) {
                $tokens = $this->sortTokens($types);
            } else {
                $tokens = $this->getTokens($types);
            }
            if (!$tokens) {
                Profile::stopTimer($_rule, 'rule');
                continue;
            }
            if ($rule instanceof MultiTokenRule) {
                $rule->processTokens($tokens);
            } else {
                /** @var Token $token */
                foreach ($tokens as $token) {
                    $rule->processToken($token);
                }
            }
            Profile::stopTimer($_rule, 'rule');
            !$this->LogProgress || $this->logProgress($_rule, TokenRule::PROCESS_TOKEN);
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
            $token = $token->_next;
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
            !$this->LogProgress || $this->logProgress($_rule, BlockRule::PROCESS_BLOCK);
        }

        $this->processCallbacks();

        foreach ($this->BeforeRender as [$_class]) {
            $rule = $this->RuleMap[$_class];
            $_rule = Get::basename($_class);
            Profile::startTimer($_rule, 'rule');
            $rule->beforeRender($this->Tokens);
            Profile::stopTimer($_rule, 'rule');
            !$this->LogProgress || $this->logProgress($_rule, Rule::BEFORE_RENDER);
        }

        Profile::startTimer(__METHOD__ . '#render');
        try {
            $first = reset($this->Tokens);
            $out = $first->render(false, $last, true);
        } catch (Throwable $ex) {
            throw new FormatterException(
                'Formatting failed: output cannot be rendered',
                null,
                $this->Debug ? $this->Tokens : null,
                $this->Log,
                null,
                $ex
            );
        } finally {
            Profile::stopTimer(__METHOD__ . '#render');
            if (!$this->Debug) {
                $this->Tokens = null;
            }
        }

        if ($fast) {
            return $eol === "\n"
                ? $out
                : str_replace("\n", $eol, $out);
        }

        Profile::startTimer(__METHOD__ . '#parse-output');
        try {
            $after = Token::onlyTokenize(
                $out,
                \TOKEN_PARSE,
                PhpToken::class,
                ...$this->ComparisonFilterList
            );
        } catch (CompileError $ex) {
            throw new FormatterException(
                'Formatting check failed: output cannot be parsed',
                $out,
                $this->Tokens,
                $this->Log,
                null,
                $ex
            );
        } finally {
            Profile::stopTimer(__METHOD__ . '#parse-output');
        }

        $before = Token::onlyTokenize(
            $code,
            \TOKEN_PARSE,
            PhpToken::class,
            ...$this->ComparisonFilterList
        );

        $before = $this->simplifyTokens($before);
        $after = $this->simplifyTokens($after);
        if ($before !== $after) {
            throw new FormatterException(
                "Formatting check failed: parsed output doesn't match input",
                $out,
                $this->Tokens,
                $this->Log,
                ['before' => $before, 'after' => $after]
            );
        }

        if ($this->ReportCodeProblems && $this->CodeProblems) {
            /** @var CodeProblem $problem */
            foreach ($this->CodeProblems as $problem) {
                $values = [];

                if ((string) $filename !== '') {
                    $values[] = $filename;
                    $values[] = $problem->Start->OutputLine;
                    $values[] = $problem->Start->OutputColumn;
                    Console::warn(sprintf($problem->Message . ': %s:%d:%d', ...$problem->Values, ...$values));
                    continue;
                }

                $values[] = Inflect::formatRange(
                    $problem->Start->OutputLine,
                    $problem->End->OutputLine ?? $problem->Start->OutputLine,
                    '{{#:on:between}} {{#:line}} {{#:#:and}}',
                );
                Console::warn(sprintf($problem->Message . ' %s', ...$problem->Values, ...$values));
            }
        }

        return $eol === "\n"
            ? $out
            : str_replace("\n", $eol, $out);
    }

    /**
     * @param array<int,true> $types
     * @return array<int,Token>
     */
    private function getTokens(array $types): array
    {
        $tokens = array_intersect_key($this->TokenIndex ?: [], $types);
        if ($base = array_shift($tokens)) {
            return array_replace($base, ...$tokens);
        }
        return [];
    }

    /**
     * @param array<int,true> $types
     * @return array<int,Token>
     */
    private function sortTokens(array $types): array
    {
        $tokens = $this->getTokens($types);
        ksort($tokens, \SORT_NUMERIC);
        return $tokens;
    }

    /**
     * Sort rules by priority
     *
     * @template TRule of Rule
     *
     * @param array<string,array{class-string<TRule>,string,int}> $rules
     * @return array<string,array{class-string<TRule>,string}>
     */
    private function sortRules(array $rules): array
    {
        foreach ($rules as $key => [$rule, $method]) {
            /** @var int|null */
            $priority = $rule::getPriority($method);
            if ($priority === null) {
                unset($rules[$key]);
                continue;
            }
            $rules[$key][3] = $priority;
        }

        // Sort by priority, then index
        uasort(
            $rules,
            fn(array $a, array $b) =>
                $a[3] <=> $b[3]
                    ?: $a[2] <=> $b[2]
        );

        foreach ($rules as $key => [$rule, $method]) {
            $result[$key] = [$rule, $method];
        }

        return $result ?? [];
    }

    /**
     * @param PhpToken[] $tokens
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
        callable $callback,
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

    private function processCallbacks(): void
    {
        if (!$this->Callbacks) {
            return;
        }
        ksort($this->Callbacks);
        foreach ($this->Callbacks as $priority => &$tokenCallbacks) {
            ksort($tokenCallbacks);
            foreach ($tokenCallbacks as $index => $callbacks) {
                foreach ($callbacks as $i => [$rule, $callback]) {
                    $_rule = Get::basename($rule);
                    Profile::startTimer($_rule, 'rule');
                    $callback();
                    Profile::stopTimer($_rule, 'rule');
                    !$this->LogProgress || $this->logProgress($_rule, "{closure:$index:$i}");
                }
                unset($tokenCallbacks[$index]);
            }
            unset($this->Callbacks[$priority]);
        }
    }

    /**
     * @param string $message e.g. `"Unnecessary parentheses"`
     * @param mixed ...$values
     */
    public function reportCodeProblem(Rule $rule, string $message, Token $start, ?Token $end = null, ...$values): void
    {
        if (!$this->CollectCodeProblems) {
            return;
        }
        $this->CodeProblems[] = new CodeProblem($rule, $message, $start, $end, ...$values);
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
            $result[] = $this->Extensions[$ext] ??= new $ext($this);
        }
        return $result ?? [];
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
        $this->Tokens = null;
        $this->TokenIndex = null;
        $this->Callbacks = null;
        $this->CodeProblems = null;
        $this->Log = null;
    }

    private function logProgress(string $rule, string $after): void
    {
        Profile::startTimer(__METHOD__ . '#render');
        try {
            $first = reset($this->Tokens);
            $last = end($this->Tokens);
            $out = $first->render(false, $last);
        } catch (Throwable $ex) {
            throw new FormatterException(
                'Formatting failed: unable to render unresolved output',
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
