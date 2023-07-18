<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Concern;

use Lkrms\Pretty\Php\Token;

trait MultiTokenRuleTrait
{
    use TokenRuleTrait;

    public function processToken(Token $token): void {}
}
