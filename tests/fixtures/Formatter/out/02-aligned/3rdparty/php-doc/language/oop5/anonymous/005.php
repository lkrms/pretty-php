<?php

// Using an anonymous class
$util->setLogger(new readonly class('[DEBUG]') {
                     public function __construct(
                         private string $prefix
                     ) {}

                     public function log($msg)
                     {
                         echo $this->prefix . ' ' . $msg;
                     }
                 });
