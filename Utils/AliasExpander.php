<?php

namespace Milo\Utils;



/**
 * Tool for run-time class alias expanding (emulate the ::class from PHP 5.5)
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
class AliasExpander
{
	/** @var self  singleton self instance */
	private static $instance;

	/** @var string  cache dir path */
	protected $cacheDir;

	/** @var \ArrayIterator */
	private $tokens;

	/** @var array  cache of aliases */
	private $cache;

	/** @var bool|int */
	private $checkSeverity = FALSE;

	/** @var bool */
	private $checkAutoload = TRUE;



	/**
	 * @throws \LogicException  when more then one instance is created
	 */
	final public function __construct()
	{
		if (self::$instance !== NULL) {
			throw new \LogicException('Class is singleton. Use ' . __CLASS__ . '::getInstance() instead.');
		}
		self::$instance = $this;
	}



	/**
	 * Returns singleton instance.
	 * @return self
	 */
	final public static function getInstance()
	{
		if (self::$instance === NULL) {
			self::$instance = new static;
		}
		return self::$instance;
	}



	/**
	 * Sets cache dir.
	 * @param  string  path to cache directory
	 * @return self
	 * @throws \RuntimeException  if cache dir is not writable
	 */
	public function setCacheDir($dir)
	{
		$dir = $dir . DIRECTORY_SEPARATOR . 'AliasExpander';
		if (!is_dir($dir)) {
			@mkdir($dir);
			$err = error_get_last();
			if (!is_dir($dir)) {
				throw new \RuntimeException("Cannot create cache directory '$dir': $err[message]");
			}
		}
		$this->cacheDir = $dir;
		return $this;
	}



	/**
	 * Check if the class expanded from the alias has been defined.
	 * @param  bool|int  FALSE = off, TRUE = RuntimeException, int = user error level (E_USER_NOTICE, ...)
	 * @param  bool  perform autoload
	 * @return self
	 */
	public function setExistsCheck($check, $autoload = TRUE)
	{
		$this->checkSeverity = $check;
		$this->checkAutoload = (bool) $autoload;
		return $this;
	}



	/**
	 * Expands class alias in context where this method is called.
	 * @param  string  class alias
	 * @param  int  how deep is wrapped this method call
	 * @return string
	 * @throws \RuntimeException  when origin of call cannot be found in backtrace
	 * @throws \LogicException  when empty alias name passed
	 */
	final public static function expand($alias, $depth = 0)
	{
		$bt = PHP_VERSION_ID < 50400
			? debug_backtrace(FALSE)
			: debug_backtrace(FALSE, $depth + 1);

		if (!isset($bt[$depth]['file'], $bt[$depth]['line'])) {
			throw new \RuntimeException('Cannot find an origin of call in backtrace.');
		}

		return self::expandExplicit($alias, $bt[$depth]['file'], $bt[$depth]['line']);
	}



	/**
	 * Expands class alias in file:line context.
	 * @param  string  class alias
	 * @param  string
	 * @param  int
	 * @return string
	 * @throws \LogicException  when empty class alias name passed
	 */
	final public static function expandExplicit($alias, $file, $line = 0)
	{
		if (empty($alias)) {
			throw new \LogicException('Alias name must not be empty.');
		}

		if ($alias[0] === '\\') { // already fully qualified
			$return = ltrim($alias, '\\');

		} else {
			if (($pos = strpos($alias, '\\')) === FALSE) {
				$lAlias = strtolower($alias);
				$suffix = '';
			} else {
				$lAlias = strtolower(substr($alias, 0, $pos));
				$suffix = substr($alias, $pos);
			}

			$parsed = self::getInstance()->parse($file);
			$next = each($parsed);
			do {
				list($nsLine, $data) = $next;
				$next = each($parsed);
			} while ($next !== FALSE && $next[0] < $line);

			if (isset($data['aliases'][$lAlias]) && $line > $data['aliases'][$lAlias][1]) {
				$return = $data['aliases'][$lAlias][0] . $suffix;
			} else {
				$return = $data['namespace'] === '' ? $alias : $data['namespace'] . '\\' . $alias;
			}
		}

		$instance = self::getInstance();
		if (!empty($instance->checkSeverity) && !class_exists($return, $instance->checkAutoload)) {
			$message = "Class $return not found";
			if (is_int($instance->checkSeverity)) {
				trigger_error($message, $instance->checkSeverity);
			} else {
				throw new \RuntimeException($message);
			}
		}

		return $return;
	}



	/**
	 * Loads data from cache.
	 * @param  string
	 * @return mixed
	 */
	protected function load($file)
	{
		if (isset($this->cache[$file])) {
			return $this->cache[$file];
		}

		if ($this->cacheDir !== NULL
			&& is_file($cacheFile = $this->cacheFileFor($file))
			&& filemtime($file) < filemtime($cacheFile)
		) {
			if (($fd = fopen($cacheFile, 'r')) === FALSE || flock($fd, LOCK_SH) === FALSE) {
				return NULL;
			}
			$cached = require $cacheFile;
			flock($fd, LOCK_UN);
			fclose($fd);

			return $this->cache[$file] = $cached;
		}

		return NULL;
	}



	/**
	 * Store data to cache.
	 * @param  string  path to parsed PHP file
	 * @param  mixed
	 * @return self
	 */
	protected function store($file, $data)
	{
		$this->cache[$file] = $data;

		if ($this->cacheDir !== NULL) {
			file_put_contents(
				$this->cacheFileFor($file),
				"<?php // AliasExpander cache for $file\n\nreturn " . var_export($data, TRUE) . ";\n",
				LOCK_EX
			);
		}

		return $this;
	}



	/**
	 * @param  string  path to parsed PHP file
	 * @return string
	 */
	private function cacheFileFor($file)
	{
		return $this->cacheDir
			. DIRECTORY_SEPARATOR
			. substr(sha1($file), 0, 5) . '-' . pathinfo($file, PATHINFO_FILENAME) . '.php';
	}



	/* --- PHP source analyzing --------------------------------------------- */
	/**
	 * Parses file and search for namespace and class aliases.
	 * @param  string  path to PHP source file
	 * @return array[int line => array[namespace => string, aliases => array]]
	 */
	private function parse($file)
	{
		if (($parsed = $this->load($file)) === NULL) {
			$this->tokens = new \ArrayIterator(token_get_all(file_get_contents($file)));

			$parsed = array(
				0 => array(
					'namespace' => '',
					'aliases' => array(),
				),
			);
			$current = & $parsed[0];
			while (($token = $this->fetchToken()) !== FALSE) {
				if (is_array($token)) {
					if ($token[0] === T_NAMESPACE) {
						$parsed[$token[2]] = array(
							'namespace' => $this->fetchTokenWhile(T_STRING, T_NS_SEPARATOR),
							'aliases' => array(),
						);
						$current = & $parsed[$token[2]];

					} elseif ($token[0] === T_USE && !$this->isNextToken('(')) {
						do {
							$class = $this->fetchTokenWhile(T_STRING, T_NS_SEPARATOR);
							if ($this->isNextToken(T_AS)) {
								$this->fetchToken();
								$alias = $this->fetchTokenWhile(T_STRING, T_NS_SEPARATOR);
							} else {
								$alias = substr($class, strrpos("\\$class", '\\'));
							}

							$current['aliases'][strtolower($alias)] = array($class, $token[2]);
						} while ($this->isNextToken(',') && $this->fetchToken());
					}
				}
			}

			$this->tokens = NULL;
			$this->store($file, $parsed);
		}

		return $parsed;
	}



	/**
	 * Fetch next token.
	 * @param  bool  move cursor or not
	 * @return array|string|FALSE
	 */
	private function fetchToken($move = TRUE)
	{
		$token = FALSE;
		for (; $this->tokens->valid(); $this->tokens->next()) {
			$token = $this->tokens->current();
			if (!is_array($token) || !in_array($token[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), TRUE)) {
				$move && $this->tokens->next();
				break;
			}
		}
		return $token;
	}



	/**
	 * Fetch concatenated tokens.
	 * @param  int|string  token type(s)
	 * @return string
	 */
	private function fetchTokenWhile($type)
	{
		$types = func_get_args();
		$result = '';
		while (($token = $this->fetchToken(FALSE)) !== FALSE && in_array(is_array($token) ? $token[0] : $token, $types, TRUE)) {
			$this->tokens->next();
			$result .= is_array($token) ? $token[1] : $token;
		}
		return $result;
	}



	/**
	 * Is next token given type?
	 * @param  int|string  token type
	 * @return string
	 */
	private function isNextToken($type)
	{
		$token = $this->fetchToken(FALSE);
		return $type === (is_array($token) ? $token[0] : $token);
	}

}
