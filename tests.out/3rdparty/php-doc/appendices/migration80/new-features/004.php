<?php
class ParentClass
{
    private function method1() {}
    private function method2() {}
    private static function method3() {}
    // Throws a warning, as "final" no longer has an effect:
    private final function method4() {}
}
class ChildClass extends ParentClass
{
    // All of the following are now allowed, even though the modifiers aren't
    // the same as for the private methods in the parent class.
    public abstract function method1() {}
    public static function method2() {}
    public function method3() {}
    public function method4() {}
}
?>