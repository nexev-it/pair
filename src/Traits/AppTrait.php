<?php

namespace Pair\Traits;

use Pair\Core\Application;
use Pair\Html\IziToast;
use Pair\Html\SweetAlert;

trait AppTrait {

	/**
	 * Get a state variable from cookie.
	 *
	 * @param	string	Name of the variable.
	 */
	public function getPersistentState(string $key): mixed {

		return Application::getInstance()->getPersistentState($key);

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

		Application::getInstance()->loadScript($src, $defer, $async, $attribs);

	}

	/**
	 * Add an alert modal to the page and return the object for further customization.
	 *
	 * @param	string	Title of the modal.
	 * @param	string	Message of the modal.
	 * @param	string	Icon for the modal.
	 */
	public function modal(string $title, string $message, string $icon='info'): SweetAlert {

		return Application::getInstance()->modal($title, $message, $icon);

	}

	/**
	 * Add a persistent alert modal to the page.
	 *
	 * @param	string	Title of the modal.
	 * @param	string	Message of the modal.
	 * @param	string	Type of the modal.
	 */
	public function persistentModal(string $title, string $message, string $type='info'): void {

		Application::getInstance()->persistentModal($title, $message, $type);

	}

	/**
	 * Redirect HTTP on the URL param. Relative path as default. Queued toast notifications
	 * get a persistent storage in a cookie in order to being retrieved later.
	 *
	 * @param	string	Location URL.
	 * @param	bool	If TRUE, will avoids to add base url (default FALSE).
	 */
	public function redirect(?string $url=NULL, bool $externalUrl=FALSE): void {

		Application::getInstance()->redirect($url, $externalUrl);

	}

	/**
	 * Set the web page HTML title tag.
	 */
	public function setPageTitle(string $title): void {

		Application::getInstance()->setPageTitle($title);

	}

	/**
	 * Store variables of any type in a cookie for next retrievement. Existent variables with
	 * same name will be overwritten.
	 */
	public function setPersistentState(string $key, mixed $value): void {

		Application::getInstance()->setPersistentState($key, $value);

	}

	/**
	 * Proxy to set a variable within global scope.
	 */
	public function setState(string $name, mixed $value): void {

		Application::getInstance()->setState($name, $value);

	}

    /**
     * Appends a toast notification message to queue.
	 *
	 * @param	string	Toast’s title, bold.
	 * @param	string	Error message.
	 * @param	string	Type of the toast (info|success|warning|error|question|progress), default info.

     */
    public function toast(string $title, string $message='', ?string $type=NULL): IziToast {

        return Application::getInstance()->toast($title, $message, $type);

    }

	/**
	 * Proxy method to queue an error with a toast notification.
	 *
	 * @param	string	Message’s text.
	 * @param	string	Optional title.
	 */
	public function toastError(string $title, string $message=''): IziToast {

		return Application::getInstance()->toastError($title, $message);

	}

	/**
	 * Proxy function to append an error toast notification to queue and redirect.
	 *
	 * @param	string	Toast’s title, bold.
	 * @param	string	Error message.
	 * @param	string	Redirect URL, optional.
	 */
	public function toastErrorRedirect(string $title, string $message='', ?string $url=NULL): void {

		Application::getInstance()->toastErrorRedirect($title, $message, $url);

	}

	/**
	 * Proxy function to append a toast notification to queue and redirect.
	 *
	 * @param	string	Toast’s title, bold.
	 * @param	string	Message.
	 * @param	string	Redirect URL, optional.
	 */
    public function toastRedirect(string $title, string $message='', ?string $url=NULL): void {

		Application::getInstance()->toastRedirect($title, $message, $url);

    }

	/**
	 * Removes a state variable from cookie.
	 */
	public function unsetPersistentState(string $key): void {

		Application::getInstance()->unsetPersistentState($key);

	}

	/**
	 * Proxy to unset a state variable.
	 */
	public function unsetState(string $name): void {

		Application::getInstance()->unsetState($name);

	}

}