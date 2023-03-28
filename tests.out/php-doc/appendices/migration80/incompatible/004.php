<?php
class X
{
    use T1, T2 {
        func as otherFunc;
    }
    function func() {}
}
?>