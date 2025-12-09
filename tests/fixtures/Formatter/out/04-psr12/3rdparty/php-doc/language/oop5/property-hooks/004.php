<?php

class Example
{
    public function __construct(
        public private(set) DateTimeInterface $created {
            set (string|DateTimeInterface $value) {
                if (is_string($value)) {
                    $value = new DateTimeImmutable($value);
                }
                $this->created = $value;
            }
        },
    ) {}
}
