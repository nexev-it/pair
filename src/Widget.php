<?php

namespace Pair;

/**
 * Class for management of an HTML widget.
 */
class Widget {

	/**
	 * Path to the file with a trailing slash.
	 * @var string
	 */
	private $scriptPath = APPLICATION_PATH . '/widgets/';

	/**
	 * Renders the widget layout and returns it.
	 *
	 * @param	string	Name of widget file without file’s extension (.php).
	 * @return	string
	 */
	public function render($name) {

		Logger::event('Rendering ' . $name . ' widget');

		$file = $this->scriptPath . $name .'.php';

		// close buffer and parse file
		ob_start();
		require $file;
		$widget = ob_get_clean();

		return $widget;

	}

}
