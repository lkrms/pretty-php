<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php;

use Lkrms\Concern\TFullyReadable;
use Lkrms\Concern\TWritable;
use Lkrms\Contract\IReadable;
use Lkrms\Contract\IWritable;
use Lkrms\Facade\Console;
use Lkrms\Facade\Convert;
use Lkrms\Facade\Env;
use Lkrms\Facade\Sys;
use Lkrms\Pretty\Php\Contract\BlockRule;
use Lkrms\Pretty\Php\Contract\Filter;
use Lkrms\Pretty\Php\Contract\ListRule;
use Lkrms\Pretty\Php\Contract\Rule;
use Lkrms\Pretty\Php\Contract\TokenRule;
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
use Lkrms\Pretty\Php\Rule\MirrorBrackets;
use Lkrms\Pretty\Php\Rule\NoMixedLists;
use Lkrms\Pretty\Php\Rule\PlaceComments;
use Lkrms\Pretty\Php\Rule\PreserveNewlines;
use Lkrms\Pretty\Php\Rule\PreserveOneLineStatements;
use Lkrms\Pretty\Php\Rule\ProtectStrings;
use Lkrms\Pretty\Php\Rule\ReindentHeredocs;
use Lkrms\Pretty\Php\Rule\ReportUnnecessaryParentheses;
use Lkrms\Pretty\Php\Rule\SimplifyStrings;
use Lkrms\Pretty\Php\Rule\SpaceDeclarations;
use Lkrms\Pretty\Php\Rule\SpaceMatch;
use Lkrms\Pretty\Php\Rule\SpaceOperators;
use Lkrms\Pretty\Php\Rule\SwitchPosition;
use Lkrms\Pretty\PrettyBadSyntaxException;
use Lkrms\Pretty\PrettyException;
use Lkrms\Pretty\WhitespaceType;
use LogicException;
use ParseError;
use Throwable;

use const Lkrms\Pretty\Php\T_ID_MAP as T;

/**
 * @property-read bool $Debug
 * @property-read int $QuietLevel
 * @property-read string|null $Filename
 * @property-read string|null $RunningService
 * @property-read array<string,string> $Log
 * @property int[] $PreserveTrailingSpaces
 */
final class Formatter implements IReadable, IWritable
{
    use TFullyReadable, TWritable;

    /**
     * @var bool
     */
    protected $Debug;

    /**
     * @var int
     */
    protected $QuietLevel;

    /**
     * @var string|null
     */
    protected $Filename;

    /**
     * @var string|null
     */
    protected $RunningService;

    /**
     * @var array<string,string>
     */
    protected $Log = [];

    /**
     * @var int[]
     */
    protected $PreserveTrailingSpaces = [];

    /**
     * @var string
     */
    public $PreserveTrailingSpacesRegex = '';

    /**
     * @var string
     */
    public $Tab;

    /**
     * @var int
     */
    public $TabSize;

    /**
     * @var string
     */
    public $SoftTab;

    /**
     * @var array<class-string<Rule>,true>
     */
    public $EnabledRules;

    /**
     * @var string
     */
    public $PreferredEol = PHP_EOL;

    /**
     * @var bool
     */
    public $PreserveEol = true;

    /**
     * @var bool
     */
    public $ClosuresAreDeclarations = true;

    /**
     * @var bool
     */
    public $MatchesAreLists = false;

    /**
     * @var bool
     */
    public $MirrorBrackets = true;

    /**
     * @var bool
     */
    public $HangingHeredocIndents = true;

    /**
     * @var bool
     */
    public $HangingMatchIndents = true;

    /**
     * If the first object operator in a chain of method calls has a leading
     * newline, align with the start of the chain?
     *
     * ```php
     * // If `false`:
     * $result = $object
     *     ->method1();
     * // If `true`:
     * $result = $object
     *               ->method1();
     * ```
     *
     * @var bool
     */
    public $AlignFirstCallInChain = false;

    /**
     * @var bool
     */
    public $OneTrueBraceStyle = false;

    /**
     * @var bool
     */
    public $LogProgress = false;

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
        AddHangingIndentation::class,            // processToken (800), callback (800)
        AddEssentialWhitespace::class,           // beforeRender (999)
    ];

    /**
     * @var array<class-string<Rule>>
     */
    public const DEFAULT_RULES = [
        ...self::MANDATORY_RULES,
        SimplifyStrings::class,               // processToken  (60)  [OPTIONAL]
        PreserveNewlines::class,              // processToken  (93)  [OPTIONAL]
        SpaceMatch::class,                    // processToken (300)  [OPTIONAL]
        ApplyMagicComma::class,               // processList  (360)  [OPTIONAL]
        SpaceDeclarations::class,             // processToken (620)  [OPTIONAL]
        ReindentHeredocs::class,              // processToken (900), beforeRender (900)  [OPTIONAL]
        ReportUnnecessaryParentheses::class,  // processToken (990)  [OPTIONAL]
    ];

    /**
     * @var array<class-string<Rule>>
     */
    public const ADDITIONAL_RULES = [
        PreserveOneLineStatements::class,  // processToken  (95)
        AddBlankLineBeforeReturn::class,   // processToken  (97)
        Laravel::class,                    // processToken (100)
        AlignAssignments::class,           // processBlock (340), callback (710)
        AlignChainedCalls::class,          // processToken (340), callback (710)
        AlignComments::class,              // processBlock (340), beforeRender (998)
        NoMixedLists::class,               // processList  (370)
        AlignArrowFunctions::class,        // processToken (380), callback (710)
        AlignTernaryOperators::class,      // processToken (380), callback (710)
        AlignLists::class,                 // processList  (400), callback (710)
        DeclareArgumentsOnOneLine::class,  // processToken
    ];

    /**
     * @var Token[]|null
     */
    public $Tokens;

    /**
     * @var Filter[]
     */
    private $FormatFilters;

    /**
     * @var Filter[]
     */
    private $ComparisonFilters;

    /**
     * [ [ Rule object, method name ], ... ]
     *
     * @var array<array{0:TokenRule|ListRule,1:string}>
     */
    private $MainLoop = [];

    /**
     * [ [ Rule object, method name ], ... ]
     *
     * @var array<array{0:BlockRule,1:string}>
     */
    private $BlockLoop = [];

    /**
     * [ [ Rule object, method name ], ... ]
     *
     * @var array<array{0:Rule,1:string}>
     */
    private $BeforeRender = [];

    /**
     * @var Rule[]
     */
    private $Rules = [];

    /**
     * @var array<int,array<int,array<array{0:Rule,1:callable}>>>
     */
    private $Callbacks = [];

    /**
     * @param array<class-string<Rule>> $skipRules
     * @param array<class-string<Rule>> $addRules
     * @param array<class-string<Filter>> $skipFilters
     */
    public function __construct(
        bool $insertSpaces = true,
        int $tabSize = 4,
        array $skipRules = [],
        array $addRules = [],
        array $skipFilters = []
    ) {
        $this->Tab = $insertSpaces ? str_repeat(' ', $tabSize) : "\t";
        $this->TabSize = $tabSize;
        $this->SoftTab = str_repeat(' ', $tabSize);

        // If using tabs for indentation, disable incompatible rules
        if (!$insertSpaces) {
            array_push(
                $skipRules,
                AlignArrowFunctions::class,
                AlignChainedCalls::class,
                AlignLists::class,
                AlignTernaryOperators::class,
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
        $this->EnabledRules = array_combine($rules, array_fill(0, count($rules), true));

        Sys::startTimer(__METHOD__ . '#sort-rules');
        $mainLoop = [];
        $blockLoop = [];
        $beforeRender = [];
        $index = 0;
        foreach ($rules as $_rule) {
            if (!is_a($_rule, Rule::class, true)) {
                throw new LogicException(sprintf('Not a %s: %s', Rule::class, $_rule));
            }
            /** @var Rule $rule */
            $rule = new $_rule($this);
            $this->Rules[] = $rule;
            if ($rule instanceof TokenRule) {
                $mainLoop[] = [$rule, TokenRule::PROCESS_TOKEN, $index];
            }
            if ($rule instanceof ListRule) {
                $mainLoop[] = [$rule, ListRule::PROCESS_LIST, $index];
            }
            if ($rule instanceof BlockRule) {
                $blockLoop[] = [$rule, BlockRule::PROCESS_BLOCK, $index];
            }
            $beforeRender[] = [$rule, Rule::BEFORE_RENDER, $index];
            $index++;
        }
        $this->MainLoop = $this->sortRules($mainLoop);
        $this->BlockLoop = $this->sortRules($blockLoop);
        $this->BeforeRender = $this->sortRules($beforeRender);
        Sys::stopTimer(__METHOD__ . '#sort-rules');

        $mandatoryFilters = [
            RemoveWhitespace::class,
            NormaliseHeredocs::class,
        ];
        $optionalFilters = [
            TrimCasts::class,
            SortImports::class,
        ];
        $comparisonFilters = [
            NormaliseStrings::class,
            RemoveComments::class,
            RemoveEmptyTokens::class,
            TrimOpenTags::class,
        ];

        $this->FormatFilters = array_map(
            fn(string $filter) => new $filter(),
            array_merge(
                $mandatoryFilters,
                array_diff(
                    $optionalFilters,
                    $skipFilters
                )
            )
        );

        $this->ComparisonFilters = array_merge(
            $this->FormatFilters,
            array_map(
                fn(string $filter) => new $filter(),
                $comparisonFilters
            )
        );

        $this->Debug = Env::debug();
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
     * Rules are processed from lowest to highest priority (smallest to biggest
     * number).
     *
     * @param string|null $filename For reporting purposes only. No file
     * operations are performed on `$filename`.
     */
    public function format(string $code, int $quietLevel = 0, ?string $filename = null, bool $fast = false): string
    {
        foreach ($this->Rules as $rule) {
            $rule->reset();
        }
        foreach ($this->ComparisonFilters as $filter) {
            $filter->reset();
        }
        $this->QuietLevel = $quietLevel;
        $this->Filename = $filename;

        $eol = $this->getEol($code);
        if ($eol !== "\n") {
            $code = str_replace($eol, "\n", $code);
        }
        $eol = $this->PreserveEol ? $eol : $this->PreferredEol;

        Sys::startTimer(__METHOD__ . '#tokenize-input');
        try {
            $this->Tokens = Token::tokenize(
                $code,
                TOKEN_PARSE,
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

            if ($last->IsCode && $last->startOfStatement()->id !== T_HALT_COMPILER) {
                $last->WhitespaceAfter |= WhitespaceType::LINE;
            }
        } finally {
            Sys::stopTimer(__METHOD__ . '#prepare-tokens');
        }

        Sys::startTimer(__METHOD__ . '#find-lists');
        // Get non-empty open brackets
        $listParents =
            array_filter(
                $this->Tokens,
                fn(Token $t) =>
                    ($t->is([T['('], T['[']]) ||
                            ($this->MatchesAreLists && $t->id === T['{'] && $t->prevSibling(2)->id === T_MATCH) ||
                            $t->is([T_IMPLEMENTS]) ||
                            ($t->is([T_EXTENDS]) && $t->isDeclaration(T_INTERFACE))) &&
                        $t->ClosedBy !== $t->nextCode()
            );
        $lists = [];
        foreach ($listParents as $i => $parent) {
            if ($parent->is([T_IMPLEMENTS, T_EXTENDS])) {
                $items = $parent->nextSiblingsWhile(
                    ...TokenType::DECLARATION_LIST
                )->filter(
                    fn(Token $t, ?Token $next, ?Token $prev) =>
                        !$prev || $t->prevCode()->id === T[',']
                );
                if ($items->count() > 1) {
                    $lists[$i] = $items;
                }
                continue;
            }
            $lists[$i] = $parent->innerSiblings()->filter(
                fn(Token $t, ?Token $next, ?Token $prev) =>
                    !$prev || (($prevCode = $t->prevCode())->id === T[','] &&
                        ($parent->id !== T['{'] ||
                            $prevCode->prevSiblingOf(T[','], ...TokenType::OPERATOR_DOUBLE_ARROW)
                                     ->is(TokenType::OPERATOR_DOUBLE_ARROW)))
            );
        }
        Sys::stopTimer(__METHOD__ . '#find-lists');

        foreach ($this->MainLoop as [$rule, $method]) {
            $this->RunningService = $_rule = Convert::classToBasename(get_class($rule));
            Sys::startTimer($_rule, 'rule');

            if ($method === ListRule::PROCESS_LIST) {
                foreach ($lists as $i => $list) {
                    $list = clone $list;
                    /** @var ListRule $rule */
                    $rule->processList($listParents[$i], $list);
                }
                Sys::stopTimer($_rule, 'rule');
                !$this->Debug || !$this->LogProgress ||
                    $this->logProgress(ListRule::PROCESS_LIST);
                continue;
            }

            // Prepare to filter the tokens as efficiently as possible
            /** @var TokenRule $rule */
            $types = $rule->getTokenTypes();
            if ($types === []) {
                continue;
            }
            $types = $types !== ['*'] ? TokenType::getIndex(...$types) : null;

            /** @var Token $token */
            foreach ($this->Tokens as $token) {
                if (!$types || ($types[$token->id] ?? false) !== false) {
                    $rule->processToken($token);
                }
            }
            Sys::stopTimer($_rule, 'rule');
            !$this->Debug || !$this->LogProgress ||
                $this->logProgress(TokenRule::PROCESS_TOKEN);
        }
        $this->RunningService = null;

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
            $this->RunningService = $_rule = Convert::classToBasename(get_class($rule));
            Sys::startTimer($_rule, 'rule');
            foreach ($blocks as $block) {
                $rule->processBlock($block);
            }
            Sys::stopTimer($_rule, 'rule');
            !$this->Debug || !$this->LogProgress ||
                $this->logProgress(BlockRule::PROCESS_BLOCK);
        }
        $this->RunningService = null;

        $this->processCallbacks();

        /** @var Rule $rule */
        foreach ($this->BeforeRender as [$rule]) {
            $this->RunningService = $_rule = Convert::classToBasename(get_class($rule));
            Sys::startTimer($_rule, 'rule');
            $rule->beforeRender($this->Tokens);
            Sys::stopTimer($_rule, 'rule');
            !$this->Debug || !$this->LogProgress ||
                $this->logProgress(Rule::BEFORE_RENDER);
        }
        $this->RunningService = null;

        Sys::startTimer(__METHOD__ . '#render');
        try {
            $out = '';
            $current = reset($this->Tokens);
            do {
                $out .= $current->render(false, $current);
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

        return $eol === "\n"
            ? $out
            : str_replace("\n", $eol, $out);
    }

    /**
     * @param int[] $spaces
     */
    protected function _setPreserveTrailingSpaces(array $spaces): void
    {
        if (!($this->PreserveTrailingSpaces = $spaces)) {
            $this->PreserveTrailingSpacesRegex = '';

            return;
        }

        $seen = [];
        $regex = [];
        foreach ($spaces as $count) {
            if ($seen[$count] ?? false) {
                continue;
            }
            $seen[$count] = true;
            $regex[] = str_repeat(' ', $count);
        }
        $this->PreserveTrailingSpacesRegex =
            sprintf('(?<!%s)', '\S' . implode('|\S', $regex));
    }

    private function getEol(string $string): string
    {
        $lfPos = strpos($string, "\n");
        if ($lfPos === false) {
            return strpos($string, "\r") === false
                ? $this->PreferredEol
                : "\r";
        }
        if ($lfPos && $string[$lfPos - 1] === "\r") {
            return "\r\n";
        }
        if (($string[$lfPos + 1] ?? null) === "\r") {
            return "\n\r";
        }

        return "\n";
    }

    /**
     * Sort rules by priority
     *
     * @template TRule of Rule
     * @param array<array{0:TRule,1:string,2:int}> $rules
     * @return array<array{0:TRule,1:string}>
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
     * @return array<array{0:int,1:string}>
     */
    private function simplifyTokens(array $tokens): array
    {
        $tokens = array_map(
            fn(Token $t) => [$t->id, $t->text],
            array_values($tokens)
        );

        return $tokens;
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
                foreach ($callbacks as [$rule, $callback]) {
                    $this->RunningService = $_rule = Convert::classToBasename(get_class($rule));
                    Sys::startTimer($_rule, 'rule');
                    $callback();
                    Sys::stopTimer($_rule, 'rule');
                    !$this->Debug || !$this->LogProgress ||
                        $this->logProgress('{closure}');
                }
                unset($tokenCallbacks[$index]);
            }
            unset($this->Callbacks[$priority]);
        }
        $this->RunningService = null;
    }

    /**
     * @param string $message e.g. `"Unnecessary parentheses"`
     * @param mixed ...$values
     */
    public function reportProblem(string $message, Token $start, ?Token $end = null, ...$values): void
    {
        if ($this->QuietLevel > 1) {
            return;
        }
        if ($this->Filename) {
            $values[] = $this->Filename;
            $values[] = $start->line;
            Console::warn(sprintf($message . ': %s:%d', ...$values));

            return;
        }

        $values[] = Convert::pluralRange(
            $start->line,
            $end ? $end->line : $start->line,
            'line'
        );
        Console::warn(sprintf($message . ' %s', ...$values));
    }

    private function logProgress(string $after): void
    {
        Sys::startTimer(__METHOD__ . '#render');
        try {
            $out = '';
            $current = reset($this->Tokens);
            do {
                $out .= $current->render(false, $current);
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
        $this->Log[$this->RunningService . '-' . $after] = $out;
    }
}
