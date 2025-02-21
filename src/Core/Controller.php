<?php

namespace Pair\Core;

use Pair\Exceptions\AppException;
use Pair\Exceptions\CriticalException;
use Pair\Exceptions\ErrorCodes;
use Pair\Helpers\Translator;
use Pair\Helpers\Utilities;
use Pair\Orm\ActiveRecord;

abstract class Controller {

	use \Pair\Traits\AppTrait;
	use \Pair\Traits\LogTrait;

	/**
	 * Application object.
	 */
	protected Application $app;

	/**
	 * Router object.
	 */
	protected Router $router;

	/**
	 * Model for this MVC stack.
	 */
	protected Model $model;

	/**
	 * View’s file name, without file extension.
	 */
	protected string $view;

	/**
	 * Translator object.
	 */
	protected Translator $translator;

	/**
	 * Controller’s name, without “Controller” suffix.
	 */
	private string $name;

	/**
	 * Path to the module for this controller.
	 */
	private string $modulePath;

	/**
	 * Inizialize name, module path, translator and view.
	 */
	final public function __construct() {

		// useful singleton objects
		$this->app = Application::getInstance();
		$this->router = Router::getInstance();
		$this->translator = Translator::getInstance();

		// set controller’s name
		$class = get_called_class();
		$this->name = substr($class, 0, strpos($class, 'Controller'));

		// path to the module folder
		$ref = new \ReflectionClass($this);
		$this->modulePath = dirname($ref->getFileName());

		// sets language subfolder’s name
		$this->translator->setModuleName($this->name);

		// sets same view as the controller action
		$this->view = $this->router->action ? $this->router->action : 'default';

		$this->init();

		// if a model is not specified, load the default one
		if (!isset($this->model) or is_null($this->model)) {
			include ($this->modulePath .'/model.php');
			$modelName = $this->name . 'Model';
			$this->model = new $modelName();
		}

		// look for extended classes
		if (is_dir($this->modulePath . '/classes')) {

			// get all folder files
			$filenames = Utilities::getDirectoryFilenames($this->modulePath . '/classes');

			// include each class file
			foreach ($filenames as $filename) {
				include_once $this->modulePath . '/classes/' . $filename;
			}

		}

	}

	/**
	 * Returns property’s value or NULL.
	 *
	 * @param	string	Property’s name.
	 * @throws	\Exception	If property doesn’t exist.
	 */
	public function __get(string $name): mixed {

		if (!property_exists($this, $name)) {
			throw new \Exception('Property “'. $name .'” doesn’t exist for '. get_called_class(), ErrorCodes::PROPERTY_NOT_FOUND);
		}
		
		return isset($this->$name) ? $this->$name : NULL;
	
	}

	public function __set(string $name, mixed $value): void {

		$this->$name = $value;

	}

	/**
	 * Print a toast notification and redirect to default action.
	 *
	 * @param	string	Optional message to enqueue.
	 */
	protected function accessDenied(?string $message=NULL): void {

		$this->toastRedirect(Translator::do('ERROR'), ($message ? $message : Translator::do('ACCESS_DENIED')), strtolower($this->name));

	}

	/**
	 * Include the file for View formatting. Display an error with a notification and
	 * redirect to default view as fallback in case of view not found for non-ajax requests.
	 */
	public function renderView(): void {

		$view = $this->getView();

		if (!is_subclass_of($view, 'Pair\Core\View')) {
			throw new CriticalException('View class not found');
		}

		$view->display();

	}

	/**
	 * Proxy to get a variable within global scope.
	 *
	 * @param	string	Variable name.
	 */
	final public function getState(string $name): mixed {

		return $this->app->getState($name);

	}

	/**
	 * Start function, being executed before each method. Optional.
	 */
	protected function init(): void {}

	/**
	 * Load a custom model.
	 */
	public function loadModel(string $modelName): void {

		if (!file_exists($this->modulePath .'/'. $modelName .'.php')) {
			throw new \Exception('Model file '. $this->modulePath .'/'. $modelName .'.php has not been found');
		}

		include ($this->modulePath .'/'. $modelName .'.php');
		$modelClass = $this->name . ucfirst($modelName);
		$this->model = new $modelClass();

	}

	/**
	 * Load a custom model for a list of actions.
	 */
	public function loadModelForActions(string $modelName, array $actions): void {

		if (in_array($this->router->action, $actions)) {
			$this->loadModel($modelName);
		}

	}

	/**
	 * Returns the object of inherited class when called with id as first parameter.
	 *
	 * @param	string	Expected object class type.
	 * 
	 * @throws	AppException
	 */
	protected function getObjectRequestedById(string $class): ?ActiveRecord {

		// reads from url requested item id
		$itemId = Router::get(0);

		if (!$itemId) {
			throw new AppException($this->lang('ID_OF_ITEM_TO_EDIT_IS_NOT_VALID', $class));
		}

		$object = new $class($itemId);

		if (!$object->isLoaded()) {
			throw new AppException($this->lang('ID_OF_ITEM_TO_EDIT_IS_NOT_VALID', $class));
		}

		return $object;

	}

	/**
	 * Return View object related to this controller.
	 * 
	 * @throws CriticalException
	 */
	public function getView(): ?View {

		if (!$this->view) {
			throw new CriticalException('View page not set for module ' . $this->name);
		}

		$file = $this->modulePath .'/view'. ucfirst($this->view) .'.php';

		if (!file_exists($file)) {
			$this->view = 'default';
			$file = $this->modulePath .'/view'. ucfirst($this->view) .'.php';
		}

		// if view file still not found, throw an exception
		if (!file_exists($file)) {
			throw new CriticalException('The page ' . $this->name . '/' . $this->view . ' does not exist');
		}

		include_once($file);

		$viewName = ucfirst($this->name) .'View'. ucfirst($this->view);

		if (!class_exists($viewName)) {
			throw new CriticalException('Class ' . $viewName . ' was not found in file ' . $file, ErrorCodes::CLASS_NOT_FOUND);
		}

		return new $viewName($this->model);

	}

	/**
	 * Proxy function to translate a string, used for AJAX return messages.
	 *
	 * @param	string	The language key.
	 * @param	string|array|NULL	Parameter or list of parameters to bind on translation string (optional).
	 */
	public function lang(string $key, string|array|NULL $vars=NULL): string {

		return Translator::do($key, (array)$vars);

	}

	/**
	 * Get error list from an ActiveRecord object and show it to the user.
	 *
	 * @param	ActiveRecord	The inherited object.
	 * 
	 * @throws	\Exception
	 */
	protected function raiseError(ActiveRecord $object): void {

		// get error list from the ActiveRecord object
		$errors = $object->getErrors();

		// choose the error messages
		$message = $errors
			? implode(" \n", $errors)
			: $this->lang('ERROR_ON_LAST_REQUEST');

		// after the message has been queued, store the error data
		Logger::error('Failure in ' . \get_class($object) . ' class', Logger::ERROR);

		// enqueue a toast notification to the UI	
		throw new \Exception($message);

	}

	/**
	 * Shortcut to HTTP redirect and show a toast notification error.
	 */
	public function redirectWithError(string $message, ?string $url=NULL): void {

		$this->toastError($message);
		$this->redirect($url);

	}

}