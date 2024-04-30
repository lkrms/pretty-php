<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Exception;

use Lkrms\PrettyPHP\Token\Token;
use Salient\Core\Utility\Json;
use Throwable;

class FormatterException extends AbstractException
{
    protected ?string $Output;

    /**
     * @var Token[]|null
     */
    protected ?array $Tokens;

    /**
     * @var array<string,string>|null
     */
    protected ?array $Log;

    /**
     * @var mixed[]|object|null
     */
    protected $Data;

    /**
     * @param Token[]|null $tokens
     * @param array<string,string>|null $log
     * @param mixed[]|object|null $data
     */
    public function __construct(
        string $message = '',
        ?string $output = null,
        ?array $tokens = null,
        ?array $log = null,
        $data = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $previous);

        $this->Output = $output;
        $this->Tokens = $tokens;
        $this->Log = $log;
        $this->Data = $data;
    }

    public function getMetadata(): array
    {
        return [
            'output' => $this->Output,
            'tokens' => Json::prettyPrint($this->Tokens, \JSON_FORCE_OBJECT),
            'data' => Json::prettyPrint($this->Data),
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
