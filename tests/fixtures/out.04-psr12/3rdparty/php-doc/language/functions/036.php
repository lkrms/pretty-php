<?php

class Test
{
    public function testing()
    {
        return function () {
            var_dump($this);
        };
    }
}

$object = new Test;
$function = $object->testing();
$function();

?>