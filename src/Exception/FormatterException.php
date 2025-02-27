<?php declare(strict_types=1);

namespace Lkrms\PrettyPHP\Exception;

use Lkrms\PrettyPHP\Contract\Extension;
use Lkrms\PrettyPHP\Token;
use Salient\Utility\Json;
use Throwable;

/**
 * @api
 *
 * @codeCoverageIgnore
 */
class FormatterException extends AbstractException
{
    protected ?string $Output;
    /** @var Token[]|null */
    protected ?array $Tokens;
    /** @var array<string,string>|null */
    protected ?array $Log;
    /** @var array<class-string<Extension>,non-empty-array<string,mixed>>|null */
    protected ?array $Data;

    /**
     * @internal
     *
     * @param Token[]|null $tokens
     * @param array<string,string>|null $log
     * @param array<class-string<Extension>,non-empty-array<string,mixed>>|null $data
     */
    public function __construct(
        string $message = '',
        ?string $output = null,
        ?array $tokens = null,
        ?array $log = null,
        ?array $data = null,
        ?Throwable $previous = null
    ) {
        $this->Output = $output;
        $this->Tokens = $tokens;
        $this->Log = $log;
        $this->Data = $data;

        parent::__construct($message, $previous);
    }

    /**
     * @inheritDoc
     */
    public function getMetadata(): array
    {
        $flags = \JSON_INVALID_UTF8_IGNORE;

        return [
            'output' => $this->Output,
            'tokens' => Json::prettyPrint($this->Tokens !== null ? (object) $this->Tokens : null, $flags),
            'data' => Json::prettyPrint($this->Data, $flags),
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
     * @return array<class-string<Extension>,non-empty-array<string,mixed>>|null
     */
    public function getData(): ?array
    {
        return $this->Data;
    }
}
