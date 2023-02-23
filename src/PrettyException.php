<?php declare(strict_types=1);

namespace Lkrms\Pretty;

use Lkrms\Exception\Exception;
use Lkrms\Pretty\Php\Token;
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
     * @var mixed[]|object|null
     */
    protected $Data;

    /**
     * @param Token[]|null $tokens
     * @param mixed[]|object|null $data
     */
    public function __construct(string $message = '', ?string $output = null, ?array $tokens = null, $data = null, ?Throwable $previous = null)
    {
        parent::__construct($message, $previous);

        $this->Output = $output;
        $this->Tokens = $tokens;
        $this->Data   = $data;
    }

    public function getDetail(): array
    {
        return [
            'output' => $this->Output,
            'tokens' => json_encode($this->Tokens, JSON_PRETTY_PRINT | JSON_FORCE_OBJECT),
            'data'   => json_encode($this->Data, JSON_PRETTY_PRINT),
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
     * @return mixed[]|object|null
     */
    public function getData()
    {
        return $this->Data;
    }
}
