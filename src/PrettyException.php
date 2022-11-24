<?php

declare(strict_types=1);

namespace Lkrms\Pretty;

use Lkrms\Exception\Exception;
use Throwable;

class PrettyException extends Exception
{
    /**
     * @var string|null
     */
    protected $Output;

    /**
     * @var array|object|null
     */
    protected $Data;

    /**
     * @param array|object|null $data
     */
    public function __construct(string $message = "", ?string $output = null, $data = null, ?Throwable $previous = null)
    {
        parent::__construct($message, $previous);

        $this->Output = $output;
        $this->Data   = $data;
    }

    public function getDetail(): array
    {
        return [
            "output" => $this->Output,
            "data"   => json_encode($this->Data, JSON_PRETTY_PRINT),
        ];
    }

    public function getOutput(): string
    {
        return $this->Output;
    }

    /**
     * @return array|object|null
     */
    public function getData()
    {
        return $this->Data;
    }

}
