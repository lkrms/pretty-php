<?php

new A()->foo;
new A()->foo();
new A()::FOO;
new A()::foo();
new A()::$foo;
new A()[0];
new A()();

new class {}->foo;
new class {}->foo();
new class {}::FOO;
new class {}::foo();
new class {}::$foo;
new class {}[0];
new class {}();
