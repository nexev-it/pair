<?php

namespace Pair\Exceptions;

class ErrorCodes {

	/**
	 * Error connecting to the database.
	 */
	const DB_CONNECTION_FAILED = 1001;

	/**
	 * Duplicate of a unique or primary key.
	 */
	const DUPLICATE_ENTRY = 1002;

	/**
	 * Data validation failed.
	 */
	const VALIDATION_FAILED = 1003;

	/**
	 * Foreign key constraint violation.
	 */
	const FOREIGN_KEY_CONSTRAINT = 1004;

	/**
	 * Query failed.
	 */
	const DB_QUERY_FAILED = 1005;

	/**
	 * Unexpected error during writing.
	 */
	const UNEXPECTED = 1006;

	/**
	 * Primary key is not populated, record creation failed.
	 */
	const PRIMARY_KEY_NOT_POPULATED = 1007;

	/**
	 * Composite primary key is not populated, record creation failed.
	 */
	const COMPOSITE_PRIMARY_KEY_NOT_POPULATED = 1008;

	/**
	 * Record not found.
	 */
	const RECORD_NOT_FOUND = 1009;

	/**
	 * Method not found.
	 */
	const METHOD_NOT_FOUND = 1010;

	/**
	 * Invalid date format.
	 */
	const INVALID_DATE_FORMAT = 1011;

	/**
	 * Error storing the record.
	 */
	const STORE_FAILED = 1012;

	/**
	 * Property not found.
	 */
	const PROPERTY_NOT_FOUND = 1013;

	/**
	 * Error code for controller not found.
	 */
	const CONTROLLER_NOT_FOUND = 1014;

	/**
	 * Error code for action not found.
	 */
	const ACTION_NOT_FOUND = 1015;

	/**
	 * Error code for view not found.
	 */
	const VIEW_NOT_FOUND = 1016;

	/**
	 * Error code for model not found.
	 */
	const ERROR_MODEL_NOT_FOUND = 1017;

	/**
	 * Error code for invalid data.
	 */
	const ERROR_INVALID_DATA = 1018;

	/**
	 * Error code for invalid request.
	 */
	const INVALID_REQUEST = 1019;

	/**
	 * Error loading template file.
	 */
	const NO_VALID_TEMPLATE = 1020;

	/**
	 * Generic database connection error.
	 */
	const CONNECTION_ERROR = 1021;

	/**
	 * Generic query error.
	 */
	const QUERY_ERROR = 1022;

	/**
	 * Query timeout error.
	 */
	const TIMEOUT_ERROR = 1023;

	/**
	 * Invalid SQL query syntax.
	 */
	const INVALID_QUERY_SYNTAX = 1024;

	/**
	 * Class not found.
	 */
	const CLASS_NOT_FOUND = 1025;

	/**
	 * Missing database error.
	 */
	const MISSING_DATABASE = 1026;

	/**
	 * Missing table error.
	 */
	const MISSING_TABLE = 1027;

	/**
	 * Permission denied error.
	 */
	const PERMISSION_DENIED = 1028;

	/**
	 * Malformed select control.
	 */
	const MALFORMED_SELECT = 1029;

	/**
	 * Error loading .env configuration file.
	 */
	const LOADING_ENV_FILE = 1030;

	/**
	 * Error unserializing cookie data.
	 */
	const UNSERIALIZE_ERROR = 1031;

	/**
	 * Foreign key not found.
	 */
	const FOREIGN_KEY_NOT_FOUND = 1032;

	/**
	 * No foreign key.
	 */
	const NO_FOREIGN_KEY = 1033;

	/**
	 * CSRF token not found.
	 */
	const CSRF_TOKEN_NOT_FOUND = 1034;

	/**
	 * CSRF token not valid.
	 */
	const CSRF_TOKEN_INVALID = 1035;

	/**
	 * Composer package not found.
	 */
	const COMPOSER_PACKAGE_NOT_FOUND = 1036;

	/**
	 * Missing configuration.
	 */
	const MISSING_CONFIGURATION = 1037;

	/**
	 * Logger failure.
	 */
	const LOGGER_FAILURE = 1038;

	/**
	 * Invalid logger.
	 */
	const INVALID_LOGGER = 1039;

	/**
	 * Telegram failure.
	 */
	const TELEGRAM_FAILURE = 1040;

	/**
	 * Email failure.
	 */
	const EMAIL_FAILURE = 1041;

	/**
	 * Error code for page not found.
	 */
	const PAGE_NOT_FOUND = 1042;

	/**
	 * Error code for widget not found.
	 */
	const WIDGET_NOT_FOUND = 1043;

	/**
	 * Plugin already installed.
	 */
	const PLUGIN_ALREADY_INSTALLED = 1044;

	/**
	 * Unvalid error level for logger.
	 */
	const UNVALID_ERROR_LEVEL = 1045;

	/**
	 * Invalid collection key.
	 */
	const INVALID_COLLECTION_KEY = 1046;

	/**
	 * Invalid collection value.
	 */
	const INVALID_COLLECTION_VALUE = 1047;

	/**
	 * Invalid collection item type.
	 */
	const INVALID_COLLECTION_ITEM_TYPE = 1048;

	/**
	 * Controller initialization failed.
	 */
	const CONTROLLER_INIT_FAILED = 1049;

	/**
	 * Controller error.
	 */
	const CONTROLLER_ERROR = 1050;

	/**
	 * View error.
	 */
	const VIEW_ERROR = 1051;

	/**
	 * Amazon S3 error.
	 */
	const AMAZON_S3_ERROR = 1052;

	/**
	 * Email send error.
	 */
	const EMAIL_SEND_ERROR = 1053;

	/**
	 * Form control not found.
	 */
	const FORM_CONTROL_NOT_FOUND = 1054;

}