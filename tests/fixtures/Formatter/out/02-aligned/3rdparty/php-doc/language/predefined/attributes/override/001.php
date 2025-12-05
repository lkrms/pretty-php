<?php

class Base
{
    protected string $foo;
}

final class Extended extends Base
{
    #[\Override]
    protected string $boo;
}

?>