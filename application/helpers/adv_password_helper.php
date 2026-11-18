<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Password helpers. New passwords are stored with password_hash() (bcrypt).
 * Legacy plain-text rows keep working until their owner next logs in, when
 * they are transparently upgraded (see adv_password_check / adv_upgrade_password).
 */

if ( ! function_exists('adv_password_hash'))
{
	function adv_password_hash($plain)
	{
		return password_hash((string) $plain, PASSWORD_DEFAULT);
	}
}

if ( ! function_exists('adv_password_is_hashed'))
{
	function adv_password_is_hashed($stored)
	{
		$info = password_get_info((string) $stored);
		return ! empty($info['algo']);
	}
}

if ( ! function_exists('adv_password_check'))
{
	/**
	 * @param string $plain  password typed by the user
	 * @param string $stored value from users.password (hash or legacy plain text)
	 */
	function adv_password_check($plain, $stored)
	{
		$plain  = (string) $plain;
		$stored = (string) $stored;
		if ($plain === '' || $stored === '')
		{
			return FALSE;
		}
		if (adv_password_is_hashed($stored))
		{
			return password_verify($plain, $stored);
		}
		// Legacy plain-text row: constant-time compare.
		return hash_equals($stored, $plain);
	}
}

if ( ! function_exists('adv_password_needs_upgrade'))
{
	function adv_password_needs_upgrade($stored)
	{
		$stored = (string) $stored;
		return ! adv_password_is_hashed($stored)
			|| password_needs_rehash($stored, PASSWORD_DEFAULT);
	}
}
