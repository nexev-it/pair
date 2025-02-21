<?php

namespace Pair\Helpers;

use Pair\Core\Application;
use Pair\Core\Config;

/**
 * Manage HTTP requests.
 */
class Post {

	/**
	 * Get data by POST array returning an array. In case
	 * of not found, return default value.
	 *
	 * @param	string	HTTP parameter name.
	 * @param	string	Default value to return, in case of null or empty.
	 */
	public static function array(string $name, ?array $default=NULL): array {

		return self::get($name, 'array', $default);

	}

	/**
	 * Get data by POST array returning an boolean. In case
	 * of not found, return default value. Manage array inputs.
	 *
	 * @param	string	HTTP parameter name.
	 * @param	string	Default value to return, in case of null or empty.
	 */
	public static function bool(string $name, ?bool $default=NULL): bool {

		return self::get($name, 'bool', $default);

	}

	/**
	 * Gets all submitted field name that matches regular expressions.
	 *
	 * @param	string	Regular expression, for instance #[A-Z][A-Z_]+#.
	 * @param	string	Type string, int, bool, date or datetime will be casted.
	 * @param	string	Default value to return, in case of null or empty.
	 */
	public static function byRegex(string $pattern, $type='string', ?array $default=NULL): array {

		$list = [];

		foreach ($_POST as $name=>$value) {

			if (preg_match($pattern, $name) or $default) {

				$val = $_POST[$name] ? $_POST[$name] : $default;
				$val = self::castTo($val, $type);

				$list[$name] = $val;

			}

		}

		return $list;

	}

	/**
	 * Casts a value to a type string, integer or boolean.
	 *
	 * @param	mixed	Variable value.
	 * @param	string	Variable type.
	 */
	private static function castTo(mixed $val, string $type): mixed {

		$app = Application::getInstance();

		switch ($type) {

			default:
			case 'string':
			case 'text':
				$val = (string)$val;
				break;

			case 'int':
			case 'integer':
				$val = (int)$val;
				break;

			case 'float':
				$val = (float)$val;
				break;

			case 'bool':
			case 'boolean':
				if ('true'==strtolower((string)$val)) {
					$val = TRUE;
				} else if ('false'==strtolower((string)$val)) {
					$val = FALSE;
				} else {
					$val = (bool)$val;
				}
				break;

			// creates a DateTime object from datepicker
			case 'date':
				if ($val) {
					if ((config::get('PAIR_FORM_DATE_FORMAT') and self::usingCustomDatepicker())) {
						$format = Config::get('PAIR_FORM_DATE_FORMAT');
					} else {
						$format = 'Y-m-d';
					}
					$val = \DateTime::createFromFormat('!' . $format, $val, $app->currentUser->getDateTimeZone());
					// DateTime is not set
					if (FALSE === $val) $val = NULL;
				} else {
					$val = NULL;
				}
				break;

			// creates a DateTime object from datetimepicker
			case 'datetime':
				if ($val) {
					if ((Config::get('PAIR_FORM_DATETIME_FORMAT') and self::usingCustomDatetimepicker())) {
						$format = Config::get('PAIR_FORM_DATETIME_FORMAT');
					} else {
						$format = 'Y-m-d H:i:s';
					}
					$val = \DateTime::createFromFormat('!' . $format, $val, $app->currentUser->getDateTimeZone());
					// DateTime is not set
					if (FALSE === $val) $val = NULL;
				} else {
					$val = NULL;
				}
				break;

			case 'array':
				$val = (array)$val;
				break;

		}

		return $val;

	}

	/**
	 * Get data by POST array returning a DateTime object or NULL. In case
	 * of not found, return default value. Manage array inputs.
	 *
	 * @param	string	HTTP parameter name.
	 * @param	string	Default value to return, in case of null or empty.
	 */
	public static function date(string $name, ?\DateTime $default=NULL): ?\DateTime {

		return self::get($name, 'date', $default);

	}

	/**
	 * Get data by POST array returning a DateTime object or NULL. In case
	 * of not found, return default value. Manage array inputs.
	 *
	 * @param	string	HTTP parameter name.
	 * @param	string	Default value to return, in case of null or empty.
	 */
	public static function datetime(string $name, ?\DateTime $default=NULL): ?\DateTime {

		return self::get($name, 'datetime', $default);

	}

	/**
	 * Get data by POST array returning the specified type or default value. In case
	 * of not found, return default value. Manage array inputs.
	 *
	 * @param	string	HTTP parameter name.
	 * @param	string	Type string -default-, int, bool, date or datetime will be casted.
	 * @param	string	Default value to return, in case of null or empty.
	 */
	public static function get(string $name, string $type='string', mixed $default=NULL): mixed {

		switch ($_SERVER['REQUEST_METHOD']) {

			case 'POST':
				$request = &$_POST;
				break;

			default:
				$request = &$_REQUEST;
				break;

		}

		// remove [] from array
		if (substr($name, -2) == '[]') {
			$name = substr($name, 0, -2);
		}

		// spaces are converted by PHP to underscores
		$name = str_replace(' ', '_', $name);

		// return default value if not found
		if (!array_key_exists($name, $request)) {
			return self::castTo($default, $type);
		}

		return self::castTo($request[$name], $type);

	}

	/**
	 * Get data by POST array returning an integer. In case
	 * of not found, return default value. Manage array inputs.
	 *
	 * @param	string	HTTP parameter name.
	 * @param	string	Default value to return, in case of null or empty.
	 */
	public static function int($name, $default=NULL): int {

		return self::get($name, 'int', $default);

	}

	/**
	 * Get data by POST array returning a float. In case
	 */
	public static function float(string $name, ?float $default=NULL): float {

		return self::get($name, 'float', $default);

	}

	/**
	 * Check wheter a field was submitted in the REQUEST array.
	 *
	 * @param	string	Field name.
	 */
	public static function sent(string $name): bool {

		// remove [] from array
		if (substr($name, -2) == '[]') {
			$name = substr($name, 0, -2);
		}

		// seeking a possible name
		return (array_key_exists($name, $_REQUEST));

	}

	/**
	 * Method to understand if page comes from an HTTP post submit.
	 *
	 * @return	bool	TRUE if method is post.
	 */
	public static function submitted(): bool {

		return (bool)count($_POST);

	}

	/**
	 * Get data by POST array returning a trimmed string. In case
	 * of not found, return default value.
	 *
	 * @param	string	HTTP parameter name.
	 * @param	string	Default value to return, in case of null or empty.
	 */
	public static function trim(string $name, ?string $default=NULL): string {

		return self::get(trim($name), 'string', $default);

	}

	/**
	 * Check if the browser is using the native datepicker.
	 * 
	 * @return	bool	TRUE if using the native datepicker.
	 */
	public static function usingCustomDatepicker(): bool {

		return !(isset($_COOKIE['InputTypesDate']) and $_COOKIE['InputTypesDate']);

	}

	/**
	 * Check if the browser is using the native datetimepicker.
	 * 
	 * @return	bool	TRUE if using the native datetimepicker.
	 */
	public static function usingCustomDatetimepicker(): bool {

		return !(isset($_COOKIE['InputTypesDatetime']) and $_COOKIE['InputTypesDatetime']);

	}

}