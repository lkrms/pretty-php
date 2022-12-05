<?php declare(strict_types=1);

namespace Lkrms\Pretty\Php\Filter;

use Lkrms\Pretty\Php\Contract\TokenFilter;

class NormaliseStrings implements TokenFilter
{
    public function __invoke(&$token): bool
    {
        if (!is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING) {
            return true;
        }

        $string = '';
        eval("\$string = {$token[1]};");
        $token[1] = var_export($string, true);

        return true;
    }
}
