<?php

namespace Pair\Traits;

use Pair\Helpers\LogBar;

trait LogTrait {

	/**
	 * Disable the collection of log events.
	 */
	public function disableLogBar(): void {

		LogBar::getInstance()->disable();

	}

	/**
	 * Add an event to the log bar.
	 */
	public function log(string $description, string $type = 'notice', string $subtext = ''): void {

		LogBar::event($description, $type, $subtext);

	}

	/**
	 * Add an error to the log bar.
	 */
	public function logError(string $description, string $subtext = ''): void {

		LogBar::event($description, 'error', $subtext);

	}

	public function logWarning(string $description, string $subtext = ''): void {

		LogBar::event($description, 'warning', $subtext);

	}

}