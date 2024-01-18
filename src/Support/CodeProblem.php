<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Support;

use Lkrms\PrettyPHP\Contract\Rule;
use Lkrms\PrettyPHP\Token\Token;

/**
 * A non-critical problem detected in formatted code
 */
final class CodeProblem
{
    /**
     * The rule that reported the problem
     *
     * @var class-string<Rule>
     */
    public string $Rule;

    /**
     * An sprintf() format string describing the problem
     */
    public string $Message;

    /**
     * Values for the sprintf() format string
     *
     * @var mixed[]
     */
    public array $Values;

    /**
     * The start of the range of tokens with the problem
     */
    public Token $Start;

    /**
     * The end of the range of tokens with the problem
     *
     * May be `null` if the problem only affects one token.
     */
    public ?Token $End;

    /**
     * @param mixed ...$values
     */
    public function __construct(Rule $rule, string $message, Token $start, ?Token $end = null, ...$values)
    {
        $this->Rule = get_class($rule);
        $this->Message = $message;
        $this->Values = $values;
        $this->Start = $start;
        $this->End = $end;
    }
}
