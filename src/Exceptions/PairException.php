<?php

namespace Pair\Exceptions;

use Pair\Core\Application;
use Pair\Core\Logger;
use Pair\Core\Router;
use Pair\Helpers\Translator;
use Pair\Helpers\Utilities;

/**
 * Custom exception for handling errors in the Pair Framework.
 */
class PairException extends \Exception {

	/**
	 * Tracks the error message in the LogBar, takes a system snapshot and sends e-mail/telegram
	 * notifications. In case of critical errors, throws a CriticalException.
	 *
	 * @param string The error message.
	 * @param int The error code.
	 * @param Throwable|NULL Optional previous exception for exception chaining.
	 */
	public function __construct(string $message, int $code = 0, ?\Throwable $previous = NULL) {

		$criticalCodes = [
			ErrorCodes::DB_CONNECTION_FAILED,
			ErrorCodes::NO_VALID_TEMPLATE,
			ErrorCodes::LOADING_ENV_FILE
		];

		// run drastic steps to close the application
		if (in_array($code, $criticalCodes)) {
			throw new CriticalException($message, $code, $previous);
		}

		// intercept any previous message
		$trackedMessage = ($previous and $previous->getMessage()) ? $previous->getMessage() : $message;
		self::track($trackedMessage);

	}

	/**
	 * Queue error in the LogBar, take a system snapshot and send e-mail/telegram notifications.
	 */
	public static function track(string $message): void {
		
		Logger::error($message);

	}

	/**
	 * Front-end notification.
	 */
	public static function frontEnd(string $message): void {

		// overwrite the message with a more user-friendly one in production
		if ('production' == Application::getEnvironment()) {
			$message = Translator::do('AN_ERROR_OCCURRED');
		}
		
		$router = Router::getInstance();
		
		// JSON error for AJAX requests or modal for web requests
		if ($router->isRaw()) {
			Utilities::pairJsonError($message);
		} else {
			$app = Application::getInstance();
			$app->modal(Translator::do('ERROR'), $message, 'error')->confirm('OK');
		}

	}

}