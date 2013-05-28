<?php

namespace Milo\Utils;



/**
 * Helper for conversion of PostgreSQL arrays to PHP array and vice versa.
 *
 * You can choose one of three licences:
 *
 * @licence New BSD License
 * @licence GNU General Public License version 2
 * @licence GNU General Public License version 3
 *
 * @see https://github.com/milo/utils
 *
 * @author  Miloslav Hůla (https://github.com/milo)
 */
class PgsqlArray
{
	/** PHP native types */
	const
		TYPE_TEXT = 'string',
		TYPE_INTEGER = 'int',
		TYPE_FLOAT = 'float',
		TYPE_BOOL = 'bool',
		TYPE_DATE = 'date',
		TYPE_TIME = 'time',
		TYPE_TIME_TZ = 'timetz',
		TYPE_TIMESTAMP = 'timestamp',
		TYPE_TIMESTAMP_TZ = 'timestamptz';



	/**
	 * Converts array() to escaped SQL string '{item,item,...}'.
	 * @param  array
	 * @return string  escaped, ready to be used in SQL query directly
	 */
	public static function toSql(array $arr = NULL)
	{
		$str = self::toStringLiteral($arr);
		$sql = self::quoteStringLiteral($str);
		return $sql;
	}



	/**
	 * Converts array() to string literal {item,item,...}.
	 * @param  array
	 * @return string  non-escaped, must be quoted before using in SQL query
	 */
	public static function toStringLiteral(array $arr = NULL)
	{
		if ($arr === NULL) {
			return 'NULL';
		}

		$elements = array();
		foreach ($arr as $v) {
			if ($v === NULL) {
				$elements[] = 'NULL';

			} elseif (is_int($v) || is_float($v)) {
				$elements[] = (string) $v;

			} elseif (is_bool($v)) {
				$elements[] = $v ? 't' : 'f';

			} elseif ($v instanceof \DateTime) {
				$elements[] = '"' . $v->format('Y-m-d H:i:s.uP') . '"';

			} elseif (is_array($v)) {
				$elements[] = self::toStringLiteral($v);

			} elseif ($v instanceof \Traversable) {
				$elements[] = self::toStringLiteral(iterator_to_array($v, FALSE));

			} else {
				$elements[] = self::escapeArrayElement((string) $v);
			}
		}

		return '{' . implode(',', $elements) . '}';
	}



	/**
	 * Creates array() from string {item,item,...}.
	 * @param  NULL|string  array in PostgreSQL syntax
	 * @param  string  type of array items; self::TYPE_* or pg_field_type() e.g. _int8
	 * @param  string  explicit format for date/time types
	 * @return NULL|array
	 * @throws \InvalidArgumentException  when passed syntactically malformed array string
	 */
	public static function fromString($string, $type = self::TYPE_TEXT, $format = NULL)
	{
		if ($string === NULL) {
			return NULL;
		}

		$type = self::deriveType($type);

		$length = strlen($string);
		if ($length < 1) {
			throw new \InvalidArgumentException('Empty string');
		} elseif ($string[0] !== '{') {
			throw new \InvalidArgumentException('Array in string must starts by { character');
		}

		$levelUp = $current = array();
		$item = NULL;
		$inQuotes = $inBackslash = FALSE;
		for ($i = 0; $i < $length; $i++) {
			$chr = $string[$i];

			if ($inBackslash) {
				$inBackslash = FALSE;
				$item .= $chr;

			} elseif ($chr === '\\') {
				$inBackslash = TRUE;

			} elseif ($inQuotes) {
				if ($chr === '"') {
					$inQuotes = FALSE;
					$current[] = self::convertTo($type, $item, TRUE, $format);
					$item = NULL;
				} else {
					$item .= $chr;
				}

			} elseif ($chr === '"') {
				$inQuotes = TRUE;
				$item = '';

			} elseif ($chr === '{') {
				$index = count($levelUp);
				$levelUp[$index] = & $current;

				$index = count($current);
				$current[$index] = array();
				$current = & $current[$index];

			} elseif ($chr === '}') {
				if ($item !== NULL) {
					$current[] = self::convertTo($type, $item, FALSE, $format);
					$item = NULL;
				}

				$index = count($levelUp) - 1;
				if ($index < 0) {
					throw new \InvalidArgumentException('Too much closing curly brackets');
				}

				$current = & $levelUp[$index];
				unset($levelUp[$index]);

			} elseif ($chr === ',') {
				if ($item !== NULL) {
					$current[] = self::convertTo($type, $item, FALSE, $format);
					$item = NULL;
				}

			} elseif ($chr === ' ' || $chr === "\t" || $chr === "\n" || $chr === "\r") {
				// ignore whitespaces

			} else {
				$item .= $chr;
			}

		}

		if (count($levelUp)) {
			throw new \InvalidArgumentException('Missing closing curly bracket');
		}

		return $current[0];
	}



	private static function escapeArrayElement($element)
	{
		return '"' . strtr($element, array('\\' => '\\\\', '"' => '\\"')) . '"';
	}



	private static function quoteStringLiteral($str)
	{
		return "E'" . strtr($str, array('\\' => '\\\\', "'" => "''")) . "'";
	}



	private static function deriveType($type)
	{
		static $cache = array(
			self::TYPE_TEXT => self::TYPE_TEXT,
			self::TYPE_INTEGER => self::TYPE_INTEGER,
			self::TYPE_FLOAT => self::TYPE_FLOAT,
			self::TYPE_BOOL => self::TYPE_BOOL,
			self::TYPE_DATE => self::TYPE_DATE,
			self::TYPE_TIME => self::TYPE_TIME,
			self::TYPE_TIME_TZ => self::TYPE_TIME_TZ,
			self::TYPE_TIMESTAMP => self::TYPE_TIMESTAMP,
			self::TYPE_TIMESTAMP_TZ => self::TYPE_TIMESTAMP_TZ,
		);

		if (isset($cache[$type])) {
			return $cache[$type];
		}

		static $patterns = array(
			'text|char|bytea|interval|money' => self::TYPE_TEXT,
			'int|serial' => self::TYPE_INTEGER,
			'numeric|real|double' => self::TYPE_FLOAT,
			'timetz' => self::TYPE_TIME_TZ,
			'time' => self::TYPE_TIME,
			'timestamptz' => self::TYPE_TIMESTAMP_TZ,
			'timestamp' => self::TYPE_TIMESTAMP,
			'date' => self::TYPE_DATE,
			'bool' => self::TYPE_BOOL,
		);

		foreach ($patterns as $pattern => $t) {
			if (preg_match("#$pattern#i", $type)) {
				return $types[$type] = $t;
			}
		}

		return $types[$type] = self::TYPE_TEXT;
	}



	private static function convertTo($type, $value, $wasQuoted, $format = NULL)
	{
		if ($value === 'NULL' && !$wasQuoted) {
			return NULL;
		}

		switch ($type) {
			case self::TYPE_TEXT:
				return $value;

			case self::TYPE_INTEGER:
				return is_float($tmp = $value * 1) ? $value : $tmp;

			case self::TYPE_FLOAT:
				if (strpos($value, '.') !== FALSE) {
					$value = rtrim(rtrim($value, '0'), '.');
				}
				$float = (float) $value;
				return (string) $float === $value ? $float : $value;

			case self::TYPE_BOOL:
				return $value === 'f' ? FALSE : (bool) $value;

			case self::TYPE_DATE:
				$format = $format === NULL ? 'Y-m-d' : $format;

			case self::TYPE_TIME:
				$format = $format === NULL ? 'H:i:s' : $format;

			case self::TYPE_TIME_TZ:
				$format = $format === NULL ? 'H:i:sP' : $format;

			case self::TYPE_TIMESTAMP:
				$format = $format === NULL ? 'Y-m-d H:i:s' : $format;

			case self::TYPE_TIMESTAMP_TZ:
				$format = $format === NULL ? 'Y-m-d H:i:sP' : $format;
				if (($return = \DateTime::createFromFormat($format, $value)) === FALSE) {
					throw new \InvalidArgumentException("Cannot convert value '$value' to DateTime by format '$format'");
				}
				return $return;
		}

		throw new \IvalidArgumentException("Type '$type' conversion is not implemented.");
	}

}
