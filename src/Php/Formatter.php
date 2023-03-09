<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php;

use Lkrms\Concern\TFullyReadable;
use Lkrms\Contract\IReadable;
use Lkrms\Facade\Console;
use Lkrms\Facade\Convert;
use Lkrms\Facade\Env;
use Lkrms\Facade\Sys;
use Lkrms\Pretty\Php\Contract\BlockRule;
use Lkrms\Pretty\Php\Contract\Rule;
use Lkrms\Pretty\Php\Contract\TokenFilter;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Filter\NormaliseStrings;
use Lkrms\Pretty\Php\Filter\RemoveCommentTokens;
use Lkrms\Pretty\Php\Filter\RemoveEmptyTokens;
use Lkrms\Pretty\Php\Filter\RemoveWhitespaceTokens;
use Lkrms\Pretty\Php\Filter\SortImports;
use Lkrms\Pretty\Php\Filter\StripHeredocIndents;
use Lkrms\Pretty\Php\Filter\TrimInsideCasts;
use Lkrms\Pretty\Php\Filter\TrimOpenTags;
use Lkrms\Pretty\Php\Rule\AddBlankLineBeforeReturn;
use Lkrms\Pretty\Php\Rule\AddEssentialWhitespace;
use Lkrms\Pretty\Php\Rule\AddHangingIndentation;
use Lkrms\Pretty\Php\Rule\AddIndentation;
use Lkrms\Pretty\Php\Rule\AddStandardWhitespace;
use Lkrms\Pretty\Php\Rule\AlignChainedCalls;
use Lkrms\Pretty\Php\Rule\AlignLists;
use Lkrms\Pretty\Php\Rule\BracePosition;
use Lkrms\Pretty\Php\Rule\BreakAfterSeparators;
use Lkrms\Pretty\Php\Rule\BreakBeforeControlStructureBody;
use Lkrms\Pretty\Php\Rule\BreakBetweenMultiLineItems;
use Lkrms\Pretty\Php\Rule\DeclareArgumentsOnOneLine;
use Lkrms\Pretty\Php\Rule\MatchPosition;
use Lkrms\Pretty\Php\Rule\PlaceAttributes;
use Lkrms\Pretty\Php\Rule\PlaceComments;
use Lkrms\Pretty\Php\Rule\PreserveNewlines;
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

/**
 * @property-read bool $Debug
 * @property-read int $QuietLevel
 * @property-read string|null $Filename
 * @property-read string|null $RunningService
 * @property-read string $Tab
 * @property-read int $TabSize
 * @property-read string $SoftTab
 * @property-read bool $MirrorBrackets
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
     * @var string
     */
    protected $Tab;

    /**
     * @var int
     */
    protected $TabSize;

    /**
     * @var string
     */
    protected $SoftTab;

    /**
     * @var bool
     */
    protected $MirrorBrackets = true;

    /**
     * @var string[]
     */
    protected $Rules = [
        ProtectStrings::class,  // processToken:
                                // - `WhitespaceMaskPrev`=NONE
                                // - `WhitespaceMaskNext`=NONE

        SimplifyStrings::class,  // processToken:
                                 // - `text`=<value>

        AddStandardWhitespace::class,  // processToken:
                                       // - WhitespaceBefore+SPACE[+LINE]
                                       // - WhitespaceAfter(+SPACE[+LINE]|+LINE)
                                       // - WhitespaceMaskNext(-SPACE|=SPACE|+LINE|=NONE)
                                       // - WhitespaceMaskPrev(-SPACE|=NONE)

        BreakAfterSeparators::class,  // processToken:
                                      // - `WhitespaceAfter`+SPACE[+LINE]
                                      // - `WhitespaceMaskNext`+SPACE
                                      // - `WhitespaceMaskPrev`(+SPACE|=NONE)
                                      // - `WhitespaceBefore`=NONE

        BreakBeforeControlStructureBody::class,  // processToken:
                                                 // - `WhitespaceBefore`+LINE+SPACE
                                                 // - `WhitespaceMaskPrev`+LINE-BREAK
                                                 // - `WhitespaceMaskNext`+LINE
                                                 // - `PreIndent`++

        PlaceAttributes::class,  // processToken:
                                 // - `WhitespaceBefore`+LINE
                                 // - `WhitespaceAfter`+LINE
                                 // - `WhitespaceMaskNext`-BLANK

        BracePosition::class,

        SpaceOperators::class,  // processToken:
                                // `WhitespaceBefore`(+SPACE|=NONE)
                                // `WhitespaceMaskNext`=NONE
                                // `WhitespaceMaskPrev`=NONE
                                // `WhitespaceAfter`(+SPACE|=NONE)

        PlaceComments::class,
        PreserveNewlines::class,          // Must be after PlaceComments
        DeclareArgumentsOnOneLine::class,
        AddBlankLineBeforeReturn::class,  // Must be after PlaceComments

        BreakBetweenMultiLineItems::class,  // processToken:
                                            // - `WhitespaceBefore`+LINE
                                            // - `WhitespaceMaskPrev`+LINE
                                            // - `WhitespaceMaskNext`+LINE

        AlignChainedCalls::class,

        AlignLists::class,  // processToken (400):
                            // - `WhitespaceBefore`+LINE    (via BreakBetweenMultiLineItems)
                            // - `WhitespaceMaskPrev`+LINE  (via BreakBetweenMultiLineItems)
                            // - `WhitespaceMaskNext`+LINE  (via BreakBetweenMultiLineItems)
                            // - `AlignedWith`=<value>
                            //
                            // callback (710):
                            // - `LinePadding`+=<value>

        AddIndentation::class,  // processToken (600):
                                // - `Indent`=<value>
                                // - `WhitespaceBefore`+LINE
                                // - `WhitespaceMaskPrev`-BLANK-LINE

        SwitchPosition::class,  // processToken (600):
                                // - `PreIndent`++
                                // - `Deindent`++

        MatchPosition::class,  // processToken (600):
                               // - `WhitespaceAfter`+LINE

        SpaceDeclarations::class,  // processToken (620):
                                   // - `WhitespaceMaskPrev`-BLANK
                                   // - `WhitespaceBefore`+BLANK

        AddHangingIndentation::class,  // processToken (800):
                                       // - `IsHangingParent`=true
                                       // - `IsOverhangingParent`=true|false
                                       // - `HangingIndent`+=<value>
                                       // - `OverhangingParents[]`=<value>
                                       // - `IndentBracketStack[]`=<value>
                                       // - `IndentStack[]`=<value>
                                       // - `IndentParentStack[]`=<value>
                                       //
                                       // callback (800):
                                       // - `HangingIndent`--
                                       // - `OverhangingParents[]`--

        ReindentHeredocs::class,  // beforeRender:
                                  // - `HeredocIndent`=<value>
                                  // - `text`=<value>

        AddEssentialWhitespace::class,

        // Read-only rules
        ReportUnnecessaryParentheses::class,
    ];

    /**
     * @var Token[]|null
     */
    public $Tokens;

    /**
     * @var TokenFilter[]
     */
    private $MandatoryFilters;

    /**
     * @var TokenFilter[]
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
                AlignChainedCalls::class,
                AlignLists::class,
            ];
        }

        if ($skipRules || ($skip ?? null)) {
            $this->Rules = array_diff($this->Rules, $skipRules, $skip ?? []);
        }

        if ($addRules) {
            array_push($this->Rules, ...$addRules);
        }

        $mandatory = [
            RemoveWhitespaceTokens::class,
            StripHeredocIndents::class,
            TrimInsideCasts::class,
            SortImports::class,
        ];
        $comparison = [
            NormaliseStrings::class,
            RemoveCommentTokens::class,
            RemoveEmptyTokens::class,
            TrimOpenTags::class,
        ];
        if ($skipFilters) {
            $mandatory  = array_diff($mandatory, $skipFilters);
            $comparison = array_diff($comparison, $skipFilters);
        }
        $this->MandatoryFilters  = array_map(fn(string $filter) => new $filter(), $mandatory);
        $this->ComparisonFilters = array_merge(
            $this->MandatoryFilters,
            array_map(fn(string $filter) => new $filter(), $comparison)
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
    public function format(string $code, int $quietLevel = 0, ?string $filename = null): string
    {
        $this->QuietLevel = $quietLevel;
        $this->Filename   = $filename;

        Sys::startTimer(__METHOD__ . '#tokenize-input');
        try {
            $this->Tokens = Token::tokenize($code,
                                            TOKEN_PARSE,
                                            ...$this->MandatoryFilters);

            if (!$this->Tokens) {
                return '';
            }
        } catch (ParseError $ex) {
            throw new PrettyBadSyntaxException('Formatting failed: input cannot be parsed', $ex);
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

            if ($last->isCode() && !$last->startOfStatement()->is(T_HALT_COMPILER)) {
                $last->WhitespaceAfter |= WhitespaceType::LINE;
            }
        } finally {
            Sys::stopTimer(__METHOD__ . '#prepare-tokens');
        }

        Sys::startTimer(__METHOD__ . '#sort-rules');
        $tokenLoop    = [];
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
                $tokenLoop[] = [$this->getPriority($rule, TokenRule::PROCESS_TOKEN), $index, $rule];
            }
            if ($rule instanceof BlockRule) {
                $blockLoop[] = [$this->getPriority($rule, BlockRule::PROCESS_BLOCK), $index, $rule];
            }
            $beforeRender[] = [$this->getPriority($rule, Rule::BEFORE_RENDER), $index, $rule];
            $index++;
        }
        $tokenLoop    = $this->sortRules($tokenLoop);
        $blockLoop    = $this->sortRules($blockLoop);
        $beforeRender = $this->sortRules($beforeRender);
        Sys::stopTimer(__METHOD__ . '#sort-rules');

        /** @var TokenRule $rule */
        foreach ($tokenLoop as $rule) {
            // Prepare to filter the tokens as efficiently as possible
            $types = $rule->getTokenTypes();
            if ($types === []) {
                continue;
            }
            $types = $types ? TokenType::getIndex(...$types) : null;

            $this->RunningService = $_rule = get_class($rule);
            Sys::startTimer($timer = Convert::classToBasename($_rule), 'rule');
            /** @var Token $token */
            foreach ($this->Tokens as $token) {
                if (!$types || ($types[$token->id] ?? false) !== false) {
                    $rule->processToken($token);
                }
            }
            Sys::stopTimer($timer, 'rule');
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

        while (!($token = $token->next())->isNull()) {
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
            $this->RunningService = $_rule = get_class($rule);
            Sys::startTimer($timer = Convert::classToBasename($_rule), 'rule');
            foreach ($blocks as $block) {
                $rule->processBlock($block);
            }
            Sys::stopTimer($timer, 'rule');
        }
        $this->RunningService = null;

        $this->processCallbacks();

        /** @var Rule $rule */
        foreach ($beforeRender as $rule) {
            $this->RunningService = $_rule = get_class($rule);
            Sys::startTimer($timer = Convert::classToBasename($_rule), 'rule');
            $rule->beforeRender($this->Tokens);
            Sys::stopTimer($timer, 'rule');
        }
        $this->RunningService = null;

        Sys::startTimer(__METHOD__ . '#render');
        try {
            $out = '';
            foreach ($this->Tokens as $token) {
                $out .= $token->render();
            }
        } catch (Throwable $ex) {
            throw new PrettyException(
                'Formatting failed: output cannot be rendered',
                $out,
                $this->Tokens,
                null,
                $ex
            );
        } finally {
            Sys::stopTimer(__METHOD__ . '#render');
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
                null,
                $ex
            );
        } finally {
            Sys::stopTimer(__METHOD__ . '#parse-output');
        }

        $before = $this->simplifyTokens(Token::tokenize($code,
                                                        TOKEN_PARSE,
                                                        ...$this->ComparisonFilters));
        $after = $this->simplifyTokens($tokensOut);
        if ($before !== $after) {
            throw new PrettyException(
                "Formatting check failed: parsed output doesn't match input",
                $out,
                $this->Tokens,
                [$before, $after]
            );
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
     * @param array<array{0:int,1:int,2:Rule}> $rules
     * @return Rule[]
     */
    private function sortRules(array $rules): array
    {
        usort($rules, fn(array $a, array $b) => ($a[0] <=> $b[0]) ?: $a[1] <=> $b[1]);

        return array_map(fn(array $rule) => $rule[2], $rules);
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
                    $this->RunningService = $_rule = get_class($rule);
                    Sys::startTimer($timer = Convert::classToBasename($_rule), 'rule');
                    $callback();
                    Sys::stopTimer($timer, 'rule');
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
}
