<?php

namespace Lkrms\Pretty\Command;

use Lkrms\Cli\CliOption;
use Lkrms\Cli\CliOptionType;
use Lkrms\Cli\Concept\CliCommand;
use Lkrms\Cli\Exception\CliArgumentsInvalidException;
use Lkrms\Facade\Env;
use Lkrms\Facade\File;
use Lkrms\Pretty\Php\Formatter;
use Lkrms\Pretty\PrettyException;

class FormatPhp extends CliCommand
{
    /**
     * @var string|null
     */
    private $DebugDirectory;

    public function getDescription(): string
    {
        return "Format a PHP file";
    }

    protected function getOptionList(): array
    {
        return [
            (CliOption::build()
                ->long("file")
                ->short("f")
                ->valueName("FILE")
                ->description("PHP file to format")
                ->optionType(CliOptionType::VALUE)),
            (CliOption::build()
                ->long("debug")
                ->valueName("DIR")
                ->description("Create debug output in DIR")
                ->optionType(CliOptionType::VALUE_OPTIONAL)
                ->defaultValue($this->app()->TempPath . "/pretty-php")),
        ];
    }

    protected function run(...$params)
    {
        $file = $this->getOptionValue("file");
        if (is_null($file) && stream_isatty(STDIN))
        {
            throw new CliArgumentsInvalidException("--file argument required when input is a TTY");
        }
        elseif (is_null($file) || $file === "-")
        {
            $file = "php://stdin";
        }

        $debug = $this->getOptionValue("debug");
        if (!is_null($debug))
        {
            Env::debug(true);
            File::maybeCreateDirectory($debug);
            $debug = $this->DebugDirectory = realpath($debug) ?: null;
        }

        $formatter = new Formatter();
        $input     = file_get_contents($file);
        try
        {
            $output = $formatter->format($input);
        }
        catch (PrettyException $ex)
        {
            $this->maybeDumpDebugOutput($input, $ex->getOutput(), $ex->getData());
            throw $ex;
        }

        $this->maybeDumpDebugOutput($input, $output, $formatter->Tokens);

        print $output;
    }

    private function maybeDumpDebugOutput(string $input, string $output, array $tokens)
    {
        if (!is_null($this->DebugDirectory))
        {
            file_put_contents($this->DebugDirectory . "/input.php", $input);
            file_put_contents($this->DebugDirectory . "/output.php", $output);
            file_put_contents($this->DebugDirectory . "/tokens.json", json_encode($tokens, JSON_PRETTY_PRINT));
        }
    }
}
