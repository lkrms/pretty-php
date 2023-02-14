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
use Lkrms\Pretty\Php\Filter\StripHeredocIndents;
use Lkrms\Pretty\Php\Filter\TrimInsideCasts;
use Lkrms\Pretty\Php\Rule\AddBlankLineBeforeDeclaration;
use Lkrms\Pretty\Php\Rule\AddBlankLineBeforeReturn;
use Lkrms\Pretty\Php\Rule\AddEssentialWhitespace;
use Lkrms\Pretty\Php\Rule\AddHangingIndentation;
use Lkrms\Pretty\Php\Rule\AddIndentation;
use Lkrms\Pretty\Php\Rule\AddStandardWhitespace;
use Lkrms\Pretty\Php\Rule\AlignAssignments;
use Lkrms\Pretty\Php\Rule\AlignChainedCalls;
use Lkrms\Pretty\Php\Rule\AlignComments;
use Lkrms\Pretty\Php\Rule\AlignLists;
use Lkrms\Pretty\Php\Rule\BracePosition;
use Lkrms\Pretty\Php\Rule\BreakAfterSeparators;
use Lkrms\Pretty\Php\Rule\BreakBeforeControlStructureBody;
use Lkrms\Pretty\Php\Rule\BreakBetweenMultiLineItems;
use Lkrms\Pretty\Php\Rule\CommaCommaComma;
use Lkrms\Pretty\Php\Rule\DeclareArgumentsOnOneLine;
use Lkrms\Pretty\Php\Rule\MatchPosition;
use Lkrms\Pretty\Php\Rule\PlaceAttributes;
use Lkrms\Pretty\Php\Rule\PlaceComments;
use Lkrms\Pretty\Php\Rule\PreserveNewlines;
use Lkrms\Pretty\Php\Rule\PreserveOneLineStatements;
use Lkrms\Pretty\Php\Rule\ProtectStrings;
use Lkrms\Pretty\Php\Rule\ReindentHeredocs;
use Lkrms\Pretty\Php\Rule\ReportUnnecessaryParentheses;
use Lkrms\Pretty\Php\Rule\SimplifyStrings;
use Lkrms\Pretty\Php\Rule\SpaceOperators;
use Lkrms\Pretty\Php\Rule\SwitchPosition;
use Lkrms\Pretty\PrettyBadSyntaxException;
use Lkrms\Pretty\PrettyException;
use Lkrms\Pretty\WhitespaceType;
use ParseError;
use RuntimeException;
use Throwable;
use UnexpectedValueException;

/**
 * @property-read bool $Debug
 * @property-read int $QuietLevel
 * @property-read string|null $Filename
 * @property-read string|null $RunningService
 * @property-read string $Tab
 * @property-read int $TabSize
 * @property-read string $SoftTab
 * @property-read string[] $Rules
 * @property-read array<string|array{0:int,1:string,2:int}>|null $PlainTokens
 * @property-read Token[]|null $Tokens
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
     * @var string[]
     */
    protected $Rules = [
        ProtectStrings::class,    // processToken:
                                  // - `WhitespaceMaskPrev`=NONE
                                  // - `WhitespaceMaskNext`=NONE

        SimplifyStrings::class,    // processToken:
                                   // - `Code`=<value>

        AddStandardWhitespace::class,    // processToken:
                                         // - WhitespaceBefore+SPACE[+LINE]
                                         // - WhitespaceAfter(+SPACE[+LINE]|+LINE)
                                         // - WhitespaceMaskNext(-SPACE|=SPACE|+LINE|=NONE)
                                         // - WhitespaceMaskPrev(-SPACE|=NONE)

        BreakAfterSeparators::class,    // processToken:
                                        // - `WhitespaceAfter`+SPACE[+LINE]
                                        // - `WhitespaceMaskNext`+SPACE
                                        // - `WhitespaceMaskPrev`(+SPACE|=NONE)
                                        // - `WhitespaceBefore`=NONE

        BreakBeforeControlStructureBody::class,    // processToken:
                                                   // - `WhitespaceBefore`+LINE+SPACE
                                                   // - `WhitespaceMaskPrev`+LINE-BREAK
                                                   // - `WhitespaceMaskNext`+LINE
                                                   // - `PreIndent`++

        PlaceAttributes::class,    // processToken:
                                   // - `WhitespaceBefore`+LINE
                                   // - `WhitespaceAfter`+LINE
                                   // - `WhitespaceMaskNext`-BLANK

        BracePosition::class,

        SpaceOperators::class,    // processToken:
                                  // `WhitespaceBefore`(+SPACE|=NONE)
                                  // `WhitespaceMaskNext`=NONE
                                  // `WhitespaceMaskPrev`=NONE
                                  // `WhitespaceAfter`(+SPACE|=NONE)

        CommaCommaComma::class,
        PreserveOneLineStatements::class,
        PlaceComments::class,
        PreserveNewlines::class,             // Must be after PlaceComments
        DeclareArgumentsOnOneLine::class,
        AddBlankLineBeforeReturn::class,     // Must be after PlaceComments

        BreakBetweenMultiLineItems::class,    // processToken:
                                              // - `WhitespaceBefore`+LINE
                                              // - `WhitespaceMaskPrev`+LINE
                                              // - `WhitespaceMaskNext`+LINE

        AlignChainedCalls::class,

        AlignLists::class,    // processToken (400):
                              // - `WhitespaceBefore`+LINE    (via BreakBetweenMultiLineItems)
                              // - `WhitespaceMaskPrev`+LINE  (via BreakBetweenMultiLineItems)
                              // - `WhitespaceMaskNext`+LINE  (via BreakBetweenMultiLineItems)
                              // - `AlignedWith`=<value>
                              //
                              // callback (710):
                              // - `LinePadding`+=<value>

        AddIndentation::class,    // processToken (600):
                                  // - `Indent`=<value>
                                  // - `WhitespaceBefore`+LINE
                                  // - `WhitespaceMaskPrev`-BLANK-LINE

        SwitchPosition::class,    // processToken (600):
                                  // - `PreIndent`++
                                  // - `Deindent`++

        MatchPosition::class,    // processToken (600):
                                 // - `WhitespaceAfter`+LINE

        AddBlankLineBeforeDeclaration::class,    // processToken (620):
                                                 // - `IsStartOfDeclaration`=true
                                                 // - `WhitespaceMaskPrev`-BLANK
                                                 // - `WhitespaceBefore`+BLANK

        AddHangingIndentation::class,    // processToken (800):
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

        ReindentHeredocs::class,    // beforeRender:
                                    // - `HeredocIndent`=<value>
                                    // - `Code`=<value>

        AddEssentialWhitespace::class,

        // BlockRules
        AlignAssignments::class,
        AlignComments::class,

        // Read-only rules
        ReportUnnecessaryParentheses::class,
    ];

    /**
     * @var array<string|array{0:int,1:string,2:int}>|null
     */
    protected $PlainTokens;

    /**
     * @var Token[]|null
     */
    protected $Tokens;

    /**
     * @var TokenFilter[]
     */
    private $Filters;

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
     */
    public function __construct(bool $insertSpaces = true, int $tabSize = 4, array $skipRules = [], array $addRules = [])
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

        $this->Filters = [
            new RemoveWhitespaceTokens(),
            new StripHeredocIndents(),
            new TrimInsideCasts(),
        ];

        $this->ComparisonFilters = [
            ...$this->Filters,
            new NormaliseStrings(),
            new RemoveCommentTokens(),
            new RemoveEmptyTokens(),
        ];

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

        Sys::startTimer(__METHOD__ . '#parse-input');
        $this->Tokens = [];
        try {
            $this->PlainTokens = token_get_all($code, TOKEN_PARSE);
        } catch (ParseError $ex) {
            throw new PrettyBadSyntaxException('Formatting failed: input cannot be parsed', $ex);
        } finally {
            Sys::stopTimer(__METHOD__ . '#parse-input');
        }

        $tokens = $this->filter($this->PlainTokens, ...$this->Filters);

        Sys::startTimer(__METHOD__ . '#build-tokens');
        $bracketStack = [];
        $altStack     = [];
        try {
            $last = null;
            foreach ($tokens as $index => $plainToken) {
                $this->Tokens[$index] = $token = new Token(
                    $index,
                    $plainToken,
                    $last,
                    $this
                );
                $last = $token;
            }

            if (!isset($token)) {
                return '';
            }
            if ($token->isCode() && !$token->startOfStatement()->is(T_HALT_COMPILER)) {
                $token->WhitespaceAfter |= WhitespaceType::LINE;
            }
        } finally {
            Sys::stopTimer(__METHOD__ . '#build-tokens');
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
            $types = $types ? array_flip($types) : null;

            $this->RunningService = $_rule = get_class($rule);
            Sys::startTimer($timer = Convert::classToBasename($_rule), 'rule');
            /** @var Token $token */
            foreach ($this->Tokens as $token) {
                if (!$types || ($types[$token->Type] ?? false) !== false) {
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
            $tokensOut = token_get_all($out, TOKEN_PARSE);
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

        $before = $this->strip($this->PlainTokens, ...$this->ComparisonFilters);
        $after  = $this->strip($tokensOut, ...$this->ComparisonFilters);
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

    public function insertToken(Token $insert, Token $before): void
    {
        $this->PlainTokens[] = '';

        $key = array_key_last($this->PlainTokens);
        if ($token = $this->Tokens[$before->Index] ?? null) {
            if ($token !== $before) {
                throw new UnexpectedValueException('Token mismatch');
            }
            Convert::arraySpliceAtKey($this->Tokens, $before->Index, 0, [$key => $insert]);
        } else {
            $this->Tokens[$key] = $insert;
        }
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
     * @param array<string|array{0:int,1:string,2:int}> $tokens
     * @return array<string|array{0:int,1:string,2:int}>
     */
    private function filter(array $tokens, TokenFilter ...$filters): array
    {
        Sys::startTimer(__METHOD__);
        foreach ($filters as $filter) {
            foreach ($tokens as $key => &$token) {
                if (!$filter($token)) {
                    unset($tokens[$key]);
                }
            }
            unset($token);
        }
        Sys::stopTimer(__METHOD__);

        return $tokens;
    }

    /**
     * @param array<string|array{0:int,1:string,2:int}> $tokens
     * @return array<string|array{0:int,1:string,2:int}>
     */
    private function strip(array $tokens, TokenFilter ...$filters): array
    {
        $tokens = $this->filter($tokens, ...$filters);
        Sys::startTimer(__METHOD__);
        $tokens = array_values($tokens);
        foreach ($tokens as &$token) {
            if (is_array($token)) {
                unset($token[2]);
                if (in_array($token[0], [T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO])) {
                    $token[1] = rtrim($token[1]);
                }
            }
        }
        unset($token);
        Sys::stopTimer(__METHOD__);

        return $tokens;
    }

    public function registerCallback(Rule $rule, Token $first, callable $callback, int $priority = 100): void
    {
        $this->Callbacks[$priority][$first->Index][] = [$rule, $callback];
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
            $values[] = $start->Line;
            Console::warn(sprintf($message . ': %s:%d', ...$values));

            return;
        }

        $values[] = Convert::pluralRange($start->Line,
                                         $end ? $end->Line : $start->Line,
                                         'line');
        Console::warn(sprintf($message . ' %s', ...$values));
    }
}
