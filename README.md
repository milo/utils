Milo's utilities
================
This repo is a bunch of single-purpose classes and scripts. Well, at now it is a very tiny bunch ;)

You may use all files under the terms of the New BSD Licence, or the GNU Public Licence (GPL) version 2 or 3.

All classes are defined in `Milo\Utils` namespace.


[AliasExpander](https://github.com/milo/utils/blob/master/Utils/AliasExpander.php)
----------------------------------------------------------------------------------
It is a tool for run-time class alias expanding to its fully qualified name. In brief, it is a workaround for missing `::class` constant from PHP 5.5 in PHP 5.3+.

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


# Sometimes may be handy expand alias in explicitly specified file and line context.
AliasExpander::expandExplicit('NS\Alias', '/dir/file.php', 231);
```

Because this class is a workaround in principle, there are some limitations:
- Code in one line like `namespace Name\Space; use Foo as F; namespace Second;` may leads to wrong expanding. It is not so easy to implement it because PHP tokenizer provides only token's line, but not the column. This can be a problem in minified code.
- Keywords `self`, `static` and `parent` are not expanded as in PHP 5.5, but this can be easily solved by `__CLASS__`, `get_called_class()` and `get_parent_class()` instead of AliasExpander using.

------

[![Build Status](https://travis-ci.org/milo/utils.png?branch=master)](https://travis-ci.org/milo/utils)
