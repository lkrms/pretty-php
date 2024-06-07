<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Exception;

use Lkrms\PrettyPHP\Contract\Rule;
use Salient\Utility\Get;

class IncompatibleRulesException extends InvalidFormatterException
{
    /** @var array<class-string<Rule>> */
    protected array $Rules;

    /**
     * @param class-string<Rule> ...$rules
     */
    public function __construct(string ...$rules)
    {
        $this->Rules = $rules;

        foreach ($rules as $rule) {
            $names[] = Get::basename($rule);
        }

        parent::__construct(sprintf(
            'Enabled rules are not compatible: %s',
            implode(', ', $names ?? []),
        ));
    }
}
