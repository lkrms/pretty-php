<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Command\Exception;

use Lkrms\PrettyPHP\Exception\AbstractException;
use Throwable;

class InvalidConfigurationException extends AbstractException
{
    public function __construct(string $message = '', ?Throwable $previous = null)
    {
        parent::__construct($message, $previous, 2);
    }
}
