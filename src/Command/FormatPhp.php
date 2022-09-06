<?php

namespace Lkrms\Pretty\Command;

use Lkrms\Cli\CliCommand;
use Lkrms\Cli\CliOption;
use Lkrms\Cli\CliOptionType;
use Lkrms\Exception\InvalidCliArgumentException;
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
        ];
    }

    protected function run(...$params)
    {
        $file = $this->getOptionValue("file");
        if (is_null($file) && stream_isatty(STDIN))
        {
            throw new InvalidCliArgumentException("--file argument required when input is a TTY");
        }
        elseif (!$file || $file === "-")
        {
            $file = "php://stdin";
        }

        $formatter = new Formatter();
        $in        = file_get_contents($file);
        $out       = $formatter->format($in);

        echo $out;
    }
}
