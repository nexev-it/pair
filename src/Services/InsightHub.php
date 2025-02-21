<?php

namespace Pair\Services;

use Pair\Core\Application;
use Pair\Core\Config;

use \Bugsnag\Client;

/**
 * This class provides a simple interface to Insight Hub.
 */
class InsightHub {

	const PHP_ERRORS = [
        E_ALL => 'E_ALL',
        E_COMPILE_ERROR => 'E_COMPILE_ERROR',
        E_COMPILE_WARNING => 'E_COMPILE_WARNING',
        E_CORE_ERROR => 'E_CORE_ERROR',
        E_CORE_WARNING => 'E_CORE_WARNING',
        E_DEPRECATED => 'E_DEPRECATED',
        E_ERROR => 'E_ERROR',
        E_NOTICE => 'E_NOTICE',
        E_PARSE => 'E_PARSE',
        E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
        E_STRICT => 'E_STRICT',
        E_USER_DEPRECATED => 'E_USER_DEPRECATED',
        E_USER_ERROR => 'E_USER_ERROR',
        E_USER_NOTICE => 'E_USER_NOTICE',
        E_USER_WARNING => 'E_USER_WARNING',
		E_WARNING => 'E_WARNING'
    ];

	/**
	 * Send an error message to Insight Hub if the API key is set.
	 *
	 * @param string The name of the error, a short (1 word) string.
	 * @param string The error message.
	 */
	public static function error(string $name, string $message): void {

		self::notify($name, $message, 'error');

	}

	/**
	 * Send an exception to Insight Hub if the API key is set.
	 *
	 * @param \Throwable $exception The exception to send.
	 * @param string $severity The severity of the exception.
	 */
	public static function exception(\Throwable $exception, string $severity = 'error'): void {

		// send the exception to Insight Hub
		$client = self::getClient();

		if (!$client) {
			return;
		}

		$client->notifyException($exception, function ($report) use ($severity) {
			$report->setSeverity($severity);
		});

	}

	/**
	 * Setup the Insight Hub client and return it.
	 */
	private static function getClient(): ?Client {

		if (!Config::get('INSIGHT_HUB_API_KEY')) {
			return NULL;
		}

		$bugsnag = Client::make(Config::get('INSIGHT_HUB_API_KEY'));

		$bugsnag->setReleaseStage(Application::getEnvironment());
		$bugsnag->setAppType('web');
		$bugsnag->setNotifier([
			'name' => Config::get('PRODUCT_NAME'),
			'version' => Config::get('PRODUCT_VERSION'),
			'url' => BASE_HREF
		]);

		return $bugsnag;

	}

	/**
	 * Return the severity of the error based on the PHP E_ type.
	 */
	public static function getSeverity(int $errorType): string {

		switch ($errorType) {

			case E_COMPILE_WARNING:
			case E_CORE_WARNING:
			case E_USER_WARNING:
			case E_WARNING:

				return 'warning';

			case E_DEPRECATED:
			case E_NOTICE:
			case E_USER_DEPRECATED:
			case E_USER_NOTICE:
		
				return 'info';

			case E_COMPILE_ERROR:
			case E_CORE_ERROR:
			case E_ERROR:
			case E_USER_ERROR:
			default:

				return 'error';

		}

	}

	/**
	 * Handle a PHP error and send it to Insight Hub if the API key is set.
	 *
	 * @param string The error message.
	 * @param int The PHP error type.
	 */
	public static function handle(string $message, int $phpError): void {

		$severity = self::getSeverity($phpError);

		$name = self::PHP_ERRORS[$phpError] ?? 'PHP Error';

		self::notify($name, $message, $severity);

	}

	/**
	 * Send an info message to Insight Hub if the API key is set.
	 *
	 * @param string The name of the error, a short (1 word) string.
	 * @param string The error message.
	 */
	public static function info(string $name, string $message): void {

		self::notify($name, $message, 'info');

	}

	/**
	 * Send an error message to Insight Hub with severity based on the parameter passed.
	 *
	 * @param string The name of the error, a short (1 word) string.
	 * @param string The error message.
	 * @param string The severity of the error (error, warning, info).
	 */
	private static function notify(string $name, string $message, string $severity): void {

		$client = self::getClient();

		if (!$client) {
			return;
		}

		$client->notifyError($name, $message, function ($report) use ($severity) {
			$report->setSeverity($severity);
		});

	}

	/**
	 * Send a warning message to Insight Hub if the API key is set.
	 *
	 * @param string The name of the error, a short (1 word) string.
	 * @param string The error message.
	 */
	public static function warning(string $name, string $message): void {

		self::notify($name, $message, 'warning');

	}

}