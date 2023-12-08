<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Exception;

use Lkrms\Exception\Exception;
use Lkrms\PrettyPHP\Rule\Contract\Rule;

/**
 * Thrown when incompatible rules are enabled
 */
class IncompatibleRulesException extends Exception
{
    /**
     * @var array<class-string<Rule>>
     */
    protected array $Rules;

    /**
     * @param class-string<Rule> $rule1
     * @param class-string<Rule> $rule2
     * @param class-string<Rule> ...$rules
     */
    public function __construct(string $rule1, string $rule2, string ...$rules)
    {
        array_unshift($rules, $rule1, $rule2);
        $this->Rules = $rules;

        parent::__construct(sprintf(
            'Enabled rules are not compatible: %s',
            implode(', ', $this->Rules)
        ));
    }
}
