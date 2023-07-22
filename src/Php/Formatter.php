<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php;

use Lkrms\Facade\Console;
use Lkrms\Facade\Sys;
use Lkrms\Pretty\Php\Catalog\FormatterFlag;
use Lkrms\Pretty\Php\Catalog\ImportSortOrder;
use Lkrms\Pretty\Php\Catalog\TokenType;
use Lkrms\Pretty\Php\Catalog\WhitespaceType;
use Lkrms\Pretty\Php\Contract\BlockRule;
use Lkrms\Pretty\Php\Contract\Filter;
use Lkrms\Pretty\Php\Contract\ListRule;
use Lkrms\Pretty\Php\Contract\MultiTokenRule;
use Lkrms\Pretty\Php\Contract\Rule;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Filter\CollectColumn;
use Lkrms\Pretty\Php\Filter\NormaliseHeredocs;
use Lkrms\Pretty\Php\Filter\NormaliseStrings;
use Lkrms\Pretty\Php\Filter\RemoveComments;
use Lkrms\Pretty\Php\Filter\RemoveEmptyTokens;
use Lkrms\Pretty\Php\Filter\RemoveWhitespace;
use Lkrms\Pretty\Php\Filter\SortImports;
use Lkrms\Pretty\Php\Filter\TrimCasts;
use Lkrms\Pretty\Php\Filter\TrimOpenTags;
use Lkrms\Pretty\Php\Rule\AddBlankLineBeforeReturn;
use Lkrms\Pretty\Php\Rule\AddEssentialWhitespace;
use Lkrms\Pretty\Php\Rule\AddHangingIndentation;
use Lkrms\Pretty\Php\Rule\AddIndentation;
use Lkrms\Pretty\Php\Rule\AddStandardWhitespace;
use Lkrms\Pretty\Php\Rule\AlignArrowFunctions;
use Lkrms\Pretty\Php\Rule\AlignAssignments;
use Lkrms\Pretty\Php\Rule\AlignChainedCalls;
use Lkrms\Pretty\Php\Rule\AlignComments;
use Lkrms\Pretty\Php\Rule\AlignLists;
use Lkrms\Pretty\Php\Rule\AlignTernaryOperators;
use Lkrms\Pretty\Php\Rule\ApplyMagicComma;
use Lkrms\Pretty\Php\Rule\BracePosition;
use Lkrms\Pretty\Php\Rule\BreakAfterSeparators;
use Lkrms\Pretty\Php\Rule\BreakBeforeControlStructureBody;
use Lkrms\Pretty\Php\Rule\BreakLists;
use Lkrms\Pretty\Php\Rule\BreakOperators;
use Lkrms\Pretty\Php\Rule\Extra\DeclareArgumentsOnOneLine;
use Lkrms\Pretty\Php\Rule\Extra\Laravel;
use Lkrms\Pretty\Php\Rule\Extra\WordPress;
use Lkrms\Pretty\Php\Rule\MirrorBrackets;
use Lkrms\Pretty\Php\Rule\NoMixedLists;
use Lkrms\Pretty\Php\Rule\NormaliseComments;
use Lkrms\Pretty\Php\Rule\PlaceComments;
use Lkrms\Pretty\Php\Rule\PreserveNewlines;
use Lkrms\Pretty\Php\Rule\PreserveOneLineStatements;
use Lkrms\Pretty\Php\Rule\ProtectStrings;
use Lkrms\Pretty\Php\Rule\ReindentHeredocs;
use Lkrms\Pretty\Php\Rule\ReportUnnecessaryParentheses;
use Lkrms\Pretty\Php\Rule\SimplifyStrings;
use Lkrms\Pretty\Php\Rule\SpaceDeclarations;
use Lkrms\Pretty\Php\Rule\SpaceOperators;
use Lkrms\Pretty\Php\Rule\SwitchPosition;
use Lkrms\Pretty\Php\Support\TokenTypeIndex;
use Lkrms\Pretty\PrettyBadSyntaxException;
use Lkrms\Pretty\PrettyException;
use Lkrms\Utility\Convert;
use Lkrms\Utility\Env;
use Lkrms\Utility\Inspect;
use LogicException;
use ParseError;
use Throwable;

final class Formatter
{
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
     * Enable strict PSR-12 compliance?
     *
     */
    public bool $Psr12Compliance = false;

    public string $PreferredEol = PHP_EOL;

    public bool $PreserveEol = true;

    /**
     * Spaces between code and comments on the same line
     *
     */
    public int $SpacesBesideCode = 2;

    public bool $ClosuresAreDeclarations = true;

    public bool $MirrorBrackets = true;

    public bool $HangingHeredocIndents = true;

    public bool $HangingMatchIndents = true;

    public bool $IncreaseIndentBetweenUnenclosedTags = true;

    public bool $NewlineBeforeFnDoubleArrows = false;

    /**
     * If the first object operator in a chain of method calls has a leading
     * newline, align with the start of the chain?
     *
     * Only applies if {@see AlignChainedCalls} is enabled.
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
     */
    public bool $AlignFirstCallInChain = true;

    public bool $OneTrueBraceStyle = false;

    /**
     * @var ImportSortOrder::*
     */
    public int $ImportSortOrder = ImportSortOrder::NAME;

    /**
     * @var array<class-string<Rule>>
     */
    public const MANDATORY_RULES = [
        ProtectStrings::class,                   // processToken  (40)
        AddStandardWhitespace::class,            // processToken  (80), callback (820)
        BreakAfterSeparators::class,             // processToken  (80)
        SpaceOperators::class,                   // processToken  (80)
        BreakBeforeControlStructureBody::class,  // processToken  (83)
        PlaceComments::class,                    // processToken  (90), beforeRender (997)
        BracePosition::class,                    // processToken  (94), beforeRender (94)
        MirrorBrackets::class,                   // processToken  (96)
        BreakOperators::class,                   // processToken  (98)
        BreakLists::class,                       // processList   (98)
        AddIndentation::class,                   // processToken (600)
        SwitchPosition::class,                   // processToken (600)
        NormaliseComments::class,                // processToken (780)
        AddHangingIndentation::class,            // processToken (800), callback (800)
        AddEssentialWhitespace::class,           // beforeRender (999)
    ];

    /**
     * @var array<class-string<Rule>>
     */
    public const DEFAULT_RULES = [
        ...self::MANDATORY_RULES,
        SimplifyStrings::class,    // processToken  (60)
        PreserveNewlines::class,   // processToken  (93)
        ApplyMagicComma::class,    // processList  (360)
        SpaceDeclarations::class,  // processToken (620)
        ReindentHeredocs::class,   // processToken (900), beforeRender (900)
    ];

    /**
     * @var array<class-string<Rule>>
     */
    public const ADDITIONAL_RULES = [
        PreserveOneLineStatements::class,     // processToken  (95)
        AddBlankLineBeforeReturn::class,      // processToken  (97)
        Laravel::class,                       // processToken (100)
        WordPress::class,                     // processToken (100)
        AlignChainedCalls::class,             // processToken (340), callback (710)
        NoMixedLists::class,                  // processList  (370)
        AlignArrowFunctions::class,           // processToken (380), callback (710)
        AlignTernaryOperators::class,         // processToken (380), callback (710)
        AlignLists::class,                    // processList  (400), callback (710)
        ReportUnnecessaryParentheses::class,  // processToken (990)
        DeclareArgumentsOnOneLine::class,     // processToken
        AlignAssignments::class,              // processBlock (340), callback (720)
        AlignComments::class,                 // processBlock (340), beforeRender (998)
    ];

    /**
     * @var array<class-string<Filter>>
     */
    public const DEFAULT_FILTERS = [
        CollectColumn::class,
        RemoveWhitespace::class,
        NormaliseHeredocs::class,
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
        NormaliseStrings::class,
        RemoveComments::class,
        RemoveEmptyTokens::class,
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
     * @var Problem[]|null
     */
    public ?array $Problems = null;

    /**
     * @var array<string,string>|null
     */
    public ?array $Log = null;

    /**
     * @var Rule[]
     */
    private array $Rules;

    /**
     * @var array<class-string<TokenRule>,array{'*'}|array<int,true>>
     */
    private array $TokenRuleTypes;

    /**
     * @var Filter[]
     */
    private array $Filters;

    /**
     * @var Filter[]
     */
    private array $FormatFilters;

    /**
     * @var Filter[]
     */
    private array $ComparisonFilters;

    /**
     * [ [ Rule object, method name ], ... ]
     *
     * @var array<array{TokenRule|ListRule,string}>
     */
    private array $MainLoop;

    /**
     * [ [ Rule object, method name ], ... ]
     *
     * @var array<array{BlockRule,string}>
     */
    private array $BlockLoop;

    /**
     * [ [ Rule object, method name ], ... ]
     *
     * @var array<array{Rule,string}>
     */
    private array $BeforeRender;

    /**
     * @var array<int,array<int,array<array{Rule,callable}>>>
     */
    private array $Callbacks;

    private bool $Debug;

    private bool $LogProgress;

    private bool $ReportProblems;

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
        $this->Tab = $insertSpaces ? str_repeat(' ', $tabSize) : "\t";
        $this->TabSize = $tabSize;
        $this->SoftTab = str_repeat(' ', $tabSize);
        $this->TokenTypeIndex = $tokenTypeIndex ?: new TokenTypeIndex();

        $this->Debug = $flags & FormatterFlag::DEBUG || Env::debug();
        $this->LogProgress = $this->Debug && $flags & FormatterFlag::LOG_PROGRESS;
        $this->ReportProblems = (bool) ($flags & FormatterFlag::REPORT_PROBLEMS);

        // If using tabs for indentation, disable incompatible rules
        if (!$insertSpaces) {
            array_push(
                $skipRules,
                AlignArrowFunctions::class,
                AlignChainedCalls::class,
                AlignLists::class,
                AlignTernaryOperators::class
            );
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
            array_diff(
                $skipRules,
                self::MANDATORY_RULES
            )
        );
        if (count(array_intersect(
            [NoMixedLists::class, AlignLists::class],
            $rules
        )) === 2) {
            throw new LogicException(sprintf(
                '%s and %s cannot both be enabled',
                NoMixedLists::class,
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
            $rule = new $_rule($this);
            $this->Rules[] = $rule;
            if ($rule instanceof TokenRule) {
                $types = $rule->getTokenTypes();
                $first = null;
                if ($types && is_bool($first = reset($types))) {
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
                if ($rule instanceof MultiTokenRule) {
                    $mainLoop[] = [$rule, MultiTokenRule::PROCESS_TOKENS, $i];
                } else {
                    $mainLoop[] = [$rule, TokenRule::PROCESS_TOKEN, $i];
                }
            }
            if ($rule instanceof ListRule) {
                $mainLoop[] = [$rule, ListRule::PROCESS_LIST, $i];
            }
            if ($rule instanceof BlockRule) {
                $blockLoop[] = [$rule, BlockRule::PROCESS_BLOCK, $i];
            }
            $beforeRender[] = [$rule, Rule::BEFORE_RENDER, $i];
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
        $filters = array_combine(
            $filters,
            $this->FormatFilters = array_map(
                fn(string $filter) => new $filter($this),
                $filters
            )
        );
        // Column values are unnecessary when comparing tokens
        unset($filters[CollectColumn::class]);
        $this->ComparisonFilters = array_merge(
            array_values($filters),
            $comparisonFilters = array_map(
                fn(string $filter) => new $filter($this),
                self::COMPARISON_FILTERS
            )
        );
        $this->Filters = array_merge($this->FormatFilters, $comparisonFilters);
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
        $this->Tokens = null;
        $this->TokenIndex = null;
        $this->Log = null;
        $this->Callbacks = [];
        $this->Problems = [];
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
                ...$this->FormatFilters
            );

            if (!$this->Tokens) {
                return '';
            }
        } catch (ParseError $ex) {
            throw new PrettyBadSyntaxException(
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
                    if ($items->count() > 1) {
                        $lists[$i] = $items;
                    }
                    continue 2;

                case T_OPEN_PARENTHESIS:;
                    if (!($prev = $parent->_prevCode) ||
                        !(($prev->id === T_CLOSE_BRACE &&
                                !$prev->isStructuralBrace()) ||
                            ($prev->id === T_AMPERSAND &&
                                $prev->prevCode()->is([T_FN, T_FUNCTION])) ||
                            $prev->is([
                                T_ARRAY,
                                T_DECLARE,
                                T_LIST,
                                T_UNSET,
                                T_USE,
                                T_VARIABLE,
                                ...TokenType::MAYBE_ANONYMOUS,
                                ...TokenType::DEREFERENCEABLE_SCALAR_END,
                                ...TokenType::NAME_WITH_READONLY,
                            ]))) {
                        continue 2;
                    }
                    break;

                case T_OPEN_BRACKET:
                    if ($parent->Expression !== $parent &&
                            ($prev = $parent->_prevCode) &&
                            $prev->id !== T_AS &&
                            $prev->is([
                                T_CLOSE_BRACE,
                                T_STRING_VARNAME,
                                T_VARIABLE,
                                ...TokenType::DEREFERENCEABLE_SCALAR_END,
                                ...TokenType::NAME,
                                ...TokenType::SEMI_RESERVED,
                            ])) {
                        continue 2;
                    }
                    break;
            }
            $items =
                $parent->innerSiblings()
                       ->filter(fn(Token $t, ?Token $next, ?Token $prev) =>
                                    $t->id !== T_COMMA &&
                                        (!$prev || $t->_prevCode->id === T_COMMA));
            if (!$items->count()) {
                continue;
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
        /** @var array<TokenCollection[]> $blocks */
        $blocks = [];
        /** @var TokenCollection[] $block */
        $block = [];
        $line = new TokenCollection();
        /** @var Token $token */
        $token = reset($this->Tokens);
        $line[] = $token;

        while (!($token = $token->next())->IsNull) {
            $before = $token->effectiveWhitespaceBefore() & (WhitespaceType::BLANK | WhitespaceType::LINE);
            if (!$before) {
                $line[] = $token;
                continue;
            }
            if ($before === WhitespaceType::LINE) {
                $block[] = $line;
                $line = new TokenCollection();
                $line[] = $token;
                continue;
            }
            $block[] = $line;
            $blocks[] = $block;
            $block = [];
            $line = new TokenCollection();
            $line[] = $token;
        }
        $block[] = $line;
        $blocks[] = $block;
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
            $out = '';
            $current = reset($this->Tokens);
            do {
                $out .= $current->render(false, true);
            } while ($current = $current->_next);
        } catch (Throwable $ex) {
            throw new PrettyException(
                'Formatting failed: output cannot be rendered',
                $out,
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
                ...$this->ComparisonFilters
            );
        } catch (ParseError $ex) {
            throw new PrettyException(
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
            ...$this->ComparisonFilters
        );

        $before = $this->simplifyTokens($tokensIn);
        $after = $this->simplifyTokens($tokensOut);
        if ($before !== $after) {
            throw new PrettyException(
                "Formatting check failed: parsed output doesn't match input",
                $out,
                $this->Tokens,
                $this->Log,
                ['before' => $before, 'after' => $after]
            );
        }

        if ($this->ReportProblems && $this->Problems) {
            /** @var Problem $problem */
            foreach ($this->Problems as $problem) {
                $values = [];

                if ($filename) {
                    $values[] = $filename;
                    $values[] = $problem->Start->OutputLine;
                    Console::warn(sprintf($problem->Message . ': %s:%d', ...$problem->Values, ...$values));
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
        usort(
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
    public function reportProblem(Rule $rule, string $message, Token $start, ?Token $end = null, ...$values): void
    {
        $this->Problems[] = new Problem($rule, $message, $start, $end, ...$values);
    }

    private function logProgress(string $rule, string $after): void
    {
        Sys::startTimer(__METHOD__ . '#render');
        try {
            $out = '';
            $current = reset($this->Tokens);
            do {
                $out .= $current->render(false, false);
            } while ($current = $current->_next);
        } catch (Throwable $ex) {
            throw new PrettyException(
                'Formatting failed: unable to render unresolved output',
                $out,
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
