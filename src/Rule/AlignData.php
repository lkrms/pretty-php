<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule;

use Lkrms\PrettyPHP\Catalog\TokenSubType;
use Lkrms\PrettyPHP\Catalog\TokenType;
use Lkrms\PrettyPHP\Contract\BlockRule;
use Lkrms\PrettyPHP\Rule\Concern\BlockRuleTrait;
use Lkrms\PrettyPHP\Token\Token;

/**
 * Align consecutive operators and values
 */
final class AlignData implements BlockRule
{
    use BlockRuleTrait;

    private const ALIGN_TOKEN = 0;

    private const ALIGN_DATA = 1;

    private const ALIGN_PREV = 2;

    private const ALIGN_NEXT = 3;

    private const TOKEN_COMPARISON_MAP = [
        \T_CONSTANT_ENCAPSED_STRING => \T_STRING,
        \T_DNUMBER => \T_STRING,
        \T_LNUMBER => \T_STRING,
        \T_VARIABLE => \T_STRING,
    ];

    /**
     * [ tokenIndex => data ]
     *
     * @var array<int,mixed[]>
     */
    private array $TokenData = [];

    public static function getPriority(string $method): ?int
    {
        switch ($method) {
            case self::PROCESS_BLOCK:
                return 340;

            case self::CALLBACK:
                return 720;

            default:
                return null;
        }
    }

    public function processBlock(array $block): void
    {
        if (($blockLines = count($block)) < 2) {
            return;
        }

        // 1. Identify alignable tokens and index them by:
        //    - type + brackets + indent
        //    - order of appearance on each line

        /**
         * [ index => [ lineIndex => [ token, ... ] ] ]
         *
         * @var array<string,array<int,Token[]>>
         */
        $ctxIdx = [];

        /**
         * [ index => maxTokensPerLine ]
         *
         * @var array<string,int>
         */
        $ctxCounts = [];

        /**
         * [ lineIndex => [ brackets => [ tokenIndex => [ type, ... ] ] ] ]
         *
         * @var array<int,array<string,array<int,string[]>>>
         */
        $lineIdx = [];

        $addToIndex =
            function (string $type, array $data = []) use (&$ctxIdx, &$ctxCounts, &$lineIdx, &$line, &$token) {
                /** @var Token $token */
                $brackets = '';
                $current = $token;
                while ($current->Parent) {
                    $current = $current->Parent;
                    $brackets = $current->text . $brackets;
                }
                $index = "$type\0$brackets\0{$token->indent()}";
                $ctxIdx[$index][$line][] = $token;
                $ctxCounts[$index] = max($ctxCounts[$index] ?? 0, count($ctxIdx[$index][$line]));
                isset($lineIdx[$line][$brackets]) || $lineIdx[$line][$brackets] = [];
                $prevTypes = end($lineIdx[$line][$brackets]) ?: [];
                $prevTypes[] = $type;
                $lineIdx[$line][$brackets][$token->Index] = $prevTypes;
                $this->TokenData[$token->Index] = $data;
            };

        foreach ($block as $line => $tokens) {
            foreach ($tokens as $token) {
                if ($token->hasNewlineBefore()) {
                    continue;
                }
                if ($token->is(TokenType::OPERATOR_ASSIGNMENT)) {
                    if (!$token->Parent || $token->Parent->isStructuralBrace()) {
                        $addToIndex('=');
                        continue;
                    }
                    if ($token->inParameterList()) {
                        // Only align default value definitions within the same
                        // declaration
                        $addToIndex(implode(':', ['fn', $token->Parent->Index]));
                    }
                    continue;
                }
                if ($token->id === \T_COLON
                        && $token->getSubType() === TokenSubType::COLON_SWITCH_CASE_DELIMITER) {
                    $addToIndex('case');
                    continue;
                }
                if ($token->id === \T_DOUBLE_ARROW) {
                    $addToIndex('=>');
                    continue;
                }
                if ($token->id === \T_COMMA
                        && !$token->hasNewlineAfter()
                        && $token->Parent
                        && $token->Parent->isArrayOpenBracket()
                        && !$this->TypeIndex->CloseBracket[$token->Next->id]) {
                    $data = [
                        'prevTypes' => $token->prevSiblings($token->prevSiblingOf(\T_COMMA)->nextCode())->getTypes(),
                        'nextTypes' => $token->Next->collectSiblings($token->nextSiblingOf(\T_COMMA)->prevCode())->getTypes(),
                    ];
                    $type = array_map(fn(array $types): string => implode(',', $this->simplifyTokenTypes($types)), $data);
                    array_unshift($type, ',');
                    $addToIndex(implode(':', $type), $data);
                }
            }
        }

        // 2. Find sequences of consecutive tokens with the same context and
        //    line position

        /**
         * [ [ "type", [ lineIndex => token, ... ] ], ... ]
         *
         * @var array<array{string,non-empty-array<int,Token>}>
         */
        $runs = [];

        $collectRun =
            function () use (&$type, &$run, &$runs) {
                if (count($run) > 1) {
                    $runs[] = [$type, $run];
                }
                $run = [];
            };

        /** @var string[] */
        $runPrevTypes = null;
        foreach ($ctxIdx as $context => $lines) {
            if (count($lines) < 2) {
                continue;
            }
            [$type, $brackets] = explode("\0", $context, 3);
            [$type] = explode(':', $type, 2);
            for ($i = 0; $i < $ctxCounts[$context]; $i++) {
                /** @var array<int,Token> */
                $run = [];
                foreach ($lines as $line => $tokens) {
                    if (!($token = $tokens[$i] ?? null)) {
                        $collectRun();
                        continue;
                    }
                    // If the alignable tokens that have appeared on the line in
                    // this context so far have changed, start a new run
                    $prevTypes = $lineIdx[$line][$brackets][$token->Index];
                    if (!$run) {
                        $runPrevTypes = $prevTypes;
                    } elseif ($prevTypes !== $runPrevTypes) {
                        $collectRun();
                        $run[$line] = $token;
                        $runPrevTypes = $prevTypes;
                        continue;
                    }
                    if ($lastToken = end($run)) {
                        $prev = key($run);
                        // Tokens must appear on consecutive lines
                        if ($line - $prev === 1
                            // Loophole: the expression they precede may span
                            // multiple lines, as long as inner lines have a
                            // higher effective indentation level than the
                            // aligned tokens (enforced in callback)
                            || (($this->Formatter->RelaxAlignmentCriteria || $type !== '=')
                                && $block[$line - 1][0]->Index
                                    <= $lastToken->NextCode->pragmaticEndOfExpression()->Index)
                            || ($this->Formatter->RelaxAlignmentCriteria
                                && $block[$line - 1][0]->PrevCode === $block[$line][0]->PrevCode)) {
                            $run[$line] = $token;
                            continue;
                        }
                        $collectRun();
                    }
                    $run[$line] = $token;
                }
                $collectRun();
            }
        }

        // 3. Create a group of token pairs from each run and register them for
        //    alignment via callback
        //
        //    A token pair contains:
        //    - the first token on the same line as the token being aligned
        //    - the token being aligned

        /** @var non-empty-array<int,Token> $run */
        foreach ($runs as [$type, $run]) {
            $first = null;
            $prev = null;
            /** @var array<int,array{Token,Token}> */
            $group = [];
            /** @var array<int,Token[]> */
            $innerLines = [];
            /** @var Token $token2 */
            foreach ($run as $line => $token2) {
                if (!$first) {
                    $first = $token2;
                }
                if ($prev && $line - $prev > 1) {
                    for ($i = $prev + 1; $i < $line; $i++) {
                        $innerLines[$prev][] = $block[$i][0];
                    }
                }
                $token1 = $block[$line][0];
                $group[$line] = [$token1, $token2];
                $prev = $line;
            }
            $end = $token2->NextCode->pragmaticEndOfExpression();
            $current = $token2;
            while (++$line < $blockLines
                    && ($current = $current->endOfLine()->Next)
                    && $current->Index <= $end->Index) {
                $innerLines[$prev][] = $block[$line][0];
            }
            $action = $type === ','
                ? self::ALIGN_DATA
                : ($type === 'case'
                    ? self::ALIGN_NEXT
                    : self::ALIGN_TOKEN);
            $this->Formatter->registerCallback(
                static::class,
                $first,
                fn() => $this->alignGroup($action, $group, $innerLines)
            );
        }
    }

    /**
     * @param int[] $types
     * @return int[]
     */
    private function simplifyTokenTypes(array $types): array
    {
        return array_map(
            fn(int $type): int =>
                self::TOKEN_COMPARISON_MAP[$type] ?? $type,
            $types
        );
    }

    /**
     * @param self::ALIGN_* $action
     * @param array<int,array{Token,Token}> $group
     * @param array<int,Token[]> $innerLines
     */
    private function alignGroup(int $action, array $group, array $innerLines): void
    {
        $lengths = [];
        $deltas = [];
        $maxLength = 0;
        $prevTypes = [];

        /**
         * @var Token $token1
         * @var Token $token2
         */
        foreach ($group as $i => [$token1, $token2]) {
            $length = mb_strlen($token1->collect($token2)->render(true, false));
            $lengths[$i] = $length;
            $maxLength = max($maxLength, $length);
            if ($action === self::ALIGN_DATA
                    && ($types = $this->TokenData[$token2->Index]['prevTypes'] ?? null)
                    // Exclude `null` from type detection heuristic
                    && !($types === [\T_STRING] && strcasecmp($token2->Prev->text, 'null'))) {
                $prevTypes[] = $types;
            }
        }

        foreach ($lengths as $i => $length) {
            $deltas[$i] = $maxLength - $length;
        }

        $innerLineIsOutdented = false;
        foreach ($innerLines as $i => $lines) {
            /** @var Token $token1 */
            foreach ($lines as $token1) {
                $indent = mb_strlen($token1->renderWhitespaceBefore(true));
                if ($indent + $deltas[$i] < $maxLength) {
                    if (!$this->Formatter->RelaxAlignmentCriteria) {
                        return;
                    }
                    $innerLineIsOutdented = true;
                    break 2;
                }
            }
        }

        if ($action === self::ALIGN_DATA) {
            if ($prevTypes && array_uintersect(
                $prevTypes,
                [[\T_LNUMBER], [\T_DNUMBER], [\T_VARIABLE]],
                fn($a, $b) => $a <=> $b
            ) === $prevTypes) {
                $action = self::ALIGN_PREV;
            } else {
                $action = self::ALIGN_NEXT;
            }
        }

        /** @var Token $token2 */
        foreach ($group as $i => [$token1, $token2]) {
            if ($action === self::ALIGN_PREV) {
                $token2->Prev->Padding += $deltas[$i];
            } elseif ($action === self::ALIGN_NEXT) {
                if ($token2->hasNewlineAfter()) {
                    continue;
                }
                $token2->Next->Padding += $deltas[$i];
            } else {
                $token2->Padding += $deltas[$i];
            }
            if ($innerLineIsOutdented || !($innerLines[$i] ?? null)) {
                continue;
            }
            $token2->collect($token2->NextCode->pragmaticEndOfExpression())
                   ->forEach(fn(Token $t) => $t->LinePadding += $deltas[$i]);
        }
    }
}
