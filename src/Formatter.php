<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP;

use Lkrms\Concern\HasMutator;
use Lkrms\Concern\TReadable;
use Lkrms\Contract\IReadable;
use Lkrms\Facade\Console;
use Lkrms\Facade\Sys;
use Lkrms\PrettyPHP\Catalog\FormatterFlag;
use Lkrms\PrettyPHP\Catalog\HeredocIndent;
use Lkrms\PrettyPHP\Catalog\ImportSortOrder;
use Lkrms\PrettyPHP\Catalog\TokenType;
use Lkrms\PrettyPHP\Catalog\WhitespaceType;
use Lkrms\PrettyPHP\Exception\FormatterException;
use Lkrms\PrettyPHP\Exception\InvalidSyntaxException;
use Lkrms\PrettyPHP\Filter\Contract\Filter;
use Lkrms\PrettyPHP\Filter\CollectColumn;
use Lkrms\PrettyPHP\Filter\RemoveEmptyTokens;
use Lkrms\PrettyPHP\Filter\RemoveHeredocIndentation;
use Lkrms\PrettyPHP\Filter\RemoveWhitespace;
use Lkrms\PrettyPHP\Filter\SortImports;
use Lkrms\PrettyPHP\Filter\StandardiseStrings;
use Lkrms\PrettyPHP\Filter\TrimCasts;
use Lkrms\PrettyPHP\Filter\TrimOpenTags;
use Lkrms\PrettyPHP\Filter\TruncateComments;
use Lkrms\PrettyPHP\Rule\Contract\BlockRule;
use Lkrms\PrettyPHP\Rule\Contract\ListRule;
use Lkrms\PrettyPHP\Rule\Contract\MultiTokenRule;
use Lkrms\PrettyPHP\Rule\Contract\Rule;
use Lkrms\PrettyPHP\Rule\Contract\TokenRule;
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
use Lkrms\Utility\Convert;
use Lkrms\Utility\Env;
use Lkrms\Utility\Inspect;
use LogicException;
use ParseError;
use Throwable;

/**
 * Formats PHP code by applying filters and rules to tokens
 *
 * @property-read bool $Psr12Compliance Enforce strict PSR-12 compliance?
 */
final class Formatter implements IReadable
{
    use HasMutator {
        withPropertyValue as public with;
    }
    use TReadable;

    /**
     * The string used for indentation
     *
     * Usually `"    "` or `"\t"`.
     *
     * @readonly
     */
    public string $Tab;

    /**
     * The size of an indent, in spaces
     *
     * @readonly
     */
    public int $TabSize;

    /**
     * A series of spaces equivalent to an indent
     *
     * @readonly
     */
    public string $SoftTab;

    /**
     * Indexed token types
     *
     * @readonly
     */
    public TokenTypeIndex $TokenTypeIndex;

    /**
     * Enabled formatting rules
     *
     * @readonly
     * @var array<class-string<Rule>,true>
     */
    public array $EnabledRules;

    /**
     * False if calls to reportCodeProblem() are ignored
     *
     * @readonly
     */
    public bool $CollectCodeProblems;

    /**
     * False if the only line breaks that should be preserved are blank lines
     * between statements
     *
     * @readonly
     */
    public bool $PreserveLineBreaks = true;

    public string $PreferredEol = PHP_EOL;

    public bool $PreserveEol = true;

    /**
     * Spaces between code and comments on the same line
     */
    public int $SpacesBesideCode = 2;

    public bool $SymmetricalBrackets = true;

    /**
     * @var int&HeredocIndent::*
     */
    public int $HeredocIndent = HeredocIndent::MIXED;

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

    public bool $OneTrueBraceStyle = false;

    /**
     * @var ImportSortOrder::*
     */
    public int $ImportSortOrder = ImportSortOrder::DEPTH;

    /**
     * @var array<class-string<Rule>>
     */
    public const MANDATORY_RULES = [
        ProtectStrings::class,           // processToken  (40)
        StandardWhitespace::class,       // processToken  (80), callback (820)
        StatementSpacing::class,         // processToken  (80)
        OperatorSpacing::class,          // processToken  (80)
        ControlStructureSpacing::class,  // processToken  (83)
        PlaceComments::class,            // processToken  (90), beforeRender (997)
        PlaceBraces::class,              // processToken  (92), beforeRender (400)
        SymmetricalBrackets::class,      // processToken  (96)
        VerticalWhitespace::class,       // processToken  (98)
        ListSpacing::class,              // processList   (98)
        StandardIndentation::class,      // processToken (600)
        SwitchIndentation::class,        // processToken (600)
        NormaliseComments::class,        // processToken (780)
        HangingIndentation::class,       // processToken (800), callback (800)
        HeredocIndentation::class,       // processToken (900), beforeRender (900)
        EssentialWhitespace::class,      // beforeRender (999)
    ];

    /**
     * @var array<class-string<Rule>>
     */
    public const DEFAULT_RULES = [
        ...self::MANDATORY_RULES,
        NormaliseStrings::class,    // processToken  (60)
        PreserveLineBreaks::class,  // processToken  (93)
        DeclarationSpacing::class,  // processToken (620)
    ];

    /**
     * @var array<class-string<Rule>>
     */
    public const ADDITIONAL_RULES = [
        PreserveOneLineStatements::class,  // processToken  (95)
        BlankLineBeforeReturn::class,      // processToken  (97)
        StrictExpressions::class,          // processToken  (98)
        Drupal::class,                     // processToken (100)
        Laravel::class,                    // processToken (100)
        Symfony::class,                    // processToken (100), processList (100)
        WordPress::class,                  // processToken (100)
        AlignChains::class,                // processToken (340), callback (710)
        StrictLists::class,                // processList  (370)
        AlignArrowFunctions::class,        // processToken (380), callback (710)
        AlignTernaryOperators::class,      // processToken (380), callback (710)
        AlignLists::class,                 // processList  (400), callback (710)
        AlignData::class,                  // processBlock (340), callback (720)
        AlignComments::class,              // processBlock (340), beforeRender (998)
    ];

    /**
     * @var array<class-string<Filter>>
     */
    public const DEFAULT_FILTERS = [
        CollectColumn::class,
        RemoveWhitespace::class,
        RemoveHeredocIndentation::class,
        SortImports::class,
        TrimCasts::class,
    ];

    /**
     * @var array<class-string<Filter>>
     */
    public const OPTIONAL_FILTERS = [
        SortImports::class,
        TrimCasts::class,
    ];

    /**
     * @var array<class-string<Filter>>
     */
    public const COMPARISON_FILTERS = [
        StandardiseStrings::class,
        RemoveEmptyTokens::class,
        TruncateComments::class,
        TrimOpenTags::class,
    ];

    /**
     * @var Token[]|null
     */
    public ?array $Tokens = null;

    /**
     * @var array<int,array<int,Token>>|null
     */
    public ?array $TokenIndex = null;

    /**
     * @var CodeProblem[]|null
     */
    public ?array $CodeProblems = null;

    /**
     * @var array<string,string>|null
     */
    public ?array $Log = null;

    /**
     * Enforce strict PSR-12 compliance?
     */
    private bool $_Psr12Compliance = false;

    /**
     * @var array<string,Rule>
     */
    private array $Rules;

    /**
     * @var array<class-string<TokenRule>,array{'*'}|array<int,true>>
     */
    private array $TokenRuleTypes;

    /**
     * @var array<string,Filter>
     */
    private array $Filters;

    /**
     * @var array<string,Filter>
     */
    private array $FormatFilters;

    /**
     * @var array<string,Filter>
     */
    private array $ComparisonFilters;

    /**
     * @var array<int,Filter>
     */
    private array $FormatFilterList;

    /**
     * @var array<int,Filter>
     */
    private array $ComparisonFilterList;

    /**
     * [ [ Rule object, method name ], ... ]
     *
     * @var array<string,array{TokenRule|ListRule,string}>
     */
    private array $MainLoop;

    /**
     * [ [ Rule object, method name ], ... ]
     *
     * @var array<string,array{BlockRule,string}>
     */
    private array $BlockLoop;

    /**
     * [ [ Rule object, method name ], ... ]
     *
     * @var array<string,array{Rule,string}>
     */
    private array $BeforeRender;

    /**
     * @var array<int,array<int,array<array{Rule,callable}>>>
     */
    private array $Callbacks;

    private bool $Debug;

    private bool $LogProgress;

    private bool $ReportCodeProblems;

    protected function _getPsr12Compliance(): bool
    {
        return $this->_Psr12Compliance;
    }

    /**
     * Get a new Formatter object
     *
     * Rules are processed from lowest to highest priority (smallest to biggest
     * number).
     *
     * @param array<class-string<Rule>> $skipRules
     * @param array<class-string<Rule>> $addRules
     * @param array<class-string<Filter>> $skipFilters
     * @param int-mask-of<FormatterFlag::*> $flags
     */
    public function __construct(
        bool $insertSpaces = true,
        int $tabSize = 4,
        array $skipRules = [],
        array $addRules = [],
        array $skipFilters = [],
        int $flags = 0,
        ?TokenTypeIndex $tokenTypeIndex = null
    ) {
        $this->SoftTab = str_repeat(' ', $tabSize);
        $this->Tab = $insertSpaces ? $this->SoftTab : "\t";
        $this->TabSize = $tabSize;
        $this->TokenTypeIndex = $tokenTypeIndex ?? new TokenTypeIndex();

        $this->Debug = ($flags & FormatterFlag::DEBUG) || Env::debug();
        $this->LogProgress = $this->Debug && ($flags & FormatterFlag::LOG_PROGRESS);
        $this->ReportCodeProblems = (bool) ($flags & FormatterFlag::REPORT_CODE_PROBLEMS);
        $this->CollectCodeProblems = $this->ReportCodeProblems || ($flags & FormatterFlag::COLLECT_CODE_PROBLEMS);

        // If using tabs for indentation, disable incompatible rules
        if (!$insertSpaces) {
            array_push(
                $skipRules,
                AlignArrowFunctions::class,
                AlignChains::class,
                AlignLists::class,
                AlignTernaryOperators::class
            );
        }

        if (in_array(PreserveLineBreaks::class, $skipRules, true)) {
            $this->PreserveLineBreaks = false;
            $this->TokenTypeIndex = $this->TokenTypeIndex->withoutPreservingNewlines();
        }

        $rules = array_diff(
            array_merge(
                self::DEFAULT_RULES,
                array_intersect(
                    self::ADDITIONAL_RULES,
                    $addRules
                )
            ),
            // Remove mandatory rules from $skipRules
            array_diff($skipRules, [
                ...self::MANDATORY_RULES,
                PreserveLineBreaks::class,
            ])
        );
        if (count(array_intersect(
            [StrictLists::class, AlignLists::class],
            $rules
        )) === 2) {
            throw new LogicException(sprintf(
                '%s and %s cannot both be enabled',
                StrictLists::class,
                AlignLists::class
            ));
        }
        $this->EnabledRules = array_combine($rules, array_fill(0, count($rules), true));

        Sys::startTimer(__METHOD__ . '#sort-rules');
        $mainLoop = [];
        $blockLoop = [];
        $beforeRender = [];
        $i = 0;
        foreach ($rules as $_rule) {
            if (!is_a($_rule, Rule::class, true)) {
                throw new LogicException(sprintf('Not a %s: %s', Rule::class, $_rule));
            }
            /** @var Rule $rule */
            $rule = (new $_rule($this))->prepare();
            $this->Rules[$_rule] = $rule;
            if ($rule instanceof TokenRule) {
                $types = $rule->getTokenTypes();
                $first = $types
                    ? reset($types)
                    : null;
                if (is_bool($first)) {
                    // If an index is returned, reduce it to types with a value
                    // of `true`
                    $index = $types;
                    $types = [];
                    foreach ($index as $type => $value) {
                        if ($value) {
                            $types[$type] = true;
                        }
                    }
                } elseif (is_int($first)) {
                    // If a list is returned, convert it to an index
                    $types = array_combine(
                        $types,
                        array_fill(0, count($types), true),
                    );
                }
                $this->TokenRuleTypes[$_rule] = $types;
                $key = "$_rule:" . TokenRule::PROCESS_TOKEN;
                if ($rule instanceof MultiTokenRule) {
                    $mainLoop[$key] = [$rule, MultiTokenRule::PROCESS_TOKENS, $i];
                } else {
                    $mainLoop[$key] = [$rule, TokenRule::PROCESS_TOKEN, $i];
                }
            }
            if ($rule instanceof ListRule) {
                $key = "$_rule:" . ListRule::PROCESS_LIST;
                $mainLoop[$key] = [$rule, ListRule::PROCESS_LIST, $i];
            }
            if ($rule instanceof BlockRule) {
                $blockLoop[$_rule] = [$rule, BlockRule::PROCESS_BLOCK, $i];
            }
            $beforeRender[$_rule] = [$rule, Rule::BEFORE_RENDER, $i];
            $i++;
        }
        $this->MainLoop = $this->sortRules($mainLoop);
        $this->BlockLoop = $this->sortRules($blockLoop);
        $this->BeforeRender = $this->sortRules($beforeRender);
        Sys::stopTimer(__METHOD__ . '#sort-rules');

        $filters = array_diff(
            self::DEFAULT_FILTERS,
            array_intersect(
                self::OPTIONAL_FILTERS,
                $skipFilters
            )
        );

        $this->FormatFilters = array_combine(
            $filters,
            array_map(
                fn(string $filter) => (new $filter($this))->prepare(),
                $filters
            )
        );

        // Column values are unnecessary when comparing tokens
        $filters = $this->FormatFilters;
        unset($filters[CollectColumn::class]);

        $this->ComparisonFilters = array_merge(
            $filters,
            $comparisonFilters = array_combine(
                self::COMPARISON_FILTERS,
                array_map(
                    fn(string $filter) => (new $filter($this))->prepare(),
                    self::COMPARISON_FILTERS
                )
            )
        );

        $this->Filters = array_merge($this->FormatFilters, $comparisonFilters);
        $this->FormatFilterList = array_values($this->FormatFilters);
        $this->ComparisonFilterList = array_values($this->ComparisonFilters);
    }

    public function __clone()
    {
        foreach ($this->Rules as $_rule => &$rule) {
            $rule = clone $rule;
            $rule->setFormatter($this);
            if ($rule instanceof TokenRule &&
                    ($this->MainLoop[$key = "$_rule:" . TokenRule::PROCESS_TOKEN] ?? null)) {
                $this->MainLoop[$key][0] = $rule;
            }
            if ($rule instanceof ListRule &&
                    ($this->MainLoop[$key = "$_rule:" . ListRule::PROCESS_LIST] ?? null)) {
                $this->MainLoop[$key][0] = $rule;
            }
            if ($rule instanceof BlockRule &&
                    ($this->BlockLoop[$_rule] ?? null)) {
                $this->BlockLoop[$_rule][0] = $rule;
            }
            if ($this->BeforeRender[$_rule] ?? null) {
                $this->BeforeRender[$_rule][0] = $rule;
            }
        }

        foreach ($this->Filters as $_filter => &$filter) {
            $filter = clone $filter;
            $filter->setFormatter($this);
            if ($this->FormatFilters[$_filter] ?? null) {
                $this->FormatFilters[$_filter] = $filter;
            }
            if ($this->ComparisonFilters[$_filter] ?? null) {
                $this->ComparisonFilters[$_filter] = $filter;
            }
        }
        $this->FormatFilterList = array_values($this->FormatFilters);
        $this->ComparisonFilterList = array_values($this->ComparisonFilters);
    }

    /**
     * @return $this
     */
    public function withPsr12Compliance()
    {
        if ($this->_Psr12Compliance) {
            return $this;
        }

        /**
         * @todo: Check ruleset compliance
         */
        $clone = clone $this;
        $clone->Tab = '    ';
        $clone->TabSize = 4;
        $clone->SoftTab = '    ';
        $clone->PreferredEol = "\n";
        $clone->PreserveEol = false;
        $clone->HeredocIndent = HeredocIndent::HANGING;
        $clone->NewlineBeforeFnDoubleArrows = true;
        $clone->OneTrueBraceStyle = false;
        $clone->_Psr12Compliance = true;

        return $clone;
    }

    /**
     * True if a formatting rule is enabled
     *
     * @param class-string<Rule> $rule
     */
    public function ruleIsEnabled(string $rule): bool
    {
        return $this->EnabledRules[$rule] ?? false;
    }

    /**
     * Get formatted code
     *
     *  1. Call `reset()` on rules and filters
     *  2. Get end-of-line sequence and replace line breaks with `"\n"` if
     *     needed
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
     * @param string|null $filename For reporting purposes only. No file
     * operations are performed on `$filename`.
     */
    public function format(string $code, ?string $filename = null, bool $fast = false): string
    {
        $errorLevel = error_reporting();
        if ($errorLevel & E_COMPILE_WARNING) {
            error_reporting($errorLevel & ~E_COMPILE_WARNING);
        }

        $this->Tokens = null;
        $this->TokenIndex = null;
        $this->Log = null;
        $this->Callbacks = [];
        $this->CodeProblems = [];
        foreach ($this->Rules as $rule) {
            $rule->reset();
        }
        foreach ($this->Filters as $filter) {
            $filter->reset();
        }
        $this->SpacesBesideCode > 0 || $this->SpacesBesideCode = 1;

        $eol = Inspect::getEol($code);
        if ($eol && $eol !== "\n") {
            $code = str_replace($eol, "\n", $code);
        }
        $eol = $eol && $this->PreserveEol ? $eol : $this->PreferredEol;

        Sys::startTimer(__METHOD__ . '#tokenize-input');
        try {
            $this->Tokens = Token::tokenize(
                $code,
                TOKEN_PARSE,
                $this->TokenTypeIndex,
                ...$this->FormatFilterList
            );

            if (!$this->Tokens) {
                return '';
            }
        } catch (ParseError $ex) {
            throw new InvalidSyntaxException(
                sprintf('Formatting failed: %s cannot be parsed', $filename ?: 'input'),
                $ex
            );
        } finally {
            Sys::stopTimer(__METHOD__ . '#tokenize-input');
        }

        Sys::startTimer(__METHOD__ . '#prepare-tokens');
        try {
            $this->Tokens = Token::prepareTokens(
                $this->Tokens,
                $this
            );

            $last = end($this->Tokens);
            if (!$last) {
                return '';
            }

            if ($last->IsCode &&
                    ($last->Statement ?: $last)->id !== T_HALT_COMPILER) {
                $last->WhitespaceAfter |= WhitespaceType::LINE;
            }
        } finally {
            Sys::stopTimer(__METHOD__ . '#prepare-tokens');
        }

        Sys::startTimer(__METHOD__ . '#index-tokens');
        foreach ($this->Tokens as $index => $token) {
            $this->TokenIndex[$token->id][$index] = $token;
        }
        Sys::stopTimer(__METHOD__ . '#index-tokens');

        Sys::startTimer(__METHOD__ . '#find-lists');
        // Get non-empty open brackets
        $listParents = $this->sortTokens([
            T_OPEN_BRACKET => true,
            T_OPEN_PARENTHESIS => true,
            T_ATTRIBUTE => true,
            T_EXTENDS => true,
            T_IMPLEMENTS => true,
        ]);
        $lists = [];
        foreach ($listParents as $i => $parent) {
            if ($parent->ClosedBy === $parent->_nextCode) {
                continue;
            }
            switch ($parent->id) {
                case T_EXTENDS:
                case T_IMPLEMENTS:
                    $items =
                        $parent->nextSiblingsWhile(...TokenType::DECLARATION_LIST)
                               ->filter(fn(Token $t, ?Token $next, ?Token $prev) =>
                                            !$prev || $t->_prevCode->id === T_COMMA);
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

                case T_OPEN_PARENTHESIS:
                    $prev = $parent->_prevCode;
                    if (!$prev) {
                        continue 2;
                    }
                    if ($prev->id === T_CLOSE_BRACE &&
                            !$prev->isStructuralBrace(false)) {
                        break;
                    }
                    if ($prev->_prevCode &&
                            $prev->is(TokenType::AMPERSAND) &&
                            $prev->_prevCode->is([T_FN, T_FUNCTION])) {
                        break;
                    }
                    if ($prev->is([
                        T_ARRAY,
                        T_DECLARE,
                        T_FOR,
                        T_ISSET,
                        T_LIST,
                        T_STATIC,
                        T_UNSET,
                        T_USE,
                        T_VARIABLE,
                        ...TokenType::MAYBE_ANONYMOUS,
                        ...TokenType::DEREFERENCEABLE_SCALAR_END,
                        ...TokenType::NAME_WITH_READONLY,
                    ])) {
                        break;
                    }

                    continue 2;

                case T_OPEN_BRACKET:
                    if ($parent->Expression === $parent) {
                        break;
                    }
                    $prev = $parent->_prevCode;
                    if ($prev && (
                        $prev->is([
                            T_CLOSE_BRACE,
                            T_STRING_VARNAME,
                            T_VARIABLE,
                            ...TokenType::DEREFERENCEABLE_SCALAR_END,
                            ...TokenType::NAME,
                            ...TokenType::MAGIC_CONSTANT,
                        ]) || (
                            $prev->_prevCode &&
                            $prev->_prevCode->id === T_DOUBLE_COLON &&
                            $prev->is(TokenType::SEMI_RESERVED)
                        )
                        // This check should never be necessary
                    ) && !$parent->children()->hasOneOf(T_COMMA)) {
                        continue 2;
                    }

                    break;
            }
            $delimiter = $parent->_prevCode && $parent->_prevCode->id === T_FOR
                ? T_SEMICOLON
                : T_COMMA;
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
        Sys::stopTimer(__METHOD__ . '#find-lists');

        foreach ($this->MainLoop as [$rule, $method]) {
            $_rule = Convert::classToBasename($_class = get_class($rule));
            Sys::startTimer($_rule, 'rule');

            if ($method === ListRule::PROCESS_LIST) {
                foreach ($lists as $i => $list) {
                    /** @var ListRule $rule */
                    $rule->processList($listParents[$i], clone $list);
                }
                Sys::stopTimer($_rule, 'rule');
                !$this->LogProgress || $this->logProgress($_rule, ListRule::PROCESS_LIST);
                continue;
            }

            /** @var TokenRule $rule */
            $types = $this->TokenRuleTypes[$_class];
            if ($types === []) {
                Sys::stopTimer($_rule, 'rule');
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
                Sys::stopTimer($_rule, 'rule');
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
            Sys::stopTimer($_rule, 'rule');
            !$this->LogProgress || $this->logProgress($_rule, TokenRule::PROCESS_TOKEN);
        }

        Sys::startTimer(__METHOD__ . '#find-blocks');

        /** @var array<TokenCollection[]> */
        $blocks = [];

        /** @var TokenCollection[] */
        $block = [];

        $line = new TokenCollection();

        /** @var Token */
        $token = reset($this->Tokens);

        while ($keep = true) {
            if ($token && $token->id !== T_INLINE_HTML) {
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

        Sys::stopTimer(__METHOD__ . '#find-blocks');

        /** @var BlockRule $rule */
        foreach ($this->BlockLoop as [$rule]) {
            $_rule = Convert::classToBasename(get_class($rule));
            Sys::startTimer($_rule, 'rule');
            foreach ($blocks as $block) {
                $rule->processBlock($block);
            }
            Sys::stopTimer($_rule, 'rule');
            !$this->LogProgress || $this->logProgress($_rule, BlockRule::PROCESS_BLOCK);
        }

        $this->processCallbacks();

        /** @var Rule $rule */
        foreach ($this->BeforeRender as [$rule]) {
            $_rule = Convert::classToBasename(get_class($rule));
            Sys::startTimer($_rule, 'rule');
            $rule->beforeRender($this->Tokens);
            Sys::stopTimer($_rule, 'rule');
            !$this->LogProgress || $this->logProgress($_rule, Rule::BEFORE_RENDER);
        }

        Sys::startTimer(__METHOD__ . '#render');
        try {
            $first = reset($this->Tokens);
            $last = end($this->Tokens);
            $out = $first->render(false, $last, true);
        } catch (Throwable $ex) {
            throw new FormatterException(
                'Formatting failed: output cannot be rendered',
                null,
                $this->Tokens,
                $this->Log,
                null,
                $ex
            );
        } finally {
            Sys::stopTimer(__METHOD__ . '#render');
        }

        if ($fast) {
            return $eol === "\n"
                ? $out
                : str_replace("\n", $eol, $out);
        }

        Sys::startTimer(__METHOD__ . '#parse-output');
        try {
            $tokensOut = Token::onlyTokenize(
                $out,
                TOKEN_PARSE,
                ...$this->ComparisonFilterList
            );
        } catch (ParseError $ex) {
            throw new FormatterException(
                'Formatting check failed: output cannot be parsed',
                $out,
                $this->Tokens,
                $this->Log,
                null,
                $ex
            );
        } finally {
            Sys::stopTimer(__METHOD__ . '#parse-output');
        }

        $tokensIn = Token::onlyTokenize(
            $code,
            TOKEN_PARSE,
            ...$this->ComparisonFilterList
        );

        $before = $this->simplifyTokens($tokensIn);
        $after = $this->simplifyTokens($tokensOut);
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

                if ($filename) {
                    $values[] = $filename;
                    $values[] = $problem->Start->OutputLine;
                    $values[] = $problem->Start->OutputColumn;
                    Console::warn(sprintf($problem->Message . ': %s:%d:%d', ...$problem->Values, ...$values));
                    continue;
                }

                $values[] = Convert::pluralRange(
                    $problem->Start->OutputLine,
                    $problem->End->OutputLine ?? $problem->Start->OutputLine,
                    'line'
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
        ksort($tokens, SORT_NUMERIC);
        return $tokens;
    }

    /**
     * Sort rules by priority
     *
     * @template TRule of Rule
     * @param array<array{TRule,string,int}> $rules
     * @return array<array{TRule,string}>
     */
    private function sortRules(array $rules): array
    {
        /**
         * @var Rule $rule
         * @var string $method
         */
        foreach ($rules as $key => [$rule, $method]) {
            $priority = $rule->getPriority($method);
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
                ($a[3] <=> $b[3]) ?: $a[2] <=> $b[2]
        );

        return array_map(
            fn(array $rule) => [$rule[0], $rule[1]],
            $rules
        );
    }

    /**
     * @param Token[] $tokens
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

    public function registerCallback(Rule $rule, Token $first, callable $callback, int $priority = 100, bool $reverse = false): void
    {
        $this->Callbacks[$priority][($reverse ? -1 : 1) * $first->Index][] = [$rule, $callback];
    }

    private function processCallbacks(): void
    {
        ksort($this->Callbacks);
        foreach ($this->Callbacks as $priority => &$tokenCallbacks) {
            ksort($tokenCallbacks);
            foreach ($tokenCallbacks as $index => $callbacks) {
                foreach ($callbacks as $i => [$rule, $callback]) {
                    $_rule = Convert::classToBasename(get_class($rule));
                    Sys::startTimer($_rule, 'rule');
                    $callback();
                    Sys::stopTimer($_rule, 'rule');
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

    private function logProgress(string $rule, string $after): void
    {
        Sys::startTimer(__METHOD__ . '#render');
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
            Sys::stopTimer(__METHOD__ . '#render');
        }
        $this->Log[$rule . '-' . $after] = $out;
    }
}
