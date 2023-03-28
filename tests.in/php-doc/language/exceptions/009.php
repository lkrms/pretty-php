<?php
/**
 * Define a custom exception class
 */
class MyException extends Exception
{
    // Redefine the exception so message isn't optional
    public function __construct($message, $code = 0, Throwable $previous = null) {
        // some code

        // make sure everything is assigned properly
        parent::__construct($message, $code, $previous);
    }

    // custom string representation of object
    public function __toString() {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }

    public function customFunction() {
        echo "A custom function for this type of exception\n";
    }
}


/**
 * Create a class to test the exception
 */
class TestException
{
    public $var;

    const THROW_NONE    = 0;
    const THROW_CUSTOM  = 1;
    const THROW_DEFAULT = 2;

    function __construct($avalue = self::THROW_NONE) {

        switch ($avalue) {
            case self::THROW_CUSTOM:
                // throw custom exception
                throw new MyException('1 is an invalid parameter', 5);
                break;

            case self::THROW_DEFAULT:
                // throw default one.
                throw new Exception('2 is not allowed as a parameter', 6);
                break;

            default:
                // No exception, object will be created.
                $this->var = $avalue;
                break;
        }
    }
}


// Example 1
try {
    $o = new TestException(TestException::THROW_CUSTOM);
} catch (MyException $e) {      // Will be caught
    echo "Caught my exception\n", $e;
    $e->customFunction();
} catch (Exception $e) {        // Skipped
    echo "Caught Default Exception\n", $e;
}

// Continue execution
var_dump($o); // Null
echo "\n\n";


// Example 2
try {
    $o = new TestException(TestException::THROW_DEFAULT);
} catch (MyException $e) {      // Doesn't match this type
    echo "Caught my exception\n", $e;
    $e->customFunction();
} catch (Exception $e) {        // Will be caught
    echo "Caught Default Exception\n", $e;
}

// Continue execution
var_dump($o); // Null
echo "\n\n";


// Example 3
try {
    $o = new TestException(TestException::THROW_CUSTOM);
} catch (Exception $e) {        // Will be caught
    echo "Default Exception caught\n", $e;
}

// Continue execution
var_dump($o); // Null
echo "\n\n";


// Example 4
try {
    $o = new TestException();
} catch (Exception $e) {        // Skipped, no exception
    echo "Default Exception caught\n", $e;
}

// Continue execution
var_dump($o); // TestException
echo "\n\n";
?>