<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\App\Exception;

use Throwable;

class InvalidConfigurationException extends AbstractAppException
{
    public function __construct(string $message = '', ?Throwable $previous = null)
    {
        parent::__construct($message, $previous, 2);
    }
}
