<?php

// -- Example #4

class User
{
	public string $name {
		set {
			if (strlen($value) === 0) {
				throw new ValueError('Name must be non-empty');
			}
			$this->name = $value;
		}
	}

	public function __construct(string $name)
	{
		$this->name = $name;
	}
}

// -- Example #6

class Foo
{
	public int $runs = 0 {
		set {
			if ($value <= 0)
				throw new Exception();
			$this->runs = $value;
		}
	}
}

$f = new Foo();

$f->runs++;

// -- Example #7

class User implements Named
{
	private bool $isModified = false;

	public function __construct(private string $first, private string $last) {}

	public string $fullName {
		// Override the "read" action with arbitrary logic.
		get => $this->first . ' ' . $this->last;

		// Override the "write" action with arbitrary logic.
		set {
			[$this->first, $this->last] = explode(' ', $value, 2);
			$this->isModified = true;
		}
	}
}

// -- Example #8

interface Named
{
	// Objects implementing this interface must have a readable
	// $fullName property.  That could be satisfied with a traditional
	// property or a property with a "get" hook.
	public string $fullName {
		get;
	}
}

// The "User" class above satisfies this interface, but so does:

class SimpleUser implements Named
{
	public function __construct(public readonly string $fullName) {}
}

// -- Example #9

class User
{
	public function __construct(private string $first, private string $last) {}

	public string $fullName {
		get {
			return $this->first . ' ' . $this->last;
		}
	}
}

$u = new User('Larry', 'Garfield');

// prints "Larry Garfield"
print $u->fullName;

// -- Example #10

class Loud
{
	public string $name {
		get {
			return strtoupper($this->name);
		}
	}
}

$l = new Loud();
$l->name = 'larry';  // The stored value is "larry"

print $l->name;  // prints "LARRY"

// -- Example #11

class User
{
	public function __construct(private string $first, private string $last) {}

	public string $fullName {
		set(string $value) {
			[$this->first, $this->last] = explode(' ', $value, 2);
		}
	}

	public function getFirst(): string
	{
		return $this->first;
	}
}

$u = new User('Larry', 'Garfield');

$u->fullName = 'Ilija Tovilo';

// prints "Ilija"
print $u->getFirst();

// -- Example #12

class User
{
	public function __construct(public string $first, public string $last) {}

	public string $fullName {
		get {
			return "$this->first $this->last";
		}
		set(string $value) {
			[$this->first, $this->last] = explode(' ', $value, 2);
		}
	}
}

$u = new User('Larry', 'Garfield');

$u->fullName = 'Ilija Tovilo';

// prints "Ilija"
print $u->first;

// -- Example #13

class User
{
	public string $username {
		set(string $value) {
			if (strlen($value) > 10)
				throw new \InvalidArgumentException('Too long');
			$this->username = strtolower($value);
		}
	}
}

$u = new User();
$u->username = 'Crell';  // the set hook is called
print $u->username;  // prints "crell", no hook is called

$u->username = 'something_very_long';  // the set hook throws \InvalidArgumentException.

// -- Example #14

use Symfony\Component\String\UnicodeString;

class Person
{
	public UnicodeString $name {
		set(string | UnicodeString $value) {
			$this->name = $value instanceof UnicodeString ? $value : new UnicodeString($value);
		}
	}
}

// -- Example #15

class C
{
	public array $_names;

	public string $names {
		set {
			$this->_names = explode(',', $value, 2);
		}
	}
}

$c = new C();
var_dump($c->names = 'Ilija,Larry');  // 'Ilija,Larry'
var_dump($c->_names);  // ['Ilija', 'Larry']

// -- Example #16

class User
{
	public function __construct(private string $first, private string $last) {}

	public string $fullName {
		get {
			return $this->first . ' ' . $this->last;
		}
	}

	public string $fullName {
		get => $this->first . ' ' . $this->last;
	}
}

// -- Example #17

class User
{
	public string $fullName {
		set(string $value) {
			[$this->first, $this->last] = explode(' ', $value, 2);
		}
	}

	public string $fullName {
		set {
			[$this->first, $this->last] = explode(' ', $value, 2);
		}
	}
}

// -- Example #18

class User
{
	public string $username {
		set(string $value) {
			$this->username = strtolower($value);
		}
	}

	public string $username {
		set => strtolower($value);
	}
}

// -- Example #19

class Person
{
	public string $phone {
		set => $this->sanitizePhone($value);
	}

	private function sanitizePhone(string $value): string
	{
		$value = ltrim($value, '+');
		$value = ltrim($value, '1');

		if (!preg_match('/\d\d\d\-\d\d\d\-\d\d\d\d/', $value)) {
			throw new \InvalidArgumentException();
		}
		return $value;
	}
}

// -- Example #20

class Foo
{
	public string $bar;

	public string $baz {
		get => $this->baz;
		set => strtoupper($value);
	}
}

$x = 'beep';

$foo = new Foo();
// This is fine; as $bar is a normal property.
$foo->bar = &$x;

// This will error, as $baz is a
// set-hooked property and so references are not allowed.
$foo->baz = &$x;

// -- Example #21

class Foo
{
	public string $baz {
		&get {
			if (!isset($this->baz)) {
				$this->baz = $this->computeBaz();
			}
			return $this->baz;
		}
	}
}

$foo = new Foo();

// This triggers the get hook, which lazily computes and caches the string.
// It then returns it by reference.
print $foo->baz;

// This obtains a reference to the baz property.
$temp = &$foo->baz;

// $foo->baz is updated to "update".
$temp = 'update';

// -- Example #22

class Foo
{
	private string $_baz;

	public string $baz {
		&get => $this->_baz;
		set {
			$this->_baz = strtoupper($value);
		}
	}
}

$foo = new Foo();

// This invokes "set", and sets $_baz to "BEEP".
$foo->baz = 'beep';

// This assigns $x to be a reference directly to $_baz
$x = &$foo->baz;

// This assigns "boop" to $_baz, bypassing the set hook.
$x = 'boop';

// -- Example #23

class C
{
	public string $a {
		&get {
			$b = $this->a;
			return $b;
		}
	}
}

$c = new C();
$c->a = 'beep';
// $c is unchanged.

// -- Example #24

foreach ($someObjectWithHooks as $key => $value) {
	// Iterates all in-scope properties, using the 'get' operation if defined.
}

foreach ($someObjectWithHooks as $key => &$value) {
	// Throws an error if any in-scope property has a hook.
}

// -- Example #25

class Test
{
	public $array = [];

	public function getArray()
	{
		echo "getArray()\n";
		return $this->array;
	}

	public function setArray($array)
	{
		echo "setArray()\n";
		$this->array = $array;
	}
}

$test = new Test();

// This is what we actually want. The array is modified directly, without any performance overhead.
$test->array[] = 'foo';

// getArray() returns a temporary value, modifying it has no effect. This approach does not work.
$test->getArray()[] = 'foo';

// Storing the value from getArray() in a temporary variable, modifying it and assigning it back
// works as expected.  However, there's an implicit copy on line 2, because the array is referenced
// from both $array and $test->array. The array is copied, just for the copy to immediately
// overwrite the original value.
$array = $test->getArray();
$array[] = 'foo';
$test->setArray($array);

// -- Example #26

class Test
{
	// ...
	public function &getArray()
	{
		echo "getArray()\n";
		return $this->array;
	}

	// ...
}

// Now it works!
$test->getArray()[] = 'foo';

// -- Example #27

class Test
{
	public $array {
		&get {
			echo "getArray()\n";
			return $this->array;
		}
		set {
			echo "setArray()\n";
			$this->array = $value;
		}
	}
}

$test = new Test();
// Appending to an array invokes &get and modifies
// the array stored in the returned reference, bypassing
// the set hook entirely.
$test->array[] = 'foo';

// -- Example #28

class Test
{
	private $_array;

	public $array {
		get => $this->_array;
	}

	public function addElement($value)
	{
		// We can validate $value, without re-validating the entire array.
		$this->_array[] = $value;
	}
}

$test = new Test();
$test->addElement('foo');

// -- Example #30

class C
{
	public array $list {
		&get {
			$this->list ??= $this->defaultListValue();
			return $this->list;
		}
	}

	private function defaultListValue()
	{
		return ['a', 'b', 'c'];
	}
}

$c = new C();

print $c->list[1];  // prints b

// This calls the &get hook, which returns a reference
// to the backing value.  Then this code modifies that reference
// to append a value.  This is allowed, as there is no set hook.
$c->list[] = 'd';

print count($c->list);  // prints 4

// -- Example #31

class User
{
	public string $role = 'anonymous' {
		set => strlen($value) <= 10 ? $value : throw new \Exception('Too long');
	}
}

// -- Example #32

class Point
{
	public int $x;
	public int $y;
}

class PositivePoint extends Point
{
	public int $x {
		set {
			if ($value < 0) {
				throw new \InvalidArgumentException('Too small');
			}
			$this->x = $value;
		}
	}
}

// -- Example #33

class Point
{
	public int $x;
	public int $y;
}

class PositivePoint extends Point
{
	public int $x {
		set($x) {
			if ($x < 0) {
				throw new \InvalidArgumentException('Too small');
			}
			parent::$x::set($x);
		}
	}
}

// -- Example #34

class Strings
{
	public string $val;
}

class CaseFoldingStrings extends Strings
{
	public bool $uppercase = true;

	public string $val {
		get => $this->uppercase
			? strtoupper(parent::$val::get())
			: strtolower(parent::$val::get());
	}
}

// -- Example #35

class User
{
	public string $username {
		final set => strtolower($value);
	}
}

class Manager extends User
{
	public string $username {
		// This is allowed
		get => strtoupper($this->username);

		// But this is NOT allowed, because set is final in the parent.
		set => strtoupper($value);
	}
}

// -- Example #36

class User
{
	// Child classes may not add hooks of any kind to this property.
	public final string $name;

	// Child classes may not add any hooks or override set,
	// but this set will still apply.
	public final string $username {
		set => strtolower($value);
	}
}

// -- Example #37

interface I
{
	// An implementing class MUST have a publicly-readable property,
	// but whether or not it's publicly settable is unrestricted.
	public string $readable {
		get;
	}

	// An implementing class MUST have a publicly-writeable property,
	// but whether or not it's publicly readable is unrestricted.
	public string $writeable {
		set;
	}

	// An implementing class MUST have a property that is both publicly
	// readable and publicly writeable.
	public string $both {
		get;
		set;
	}
}

// This class implements all three properties as traditional, un-hooked
// properties. That's entirely valid.
class C1 implements I
{
	public string $readable;

	public string $writeable;

	public string $both;
}

// This class implements all three properties using just the hooks
// that are requested.  This is also entirely valid.
class C2 implements I
{
	private string $written = '';
	private string $all = '';

	// Uses only a get hook to create a virtual property.
	// This satisfies the "public get" requirement. It is not
	// writeable, but that is not required by the interface.
	public string $readable {
		get => strtoupper($this->writeable);
	}

	// The interface only requires the property be settable,
	// but also including get operations is entirely valid.
	// This example creates a virtual property, which is fine.
	public string $writeable {
		get => $this->written;
		set => $value;
	}

	// This property requires both read and write be possible,
	// so we need to either implement both, or allow it to have
	// the default behavior.
	public string $both {
		get => $this->all;
		set => strtoupper($value);
	}
}

// -- Example #38

abstract class A
{
	// Extending classes must have a publicly-gettable property.
	abstract public string $readable {
		get;
	}

	// Extending classes must have a protected- or public-writeable property.
	abstract protected string $writeable {
		set;
	}

	// Extending classes must have a protected or public symmetric property.
	abstract protected string $both {
		get;
		set;
	}
}

class C extends A
{
	// This satisfies the requirement and also makes it settable, which is valid.
	public string $readable;

	// This would NOT satisfy the requirement, as it is not publicly readable.
	protected string $readable;

	// This satisfies the requirement exactly, so is sufficient. It may only
	// be written to, and only from protected scope.
	protected string $writeable {
		set => $value;
	}

	// This expands the visibility from protected to public, which is fine.
	public string $both;
}

// -- Example #39

abstract class A
{
	// This provides a default (but overridable) set implementation, and requires
	// child classes to provide a get implementation.
	abstract public string $foo {
		get;
		set {
			$this->foo = $value;
		}
	}
}

// -- Example #40

class Animal {}
class Dog extends Animal {}
class Poodle extends Dog {}

interface PetOwner
{
	// Only a get operation is required, so this may be covariant.
	public Animal $pet {
		get;
	}
}

class DogOwner implements PetOwner
{
	// This may be a more restrictive type since the "get" side
	// still returns an Animal.  However, as a native property
	// children of this class may not change the type anymore.
	public Dog $pet;
}

class PoodleOwner extends DogOwner
{
	// This is NOT ALLOWED, because DogOwner::$pet has both
	// get and set operations defined and required.
	public Poodle $pet;
}

// -- Example #41

class C
{
	private string $name {
		get => $this->name;
		set => ucfirst($value);
	}

	public function __set($var, $val)
	{
		print "In __set\n";
		$this->$var = $val;
	}
}

$c = new C();

$c->name = 'picard';

// prints "In __set"
// $c->name now has the value "Picard"

// -- Example #42

class User
{
	public function __construct(
		public string $username {
			set => strtolower($value);
		}
	) {}
}

// -- Example #44

#[Attribute(Attribute::TARGET_METHOD)]
class A {}

#[Attribute(Attribute::TARGET_METHOD)]
class B {}

class C
{
	public $prop {
		#[A]
		get {
		}
		#[B]
		set {
		}
	}
}

$getAttr = (new ReflectionProperty(C::class, 'prop'))
	->getHook(PropertyHookType::Get)
	->getAttributes()[0];
$aAttrib = $getAttr->getInstance();

// $aAttrib is an instance of A.

// -- Example #45

class C
{
	public int $prop {
		set(
			#[SensitiveParameter]
			int $value
		) {
			throw new Exception('Exception from $prop');
		}
	}
}

$c = new C();
$c->prop = 'secret';
// Exception: Exception from $prop in %s:%d
// Stack trace:
// #0 example.php(4): C->$prop::set(Object(SensitiveParameterValue))
// #1 {main}

// -- Example #48

class A
{
	public static $prop = 'C';
}

class B extends A
{
	public function test()
	{
		return parent::$prop::get();
	}
}

class C
{
	public static function get()
	{
		return 'Hello from C::get';
	}
}

// -- Example #49

class B extends A
{
	public function test()
	{
		$class = parent::$prop;
		return $class::get();
	}
}
