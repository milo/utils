<?php

/**
 * Test: AliasExpander basics
 *
 * @author  Miloslav Hůla
 */

namespace Test\Space;

use Tester\Assert,
	Milo\Utils\AliasExpander;

require __DIR__ . '/../bootstrap.php';



use First, Second as Sec;
use Third as Thi;

$cases = array(
	'\Absolute' => 'Absolute',
	'\Absolute\Foo' => 'Absolute\Foo',

	'First' => 'First',
	'First\Foo' => 'First\Foo',
	'Foo\First' => __NAMESPACE__ . '\Foo\First',
	'Foo\First\Bar' => __NAMESPACE__ . '\Foo\First\Bar',

	'Sec' => 'Second',
	'Sec\Foo' => 'Second\Foo',
	'Foo\Sec' => __NAMESPACE__ . '\Foo\Sec',
	'Foo\Sec\Bar' => __NAMESPACE__ . '\Foo\Sec\Bar',
	'Second' => __NAMESPACE__ . '\Second',
	'Second\Foo' => __NAMESPACE__ . '\Second\Foo',

	'Thi' => 'Third',
);

foreach ($cases as $alias => $expanded) {
	Assert::same( $expanded, AliasExpander::expand($alias) );
}



/* 'use' clause in code */
Assert::same( __NAMESPACE__ . '\Fif', AliasExpander::expand('Fif') );
use Fifth as Fif;
Assert::same( 'Fifth', AliasExpander::expand('Fif') );



/* Switch namespace */
namespace Test\Universe;

use Tester\Assert,
	Milo\Utils\AliasExpander;


use Sixth as Six;

Assert::same( __NAMESPACE__ . '\First', AliasExpander::expand('First') );
Assert::same( 'Sixth', AliasExpander::expand('Six') );

Assert::same( __NAMESPACE__ . '\Sec', AliasExpander::expand('Sec') );
use Second as Sec;
Assert::same( 'Second', AliasExpander::expand('Sec') );



/* Wrapping expand() */
function wrapOne($alias, $depth) {
	return AliasExpander::expand($alias, $depth + 1);
}

function wrapTwo($alias, $depth) {
	return wrapOne($alias, $depth + 1);
}

function aliasFqn($alias) {
	return wrapTwo($alias, 1);
}

Assert::same( 'Second', aliasFqn('Sec') );



/* Class existency check */
use Nonexists as Non;

Assert::same( 'Nonexists', AliasExpander::expand('Non') );

AliasExpander::getInstance()->setExistsCheck(TRUE);

Assert::exception( function() {
	AliasExpander::expand('Non');
}, 'RuntimeException', 'Class Nonexists not found');
