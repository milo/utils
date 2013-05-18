<?php

if (!is_file($autoloadFile = __DIR__ . '/../vendor/autoload.php')) {
	echo "Tester not found. Install Nette Tester using 'composer update --dev'.\n";
	exit(1);
}
include $autoloadFile;
unset($autoloadFile);


Tester\Helpers::setup();
class_alias('Tester\Assert', 'Assert');
date_default_timezone_set('UTC');


if (extension_loaded('xdebug')) {
	xdebug_disable();
	Tester\CodeCoverage\Collector::start(__DIR__ . '/coverage.dat');
}


/**
 * Creates an empty temporary directory.
 * @return string
 */
function createTempDir() {
	$tmp = __DIR__ . '/tmp';
	if (!is_dir($tmp)) @mkdir($tmp);

	$tmp = "$tmp/" . md5(serialize(isset($_SERVER['argv']) ? $_SERVER['argv'] : array()));
	if (!is_dir($tmp)) @mkdir($tmp);

	Tester\Helpers::purge($tmp);

	return realpath($tmp);
}
