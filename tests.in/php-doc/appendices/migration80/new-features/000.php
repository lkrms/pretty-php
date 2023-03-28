<?php
class A {
     public function method(int $many, string $parameters, $here) {}
}
class B extends A {
     public function method(...$everything) {}
}
?>