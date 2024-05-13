<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Exception;

use Lkrms\PrettyPHP\Token\Token;

class InvalidTokenException extends InvalidSyntaxException
{
    public function __construct(Token $token)
    {
        parent::__construct(sprintf(
            'Invalid %s at %s:%d:%d',
            $token->getTokenName() ?? sprintf('<token#%d>', $token->id),
            $token->Formatter->Filename ?? '<input>',
            $token->line,
            $token->column,
        ));
    }
}
