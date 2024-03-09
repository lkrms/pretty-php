<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Exception;

use Lkrms\PrettyPHP\Token\Token;

class InvalidTokenException extends AbstractException
{
    public function __construct(Token $token)
    {
        parent::__construct(sprintf(
            'Invalid %s at %s:%d:%d',
            $token->Formatter->Filename ?? '<input>',
            $token->getTokenName() ?? "token #{$token->id}",
            $token->line,
            $token->column,
        ));
    }
}
