<?php

class A
{
    private $val;

    public function __construct($val)
    {
        $this->val = $val;
    }

    public function getClosure()
    {
        // Returns closure bound to this object and scope
        return function () {
            return $this->val;
        };
    }
}

$ob1 = new A(1);
$ob2 = new A(2);

$cl = $ob1->getClosure();
echo $cl(), "\n";

$cl = $cl->bindTo($ob2);
echo $cl(), "\n";

?>