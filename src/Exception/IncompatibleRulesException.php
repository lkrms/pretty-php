<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Exception;

use Lkrms\PrettyPHP\Contract\Rule;
use Salient\Core\Utility\Get;

class IncompatibleRulesException extends AbstractException
{
    /**
     * @var array<class-string<Rule>>
     */
    protected array $Rules;

    /**
     * @param class-string<Rule> ...$rules
     */
    public function __construct(string ...$rules)
    {
        $this->Rules = $rules;

        $rules = '';
        foreach ($this->Rules as $rule) {
            $rules .= ($rules === '' ? '' : ', ') . Get::basename($rule);
        }

        parent::__construct(sprintf(
            'Enabled rules are not compatible: %s',
            $rules
        ));
    }
}
