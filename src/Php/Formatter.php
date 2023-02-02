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
use Lkrms\Pretty\Php\Contract\TokenFilter;
use Lkrms\Pretty\Php\Contract\TokenRule;
use Lkrms\Pretty\Php\Contract\Rule;
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
use Lkrms\Pretty\Php\Rule\AlignArguments;
use Lkrms\Pretty\Php\Rule\AlignAssignments;
use Lkrms\Pretty\Php\Rule\AlignChainedCalls;
use Lkrms\Pretty\Php\Rule\AlignComments;
use Lkrms\Pretty\Php\Rule\BracePosition;
use Lkrms\Pretty\Php\Rule\BreakAfterSeparators;
use Lkrms\Pretty\Php\Rule\BreakBeforeControlStructureBody;
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

/**
 * @property-read bool $Debug
 * @property int $QuietLevel
 * @property string|null $Filename
 * @property-read string|null $RunningService
 * @property-read string $Tab
 * @property-read string $SoftTab
 * @property-read string[] $Rules
 * @property-read array<string|array{0:int,1:string,2:int}>|null $PlainTokens
 * @property-read Token[]|null $Tokens
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
    protected $QuietLevel = 0;

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
     * @var string
     */
    protected $SoftTab;

    /**
     * @var string[]
     */
    protected $Rules = [
        // TokenRules
        ProtectStrings::class,
        SimplifyStrings::class,
        BreakAfterSeparators::class,
        BreakBeforeControlStructureBody::class,
        PlaceAttributes::class,
        BracePosition::class,
        SpaceOperators::class,
        CommaCommaComma::class,
        PreserveOneLineStatements::class,
        AddStandardWhitespace::class,
        PlaceComments::class,
        PreserveNewlines::class,                   // Must be after PlaceComments
        DeclareArgumentsOnOneLine::class,
        AddBlankLineBeforeReturn::class,           // Must be after PlaceComments
        AddIndentation::class,
        SwitchPosition::class,
        MatchPosition::class,
        AddBlankLineBeforeDeclaration::class,
        AlignChainedCalls::class,
        AlignArguments::class,
        AddHangingIndentation::class,              // Must be after AlignChainedCalls
        ReindentHeredocs::class,
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
    public function __construct(string $tab = '    ', int $tabSize = 4, array $skipRules = [], array $addRules = [])
    {
        $this->Tab     = $tab;
        $this->SoftTab = str_repeat(' ', $tabSize);

        if ($skipRules) {
            $this->Rules = array_diff($this->Rules, $skipRules);
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

    public static function getWritable(): array
    {
        return [
            'QuietLevel',
            'Filename',
        ];
    }

    public function format(string $code): string
    {
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
        $openTag      = null;
        try {
            foreach ($tokens as $index => $plainToken) {
                $this->Tokens[$index] = $token = new Token(
                    $index,
                    $plainToken,
                    end($this->Tokens) ?: null,
                    $bracketStack,
                    $this
                );

                if ($token->isOpenBracket()) {
                    $bracketStack[] = $token;
                    continue;
                }

                if ($token->isCloseBracket()) {
                    $opener           = array_pop($bracketStack);
                    $opener->ClosedBy = $token;
                    $token->OpenedBy  = $opener;
                    continue;
                }

                if ($token->startsAlternativeSyntax()) {
                    $bracketStack[] = $token;
                    $altStack[]     = $token;
                    continue;
                }

                if ($token->endsAlternativeSyntax()) {
                    $opener    = array_pop($bracketStack);
                    $altOpener = array_pop($altStack);
                    if ($opener !== $altOpener) {
                        throw new RuntimeException('Formatting failed: unable to traverse control structures');
                    }
                    $virtual = new VirtualToken(
                        $this->PlainTokens,
                        $this->Tokens,
                        $token,
                        $token->BracketStack,
                        $this,
                        $bracketStack
                    );
                    $opener->ClosedBy  = $virtual;
                    $virtual->OpenedBy = $opener;
                    array_pop($token->BracketStack);
                    continue;
                }

                if ($token->isOneOf(T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO)) {
                    $openTag = $token;
                    continue;
                }

                if ($token->is(T_CLOSE_TAG)) {
                    /** @var Token $openTag */
                    $openTag->ClosedBy = $token;
                    $token->OpenedBy   = $openTag;
                    $openTag           = null;
                }
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
            $this->RunningService = $_rule = get_class($rule);
            Sys::startTimer($timer = Convert::classToBasename($_rule), 'rule');
            /** @var Token $token */
            foreach ($this->Tokens as $token) {
                $rule->processToken($token);
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

        /** @var Rule $rule */
        foreach ($beforeRender as $rule) {
            $this->RunningService = $_rule = get_class($rule);
            Sys::startTimer($timer = Convert::classToBasename($_rule), 'rule');
            $rule->beforeRender();
            Sys::stopTimer($timer, 'rule');
        }
        $this->RunningService = null;

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
