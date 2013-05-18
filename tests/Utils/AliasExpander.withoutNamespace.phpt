<?php

/**
 * Test: AliasExpander basics
 *
 * @author  Miloslav Hůla
 */

require __DIR__ . '/../bootstrap.php';

use Tester\Assert,
	Milo\Utils\AliasExpander;



use Second as Sec;

class Foo {};

class_alias('Foo', 'Bar');


$cases = array(
	'\Absolute' => 'Absolute',
	'First' => 'First',
	'Sec' => 'Second',
	'Foo' => 'Foo',
	'Bar' => 'Bar',
);

foreach ($cases as $alias => $expanded) {
	Assert::same( $expanded, AliasExpander::expand($alias) );
}
