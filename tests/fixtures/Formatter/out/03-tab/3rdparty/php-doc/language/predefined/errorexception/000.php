<?php
set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline) {
	if (!(error_reporting() & $errno)) {
		// This error code is not included in error_reporting.
		return;
	}
	if ($errno === E_DEPRECATED || $errno === E_USER_DEPRECATED) {
		// Do not throw an Exception for deprecation warnings as new or unexpected
		// deprecations would break the application.
		return;
	}
	throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
});

// Unserializing broken data triggers a warning which will be turned into an
// ErrorException by the error handler.
unserialize('broken data');
?>