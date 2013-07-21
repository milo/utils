Milo's utilities
================
This repo is a bunch of single-purpose classes and scripts. Well, at now it is a very tiny bunch ;)

You may use all files under the terms of the New BSD Licence, or the GNU Public Licence (GPL) version 2 or 3.

All classes are defined in `Milo\Utils` namespace.


------


[AliasExpander]()
-----------------
It is a tool for run-time class alias expanding to its fully qualified name. In brief, it is a workaround for missing `::class` constant from PHP 5.5 in PHP 5.3+ and a helper for annotations processing.

AliasExpander has been moved to own [repository](https://github.com/milo/alias-expander).


------


[PgsqlArray](https://github.com/milo/utils/blob/master/Utils/PgsqlArray.php)
----------------------------------------------------------------------------
Helper for conversion of PostgreSQL arrays to PHP array and vice versa.

```php
# PHP --> PostgreSQL
$array = array('a', 'b', 'c', NULL);
PgsqlArray::toStringLiteral($array);  #  {"a","b","c",NULL}
PgsqlArray::toSql($array);            # '{"a","b","c",NULL}'


# PostgreSQL --> PHP, result array(1, 2, NULL)
PgsqlArray::fromString('{1,2,NULL}', PgsqlArray::TYPE_INTEGER); # or
PgsqlArray::fromString('{1,2,NULL}', pg_field_type(...));


# Timestamps supported, result array(object DateTime(...))
PgsqlArray::fromString('{"1920-05-06 16:24:00+01"}', PgsqlArray::TYPE_TIMESTAMP_TZ);


# Multidimensional arrays supported, result array(array(1, 2), array(3, 4), array(5, 6))
PgsqlArray::fromString('{{1,2},{3,4},{5,6}}', PgsqlArray::TYPE_INTEGER);
```

`Note` Very big integers or floats are left as string when cannot be represented in PHP native type.


------


[![Build Status](https://travis-ci.org/milo/utils.png?branch=master)](https://travis-ci.org/milo/utils)
