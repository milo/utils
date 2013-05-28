Milo's utilities
================
This repo is a bunch of single-purpose classes and scripts. Well, at now it is a very tiny bunch ;)

You may use all files under the terms of the New BSD Licence, or the GNU Public Licence (GPL) version 2 or 3.

All classes are defined in `Milo\Utils` namespace.


------


[AliasExpander](https://github.com/milo/utils/blob/master/Utils/AliasExpander.php)
----------------------------------------------------------------------------------
It is a tool for run-time class alias expanding to its fully qualified name. In brief, it is a workaround for missing `::class` constant from PHP 5.5 in PHP 5.3+ and a helper for annotations processing.

```php
# Ordinary 'use' usage in namespaced code. But how to expand alias to full class name?
use Other\Lib as OL;

# in PHP 5.5+
echo OL::class;  // 'Other\Lib'

# in PHP 5.3+
echo AliasExpander::expand('OL');  // 'Other\Lib'


# If the static call is too long for you, wrap it in own function. It will be easy to replace when upgrade to PHP 5.5.
function aliasFqn($alias) {
	return \Milo\Utils\AliasExpander::expand($alias, 1);
}


# Due to performance, it is good to set writable directory for caching.
AliasExpander::getInstance()->setCacheDir('/path/to/tmp');


# If you want to be strict and ensure that alias expands only to defined class name, set exists checking.
AliasExpander::getInstance()->setExistsCheck(TRUE);
# or
AliasExpander::getInstance()->setExistsCheck(E_USER_WARNING);


# Expanding alias in explicitly specified file and line context is useful for annotations processing.
$method = new ReflectionMethod($object, 'method');
AliasExpander::expandExplicit('NS\Alias', $method->getFileName(), $method->getStartLine());
```

There are some limitations:
- One line code like `namespace First; AliasExpander::expand('Foo'); namespace Second;` may leads to wrong expanding. It is not so easy to implement it because PHP tokenizer and debug_backtrace() provides only line number, but not the column. This can be a problem in minified code.
- Keywords `self`, `static` and `parent` are not expanded as in PHP 5.5, but this can be easily solved by `__CLASS__`, `get_called_class()` and `get_parent_class()` instead of AliasExpander using.


------


[PgsqlArray](https://github.com/milo/utils/blob/master/Utils/PgsqlArray.php)
----------------------------------------------------------------------------
Helper for conversion of PostgreSQL arrays to PHP array and vice versa.

```php
# From PHP to PostgreSQL
$array = array('a', 'b', 'c');
PgsqlArray::toStringLiteral($array);  #  {"a","b","c"}
PgsqlArray::toSql($array);            # '{"a","b","c"}'


# From PostgreSQL to PHP
$type = PgsqlArray::TYPE_INTEGER;  # or use pg_field_type(), e.g. _int8
PgsqlArray::fromString('{1,2,NULL}', $type);  # array(1, 2, NULL);


# Timestamps supported
PgsqlArray::fromString('{"1920-05-06 16:24:00+01"}', PgsqlArray::TYPE_TIMESTAMP_TZ);
```

`Note` Very big integers or floats are left as string when cannot be represented in PHP native type.


------


[![Build Status](https://travis-ci.org/milo/utils.png?branch=master)](https://travis-ci.org/milo/utils)
