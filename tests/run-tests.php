#!/usr/bin/env php
<?php

set_time_limit(0);
error_reporting(E_ALL);
ini_set('display_errors', 1);



$runnerScript = realpath(__DIR__ . '/../vendor/nette/tester/Tester/tester.php');
if ($runnerScript === FALSE) {
	echo "Nette Tester is missing. You can install it using Composer:\n";
	echo "php composer.phar update --dev\n";
	exit(2);
}



# Default values
$jobsNum = 20;
$phpIni = NULL;



# Command line arguments processing
$runnerArgs = array();
$args = isset($_SERVER['argv']) ? array_slice($_SERVER['argv'], 1) : array();
while (count($args)) {
	$arg = array_shift($args);
	if ($arg === '-j') {
		if (($jobsNum = array_shift($args)) === NULL) {
			echo "Missing argument for -j option.\n";
			exit(2);
		}

	} elseif ($arg === '-c') {
		if (($phpIni = array_shift($args)) === NULL) {
			echo "Missing argument for -c option.\n";
			exit(2);
		}

	} else {
		$runnerArgs[] = $arg;
	}
}



# Command building
$command = array_merge(
	array('php'),
	isset($phpIni) ? array('-c', $phpIni) : array(),
	array($runnerScript),
	isset($phpIni) ? array('-c', $phpIni) : array(),
	array('-j', $jobsNum),
	$runnerArgs
);
$command = implode(' ', array_map('escapeshellarg', $command));



# Tests running
$descriptors = array(
	0 => array('file', 'php://stdin', 'r'),
	1 => array('file', 'php://stdout', 'a'),
	2 => array('file', 'php://stderr', 'a'),
);
$proc = @proc_open($command, $descriptors, $pipes, NULL, NULL, array('bypass_shell' => TRUE));
if ($proc === FALSE) {
	$err = error_get_last();
	echo "Cannot run tester command $command\n";
	echo "Error: $err[message]\n";
	exit(2);
}

do {
	$stat = proc_get_status($proc);
	if ($stat['running'] === FALSE) {
		$retCode = $stat['exitcode'];
		break;
	}
	usleep(100000);
} while (TRUE);

exit($retCode);
