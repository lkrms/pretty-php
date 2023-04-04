<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php;

use Lkrms\Concern\TFullyReadable;
use Lkrms\Contract\IReadable;
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
use Lkrms\Pretty\Php\Rule\BreakOperators;
use Lkrms\Pretty\Php\Rule\Extra\AddSpaceAfterFn;
use Lkrms\Pretty\Php\Rule\Extra\AddSpaceAfterNot;
use Lkrms\Pretty\Php\Rule\Extra\DeclareArgumentsOnOneLine;
use Lkrms\Pretty\Php\Rule\Extra\SuppressSpaceAroundStringOperator;
use Lkrms\Pretty\Php\Rule\NoMixedLists;
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
use Lkrms\Pretty\PrettyBadSyntaxException;
use Lkrms\Pretty\PrettyException;
use Lkrms\Pretty\WhitespaceType;
use ParseError;
use RuntimeException;
use Throwable;

use const Lkrms\Pretty\Php\T_ID_MAP as T;

/**
 * @property-read bool $Debug
 * @property-read int $QuietLevel
 * @property-read string|null $Filename
 * @property-read string|null $RunningService
 * @property-read array<string,string> $Log
 * @property-read string[] $Rules
 */
final class Formatter implements IReadable
{
    use TFullyReadable;

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
     * @var bool
     */
    public $ClosuresAreDeclarations = true;

    /**
     * @var bool
     */
    public $MirrorBrackets = true;

    /**
     * @var bool
     */
    public $HangingHeredocIndents = true;

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
     * Only align method chains that start at the beginning of a statement?
     *
     * ```php
     * // If `false`:
     * $result = $object->method1()
     *                  ->method2();
     * $object->action1()
     *        ->action2();
     * // If `true`:
     * $result = $object->method1()
     *     ->method2();
     * $object->action1()
     *        ->action2();
     * ```
     *
     * @var bool
     */
    public $OnlyAlignChainedStatements = false;

    /**
     * @var bool
     */
    public $OneTrueBraceStyle = false;

    /**
     * @var string[]
     */
    protected $Rules = [
        ProtectStrings::class,                   // processToken  (40)
        SimplifyStrings::class,                  // processToken  (60)                      [OPTIONAL]
        AddStandardWhitespace::class,            // processToken  (80)
        BreakAfterSeparators::class,             // processToken  (80)
        SpaceOperators::class,                   // processToken  (80)
        BracePosition::class,                    // processToken  (80), beforeRender (80)
        BreakBeforeControlStructureBody::class,  // processToken  (83)
        PlaceComments::class,                    // processToken  (90), beforeRender (997)
        PreserveNewlines::class,                 // processToken  (93)                      [OPTIONAL]
        BreakOperators::class,                   // processToken  (98)
        ApplyMagicComma::class,                  // processList  (360)                      [OPTIONAL]
        AddIndentation::class,                   // processToken (600)
        SwitchPosition::class,                   // processToken (600)
        SpaceDeclarations::class,                // processToken (620)                      [OPTIONAL]
        AddHangingIndentation::class,            // processToken (800), callback (800)
        ReindentHeredocs::class,                 // processToken (900), beforeRender (900)  [OPTIONAL]
        ReportUnnecessaryParentheses::class,     // processToken (990)                      [OPTIONAL]
        AddEssentialWhitespace::class,           // beforeRender (999)
    ];

    /**
     * @var string[]
     */
    protected $AvailableRules = [
        PreserveOneLineStatements::class,          // processToken  (95)
        AddBlankLineBeforeReturn::class,           // processToken  (97)
        AlignAssignments::class,                   // processBlock (340), callback (710)
        AlignChainedCalls::class,                  // processToken (340), callback (710)
        AlignComments::class,                      // processBlock (340), beforeRender (998)
        NoMixedLists::class,                       // processList  (370)
        AlignArrowFunctions::class,                // processToken (380), callback (710)
        AlignTernaryOperators::class,              // processToken (380), callback (710)
        AlignLists::class,                         // processList  (400), callback (710)
        AddSpaceAfterFn::class,                    // processToken
        AddSpaceAfterNot::class,                   // processToken
        DeclareArgumentsOnOneLine::class,          // processToken
        SuppressSpaceAroundStringOperator::class,  // processToken
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
     * @var array<int,array<int,array<array{0:Rule,1:callable}>>>
     */
    private $Callbacks = [];

    /**
     * @param string[] $skipRules
     * @param string[] $addRules
     * @param string[] $skipFilters
     */
    public function __construct(bool $insertSpaces = true, int $tabSize = 4, array $skipRules = [], array $addRules = [], array $skipFilters = [])
    {
        $this->Tab     = $insertSpaces ? str_repeat(' ', $tabSize) : "\t";
        $this->TabSize = $tabSize;
        $this->SoftTab = str_repeat(' ', $tabSize);

        if (!$insertSpaces) {
            $skip = [
                AlignArrowFunctions::class,
                AlignChainedCalls::class,
                AlignLists::class,
                AlignTernaryOperators::class,
            ];
        }

        if ($addRules) {
            array_push($this->Rules, ...$addRules);
        }

        if ($skipRules || ($skip ?? null)) {
            $this->Rules = array_diff($this->Rules, $skipRules, $skip ?? []);
        }

        $mandatory = [
            RemoveWhitespace::class,
            NormaliseHeredocs::class,
        ];
        $optional = [
            TrimCasts::class,
            SortImports::class,
        ];
        $comparison = [
            NormaliseStrings::class,
            RemoveComments::class,
            RemoveEmptyTokens::class,
            TrimOpenTags::class,
        ];
        if ($skipFilters) {
            $optional = array_diff($optional, $skipFilters);
        }
        $this->FormatFilters = array_map(
            fn(string $filter) => new $filter(),
            array_merge($mandatory, $optional)
        );
        $this->ComparisonFilters = array_merge(
            $this->FormatFilters,
            array_map(
                fn(string $filter) => new $filter(),
                $comparison
            )
        );

        $this->Debug = Env::debug();
    }

    /**
     * Get formatted code
     *
     * Rules are processed from lowest to highest priority (smallest to biggest
     * number). The default priority (applied if {@see Rule::getPriority()}
     * returns `null`) is 100.
     *
     * Order of operations:
     *
     * 1. {@see TokenRule::processToken()} is called on enabled rules that
     *    implement {@see TokenRule}.
     * 2. {@see BlockRule::processBlock()} is called on enabled rules that
     *    implement {@see BlockRule}.
     * 3. Callbacks registered via {@see Formatter::registerCallback()} are
     *    called in order of priority and token offset.
     * 4. {@see Rule::beforeRender()} is called on enabled rules.
     *
     * @param string|null $filename Advisory only. No file operations are
     * performed on `$filename`.
     */
    public function format(string $code, int $quietLevel = 0, ?string $filename = null, bool $fast = false): string
    {
        $this->QuietLevel = $quietLevel;
        $this->Filename   = $filename;
        if ($this->Tokens) {
            Token::destroyTokens($this->Tokens);
        }

        Sys::startTimer(__METHOD__ . '#tokenize-input');
        try {
            $this->Tokens = Token::tokenize($code,
                                            TOKEN_PARSE,
                                            ...$this->FormatFilters);

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
            $this->Tokens = Token::prepareTokens($this->Tokens,
                                                 $this);

            $last = end($this->Tokens);
            if (!$last) {
                return '';
            }

            if ($last->IsCode && !$last->startOfStatement()->is(T_HALT_COMPILER)) {
                $last->WhitespaceAfter |= WhitespaceType::LINE;
            }
        } finally {
            Sys::stopTimer(__METHOD__ . '#prepare-tokens');
        }

        Sys::startTimer(__METHOD__ . '#sort-rules');
        $mainLoop     = [];
        $blockLoop    = [];
        $beforeRender = [];
        $index        = 0;
        foreach ($this->Rules as $_rule) {
            if (!is_a($_rule, Rule::class, true)) {
                throw new RuntimeException('Not a ' . Rule::class . ': ' . $_rule);
            }
            /** @var Rule $rule */
            $rule = new $_rule($this);
            if ($rule instanceof TokenRule) {
                $mainLoop[] = [$this->getPriority($rule, TokenRule::PROCESS_TOKEN), $index, $rule, TokenRule::class];
            }
            if ($rule instanceof ListRule) {
                $mainLoop[] = [$this->getPriority($rule, ListRule::PROCESS_LIST), $index, $rule, ListRule::class];
            }
            if ($rule instanceof BlockRule) {
                $blockLoop[] = [$this->getPriority($rule, BlockRule::PROCESS_BLOCK), $index, $rule];
            }
            $beforeRender[] = [$this->getPriority($rule, Rule::BEFORE_RENDER), $index, $rule];
            $index++;
        }
        $mainLoop     = $this->sortRules($mainLoop);
        $blockLoop    = $this->sortRules($blockLoop);
        $beforeRender = $this->sortRules($beforeRender);
        Sys::stopTimer(__METHOD__ . '#sort-rules');

        Sys::startTimer(__METHOD__ . '#find-lists');
        // Get non-empty open brackets
        $listParents =
            array_filter(
                $this->Tokens,
                fn(Token $t) =>
                    ($t->is([T['('], T['[']]) ||
                            ($t->id === T['{'] &&
                                $t->prevSibling(2)->id === T_MATCH) ||
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
                        !$prev || $t->prevCode()->is(T[','])
                );
                if ($items->count() > 1) {
                    $lists[$i] = $items;
                }
                continue;
            }
            $lists[$i] = $parent->innerSiblings()->filter(
                fn(Token $t, ?Token $next, ?Token $prev) =>
                    !$prev || ($t->prevCode()->is(T[',']) &&
                        ($parent->id !== T['{'] ||
                            $t->prevSiblingOf(T[','], ...TokenType::OPERATOR_DOUBLE_ARROW)
                              ->is(TokenType::OPERATOR_DOUBLE_ARROW)))
            );
        }
        Sys::stopTimer(__METHOD__ . '#find-lists');

        foreach ($mainLoop as [$rule, $ruleType]) {
            $this->RunningService = $_rule = Convert::classToBasename(get_class($rule));
            Sys::startTimer($_rule, 'rule');

            if ($ruleType === ListRule::class) {
                foreach ($lists as $i => $list) {
                    $list = clone $list;
                    /** @var ListRule $rule */
                    $rule->processList($listParents[$i], $list);
                }
                Sys::stopTimer($_rule, 'rule');
                !$this->Debug || $this->logProgress(ListRule::PROCESS_LIST);
                continue;
            }

            // Prepare to filter the tokens as efficiently as possible
            /** @var TokenRule $rule */
            $types = $rule->getTokenTypes();
            if ($types === []) {
                continue;
            }
            $types = $types ? TokenType::getIndex(...$types) : null;

            /** @var Token $token */
            foreach ($this->Tokens as $token) {
                if (!$types || ($types[$token->id] ?? false) !== false) {
                    $rule->processToken($token);
                }
            }
            Sys::stopTimer($_rule, 'rule');
            !$this->Debug || $this->logProgress(TokenRule::PROCESS_TOKEN);
        }
        $this->RunningService = null;

        Sys::startTimer(__METHOD__ . '#find-blocks');
        /** @var array<TokenCollection[]> $blocks */
        $blocks = [];
        /** @var TokenCollection[] $block */
        $block  = [];
        $line   = new TokenCollection();
        /** @var Token $token */
        $token  = reset($this->Tokens);
        $line[] = $token;

        while (!($token = $token->next())->IsNull) {
            $before = $token->effectiveWhitespaceBefore() & (WhitespaceType::BLANK | WhitespaceType::LINE);
            if (!$before) {
                $line[] = $token;
                continue;
            }
            if ($before === WhitespaceType::LINE) {
                $block[] = $line;
                $line    = new TokenCollection();
                $line[]  = $token;
                continue;
            }
            $block[]  = $line;
            $blocks[] = $block;
            $block    = [];
            $line     = new TokenCollection();
            $line[]   = $token;
        }
        $block[]  = $line;
        $blocks[] = $block;
        Sys::stopTimer(__METHOD__ . '#find-blocks');

        /** @var BlockRule $rule */
        foreach ($blockLoop as $rule) {
            $this->RunningService = $_rule = Convert::classToBasename(get_class($rule));
            Sys::startTimer($_rule, 'rule');
            foreach ($blocks as $block) {
                $rule->processBlock($block);
            }
            Sys::stopTimer($_rule, 'rule');
            !$this->Debug || $this->logProgress(BlockRule::PROCESS_BLOCK);
        }
        $this->RunningService = null;

        $this->processCallbacks();

        /** @var Rule $rule */
        foreach ($beforeRender as $rule) {
            $this->RunningService = $_rule = Convert::classToBasename(get_class($rule));
            Sys::startTimer($_rule, 'rule');
            $rule->beforeRender($this->Tokens);
            Sys::stopTimer($_rule, 'rule');
            !$this->Debug || $this->logProgress(Rule::BEFORE_RENDER);
        }
        $this->RunningService = null;

        Sys::startTimer(__METHOD__ . '#render');
        try {
            $out     = '';
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
            return $out;
        }

        Sys::startTimer(__METHOD__ . '#parse-output');
        try {
            $tokensOut = Token::tokenize($out,
                                         TOKEN_PARSE,
                                         ...$this->ComparisonFilters);
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

        $tokensIn = Token::tokenize($code,
                                    TOKEN_PARSE,
                                    ...$this->ComparisonFilters);

        $before = $this->simplifyTokens($tokensIn);
        $after  = $this->simplifyTokens($tokensOut);
        if ($before !== $after) {
            throw new PrettyException(
                "Formatting check failed: parsed output doesn't match input",
                $out,
                $this->Tokens,
                $this->Log,
                ['before' => $before, 'after' => $after]
            );
        }

        Token::destroyTokens($tokensOut);
        Token::destroyTokens($tokensIn);
        foreach ($beforeRender as $rule) {
            $rule->destroy();
        }

        return $out;
    }

    private function getPriority(Rule $rule, string $method): int
    {
        $priority = $rule->getPriority($method);

        return is_null($priority)
                   ? 100
                   : $priority;
    }

    /**
     * Sort rules by priority
     *
     * @param array<array{0:int,1:int,2:Rule,3?:class-string<Rule>}> $rules
     * @return array<Rule|array{0:Rule,1:class-string<Rule>}>
     */
    private function sortRules(array $rules): array
    {
        usort(
            $rules,
            fn(array $a, array $b) =>
                ($a[0] <=> $b[0]) ?: $a[1] <=> $b[1]
        );

        return array_map(
            fn(array $rule) =>
                ($rule[3] ?? null) ? [$rule[2], $rule[3]] : $rule[2],
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
                    !$this->Debug || $this->logProgress('{closure}');
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

        $values[] = Convert::pluralRange($start->line,
                                         $end ? $end->line : $start->line,
                                         'line');
        Console::warn(sprintf($message . ' %s', ...$values));
    }

    private function logProgress(string $after): void
    {
        Sys::startTimer(__METHOD__ . '#render');
        try {
            $out     = '';
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
