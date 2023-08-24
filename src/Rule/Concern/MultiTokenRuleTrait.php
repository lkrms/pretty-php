<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Rule\Concern;

use Lkrms\PrettyPHP\Token\Token;

trait MultiTokenRuleTrait
{
    use TokenRuleTrait;

    public function processToken(Token $token): void {}
}
