<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Exception;

use Salient\Core\AbstractException;
use Throwable;

/**
 * Thrown when an invalid configuration file is found
 */
class InvalidConfigurationException extends AbstractException
{
    public function __construct(string $message = '', ?Throwable $previous = null)
    {
        parent::__construct($message, $previous, 2);
    }
}
