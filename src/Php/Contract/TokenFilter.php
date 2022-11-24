<?php

declare(strict_types=1);

namespace Lkrms\Pretty\Php\Contract;

interface TokenFilter
{
    /**
     * @param string|array{0:int,1:string,2:int} $token As per
     * `token_get_all()`, either a single character or a 3-element array:
     * `[$tokenId, $code, $lineNumber]`
     * @return bool `false` if the token should be discarded, otherwise `true`.
     */
    public function __invoke(&$token): bool;

}
