<?php
/**
 * Load the single .env file from the project root.
 * It holds both DB_LOCAL_* (development) and DB_* (production) settings;
 * advpost_resolve_environment() decides which set is used.
 * Real server vars always win over .env values.
 *
 * @param string $root Project root (directory that contains .env)
 * @param string $filename Env filename (default .env)
 * @return void
 */
function advpost_load_env($root, $filename = '.env')
{
	// Remember a CI_ENV set outside .env (shell, cron, Apache SetEnv) before .env can fill it in.
	if ( ! isset($GLOBALS['advpost_real_ci_env']))
	{
		$real = getenv('CI_ENV');
		$GLOBALS['advpost_real_ci_env'] = ($real !== false) ? $real : (isset($_SERVER['CI_ENV']) ? $_SERVER['CI_ENV'] : '');
	}

	advpost_load_env_file($root, $filename);
}

/**
 * Load key=value pairs from one env file into the process environment.
 * Existing getenv / $_ENV values are never overwritten.
 *
 * @param string $root
 * @param string $filename
 * @return void
 */
function advpost_load_env_file($root, $filename)
{
	$path = rtrim($root, "/\\") . DIRECTORY_SEPARATOR . $filename;
	if ( ! is_file($path) || ! is_readable($path))
	{
		return;
	}

	$lines = file($path, FILE_IGNORE_NEW_LINES);
	if ($lines === false)
	{
		return;
	}

	foreach ($lines as $line)
	{
		$line = trim($line);
		if ($line === '' || isset($line[0]) && $line[0] === '#')
		{
			continue;
		}

		if (strpos($line, '=') === false)
		{
			continue;
		}

		list($name, $value) = explode('=', $line, 2);
		$name = trim($name);
		if ($name === '' || ! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name))
		{
			continue;
		}

		// Do not clobber real environment / already-set values.
		$existing = getenv($name);
		if ($existing !== false)
		{
			continue;
		}
		if (array_key_exists($name, $_ENV))
		{
			continue;
		}

		$value = trim($value);
		$len = strlen($value);
		if ($len >= 2)
		{
			$q = $value[0];
			if (($q === '"' || $q === "'") && $value[$len - 1] === $q)
			{
				$value = substr($value, 1, -1);
				if ($q === '"')
				{
					$value = str_replace(array('\\n', '\\r', '\\t', '\\"', '\\\\'), array("\n", "\r", "\t", '"', '\\'), $value);
				}
			}
		}

		putenv($name . '=' . $value);
		$_ENV[$name] = $value;
		$_SERVER[$name] = $value;
	}
}

if ( ! function_exists('adv_env'))
{
	/**
	 * Read an environment variable with an optional default.
	 * Empty string is a valid value (not treated as missing).
	 *
	 * @param string $key
	 * @param mixed  $default
	 * @return mixed
	 */
	function adv_env($key, $default = '')
	{
		$value = getenv($key);
		if ($value === false)
		{
			if (array_key_exists($key, $_ENV))
			{
				return $_ENV[$key];
			}
			return $default;
		}
		return $value;
	}
}

if ( ! function_exists('adv_env_is_set'))
{
	/**
	 * Whether a variable is defined in the environment (even if empty).
	 *
	 * @param string $key
	 * @return bool
	 */
	function adv_env_is_set($key)
	{
		if (getenv($key) !== false)
		{
			return true;
		}
		return array_key_exists($key, $_ENV);
	}
}

if ( ! function_exists('advpost_is_local_host'))
{
	/**
	 * True when the HTTP request is served from a local dev host.
	 *
	 * @return bool
	 */
	function advpost_is_local_host()
	{
		$host = isset($_SERVER['HTTP_HOST']) ? strtolower($_SERVER['HTTP_HOST']) : '';
		if ($host === '')
		{
			return PHP_SAPI === 'cli';
		}
		$host = preg_replace('/:\d+$/', '', $host); // drop port (localhost:8080)
		return $host === 'localhost'
			|| $host === '127.0.0.1'
			|| $host === '[::1]'
			|| substr($host, -6) === '.local';
	}
}

if ( ! function_exists('advpost_resolve_environment'))
{
	/**
	 * Resolve CI environment:
	 *   1. CI_ENV set outside .env (shell / cron / vhost) – explicit override
	 *   2. Web request: localhost / 127.0.0.1 / *.local → development, any other host → production
	 *   3. CLI: CI_ENV from .env, then private/environment.php, else development
	 *
	 * @return string
	 */
	function advpost_resolve_environment()
	{
		$real_ci_env = isset($GLOBALS['advpost_real_ci_env']) ? $GLOBALS['advpost_real_ci_env'] : '';
		if ($real_ci_env !== '')
		{
			return $real_ci_env;
		}

		if (PHP_SAPI !== 'cli')
		{
			return advpost_is_local_host() ? 'development' : 'production';
		}

		$ci_env = adv_env('CI_ENV', '');
		if ($ci_env !== '')
		{
			return $ci_env;
		}

		$environment_file = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'environment.php';
		if (is_file($environment_file))
		{
			include_once $environment_file;
			if (defined('ENVIRONMENT'))
			{
				return ENVIRONMENT;
			}
		}

		return advpost_is_local_host() ? 'development' : 'production';
	}
}

if ( ! function_exists('adv_db_env'))
{
	/**
	 * Database setting: DB_LOCAL_* on development, DB_* on production.
	 *
	 * @param string $suffix HOST|USERNAME|PASSWORD|DATABASE|DRIVER|CHARSET|COLLATION
	 * @param mixed  $default
	 * @return mixed
	 */
	function adv_db_env($suffix, $default = '')
	{
		$is_production = defined('ENVIRONMENT') && ENVIRONMENT === 'production';
		$key = ($is_production ? 'DB_' : 'DB_LOCAL_') . $suffix;

		if (adv_env_is_set($key))
		{
			return adv_env($key, $default);
		}

		if ( ! $is_production)
		{
			$fallback = 'DB_' . $suffix;
			if (adv_env_is_set($fallback))
			{
				return adv_env($fallback, $default);
			}
		}

		return $default;
	}
}
