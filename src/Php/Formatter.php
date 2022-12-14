<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php;

use Lkrms\Concern\TFullyReadable;
use Lkrms\Contract\IReadable;
use Lkrms\Facade\Convert;
use Lkrms\Facade\Env;
use Lkrms\Facade\Sys;
use Lkrms\Pretty\Php\Contract\BlockRule;
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
use Lkrms\Pretty\Php\Rule\AddBlankLineBeforeYield;
use Lkrms\Pretty\Php\Rule\AddEssentialWhitespace;
use Lkrms\Pretty\Php\Rule\AddHangingIndentation;
use Lkrms\Pretty\Php\Rule\AddIndentation;
use Lkrms\Pretty\Php\Rule\AddStandardWhitespace;
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

/**
 * @property-read bool $Debug
 * @property-read string|null $RunningService
 * @property-read string $Tab
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
     * @var string|null
     */
    protected $RunningService;

    /**
     * @var string
     */
    protected $Tab;

    /**
     * @var string[]
     */
    protected $Rules = [
        // TokenRules
        ProtectStrings::class,
        SimplifyStrings::class,
        BreakAfterSeparators::class,
        PlaceAttributes::class,
        BracePosition::class,
        SpaceOperators::class,
        CommaCommaComma::class,
        AddStandardWhitespace::class,
        PlaceComments::class,
        PreserveNewlines::class,
        PreserveOneLineStatements::class,
        DeclareArgumentsOnOneLine::class,
        AddBlankLineBeforeReturn::class,           // Must be after PlaceComments
        AddBlankLineBeforeYield::class,            // Ditto
        AddIndentation::class,
        SwitchPosition::class,
        MatchPosition::class,
        AddBlankLineBeforeDeclaration::class,
        BreakBeforeControlStructureBody::class,
        AddHangingIndentation::class,
        AlignChainedCalls::class,
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
     * @param string[] $skipRules
     * @param string[] $addRules
     */
    public function __construct(string $tab = '    ', array $skipRules = [], array $addRules = [])
    {
        $this->Tab = $tab;

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

    public function format(string $code): string
    {
        try {
            [$this->PlainTokens, $this->Tokens] = [token_get_all($code, TOKEN_PARSE), []];
        } catch (ParseError $ex) {
            throw new PrettyBadSyntaxException('Formatting failed: input cannot be parsed', $ex);
        }

        $bracketStack = [];
        $openTag      = null;
        foreach ($this->filter($this->PlainTokens, ...$this->Filters) as $index => $plainToken) {
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
        $token->WhitespaceAfter |= WhitespaceType::LINE;

        /** @var TokenRule[] $rules */
        $rules  = [];
        /** @var array<array{int,int,TokenRule,int}> $stages */
        $stages = [];
        $index  = 0;
        foreach ($this->Rules as $_rule) {
            if (!is_a($_rule, TokenRule::class, true)) {
                continue;
            }
            /** @var TokenRule $rule */
            $rule    = new $_rule($this);
            $rules[] = $rule;
            foreach ($rule->getStages() as $stage => $priority) {
                $stages[] = [
                    is_null($priority) ? 100 : $priority,
                    $index,
                    $rule,
                    $stage,
                ];
            }
            $index++;
        }
        // Sort by priority then order of appearance
        usort($stages, fn(array $a, array $b) => ($a[0] <=> $b[0]) ?: $a[1] <=> $b[1]);
        foreach ($stages as [,, $rule, $stage]) {
            $this->RunningService = $_rule = get_class($rule);
            Sys::startTimer($timer = Convert::classToBasename($_rule), 'rule');
            /** @var Token $token */
            foreach ($this->Tokens as $token) {
                $rule($token, $stage);
            }
            Sys::stopTimer($timer, 'rule');
        }
        foreach ($rules as $rule) {
            $this->RunningService = $_rule = get_class($rule);
            Sys::startTimer($timer = Convert::classToBasename($_rule), 'rule');
            $rule->afterTokenLoop();
            Sys::stopTimer($timer, 'rule');
        }
        $this->RunningService = null;

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

        foreach ($this->Rules as $_rule) {
            if (!is_a($_rule, BlockRule::class, true)) {
                continue;
            }

            $this->RunningService = $_rule;
            $rule                 = new $_rule();

            Sys::startTimer($timer = Convert::classToBasename($_rule), 'rule');
            foreach ($blocks as $block) {
                $rule($block);
            }
            Sys::stopTimer($timer, 'rule');
        }
        $this->RunningService = null;

        foreach ($rules as $rule) {
            $this->RunningService = get_class($rule);
            $rule->beforeRender();
        }
        $this->RunningService = null;

        $out = '';
        foreach ($this->Tokens as $token) {
            $out .= $token->render();
        }

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

    /**
     * @param array<string|array{0:int,1:string,2:int}> $tokens
     * @return array<string|array{0:int,1:string,2:int}>
     */
    private function filter(array $tokens, TokenFilter ...$filters): array
    {
        foreach ($filters as $filter) {
            foreach ($tokens as $key => &$token) {
                if (!$filter($token)) {
                    unset($tokens[$key]);
                }
            }
            unset($token);
        }

        return $tokens;
    }

    /**
     * @param array<string|array{0:int,1:string,2:int}> $tokens
     * @return array<string|array{0:int,1:string,2:int}>
     */
    private function strip(array $tokens, TokenFilter ...$filters): array
    {
        $tokens = array_values($this->filter($tokens, ...$filters));
        foreach ($tokens as &$token) {
            if (is_array($token)) {
                unset($token[2]);
                if (in_array($token[0], [T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO])) {
                    $token[1] = rtrim($token[1]);
                }
            }
        }
        unset($token);

        return $tokens;
    }
}
