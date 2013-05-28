<?php

/**
 * Test: PgsqlArray functionality
 *
 * @author  Miloslav Hůla
 */

use	Milo\Utils\PgsqlArray;

require __DIR__ . '/../bootstrap.php';



class TraversableTest implements IteratorAggregate {
	public function getIterator() {
		return new ArrayIterator(array(1,2,3));
	}
}



/* PHP -> PostgreSQL */
foreach (array(
	array(
		NULL,
		'NULL',
	),

	array(
		array(NULL, 'NULL', 0, 1, -1, 0.9, TRUE, FALSE, 'str', ' '),
		'{NULL,"NULL",0,1,-1,0.9,t,f,"str"," "}',
	),

	array(
		array(array(), array(1), NULL, new TraversableTest),
		'{{},{1},NULL,{1,2,3}}',
	),

	array(
		array(DateTime::createFromFormat('Y-m-d H:i:s.uP', '2000-01-02 15:16:17.231+02:30')),
		'{"2000-01-02 15:16:17.231000+02:30"}',
	),
) as $value) {
	Assert::same($value[1], PgsqlArray::toStringLiteral($value[0]));
}



$value = array(
	array('{', '}', '"', '\\', "'"),
	'{"{","}","\\"","\\\\","\'"}',
	"E'" . '{"{","}","\\\\"","\\\\\\\\","\'\'"}' . "'",
);
Assert::same($value[1], PgsqlArray::toStringLiteral($value[0]));
Assert::same($value[2], PgsqlArray::toSql($value[0]));



/* PostgreSQL -> PHP */
Assert::exception(function() {
	PgsqlArray::fromString('');
}, 'InvalidArgumentException', 'Empty string');

Assert::exception(function() {
	PgsqlArray::fromString('foo');
}, 'InvalidArgumentException', 'Array in string must starts by { character');

Assert::exception(function() {
	PgsqlArray::fromString('{{');
}, 'InvalidArgumentException', 'Missing closing curly bracket');

Assert::exception(function() {
	PgsqlArray::fromString('{}}');
}, 'InvalidArgumentException', 'Too much closing curly brackets');

Assert::same(NULL, PgsqlArray::fromString(NULL));



foreach (array(
	'{NULL}' => array(NULL),
	'{"NULL"}' => array('NULL'),
	"{'NULL'}" => array("'NULL'"),
	'{a,b\\"b,"c\\"c","d\\\\\\"d"}' => array('a', 'b"b', 'c"c', 'd\\"d'),
	'{"{","}",",","{a,b}"}' => array('{', '}', ',', '{a,b}'),
	'{1}' => array('1'),
	'{1.0}' => array('1.0'),
	'{t,f}' => array('t', 'f'),
	"{ a \r\n\t , \" \r\n\t\"}" => array('a', " \r\n\t"),
) as $string => $expected) {
	Assert::same($expected, PgsqlArray::fromString($string));
}



Assert::same(
	array(0, 1, '999999999999999999999999999999999999999999'),
	PgsqlArray::fromString('{0,1,999999999999999999999999999999999999999999}', PgsqlArray::TYPE_INTEGER)
);

Assert::same(
	array(0.0, 1.1, '0.999999999999999999999999999999999'),
	PgsqlArray::fromString('{0.0,1.1,0.999999999999999999999999999999999000}', PgsqlArray::TYPE_FLOAT)
);

Assert::same(
	array(TRUE, FALSE, TRUE, FALSE),
	PgsqlArray::fromString('{t,f,1,0}', PgsqlArray::TYPE_BOOL)
);

Assert::equal(
	array(DateTime::createFromFormat('Y-m-d', '2013-05-01')),
	PgsqlArray::fromString('{2013-05-01}', PgsqlArray::TYPE_DATE)
);

Assert::equal(
	array(DateTime::createFromFormat('H:i:s', '12:00:00')),
	PgsqlArray::fromString('{12:00:00}', PgsqlArray::TYPE_TIME)
);

Assert::equal(
	array(
		DateTime::createFromFormat('H:i:sP', '12:00:00+02'),
		DateTime::createFromFormat('H:i:sP', '12:00:00+02:30'),
	),
	PgsqlArray::fromString('{12:00:00+02,12:00:00+02:30}', PgsqlArray::TYPE_TIME_TZ)
);

Assert::equal(
	array(DateTime::createFromFormat('Y-m-d H:i:s', '2013-05-01 12:00:00')),
	PgsqlArray::fromString('{"2013-05-01 12:00:00"}', PgsqlArray::TYPE_TIMESTAMP)
);

Assert::equal(
	array(DateTime::createFromFormat('Y-m-d H:i:s', '2012-12-24 00:00:00')),
	PgsqlArray::fromString('{"apocalypse: 2012-12-24 00:00:00"}', PgsqlArray::TYPE_TIMESTAMP, '\a\p\o\c\a\l\y\p\s\e\:\ Y-m-d H:i:s')
);



Assert::same(
	array(1),
	PgsqlArray::fromString('{1}', '_int8')
);

Assert::same(
	array('1'),
	PgsqlArray::fromString('{1}', '_interval')
);
