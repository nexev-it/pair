<?php

namespace Pair\Exceptions;

/**
 * Custom exception for handling application errors of the web-app GUI front-end; after logging the error, notify
 * the user with modal or JSON (for AJAX).
 */
class AppException extends PairException {

	/**
	 * Constructor for the ApiException.
	 *
	 * @param string $message The error message.
	 * @param int $code The error code.
	 * @param Throwable|NULL $previous Optional previous exception for exception chaining.
	 */
	public function __construct(string $message, int $code = 0, ?\Throwable $previous = NULL) {

		// notify the user
		self::frontEnd($message);
		
		// track the error message
		parent::__construct($message, $code, $previous);

	}

}