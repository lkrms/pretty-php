<?php
class IPv4Address implements Stringable
{
    public function __construct(
        private string $oct1,
        private string $oct2,
        private string $oct3,
        private string $oct4,
    ) {}

    public function __toString(): string
    {
        return "$this->oct1.$this->oct2.$this->oct3.$this->oct4";
    }
}

function showStuff(string|Stringable $value)
{
    // For a Stringable, this will implicitliy call __toString().
    print $value;
}

$ip = new IPv4Address('123', '234', '42', '9');

showStuff($ip);
?>