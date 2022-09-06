<?php

namespace Lkrms\Pretty\Command;

use Lkrms\Cli\CliCommand;
use Lkrms\Cli\CliOption;
use Lkrms\Cli\CliOptionType;
use Lkrms\Exception\InvalidCliArgumentException;
use Lkrms\Facade\Env;
use Lkrms\Facade\File;
use Lkrms\Pretty\Php\Formatter;

class FormatPhp extends CliCommand
{
    protected function _getDescription(): string
    {
        return "Format a PHP file";
    }

    protected function _getOptions(): array
    {
        return [
            (CliOption::build()
                ->long("file")
                ->short("f")
                ->valueName("FILE")
                ->description("PHP file to format")
                ->optionType(CliOptionType::VALUE)
                ->get()),
            (CliOption::build()
                ->long("debug")
                ->valueName("DIR")
                ->description("Create debug output in DIR")
                ->optionType(CliOptionType::VALUE_OPTIONAL)
                ->defaultValue($this->app()->TempPath . "/pretty-php")
                ->get()),
        ];
    }

    protected function run(...$params)
    {
        $file = $this->getOptionValue("file");
        if (is_null($file) && stream_isatty(STDIN))
        {
            throw new InvalidCliArgumentException("--file argument required when input is a TTY");
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
            $debug = realpath($debug);
        }

        $formatter = new Formatter();
        $input     = file_get_contents($file);
        $output    = $formatter->format($input);

        if (!is_null($debug))
        {
            file_put_contents($debug . "/input.php", $input);
            file_put_contents($debug . "/output.php", $output);
            file_put_contents($debug . "/tokens.json", json_encode($formatter->Tokens, JSON_PRETTY_PRINT));
        }

        print $output;
    }
}
