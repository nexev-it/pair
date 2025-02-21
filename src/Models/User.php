<?php

namespace Pair\Models;

use Pair\Core\Application;
use Pair\Core\Config;
use Pair\Core\Router;
use Pair\Helpers\Translator;
use Pair\Helpers\Utilities;
use Pair\Orm\ActiveRecord;
use Pair\Orm\Collection;
use Pair\Orm\Database;
use Pair\Models\Audit;
use Pair\Models\Locale;
use Pair\Models\Rule;
use Pair\Models\Session;
use Pair\Models\UserRemember;

/**
 * Base class for Pair framework users. Can be extended to have more user’s properties.
 */
class User extends ActiveRecord {

	/**
	 * User unique identifier.
	 */
	protected int $id;

	/**
	 * Id group belongs to this user.
	 */
	protected int $groupId;

	/**
	 * Id of user locale.
	 */
	protected int $localeId;

	/**
	 * Username for local authentication
	 */
	protected string $username;

	/**
	 * Password hash.
	 */
	protected string $hash;

	/**
	 * User name.
	 */
	protected string $name;

	/**
	 * User surname.
	 */
	protected string $surname;

	/**
	 * Property that binds db field email.
	 */
	protected ?string $email = NULL;

	/**
	 * If TRUE, this user is admin.
	 * @var bool
	 */
	protected $admin;

	/**
	 * Flag for user enabled.
	 * @var bool
	 */
	protected $enabled;

	/**
	 * Last login’s date, properly converted when inserted into db.
	 */
	protected ?\DateTime $lastLogin;

	/**
	 * Amount of wrong login.
	 */
	protected int $faults = 0;

	/**
	 * Token to start password reset.
	 */
	protected ?string $pwReset;

	/**
	 * Time zone offset in hours. Cached.
	 * @var float
	 */
	protected $tzOffset;

	/**
	 * Time zone name. Cached.
	 * @var string
	 */
	protected $tzName;

	/**
	 * Name of related db table.
	 */
	const TABLE_NAME = 'users';

	/**
	 * Name of primary key db field.
	 */
	const TABLE_KEY = 'id';

	/**
	 * Properties that are stored in the shared cache.
	 */
	const SHARED_CACHE_PROPERTIES = ['groupId', 'localeId'];

	/**
	 * Will returns property’s value if set. Throw an exception if not set.
	 * Pass fullName to return name + surname.
	 *
	 * @param	string	Property’s name.
	 */
	public function __get(string $name): mixed {

		if (isset($this->id) and in_array($name, ['fullName', 'groupName', 'tzName', 'tzOffset'])) {

			switch ($name) {

				case 'fullName':
					return $this->name . ' ' . $this->surname;

				case 'groupName':
					return $this->getGroup()->name;

				case 'tzName':
					$this->loadTimezone();
					return $this->tzName;

				case 'tzOffset':
					$this->loadTimezone();
					return $this->tzOffset;

			}

		}

		return parent::__get($name);

	}

	/**
	 * Adds +1 to faults counter property.
	 */
	private function addFault() {

		$this->faults++;
		$this->update('faults');

	}

	/**
	 * Track the user creation in Audit table just after record saving.
	 */
	protected function afterCreate() {

		Audit::userCreated($this);

	}

	/**
	 * Trigger function called after login().
	 */
	protected function afterLogin() {}

	/**
	 * Trigger function called after logout().
	 */
	protected function afterLogout() {}

	/**
	 * Deletes sessions of an user before its deletion.
	 */
	protected function beforeDelete(): void {

		// deletes user sessions
		Database::run('DELETE FROM `sessions` WHERE `id_user` = ?', [$this->id]);

		// deletes error_logs of this user
		Database::run('DELETE FROM `error_logs` WHERE `user_id` = ?', [$this->id]);

		if ($this->isDeletable()) {
			Audit::userDeleted($this);
		}

	}

	/**
	 * Trigger function called before login().
	 */
	protected function beforeLogin() {}

	/**
	 * Trigger function called before logout().
	 */
	protected function beforeLogout() {}

	/**
	 * Check if this user has access permission to a module and optionally to a specific action.
	 * Admin can access everything. This method use cache variable to load once from db.
	 *
	 * @param	string	Module name.
	 * @param	string	Optional action name.
	 */
	public function canAccess(string $module, ?string $action=NULL): bool {

		// patch for public folder content
		if ('public' == $module) {
			return TRUE;
		}

		// reveal module/action type
		if (is_null($action) and FALSE !== strpos($module, '/')) {
			list($module,$action) = explode('/', $module);
		}

		// check if it’s a custom route
		$router = Router::getInstance();
		$url = '/' . $module . ($action ? '/' . $action : '');
		$res = $router->getModuleActionFromCustomUrl($url);

		// in case, overwrite module and action
		if (is_a($res, '\stdClass')) {
			$module = $res->module;
			$action = $res->action;
		}

		// user module is for login and personal profile
		if ('user'==$module) {
			return TRUE;
		}

		// acl is cached
		$acl = $this->getAcl();

		foreach ($acl as $rule) {
			if ($rule->moduleName == $module and (($rule->adminOnly and $this->admin) or !$rule->action or ($rule->action and $rule->action == $action))) {
				return TRUE;
			}
		}

		return FALSE;

	}

	/**
	 * Checks if password matches hash for local auth.
	 *
	 * @param	string	Plain text password.
	 * @param	string	Crypted hash.
	 */
	public static function checkPassword(string $password, string $hash): bool {

		return ($hash == crypt($password, $hash));

	}

	/**
	 * Create a remember-me object, store it into DB and set the browser’s cookie.
	 *
	 * @param	string	IANA time zone identifier.
	 */
	public function createRememberMe(string $timezone): bool {

		$dateTimeZone = User::getValidTimeZone($timezone);

		// set a random string
		$ur = new UserRemember();
		$ur->userId = $this->id;
		$ur->rememberMe = Utilities::getRandomString(32);
		$ur->createdAt = new \DateTime('now', $dateTimeZone);

		if (!$ur->store()) {
			return FALSE;
		}

		// serialize an array with timezone and RememberMe string
		$content = serialize([$timezone, $ur->rememberMe]);

		// expire in 30 days 2592000
		$expires = time() + 2592000;

		// set cookie and return the result
		return setcookie(UserRemember::getCookieName(), $content, Application::getCookieParams($expires));

	}

	/**
	 * Starts a new session, writes on db and updates users table for last login.
	 * Returns true if both db writing has been done succesfully.
	 *
	 * @param	string	IANA time zone identifier.
	 * @param	int		Possible ID of the user before impersonation.
	 */
	private function createSession(string $timezone, ?int $formerUserId=NULL): bool {

		// get a valid DateTimeZone object
		$dateTimeZone = User::getValidTimeZone($timezone);

		// gets offset by timezone name
		$dt = new \DateTime('now', $dateTimeZone);
		$diff = $dt->format('P');
		list ($hours, $mins) = explode(':', $diff);
		$offset = (float)$hours + ($hours > 0 ? ($mins / 60) : -($mins / 60));

		// creates session
		$session = new Session();

		$session->id				= session_id();
		$session->idUser			= $this->id;
		$session->startTime			= new \DateTime();
		$session->timezoneOffset	= $offset;
		$session->timezoneName		= $timezone;
		$session->formerUserId		= $formerUserId;

		$res1 = $session->create();

		// deletes all other sessions for this user
		if (Config::get('PAIR_SINGLE_SESSION')) {
			Database::run('DELETE FROM `sessions` WHERE `id_user` = ? AND `id` != ?', [$this->id, session_id()]);
		}

		// updates last user login
		$this->lastLogin	= new \DateTime();
		$this->tzOffset		= $offset;
		$this->tzName		= $timezone;

		$res2 = $this->update('lastLogin');

		return ($res1 and $res2);

	}

	/**
	 * Return the current Application connected User object or its child, NULL otherwise.
	 */
	public static function current(): ?static {

		$app = Application::getInstance();
		return $app->currentUser;

	}

	/**
	 * Checks if username/password matches a record into database for local auth and returns a
	 * \stdClass with error, message and userId parameters.
	 *
	 * @param	string	Username.
	 * @param	string	Plain text password.
	 * @param	string	IANA time zone identifier.
	 */
	public static function doLogin(string $username, string $password, string $timezone): \stdClass {

		$ret = new \stdClass();

		$ret->error		= FALSE;
		$ret->message	= NULL;
		$ret->userId	= NULL;
		$ret->sessionId	= NULL;

		$query = 'SELECT * FROM `users` WHERE `' . (Config::get('PAIR_AUTH_BY_EMAIL') ? 'email' : 'username') . '` = ?';

		// load user row
		$row = Database::load($query, [$username], Database::OBJECT);

		// track ip address and user_agent for audit
		$ipAddress = $_SERVER['REMOTE_ADDR'] ?? NULL;
		$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? NULL;

		if (is_object($row)) {

			// load this user
			$user = new static($row);

			// over 9 faults
			if ($user->faults > 9) {

				$ret->error = TRUE;
				$ret->message = Translator::do('TOO_MANY_LOGIN_ATTEMPTS');
				$user->addFault();

				Audit::loginFailed($username, $ipAddress, $userAgent);

			// user disabled
			} else if ('0' == $user->enabled) {

				$ret->error = TRUE;
				$ret->message = Translator::do('USER_IS_DISABLED');
				$user->addFault();

				Audit::loginFailed($username, $ipAddress, $userAgent);

			// user password doesn’t match
			} else if (!User::checkPassword($password, $user->hash)) {

				$ret->error = TRUE;
				$ret->message = Translator::do('PASSWORD_IS_NOT_VALID');
				$user->addFault();

				Audit::loginFailed($username, $ipAddress, $userAgent);

			// login ok
			} else {

				// hook for tasks to be executed before login
				$user->beforeLogin();

				// creates session for this user
				$user->createSession($timezone);
				$ret->userId = $user->id;
				$ret->sessionId = session_id();
				$user->resetFaults();

				// clear any password-reset
				if (!is_null($user->pwReset)) {
					$user->pwReset = NULL;
					$user->store();
				}

				// hook for tasks to be executed after login
				$user->afterLogin();

				$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? NULL;

				Audit::loginSuccessful($user, $ipAddress, $userAgent);

			}

		// this username doesn’t exist into db
		} else {

			Audit::loginFailed($username, $ipAddress, $userAgent);

			$ret->error = TRUE;
			$ret->message = Translator::do('USERNAME_NOT_VALID');

		}

		return $ret;

	}

	/**
	 * Does the logout action and returns TRUE if session is found and deleted.
	 *
	 * @param	string	Session ID to close.
	 */
	public static function doLogout(string $sid): bool {

		// get User object by Session
		$session = new Session($sid);
		$user = $session->getUser();

		if (is_null($user)) {
			return FALSE;
		}

		// hook for tasks to be executed before logout
		$user->beforeLogout();

		// record the logout
		Audit::logout($user);

		// delete session
		$res = Database::run('DELETE FROM `sessions` WHERE `id` = ?', [$sid]);

		// unset all persistent states
		$app = Application::getInstance();
		$app->unsetAllPersistentStates();

		// unset RememberMe
		$app->currentUser->unsetRememberMe();

		// reset the user in Application object
		$app->currentUser = NULL;

		// hook for tasks to be executed after logout
		$user->afterLogout();

		return (bool)$res;

	}

	/**
	 * Load the rule list for this user. Cached.
	 */
	private function getAcl(): Collection {

		if (!$this->issetCache('acl')) {

			$query =
				'SELECT r.*, m.`name` AS `module_name`
				FROM `rules` AS r
				INNER JOIN `acl` AS a ON a.`rule_id` = r.`id`
				INNER JOIN `modules` AS m ON r.`module_id` = m.`id`
				WHERE a.`group_id` = ?';

			$this->setCache('acl', Rule::getObjectsByQuery($query, [$this->__get('groupId')]));

		}

		return $this->getCache('acl');

	}

	/**
	 * Returns array with matching object property name on related db fields.
	 */
	protected static function getBinds(): array {

		return [
			'id'		=> 'id',
			'groupId'	=> 'group_id',
			'localeId'	=> 'locale_id',
			'username'	=> 'username',
			'hash'		=> 'hash',
			'name'		=> 'name',
			'surname'	=> 'surname',
			'email'		=> 'email',
			'admin'		=> 'admin',
			'enabled'	=> 'enabled',
			'lastLogin'	=> 'last_login',
			'faults'	=> 'faults',
			'pwReset'	=> 'pw_reset'
		];

	}

	/**
	 * Return an user that matches pw_reset string. NULL if not found.
	 *
	 * @param	string		PwReset value.
	 */
	public static function getByPwReset(string $pwReset): ?User {

		$query = 'SELECT * FROM `users` WHERE `pw_reset` IS NOT NULL AND `pw_reset` = ?';

		return static::getObjectByQuery($query, [$pwReset]);

	}

	/**
	 * It will returns DateTimeZone object for this User.
	 */
	public function getDateTimeZone(): \DateTimeZone {

		$this->loadTimezone();

		// tzName is still NULL for guest users
		return new \DateTimeZone($this->tzName ? $this->tzName : BASE_TIMEZONE);

	}

	/**
	 * Join the user’s name and surname and return it
	 */
	public function getFullName(): string {

		return $this->name . ' ' . $this->surname;

	}

	public function getGroup(): Group {

		return new Group($this->groupId);

	}

	/**
	 * Creates and returns an Hash for user password adding salt.
	 *
	 * @param	string	The user password.
	 * @return	string	Hashed password
	 *
	 * @see		http://php.net/crypt
	 */
	public static function getHashedPasswordWithSalt(string $password): string {

		// salt for bcrypt needs to be 22 base64 characters (only [./0-9A-Za-z])
		$salt = substr(str_replace('+', '.', base64_encode(sha1(microtime(true), true))), 0, 22);

		// 2a = bcrypt algorithm selector, 12 = the workload factor
		$hash = crypt($password, '$2a$12$' . $salt);

		return $hash;

	}

	/**
	 * Get landing module and action as object properties where the user goes after login.
	 */
	public function getLanding(): ?\stdClass {

		$query =
			'SELECT m.`name` AS `module`, r.`action`
			FROM `acl` AS a
			INNER JOIN `rules` AS r ON r.id = a.`rule_id`
			INNER JOIN `modules` AS m ON m.`id` = r.`module_id`
			WHERE a.`is_default` = 1
			AND a.`group_id` = ?';

		return Database::load($query, [$this->__get('groupId')], Database::OBJECT);

	}

	/**
	 * Return the language code of this user. Cached.
	 */
	public function getLanguageCode(): ?string {

		if (!$this->issetCache('lang')) {

			$query =
				'SELECT l.`code`
				FROM `languages` AS l
				INNER JOIN `locales` AS lc ON l.`id` = lc.`language_id`
				INNER JOIN `users` AS u ON u.`locale_id` = lc.`id`
				WHERE u.`id` = ?';

			$this->setCache('lang', Database::load($query, [$this->id], Database::RESULT));

		}

		return $this->getCache('lang');

	}

	/**
	 * Returns the Locale object for this user, if set, otherwise the default Locale.
	 */
	public function getLocale(): Locale {

		if ($this->isLocaleSet()) {
			return new Locale($this->localeId);
		} else {
			return Locale::getDefault();
		}

	}

	/**
	 * Compare the past TimeZone value with the list of valid identifiers and,
	 * if not found in this list, assign the system default. Then create the
	 * object to be returned.
	 *
	 * @param	string	IANA time zone identifier.
	 */
	public static function getValidTimeZone(string $timezone): \DateTimeZone {

		// checks if time zone name is valid
		if (!in_array($timezone, \DateTimeZone::listIdentifiers())) {
			$timezone = date_default_timezone_get();
		}

		return new \DateTimeZone($timezone);

	}

	/**
	 * Set for converts from string to Datetime, integer or boolean object in two ways.
	 */
	protected function init(): void {

		$this->bindAsBoolean('admin', 'enabled');

		$this->bindAsDatetime('lastLogin');

		$this->bindAsInteger('id', 'groupId', 'languageId', 'faults');

	}

	/**
	 * Return TRUE if this user or the the former User object if impersonating, is admin.
	 */
	public function isAdmin(): bool {

		if ($this->admin) {
			return TRUE;
		} else {
			$query =
				'SELECT COUNT(1) FROM `users`
				INNER JOIN `sessions` AS s ON u.`id` = s.`id_user`
				WHERE s.`id` = ? AND u.`id` = ? AND `admin` = 1';
			return (bool)Database::load($query, [Session::current(), $this->id], Database::COUNT);
		}

	}

	/**
	 * Check whether record of this object is deletable based on inverse foreign-key list
	 * and the user is not the same connected.
	 */
	public function isDeletable(): bool {

		$app = Application::getInstance();

		if ($this->id == $app->currentUser->id) {
			return FALSE;
		}

		return parent::isDeletable();

	}

	/**
	 * Check if the localeId parameter has been set and returns TRUE if so.
	 */
	public function isLocaleSet(): bool {

		return isset($this->localeId) ? (bool)$this->localeId : FALSE;

	}

	/**
	 * If time zone name or offset is null, will loads from session table their values and
	 * populates this object cache properties.
	 */
	private function loadTimezone() {

		if (!is_null($this->id) and is_null($this->tzName) and is_null($this->tzOffset)) {
			$session = Session::current();
			$this->tzOffset	= $session->timezoneOffset;
			$this->tzName	= $session->timezoneName;
		}

	}

	/**
	 * Performs a login for the user passed in parameters. It returns an
	 * \stdClass with error, message and userId parameters.
	 *
	 * @param	\Pair\Models\User 	$user
	 * @param	string		$timezone	IANA time zone identifier.
	 * @param	int|NULL	Former user ID.
	 */
	public static function loginAs(User $user, string $timezone, ?int $formerUserId = null): \stdClass {

		$ret = new \stdClass();

		$ret->error		= FALSE;
		$ret->message	= NULL;
		$ret->userId	= NULL;
		$ret->sessionId	= NULL;

		// track ip address and user_agent for audit
		$ipAddress = $_SERVER['REMOTE_ADDR'] ?? NULL;
		$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? NULL;

		if ('0' == $user->enabled) {

			$ret->error = TRUE;
			$ret->message = Translator::do('USER_IS_DISABLED');
			$user->addFault();

			Audit::loginFailed($user->email, $ipAddress, $userAgent);

		// user password doesn’t match
		} else {

			// hook for tasks to be executed before login
			$user->beforeLogin();

			// creates session for this user
			$user->createSession($timezone, $formerUserId);
			$ret->userId = $user->id;
			$ret->sessionId = session_id();

			// hook for tasks to be executed after login
			$user->afterLogin();

			Audit::loginSuccessful($user, $ipAddress, $userAgent);
		}

		return $ret;

	}

	/**
	 * Check if the browser’s cookie contains a RememberMe. In case, do an auto-login.
	 */
	public static function loginByRememberMe(): bool {

		// get the cookie content
		$cookieContent = UserRemember::getCookieContent();

		// check if cookie exists
		if (is_null($cookieContent)) {
			return FALSE;
		}

		// try to load user
		$user = UserRemember::getUserByRememberMe($cookieContent->rememberMe);

		// if user exists, return it
		if (is_a($user, 'Pair\Models\User')) {
			$user->createSession($cookieContent->timezone);
			$user->renewRememberMe();
			$app = Application::getInstance();
			$app->setCurrentUser($user);
			return TRUE;
		}

		// login unsucceded
		return FALSE;

	}

	/**
	 * Sets to 0 faults counter property.
	 */
	public function resetFaults(): bool {

		$this->faults = 0;
		return $this->update('faults');

	}

	/**
	 * Redirect user’s browser to his default landing web-page.
	 */
	public function redirectToDefault() {

		$app	 = Application::getInstance();
		$landing = $this->getLanding();

		$app->redirect($landing->module . '/' . $landing->action);

	}

	/**
	 * Update the expire date of RememberMe cookie.
	 */
	public function renewRememberMe(): bool {

		// build the cookie name
		$cookieName = UserRemember::getCookieName();

		// check if cookie exists
		if (!isset($_COOKIE[$cookieName])) {
			return FALSE;
		}

		$cookieContent = UserRemember::getCookieContent();

		// update created_at date and delete older remember-me records
		Database::run('UPDATE `users_remembers` SET `created_at` = NOW() WHERE `user_id` = ? AND `remember_me` = ?', [$this->id, $cookieContent->rememberMe]);
		Database::run('DELETE FROM `users_remembers` WHERE `user_id` = ? AND `remember_me` != ?', [$this->id, $cookieContent->rememberMe]);

		// expires in 30 days
		$expires = time() + 2592000;

		// set cookie and return the result
		return setcookie($cookieName, $_COOKIE[$cookieName], Application::getCookieParams($expires));

	}

	/**
	 * Apply a password reset for this User.
	 *
	 * @param	string	New password to set.
	 * @param	string	IANA time zone identifier.
	 */
	public function setNewPassword(string $newPassword, string $timezone): bool {

		$this->pwReset = NULL;
		$this->hash = static::getHashedPasswordWithSalt($newPassword);

		if (!$this->store()) {
			return FALSE;
		}

		Audit::passwordChanged($this);

		// creates session for this user
		$this->createSession($timezone);
		$this->resetFaults();

		return TRUE;

	}

	/**
	 * Delete DB record and browser’s cookie because when logout is invoked.
	 */
	public function unsetRememberMe(): bool {

		// build the cookie name
		$cookieContent = UserRemember::getCookieContent();

		// check if cookie exists
		if (is_null($cookieContent)) {
			return FALSE;
		}

		// delete the current remember-me DB record
		Database::run('DELETE FROM `users_remembers` WHERE `user_id` = ? AND `remember_me` = ?', [$this->id, $cookieContent->rememberMe]);

		// delete the current remember-me Cookie
		return setcookie(UserRemember::getCookieName(), '', Application::getCookieParams(-1));

	}

}