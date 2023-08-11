<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP;

use Lkrms\Exception\Exception;
use Lkrms\PrettyPHP\Token;
use Throwable;

class PrettyException extends Exception
{
    /**
     * @var string|null
     */
    protected $Output;

    /**
     * @var Token[]|null
     */
    protected $Tokens;

    /**
     * @var array<string,string>|null
     */
    protected $Log;

    /**
     * @var mixed[]|object|null
     */
    protected $Data;

    /**
     * @param Token[]|null $tokens
     * @param array<string,string>|null $log
     * @param mixed[]|object|null $data
     */
    public function __construct(string $message = '', ?string $output = null, ?array $tokens = null, ?array $log = null, $data = null, ?Throwable $previous = null)
    {
        parent::__construct($message, $previous);

        $this->Output = $output;
        $this->Tokens = $tokens;
        $this->Log = $log;
        $this->Data = $data;
    }

    public function getDetail(): array
    {
        return [
            'output' => $this->Output,
            'tokens' => json_encode($this->Tokens, JSON_PRETTY_PRINT | JSON_FORCE_OBJECT),
            'data' => json_encode($this->Data, JSON_PRETTY_PRINT),
        ];
    }

    public function getOutput(): ?string
    {
        return $this->Output;
    }

    /**
     * @return Token[]|null
     */
    public function getTokens(): ?array
    {
        return $this->Tokens;
    }

    /**
     * @return array<string,string>|null
     */
    public function getLog(): ?array
    {
        return $this->Log;
    }

    /**
     * @return mixed[]|object|null
     */
    public function getData()
    {
        return $this->Data;
    }
}
