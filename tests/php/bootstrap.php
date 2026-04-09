<?php
/**
 * PHPUnit bootstrap file.
 *
 * Loads the Composer autoloader and defines minimal WordPress function
 * stubs so feature classes can be tested in isolation.
 */

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

// Load WordPress function stubs for unit testing.
require_once __DIR__ . '/stubs.php';
