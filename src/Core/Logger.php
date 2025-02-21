<?php

namespace Pair\Core;

use Pair\Core\Config;
use Pair\Helpers\LogBar;
use Pair\Helpers\Mailer;
use Pair\Helpers\Utilities;
use Pair\Models\ErrorLog;
use Pair\Services\AmazonSes;
use Pair\Services\InsightHub;
use Pair\Services\SendMail;
use Pair\Services\SmtpMailer;
use Pair\Services\TelegramNotifier;

/**
 * Singleton object for managing internal logger functions.
 */
class Logger {

	/**
	 * Singleton property.
	 */
	protected static ?self $instance = NULL;

	/**
	 * Default property is TRUE, can be disabled either by config or by method disable().
	 */
	protected bool $enabled = TRUE;

	/**
	 * If a log has a level equal or lower than this number, an e-mail is sent.
	 */
	protected int $emailThreshold = 4;

	/**
	 * E-mail recipients for error notifications.
	 */
	protected array $emailRecipients = [];

	/**
	 * Mailer object.
	 */
	protected ?Mailer $mailer = NULL;

	/**
	 * If a log has a level equal or lower than this number, a Telegram notification is sent.
	 */
	protected $telegramThreshold = 3;

	/**
	 * Telegram bot token.
	 */
	protected ?string $telegramBotToken = NULL;

	/**
	 * Telegram chat ID recipients for error notifications.
	 */
	protected array $telegramChatIds = [];

	/**
	 * Description: The most critical level. Indicates that the system is completely unusable.
	 * Example: The entire platform is down or a critical dependency has failed.
	 * Typical Message: "System is unusable."
	 */
	const EMERGENCY = 1;

	/**
	 * Description: Requires immediate action.
	 * Example: Data loss or a critical security issue that needs attention right away.
	 * Typical Message: "Database connection lost."
	 */
	const ALERT = 2;

	/**
	 * Description: Critical conditions that may prevent key application features from working.
	 * Example: A critical service is down.
	 * Typical Message: "Payment system unavailable."
	 */
	const CRITICAL = 3;

	/**
	 * Description: Runtime errors that do not halt the application but require fixing.
	 * Example: An exception that was caught or a missing file.
	 * Typical Message: "File not found."
	 */
	const ERROR = 4;

	/**
	 * Description: Exceptional conditions that are not errors but should be looked into.
	 * Example: High memory usage or deprecated features being used.
	 * Typical Message: "Memory usage is high."
	 */
	const WARNING = 5;

	/**
	 * Description: Normal but noteworthy events that may be of interest for monitoring.
	 * Example: A configuration setting is suboptimal.
	 * Typical Message: "Using default configuration."
	 */
	const NOTICE = 6;

	/**
	 * Description: Informational messages about the system’s normal operations.
	 * Example: A user logs in or a connection is successfully established.
	 * Typical Message: "User logged in."
	 */
	const INFO = 7;

	/**
	 * Description: Detailed debugging information for development purposes.
	 * Example: API responses during testing or SQL query execution.
	 * Typical Message: "Query executed: SELECT * FROM users."
	 */
	const DEBUG = 8;

	/**
	 * Private constructor called by getInstance().
	 */
	private function __construct() {

		// can be disabled both by config and by disable() method
		if (TRUE === Config::get('PAIR_LOGGER_DISABLED')) {
			$this->enabled = FALSE;
		}

		if (Config::get('PAIR_LOGGER_EMAIL_RECIPIENTS')) {
			$this->emailRecipients = array_unique(Utilities::arrayToEmail(explode(',', Config::get('PAIR_LOGGER_EMAIL_RECIPIENTS'))));
		}

		if (Config::get('PAIR_LOGGER_EMAIL_THRESHOLD')) {
			$this->emailThreshold = (int)Config::get('PAIR_LOGGER_EMAIL_THRESHOLD');
			if ($this->emailThreshold < 1 or $this->emailThreshold > 8) {
				self::error('Invalid PAIR_LOGGER_EMAIL_THRESHOLD value in configuration file.');
			}
		}

		if (Config::get('TELEGRAM_BOT_TOKEN')) {
			$this->telegramBotToken = Config::get('TELEGRAM_BOT_TOKEN');
		}

		if (Config::get('PAIR_LOGGER_TELEGRAM_CHAT_IDS')) {
			$this->telegramChatIds = array_unique(Utilities::arrayToInt(explode(',', (string)Config::get('PAIR_LOGGER_TELEGRAM_CHAT_IDS'))));
		}

		if (Config::get('PAIR_LOGGER_TELEGRAM_THRESHOLD')) {
			$this->telegramThreshold = (int)Config::get('PAIR_LOGGER_TELEGRAM_THRESHOLD');
			if ($this->telegramThreshold < 1 or $this->telegramThreshold > 8) {
				self::error('Invalid PAIR_LOGGER_TELEGRAM_THRESHOLD value in configuration file.');
			}
		}

	}

	/**
	 * Log an API call in LogBar.
	 */
	public static function api(string $description, ?string $subtext=NULL): void {

		LogBar::event($description, 'api', $subtext);

	}

	/**
	 * Check if the mailer is configured.
	 */
	private function checkMailer(): void {

		if (!$this->mailer) {
			self::error('Mailer not configured.');
		}

		$this->mailer->checkConfig();

	}

	/**
	 * Disables the logger.
	 */
	public function disable(): void {

		$this->enabled = FALSE;

	}

	/**
	 * Custom error handler.
	 *
	 * @param	int		Error number.
	 * @param	string	Error text message.
	 * @param	string	Error full file path.
	 * @param	int		Error line.
	 * @param	array	(Optional) it will be passed an array that points to the active symbol table at the point the error occurred.
	 */
	public static function errorHandler(int $errno, string $errstr, string $errfile, int $errline, ?array $errcontext=NULL): void {

		// log the error internally
		$fullMsg = 'Error ' . $errno . ': ' . $errstr . ' in ' . $errfile . ' line ' . $errline;
		self::error($fullMsg, self::ERROR);

		// send the error to Sentry if enabled
		if (Config::get('SENTRY_DSN')) {
			\Sentry\init(['dsn' => Config::get('SENTRY_DSN'),'environment'=>Application::getEnvironment()]);
			\Sentry\captureLastError();
		}

		// send the error to Insight Hub if enabled
		InsightHub::handle($errstr, $errno);

	}

	/**
	 * Log a critical error in LogBar, database and send notifications.
	 */
	public static function error(string $description, int $level=4): void {

		LogBar::event($description, 'error');

		// if level is out of range, set to the default value
		if (self::ERROR < $level or self::EMERGENCY > $level) {
			$level = self::ERROR;
		}

		$self = self::getInstance();
		$self->handle($description, $level);

	}

	/**
	 * Custom exception handler for all uncaught exceptions.
	 */
	public static function exceptionHandler(\Throwable $e): void {

		// send the error to Sentry if enabled
		if (Config::get('SENTRY_DSN')) {
			\Sentry\init(['dsn' => Config::get('SENTRY_DSN'),'environment'=>Application::getEnvironment()]);
			\Sentry\captureException($e);
		}

		// send the error to Insight Hub if enabled
		InsightHub::exception($e);

		// log the error internally
		$trace = $e->getTrace()[0];
		$fullMsg = get_class($e) . ': ' . $e->getMessage() . ' in ' . $trace['file'] . ' line ' . $trace['line'];
		self::error($fullMsg, self::ERROR);

	}

	/**
	 * Singleton method.
	 */
	public static function getInstance(): self {

		if (!self::$instance) {
			self::$instance = new self();
		}

		return self::$instance;

	}

	/**
	 * Register an error in the database and send telegram and e-mail notifications.
	 *
	 * @param string	Description of the error.
	 * @param int		PSR-3 log level number equivalent.
	 */
	private function handle(string $description, int $level): void {

		if (!$this->enabled) {
			return;
		}

		// register error in database
		$this->storeError($description, $level);

		$levels = [
			self::EMERGENCY	=> 'Emergency',
			self::ALERT		=> 'Alert',
			self::CRITICAL	=> 'Critical',
			self::ERROR		=> 'Error',
			self::WARNING	=> 'Warning',
			self::NOTICE	=> 'Notice',
			self::INFO		=> 'Info',
			self::DEBUG		=> 'Debug'
		];

		$levelDescription = $levels[$level] ?? 'Unknown';

		// send e-mail, if level is below threshold and recipients are set
		if ($this->mailer and count($this->emailRecipients) and $level <= $this->emailThreshold) {

			$this->checkMailer();

			$app = Application::getInstance();

			$subject = $levelDescription . ' level in ' . Config::get('PRODUCT_NAME') . ' ' . Config::get('PRODUCT_VERSION') . ' ' . $app->getEnvironment();
			$title = 'Error occurred';
			$text = $subject . ' ' . $app->getEnvironment() . ' at ' . date('Y-m-d H:i:s') . "\n\n" . $description;
			$text .= "\n\nUser ID: " . ($app->currentUser->id ?? 'Guest');
			$text .= "\nUser IP: " . $_SERVER['REMOTE_ADDR'];
			$text .= "\nUser Agent: " . $_SERVER['HTTP_USER_AGENT'];
			$text .= "\nReferer: " . $_SERVER['HTTP_REFERER'];
			$text .= "\nYou can personalize the error notification threshold in the configuration file.";

			$this->mailer->send($this->emailRecipients, $subject, $title, $text);

		}

		// send Telegram notification, if level is below threshold and recipients are set
		if ($this->telegramBotToken and count($this->telegramChatIds) and $level <= $this->telegramThreshold) {

			$tgNotifier = new TelegramNotifier($this->telegramBotToken);

			$message = $levelDescription . ' level in ' . Config::get('PRODUCT_NAME') . ' ' . Config::get('PRODUCT_VERSION') . ' ' . Application::getEnvironment() . ' at ' . date('Y-m-d H:i:s');
			$message .= "\n\n" . $description;

			foreach ($this->telegramChatIds as $chatId) {
				if ($chatId>0) {
					$tgNotifier->sendMessage($chatId, $message);
				}
			}

		}

	}

	/**
	 * Log a notice in LogBar.
	 */
	public static function notice(string $description): void {

		LogBar::event($description);

	}

	/**
	 * Log a query in LogBar.
	 */
	public static function query(string $description, ?string $subtext=NULL): void {

		LogBar::event($description, 'query', $subtext);

	}

	/**
	 * Set Amazon SES configuration for sending error notification e-mails.
	 */
	public function setAmazonSesConfig(array $config): void {

		$this->mailer = new AmazonSes($config);

	}

	/**
	 * Set custom handlers for errors and uncaught exceptions.
	 */
	public static function setCustomErrorHandlers(): void {

		set_error_handler([self::class, 'errorHandler']);
		set_exception_handler([self::class, 'exceptionHandler']);
		register_shutdown_function([self::class, 'shutdownHandler']);

	}

	/**
	 * Set the e-mail recipients for error notifications.
	 */
	public function setEmailRecipients(array $recipients): void {

		$this->emailRecipients = array_unique(Utilities::arrayToEmail($recipients));

	}

	/**
	 * Set the e-mail notification threshold.
	 */
	public function setEmailThreshold(int $threshold): void {

		if ($threshold < 1 or $threshold > 8) {
			self::error('Invalid E-mail error threshold value');
		}

		$this->emailThreshold = $threshold;

	}

	/**
	 * Set Mailer configuration for sending error notification e-mails.
	 */
	public function setSendMailConfig(array $config): void {

		$this->mailer = new SendMail($config);

	}

	/**
	 * Set SmtpMailer configuration for sending error notification e-mails.
	 */
	public function setSmtpMailerConfig(array $config): void {

		$this->mailer = new SmtpMailer($config);

	}

	/**
	 * Set the Telegram bot token for sending error notifications.
	 */
	public function setTelegramBotToken(string $token): void {

		$this->telegramBotToken = $token;

	}

	/**
	 * Set the Telegram chat IDs for sending error notifications.
	 */
	public function setTelegramChatIds(array $chatIds): void {

		$this->telegramChatIds = array_unique(Utilities::arrayToInt($chatIds));

	}

	/**
	 * Set the Telegram notification threshold.
	 */
	public function setTelegramThreshold(int $threshold): void {

		if ($threshold < 1 or $threshold > 8) {
			self::error('Invalid Telegram error threshold value');
		}

		$this->telegramThreshold = $threshold;

	}

	/**
	 * Manages fatal errors (out of memory etc.) sending email to all address in options.
	 */
	public static function shutdownHandler(): void {

		$error = error_get_last();

		// premature exit without errors
		if (is_null($error) or !in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
			return;
		}

		// log the error internally
		$fullMsg = 'Fatal error [' . $error['type'] . ']: ' . $error['message'] . ' in ' . $error['file'] . ' line ' . $error['line'];
		self::error($fullMsg, self::ERROR);

		// send the error to Sentry if enabled
		if (Config::get('SENTRY_DSN')) {
			\Sentry\init(['dsn' => Config::get('SENTRY_DSN'),'environment'=>Application::getEnvironment()]);
			\Sentry\captureLastError();
		}

		// send the error to Insight Hub if enabled
		InsightHub::error(InsightHub::PHP_ERRORS[$error['type']] ?? 'FatalError', $error['message'] ?? 'Unknown error');

	}

	/**
	 * Allows to keep the current Application and browser state.
	 *
	 * @param	string	Description of the snapshot moment.
	 * @param	int		Optional PSR-3 log level number equivalent, default is 8 (DEBUG).
	 */
	private function storeError(string $description, ?int $level=NULL): void {

		if (!$this->enabled) {
			return;
		}

		if (!is_null($level) and ($level > 8 or $level < 1)) {
            $level = 8;
        }

		$app = Application::getInstance();
		$router = Router::getInstance();

		$errorLog = new ErrorLog();

		$errorLog->level 		= $level ?: self::DEBUG;
		$errorLog->userId		= $app->currentUser->id ?? NULL;
		$errorLog->path			= substr((string)$router->url,1);
		$errorLog->getData		= $_GET;
		$errorLog->postData		= $_POST;
		$errorLog->filesData	= $_FILES;
		$errorLog->cookieData	= []; //$_COOKIE;
		$errorLog->description	= substr($description, 0, 255);
		$errorLog->userMessages	= $app->getAllNotificationsMessages();

		if (isset($_SERVER['HTTP_REFERER'])) {

			// removes application base url from referer
			$errorLog->referer = (0 === strpos($_SERVER['HTTP_REFERER'], BASE_HREF))
				? substr($_SERVER['HTTP_REFERER'], strlen(BASE_HREF))
				: (string)$_SERVER['HTTP_REFERER'];

		} else {

			$errorLog->referer = '';

		}

		$errorLog->create();

		Logger::notice($description);

	}

	/**
	 * Log a warning in LogBar.
	 */
	public static function warning(string $description): void {

		LogBar::event($description, 'warning');

	}

}