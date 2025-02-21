<?php

namespace Pair\Core;

use Pair\Exceptions\AppException;
use Pair\Exceptions\CriticalException;
use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;
use Pair\Helpers\LogBar;
use Pair\Helpers\Options;
use Pair\Helpers\Translator;
use Pair\Helpers\Utilities;
use Pair\Html\IziToast;
use Pair\Html\SweetAlert;
use Pair\Html\Widget;
use Pair\Models\Audit;
use Pair\Models\Oauth2Token;
use Pair\Models\Session;
use Pair\Models\Template;
use Pair\Models\Token;
use Pair\Models\User;
use Pair\Orm\ActiveRecord;
use Pair\Orm\Database;

/**
 * Singleton object globally available for caching, queuing messages and render the template.
 */
class Application {

	/**
	 * Singleton property.
	 */
	static protected ?self $instance;

	/**
	 * List of temporary variables.
	 */
	private array $state = [];

	/**
	 * List of temporary variables, stored also in the browser cookie.
	 */
	private array $persistentState = [];

	/**
	 * Multi-array cache as list of class-names with list of ActiveRecord’s objects.
	 */
	private array $activeRecordCache = [];

	/**
	 * Web page title, in plain text.
	 */
	private string $pageTitle = '';

	/**
	 * HTML content of web page.
	 */
	private string $pageContent = '';

	/**
	 * Contains a list of plain text script to add.
	 */
	private array $scriptContent = [];

	/**
	 * Contains all external script files to load.
	 */
	private array $scriptFiles = [];

	/**
	 * Contains all CSS files to load.
	 */
	private array $cssFiles = [];

	/**
	 * Contains Manifest files to load.
	 */
	private array $manifestFiles = [];

	/**
	 * Toast notifications, to be shown on page load.
	 * @var IziToast[]
	 */
	private array $toasts = [];

	/**
	 * Modal alert to be shown on page load.
	 */
	private ?SweetAlert $modal = NULL;

	/**
	 * Currently connected user.
	 */
	private ?User $currentUser = NULL;

	/**
	 * Contents variables for layouts.
	 */
	private array $vars = [];

	/**
	 * URL of the active menu item.
	 */
	private ?string $activeMenuItem = NULL;

	/**
	 * Template’s object.
	 */
	private ?Template $template = NULL;

	/**
	 * Template-style’s file name (without extension).
	 */
	private string $style = 'default';

	/**
	 * List of modules that can run with no authentication required.
	 */
	private array $guestModules = [];

	/**
	 * Contains the page scripts to be loaded at the end of the page.
	 */
	protected string $pageScripts = '';

	/**
	 * Contains the LobBar render or NULL if disabled.
	 */
	protected ?LogBar $lobgBar = NULL;

	/**
	 * Private constructor called by getInstance(). No LogBar calls here.
	 */
	private function __construct() {

		// override error settings on server
		ini_set('error_reporting',	E_ALL);
		ini_set('display_errors',	TRUE);

		// prevent loop error for recursive __construct
		if (defined('APPLICATION_PATH')) {
			return;
		}

		// application folder without trailing slash
		define('APPLICATION_PATH', dirname(dirname(dirname(dirname(dirname(dirname(__FILE__)))))));

		// check config file or start installation
		if (!Config::envFileExists() and file_exists(APPLICATION_PATH . '/installer/start.php')) {
			include APPLICATION_PATH . '/installer/start.php';
			die();
		}

		Config::load();

		$this->defineConstants();

		if (!Config::envFileExists()) {
			CriticalException::terminate('Configuration file not found, check .env file', ErrorCodes::LOADING_ENV_FILE);
		}

		// custom error handlers
		Logger::setCustomErrorHandlers();

		// routing initialization
		$router = Router::getInstance();
		$router->parseRoutes();

		// force utf8mb4
		if (Config::get('DB_UTF8')) {
			$db = Database::getInstance();
			$db->setUtf8unicode();
		}

		// default page title, maybe overwritten
		$this->setPageTitle(Config::get('PRODUCT_NAME'));

		// raw calls will jump templates inclusion, so turn-out output buffer
		if (!$router->isRaw()) {

			$gzip  = (isset($_SERVER['HTTP_ACCEPT_ENCODING']) and substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip'));

			// if supported, output is compressed with gzip
			if ($gzip and extension_loaded('zlib') and !ini_get('zlib.output_compression')) {
				ob_start('ob_gzhandler');
			} else {
				ob_start();
			}

		}

		// retrieve persistent notifications and modal
		$this->retrievePersistentNotifications();

	}

	/**
	 * Returns, if any, variable assigned to the template,
	 * otherwise the properties of the method, otherwise NULL
	 *
	 * @param	string	Requested property’s name.
	 */
	public function __get(string $name): mixed {

		switch ($name) {

			// useful in html tag to set language code
			case 'langCode':

				$translator = Translator::getInstance();
				$value = $translator->getCurrentLocale()->getRepresentation();
				break;

			default:

				$allowedProperties = ['activeRecordCache', 'activeMenuItem', 'currentUser', 'pageTitle', 'pageContent', 'template', 'messages'];

				// search into variable assigned to the template as first
				if (array_key_exists($name, $this->vars)) {

					$value = $this->vars[$name];

				// then search in properties
				} else if (property_exists($this, $name) and in_array($name, $allowedProperties)) {

					$value = $this->$name;

				// then return NULL
				} else {

					Logger::error('Property “'. $name .'” doesn’t exist for this object '. get_called_class());
					$value = NULL;

				}
				break;

		}

		return $value;

	}

	/**
	 * Magic method to set an object property value.
	 *
	 * @param	string	Property’s name.
	 * @param	mixed	Property’s value.
	 */
	public function __set(string $name, mixed $value): void {

		if (property_exists($this, $name)) {

			// object properties
			$this->$name = $value;

		} else {

			// layout’s variables
			$this->vars[$name] = $value;

		}

	}

	/**
	 * Add script content that will be loaded by jQuery into the #pair-script-container DOM element.
	 *
	 * @param	string	Javascript content.
	 */
	public function addScript(string $script): void {

		$this->scriptContent[] = $script;

	}

	private function defineConstants(): void {

		// Pair folder
		define('PAIR_FOLDER', substr(dirname(dirname(__FILE__)), strlen(APPLICATION_PATH)+1));

		// set the default user class if not customized
		if (!defined('PAIR_USER_CLASS')) {
			define ('PAIR_USER_CLASS', 'Pair\Models\User');
		}

		// path to temporary folder
		define('TEMP_PATH', APPLICATION_PATH . '/temp/');

		// force php server date to UTC
		if (Config::get('UTC_DATE')) {
			ini_set('date.timezone', 'UTC');
			define('BASE_TIMEZONE', 'UTC');
		} else {
			define('BASE_TIMEZONE', (date_default_timezone_get() ?? 'UTC'));
		}

		// base URL is NULL
		if (static::isCli()) {
			$baseHref = $urlPath = NULL;
		// define full URL to web page index with trailing slash or NULL
		} else {
			$protocol = ($_SERVER['SERVER_PORT'] == 443 or (isset($_SERVER['HTTPS']) and $_SERVER['HTTPS'] !== 'off')) ? "https://" : "http://";
			$urlPath = substr($_SERVER['SCRIPT_NAME'], 0, -strlen('/public/index.php'));
			$baseHref = isset($_SERVER['HTTP_HOST']) ? $protocol . $_SERVER['HTTP_HOST'] . $urlPath . '/' : NULL;
		}

		define('URL_PATH', $urlPath);
		
		define('BASE_HREF', $baseHref);

	}

	/**
	 * Check the temporary folder and, if it does not exist or is inaccessible, create it.
	 */
	public static function fixTemporaryFolder(): bool {

		if (!file_exists(TEMP_PATH) or !is_dir(TEMP_PATH) or !is_writable(TEMP_PATH)) {

			// remove any file named as wanted temporary folder
			if (file_exists(TEMP_PATH) and !unlink(TEMP_PATH)) {
				trigger_error('File ' . TEMP_PATH . ' exists and can’t be removed');
				return FALSE;
			}

			// create the folder
			$old = umask(0);
			if (!mkdir(TEMP_PATH, 0777, TRUE)) {
				trigger_error('Directory creation on ' . TEMP_PATH . ' failed');
				return FALSE;
			}
			umask($old);

			// sets full permissions
			if (!chmod(TEMP_PATH, 0777)) {
				trigger_error('Set permissions on directory ' . TEMP_PATH . ' failed');
				return FALSE;
			}

		}

		return TRUE;

	}

	/**
	 * Return an ActiveRecord object cached or NULL if not found.
	 *
	 * @param	string		Name of the ActiveObject class.
	 * @param	int|string	Unique identifier of the class.
	 */
	final public function getActiveRecordCache(string $class, int|string $id): ?ActiveRecord {

		return (isset($this->activeRecordCache[$class][$id])
			? $this->activeRecordCache[$class][$id]
			: NULL);

	}

	final public function getAllNotificationsMessages(): array {

		$messages = [];

		if ($this->modal) {
			$messages[] = $this->modal->getText();
		}

		foreach ($this->toasts as $toast) {
			$messages[] = $toast->getText();
		}

		return $messages;

	}

	/**
	 * Return a list of common parameters for cookies with custom expiration time.
	 */
	public static function getCookieParams(int $expires): array {

		return [
			'expires' => $expires,
			'path' => '/',
			'samesite' => 'Lax',
			'secure' => 'development' != Application::getEnvironment()
		];

	}

	/**
	 * Return a cookie prefix based on product name, like ProductName.
	 */
	public static function getCookiePrefix(): string {

		return str_replace(' ', '', ucwords(str_replace('_', ' ', Config::get('PRODUCT_NAME'))));

	}

	/**
	 * Return 'development', 'staging' or 'production' (default).
     */
    public static function getEnvironment(): string {

		if (in_array(Config::get('PAIR_ENVIRONMENT'), ['development','staging'])) {
        	return Config::get('PAIR_ENVIRONMENT');
		}

		return 'production';

    }

	/**
	 * Create singleton Application object and return it.
	 */
	public static function getInstance(): Application {

		// could be this class or inherited
		$class = get_called_class();

		if (!isset(static::$instance) or is_null(static::$instance)) {
			static::$instance = new $class();
		}

		return static::$instance;

	}

	/**
	 * Returns javascript code for displaying a modal alert.
	 */
	private function getModalScript(): string {

		return $this->modal ? $this->modal->render() : '';

	}

	/**
	 * Returns javascript code for displaying toast notifications.
	 */
	private function getToastsScript(): string {

		$script = '';

		foreach ($this->toasts as $toast) {
			$script .= $toast->render();
		}

		return $script;

	}

	/**
	 * Retrieves variables of any type form a cookie named like in param.
	 */
	public function getPersistentState(string $name): mixed {

		$stateName = $this->getPersistentStateName($name);

		if (array_key_exists($stateName, $this->persistentState)) {

			return $this->persistentState[$stateName];

		} else if (array_key_exists($stateName, $_COOKIE)) {

			// as of PHP 8.4.0 throws an Exception
			if (PHP_VERSION >= '8.4.0') {
				try {
					return unserialize($_COOKIE[$stateName]);
				} catch (\Exception $e) {
					throw new AppException('Error unserializing cookie ' . $stateName, ErrorCodes::UNSERIALIZE_ERROR, $e);
				}
			} else if (Utilities::isSerialized($_COOKIE[$stateName])) {
				return unserialize($_COOKIE[$stateName]);
			} else {
				$this->unsetPersistentState($name);
				throw new AppException('Error unserializing cookie ' . $stateName, ErrorCodes::UNSERIALIZE_ERROR);
			}

		}

		return NULL;

	}

	private function getPersistentStateName(string $name): string {

		return static::getCookiePrefix() . ucfirst($name);

	}

	/**
	 * Returns the requested session state variable.
	 *
	 * @param	string	State’s name.
	 */
	final public function getState(string $name): mixed {

		if (array_key_exists($name, $this->state)) {
			return $this->state[$name];
		} else {
			return NULL;
		}

	}

	/**
	 * Returns the time zone of the logged in user, otherwise the default for the application.
	 */
	public static final function getTimeZone(): \DateTimeZone {

		$app = Application::getInstance();

		// in login page the currentUser doesn’t exist
		return is_a($app->currentUser, 'User')
			? $app->currentUser->getDateTimeZone()
			: new \DateTimeZone(BASE_TIMEZONE);

	}

	/**
	 * If current selected template is not valid, replace it with the default one. It’s private to avoid
	 * loops on derived templates load.
	 */
	private function getTemplate(): Template {

		if (!$this->template or !$this->template->areKeysPopulated()) {
			$this->template = Template::getDefault();
		}

		if (!$this->template) {
			throw new CriticalException(Translator::do('NO_VALID_TEMPLATE'), ErrorCodes::NO_VALID_TEMPLATE);
		}

		// if this is derived template, load derived.php file
		if ($this->template->derived) {
			$derivedFile = $this->template->getBaseFolder() . '/'  . strtolower($this->template->name) . '/derived.php';
			if (file_exists($derivedFile)) require $derivedFile;
		}

		return $this->template;

	}

	/**
	 * Return TRUE if Pair was invoked by CLI.
	 */
	final public static function isCli(): bool {

		return (php_sapi_name() === 'cli');

	}

	/**
	 * Retrieves variables of any type form a cookie named like in param.
	 */
	public function issetPersistentState(string $name): bool {

		$stateName = $this->getPersistentStateName($name);

		return (array_key_exists($stateName, $this->persistentState) or isset($_COOKIE[$stateName]));

	}

	/**
	 * Returns TRUE if state has been previously set, NULL value included.
	 *
	 * @param	string	Name of the state variable.
	 */
	final public function issetState(string $name): bool {

		return (array_key_exists($name, $this->state));

	}

	/**
	 * Useful to collect CSS file list and render tags into page head.
	 *
	 * @param	string	Path to stylesheet, absolute or relative with no trailing slash.
	 */
	public function loadCss(string $href): void {

		$this->cssFiles[] = $href;

	}

	/**
	 * Collect Manifest files and includes into the page head.
	 *
	 * @param	string	Path to manifest file, absolute or relative with no trailing slash.
	 */
	public function loadManifest(string $href): void {

		$this->manifestFiles[] = $href;

	}

	/**
	 * Set esternal script file load with optional attributes.
	 *
	 * @param	string	Path to script, absolute or relative with no trailing slash.
	 * @param	bool	Defer attribute (default FALSE).
	 * @param	bool	Async attribute (default FALSE).
	 * @param	array	Optional attribute list (type, integrity, crossorigin, charset).
	 */
	public function loadScript(string $src, bool $defer = FALSE, bool $async = FALSE, array $attribs=[]): void {

		// the script object
		$script = new \stdClass();

		$script->src = $src;

		if ($defer)	$script->defer = TRUE;
		if ($async) $script->async = TRUE;

		// list of valid type attributes
		$validTypes = ['text/javascript','text/ecmascript','application/javascript','application/ecmascript'];

		if (isset($attribs['type']) and in_array($attribs['type'], $validTypes)) {
			$script->type = $attribs['type'];
		}

		if (isset($attribs['integrity']) and strlen(trim($attribs['integrity']))) {
			$script->integrity = $attribs['integrity'];
		}

		if (isset($attribs['crossorigin']) and strlen(trim($attribs['crossorigin']))) {
			$script->crossorigin = $attribs['crossorigin'];
		}

		if (isset($attribs['charset']) and strlen(trim($attribs['charset']))) {
			$script->charset = $attribs['charset'];
		}

		$this->scriptFiles[] = $script;

	}

	/**
	 * Stores toast notifications for retrieval on next web page load.
	 */
	public function makeToastNotificationsPersistent(): void {

		$this->setPersistentState('ToastNotifications', $this->toasts);

	}

	/**
	 * Start the session and set the User class (Pair/User or a custom one that inherites
	 * from Pair/User). Must use only for command-line and web application access.
	 */
	public function manageSession(): void {

		// can be customized before Application is initialized
		$userClass = PAIR_USER_CLASS;

		// get required singleton instances
		$router = Router::getInstance();

		// start session or resume session started by runApi
		session_start();

		// session time length in minutes
		$sessionTime = Options::get('session_time');

		// stop processing if it is a CLI or guest module
		if (static::isCli() or in_array($router->module, $this->guestModules)) {
			return;
		}

		// get existing previous session
		$session = new Session(session_id());

		// clean all old sessions
		Session::cleanOlderThan($sessionTime);

		// sets an empty user object
		$this->setCurrentUser(new $userClass());

		// session exists
		if ($session->isLoaded()) {

			// session is expired
			if ($session->isExpired($sessionTime)) {

				Audit::sessionExpired($session);

				// check RememberMe cookie
				if (User::loginByRememberMe()) {
					Audit::rememberMeLogin();
					return;
				}

				$comment = Translator::do('USER_SESSION_EXPIRED');

				// sends js message about session expired
				if ($router->isRaw()) {

					Utilities::pairJsonError($comment);
					exit();

				// redirects to login page
				} else {

					// delete the expired session from DB
					$session->delete();

					// set the page coming from avoiding post requests
					if (isset($_SERVER['REQUEST_METHOD']) and 'POST' !== $_SERVER['REQUEST_METHOD']) {
						$this->setPersistentState('lastRequestedUrl', $router->getUrl());
					}

					// queue a toast notification for the connected user
					$this->toast($comment);

					// goes to login page
					$this->redirect('user/login');

				}

			// session loaded
			} else {

				// if session exists, extend session timeout
				$session->extendTimeout();

				// create User object
				$user = new $userClass($session->idUser);
				$this->setCurrentUser($user);

				$eventMessage = 'User session for ' . $user->fullName . ' is alive' .
					', user time zone is ' . $this->currentUser->tzName .
					' (' . sprintf('%+06.2f', (float)$this->currentUser->tzOffset) . ')';

				// add log about user session
				Logger::notice($eventMessage);

				// set defaults in case of no module
				if (!$router->module) {
					$landing = $user->getLanding();
					$router->module = $landing->module;
					$router->action = $landing->action;
				}

				$resource = $router->module . '/' . $router->action;

				// access denied
				if (!$this->currentUser->canAccess((string)$router->module, $router->action)) {
					$landing = $user->getLanding();
					$this->toastError(Translator::do('ERROR'), 'Access denied to ' . $resource);
					// avoid infinite loop
					if ($resource != $landing->module . '/' . $landing->action) {
						$this->redirect($landing->module . '/' . $landing->action);
					} else {
						$this->redirect('user/logout');
					}
				}

			}

		// user is not logged in
		} else {

			// in case of AJAX call, sends a JSON error
			if ($router->isRaw()) {
				Utilities::jsonResponseSessionExpired();
			}

			// check RememberMe cookie
			if (User::loginByRememberMe()) {
				$this->currentUser->redirectToDefault();
			}

			// redirect to login page if action is not login or password reset
			if (!('user'==$router->module and in_array($router->action, ['login','reset','confirm','sendResetEmail','newPassword','setNewPassword']))) {
				$this->redirect('user/login');
			}

		}

	}

	/**
	 * Add an alert modal to the page and return the object for further customization.
	 */
	public function modal(string $title, string $text, ?string $icon = NULL): SweetAlert {

		$this->modal = new SweetAlert($title, $text, $icon);

		return $this->modal;

	}

	/**
	 * Prints the page Javascript content.
	 */
	final public function printScripts(): void {

		$pageScripts = '';

		// collect script files
		foreach ($this->scriptFiles as $file) {

			// initialize attributes render
			$attribsRender = '';

			// render each attribute based on its variable type (string or boolean)
			foreach ($file as $tag => $value) {
				$attribsRender .= ' ' . (is_bool($value) ? $tag : $tag . '="' . htmlspecialchars((string)$value) . '"');
			}

			// add each script
			$pageScripts .= '<script' . $attribsRender . '></script>' . "\n";

		}

		// add Insight Hub script for error tracking and performance monitoring
		if (Config::get('INSIGHT_HUB_API_KEY') and Config::get('INSIGHT_HUB_PERFORMANCE')) {
			$pageScripts .= '<script src="https://cdn.jsdelivr.net/npm/bugsnag-js" crossorigin="anonymous"></script>' . "\n";
			$pageScripts .= '<script type="module">import BugsnagPerformance from "//d2wy8f7a9ursnm.cloudfront.net/v1/bugsnag-performance.min.js";BugsnagPerformance.start({apiKey:"' . Config::get('INSIGHT_HUB_API_KEY') .'"})</script>' . "\n";
		}

		// collect plain text scripts
		if (count($this->scriptContent) or $this->modal or count($this->toasts)) {

			$pageScripts .= "<div id=\"pair-script-container\"><script defer>\n";

			foreach ($this->scriptContent as $s) {
				$pageScripts .= $s ."\n";
			}

			// add modal and toasts
			$pageScripts .= "document.addEventListener('DOMContentLoaded', function() {\n";
			$pageScripts .= $this->getModalScript();
			$pageScripts .= $this->getToastsScript();
			$pageScripts .= "});\n";
			$pageScripts .= "</script></div>";

		}

		print $pageScripts;

	}

	/**
	 * Prints the page stylesheets.
	 */
	final public function printStyles(): void {

		$pageStyles = '';

		// collect stylesheets
		foreach ($this->cssFiles as $href) {

			$pageStyles .= '<link rel="stylesheet" href="' . $href . '">' . "\n";

		}

		// collect manifest
		foreach ($this->manifestFiles as $href) {

			$pageStyles .= '<link rel="manifest" href="' . $href . '">' . "\n";

		}

		print $pageStyles;

	}

	/**
	 * Set a modal alert queued for next page load.
	 */
	public function persistentModal(string $title, string $text, ?string $icon = NULL): void {

		$modal = new SweetAlert($title, $text, $icon);

		$this->setPersistentState('Modal', $modal);

	}

	/**
	 * Print a widget by its name.
	 */
	public function printWidget(string $name): void {

		$widget = new Widget($name);
		print $widget->render();

	}

	/**
	 * Store an ActiveRecord object into the common cache of Application singleton.
	 *
	 * @param	string			Name of the ActiveObject class.
	 * @param	ActiveRecord	Object to cache.
	 */
	final public function putActiveRecordCache(string $class, ActiveRecord $object): void {

		// can’t manage composite key
		if (1 == count((array)$object->keyProperties) ) {

			$this->activeRecordCache[$class][(string)$object->getId()] = $object;
			
			$class = get_class($object);
			$className = basename(str_replace('\\', '/', $class));
			Logger::notice('Cached ' . $className . ' object with id=' . (string)$object->getId());
		
		}

	}

	/**
	 * Redirect HTTP on the URL param. Relative path as default. Queued toast notifications
	 * get a persistent storage in a cookie in order to being retrieved later.
	 *
	 * @param	string	Location URL.
	 * @param	bool	If TRUE, will avoids to add base url (default FALSE).
	 */
	public function redirect(?string $url=NULL, bool $externalUrl=FALSE): void {

		if (is_null($url)) {
			$router = Router::getInstance();
			$url = $router->module;
		}

		// stores modal and toast notifications for next retrievement
		$this->setPersistentState('Modal', $this->modal);
		$this->makeToastNotificationsPersistent();

		if (!$url) return;

		// external redirect
		if ($externalUrl) {

			header('Location: ' . $url);

		// redirect to internal path
		} else {

			$router = Router::getInstance();
			$page  = $router->getPage();

			if ($page > 1) {

				// removes a possible leading slash
				if ('/'==$url[0]) {
					$url = substr($url,1);
				}

				// if url contains just module, create a fake action placeholder
				if (FALSE == strpos($url, '/')) {
					$url .= '/';
				}

				header('Location: ' . BASE_HREF . $url . '/page-' . $page);

			} else {

				header('Location: ' . BASE_HREF . $url);

			}

		}

		die();

	}

	public function redirectToUserDefault(): void {
		
		if ($this->currentUser) {
			$this->currentUser->redirectToDefault();
		} else {
			$this->redirect('user/login');
		}

	}

	private function retrievePersistentNotifications(): void {

		$persistentModal = $this->getPersistentState('Modal');

		// retrieve cookie modal and puts in queue
		if (is_a($persistentModal, 'Pair\Html\SweetAlert')) {
			$this->unsetPersistentState('Modal');
			$this->modal = $persistentModal;
		}

		$persistentToasts = $this->getPersistentState('ToastNotifications');

		// retrieve all cookie toasts and puts in queue
		if (is_array($persistentToasts)) {
			$this->unsetPersistentState('ToastNotifications');
			foreach ($persistentToasts as $toast) {
				if (is_a($toast, 'Pair\Html\IziToast')) {
					$this->toasts[]= $toast;
				}
			}
		}

	}

	/**
	 * Manage API login, logout and custom requests.
	 *
	 * @param	string	Name of module that executes API requests. Default is “api”.
	 */
	public function runApi(string $name = 'api'): void {

		$router = Router::getInstance();

		// check if API has been called
		if (!trim($name) or $name != $router->module or !file_exists(APPLICATION_PATH . '/' . MODULE_PATH . 'controller.php')) {
			return;
		}

		// set as raw request
		Router::setRaw();

		$logBar = LogBar::getInstance();
		$logBar->disable();

		// require controller file
		require (APPLICATION_PATH . '/' . MODULE_PATH . 'controller.php');

		// get SID or token via GET
		$sid		= Router::get('sid');
		$tokenValue	= Router::get('token');

		// read the Bearer token via HTTP header
		$bearerToken = Oauth2Token::readBearerToken();

		// assemble the API controller name
		$ctlName = $name . 'Controller';

		if (!class_exists($ctlName)) {
			print ('The API Controller class is incorrect');
			exit();
		}

		// new API Controller instance
		$apiCtl = new $ctlName();

		// set the action function
		$action = $router->action ? $router->action . 'Action' : 'defaultAction';

		// check token as first
		if ($tokenValue) {

			$token = Token::getByValue((string)$tokenValue);

			// set token and start controller
			if (!$token) {
				$apiCtl->sendError(19);
			}

			$token->updateLastUse();
			$apiCtl->setToken($token);

		// or check for Oauth2 Bearer token via http header
		} else if ($bearerToken) {

			if (!Oauth2Token::validate($bearerToken)) {
				sleep(3);
				Oauth2Token::unauthorized('Authentication failed');
			}

			// verify that the bearer token is valid
			$apiCtl->setBearerToken($bearerToken);

		} else if ('login' == $router->action) {

			unset($_COOKIE[session_name()]);
			session_destroy();
			session_start();

		} else if ('logout' == $router->action) {

			session_start();

		// signup
		} else if ('signup' == $router->action) {

			// continue with apiCtl action

		// all the other requests with sid
		} else if ($sid) {

			// get passed session
			$session = new Session($sid);

			// check if sid is valid
			if (!$session->isLoaded()) {
				$apiCtl->sendError(27);
			}

			// if session exists, extend session timeout
			$session->extendTimeout();

			// create User object for API
			$userClass = PAIR_USER_CLASS;
			$user = new $userClass($session->idUser);
			$this->setCurrentUser($user);

			// set session and start controller
			$apiCtl->setSession($session);

		// unauthorized request
		} else {

			Oauth2Token::unauthorized(Config::get('PRODUCT_NAME') . '-API: Authentication failed');

		}

		try {
			$apiCtl->$action();
		} catch (\Throwable $e) {
			$apiCtl->sendError(4, [$e->getMessage()]);
		}

		exit();

	}

	/**
	 * Sets current user, default template and translation locale.
	 * @param	User	User object or inherited class object.
	 */
	public function setCurrentUser(User $user): void {

		if (is_a($user,'Pair\Models\User')) {

			$this->currentUser = $user;

			// sets user language
			$tran = Translator::getInstance();
			$tran->setLocale($user->getLocale());

		}

	}

	/**
	 * Add the name of a module to the list of guest modules, for which authorization is not required.
	 *
	 * @param	string	Module name.
	 */
	public function setGuestModule(string $moduleName): void {

		if (!in_array($moduleName, $this->guestModules)) {
			$this->guestModules[] = $moduleName;
		}

	}

	/**
	 * Set the web page HTML title tag.
	 */
	public function setPageTitle(string $title): void {

		$this->pageTitle = $title;

	}

	/**
	 * Store variables of any type in a cookie for next retrievement. Existent variables with
	 * same name will be overwritten.
	 */
	public function setPersistentState(string $name, mixed $value): void {

		$stateName = $this->getPersistentStateName($name);

		$this->persistentState[$stateName] = $value;

		// cookie lifetime is 30 days
		$params = self::getCookieParams(time() + 2592000);
		setcookie($stateName, serialize($value), $params);

	}

	/**
	 * Sets a session state variable.
	 *
	 * @param	string	Name of the state variable.
	 * @param	mixed	Value of any type as is, like strings, custom objects etc.
	 */
	public function setState(string $name, mixed $value): void {

		$this->state[$name] = $value;

	}

	/**
	 * Parse template file, replace variables and return it.
	 */
	final public function startMvc(): void {

		$router	= Router::getInstance();

		// make sure to have a template set
		$template = $this->getTemplate();

		$controllerFile = APPLICATION_PATH . '/modules/' . $router->module . '/controller.php';

		// check controller file existence
		if (!file_exists($controllerFile) or '404' == $router->url) {

			$this->toastError(Translator::do('ERROR'), Translator::do('RESOURCE_NOT_FOUND', $router->url));
			$this->style = '404';
			$this->setPageTitle('HTTP 404 error');
			http_response_code(404);

		} else {

			require ($controllerFile);

			// build controller object
			$controllerName = ucfirst($router->module) . 'Controller';

			// set the action
			$action = $router->action ? $router->action . 'Action' : 'defaultAction';

			// set log of ajax call
			if ($router->ajax) {

				$params = [];
				foreach ($router->vars as $key=>$value) {
					$params[] = $key . '=' . Utilities::varToText($value);
				}
				Logger::notice(date('Y-m-d H:i:s') . ' AJAX call on ' . $router->module . '/' . $router->action . ' with params ' . implode(', ', $params));

			// log controller method call
			} else {

				Logger::notice('Called controller method ' . $controllerName . '->' . $action . '()');

			}

			if (!class_exists($controllerName)) {
				throw new CriticalException('Controller ' . $controllerName . ' not found', ErrorCodes::CONTROLLER_NOT_FOUND);
			}

			try {
				$controller = new $controllerName();
			} catch (\Exception $e) {
				throw new CriticalException('Error instantiating controller ' . $controllerName, ErrorCodes::CONTROLLER_NOT_FOUND, $e);
			}

			if (method_exists($controller, $action)) {
				try {
					$controller->$action();
				} catch (\Exception $e) {

				}
			} else {
				Logger::notice('Method ' . $controllerName . '->' . $action . '() not found');
			}

			// raw calls will jump controller->display, ob and log
			if ($router->isRaw()) {
				return;
			}

			// invoke the view and render the page
			try {
				$controller->renderView();
			} catch (\Exception $e) {
				PairException::frontEnd($e->getMessage());
			}

			$this->logBar = LogBar::getInstance();

		}
		
		// populate the placeholder for the content
		if (in_array($this->style, ['404','500'])) {
			ob_clean();
		} else {
			$this->pageContent = ob_get_clean();
		}

		$styleFile = $template->getStyleFile($this->style);
		
		// parse the template
		Template::parse($styleFile);

	}

	/**
	 * Appends a toast notification message to queue.
	 * 
	 * @param	string	Toast’s title, bold.
	 * @param	string	Error message.
	 * @param	string	Type of the toast (info|success|warning|error|question|progress), default info.
	 */
	public function toast(string $title, string $message='', ?string $type=NULL): IziToast {

		$toast = new IziToast($title, $message, $type);
		$this->toasts[] = $toast;
		return $toast;

	}

	/**
	 * Proxy function to append an error toast notification to queue.
	 *
	 * @param	string	Toast’s title, bold.
	 * @param	string	Error message.
	 */
	public function toastError(string $title, string $message=''): IziToast {

		return $this->toast($title, $message, 'error');

	}

	/**
	 * Proxy function to append an error toast notification to queue and redirect.
	 * 
	 * @param	string	Toast’s title, bold.
	 * @param	string	Error message.
	 * @param	string	Redirect URL, optional.
	 */
	public function toastErrorRedirect(string $title, string $message='', ?string $url=NULL): void {

		$this->toast($title, $message, 'error');
		$this->makeToastNotificationsPersistent();
		$this->redirect($url);

	}

	/**
	 * Proxy function to append a toast notification to queue and redirect.
	 * 
	 * @param	string	Toast’s title, bold.
	 * @param	string	Message.
	 * @param	string	Redirect URL, optional.
	 */
	public function toastRedirect(string $title, string $message='', ?string $url=NULL): void {

		$this->toast($title, $message, 'success');
		$this->makeToastNotificationsPersistent();
		$this->redirect($url);

	}

	/**
	 * Removes all state variables from cookies.
	 */
	public function unsetAllPersistentStates(): void {

		$prefix = static::getCookiePrefix();

		foreach (array_keys($_COOKIE) as $name) {
			if (0 == strpos($name, $prefix)) {
				$this->unsetPersistentState($name);
			}
		}

	}

	/**
	 * Removes a state variable from cookie.
	 */
	public function unsetPersistentState(string $name): void {

		$stateName = $this->getPersistentStateName($name);

		unset($this->persistentState[$stateName]);

		if (isset($_COOKIE[$stateName])) {
			setcookie($stateName, '', self::getCookieParams(-1));
			unset($_COOKIE[$stateName]);
		}

	}

	/**
	 * Deletes a session state variable.
	 *
	 * @param	string	Name of the state variable.
	 */
	public function unsetState(string $name): void {

		unset($this->state[$name]);

	}

}