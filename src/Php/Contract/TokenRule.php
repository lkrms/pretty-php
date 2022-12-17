<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Contract;

use Lkrms\Pretty\Php\Formatter;
use Lkrms\Pretty\Php\Token;

interface TokenRule
{
    public const STAGE_AFTER_TOKEN_LOOP = -1;
    public const STAGE_BEFORE_RENDER    = -2;

    public function __construct(Formatter $formatter);

    public function __invoke(Token $token, int $stage): void;

    /**
     * Return an array that maps stages to (optional) priorities
     *
     * User-defined stages are numbered from 1 to *n* for consistency, but any
     * integer greater than zero can be used.
     *
     * Every non-whitespace token identified in the input file is passed to
     * {@see TokenRule::__invoke()} once per stage.
     *
     * The formatter processes the stages of each enabled `TokenRule` in
     * priority order, then calls each rule's {@see TokenRule::afterTokenLoop()}
     * method before grouping lines into blocks and passing them to enabled
     * {@see BlockRule}s. Finally, {@see TokenRule::beforeRender()} is called on
     * each enabled `TokenRule`.
     *
     * Higher priorities (bigger numbers) correspond to later invocation.
     *
     * See below for {@see \Lkrms\Pretty\Php\Concept\AbstractTokenRule}'s
     * implementation of {@see TokenRule::getStages()}, which returns `null`
     * values to apply the default priority (100) to user-defined stage 1,
     * {@see TokenRule::afterTokenLoop()}, and {@see TokenRule::beforeRender()}.
     *
     * ```php
     * public function getStages(): array
     * {
     *     return [
     *         1                            => null,
     *         self::STAGE_AFTER_TOKEN_LOOP => null,
     *         self::STAGE_BEFORE_RENDER    => null,
     *     ];
     * }
     * ```
     *
     * @return array<int,int|null>
     */
    public function getStages(): array;

    public function afterTokenLoop(): void;

    public function beforeRender(): void;

    public function clear(): void;
}
